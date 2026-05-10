<?php
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/**
 * Human-readable relative time for activity rows.
 */
function dashboard_time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 604800) {
        return (int) floor($diff / 86400) . ' days ago';
    }

    return date('M j, Y', $ts);
}

// ----- Logged-in user (name, email, avatar initials) -----
$userRow = null;
$uStmt   = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
if ($uStmt) {
    $uStmt->bind_param('i', $user_id);
    $uStmt->execute();
    $userRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
}

$userName  = trim((string) ($userRow['name'] ?? $_SESSION['name'] ?? 'Student'));
$userEmail = (string) ($userRow['email'] ?? $_SESSION['email'] ?? '');
$parts     = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$avatar    = '?';
if ($parts !== false && $parts !== []) {
    $avatar = count($parts) >= 2
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
        : strtoupper(strlen($parts[0]) >= 2 ? substr($parts[0], 0, 2) : substr($parts[0], 0, 1) . '?');
}

$firstName = (string) ($parts[0] ?? $userName);

// ----- Results aggregates (trust = AVG percentage), consistency helper -----
$rowStats = ['total_tests' => 0, 'avg_pct' => null, 'std_pct' => null];
$sStmt    = $conn->prepare(
    'SELECT COUNT(*) AS total_tests, AVG(percentage) AS avg_pct, STDDEV_SAMP(percentage) AS std_pct
     FROM results WHERE user_id = ?'
);
if ($sStmt) {
    $sStmt->bind_param('i', $user_id);
    $sStmt->execute();
    $r = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();
    if ($r) {
        $rowStats['total_tests'] = (int) ($r['total_tests'] ?? 0);
        $rowStats['avg_pct']     = $r['avg_pct'] !== null ? (float) $r['avg_pct'] : null;
        $rowStats['std_pct']     = $r['std_pct'] !== null ? (float) $r['std_pct'] : null;
    }
}

$total_tests = $rowStats['total_tests'];
$trust       = (int) round((float) ($rowStats['avg_pct'] ?? 0));

// Sub-metrics: accuracy reflects score quality; consistency comes from stable performance; speed is derived from activity volume
$accuracyPct = $trust;
$std         = $rowStats['std_pct'];
$consistencyPct = 85;
if ($std !== null && $total_tests > 1) {
    $consistencyPct = (int) max(0, min(100, round(100 - min(50.0, $std * 2.5))));
} elseif ($total_tests <= 1) {
    $consistencyPct = $total_tests === 0 ? 0 : min(100, $trust + 5);
}
$speedPct = (int) max(0, min(95, 45 + min(50, $total_tests * 5)));

$submetrics = [
    ['label' => 'Accuracy', 'val' => $accuracyPct, 'color' => '#6366f1'],
    ['label' => 'Consistency', 'val' => $consistencyPct, 'color' => '#10b981'],
    ['label' => 'Speed', 'val' => $speedPct, 'color' => '#f59e0b'],
];

// ----- Global rank (users with at least one result); ties: count strictly higher averages -----
$user_rank = '-';
$total_ranked_users = 0;
$cStmt = $conn->prepare('SELECT COUNT(*) AS c FROM (SELECT 1 FROM results GROUP BY user_id) x');
if ($cStmt) {
    $cStmt->execute();
    $cr = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();
    $total_ranked_users = (int) ($cr['c'] ?? 0);
}
if ($total_tests > 0 && $rowStats['avg_pct'] !== null) {
    $rkStmt = $conn->prepare(
        'SELECT COUNT(*) + 1 AS rk FROM (
            SELECT user_id FROM results GROUP BY user_id
            HAVING AVG(percentage) > (SELECT AVG(percentage) FROM results WHERE user_id = ?)
        ) t'
    );
    if ($rkStmt) {
        $rkStmt->bind_param('i', $user_id);
        $rkStmt->execute();
        $rkRow = $rkStmt->get_result()->fetch_assoc();
        $rkStmt->close();
        if ($rkRow && isset($rkRow['rk'])) {
            $user_rank = (string) (int) $rkRow['rk'];
        }
    }
}

$beat_pct = 0;
if ($total_ranked_users > 0 && $user_rank !== '-') {
    $ur = (int) $user_rank;
    $beat_pct = (int) max(0, min(100, round(100 * ($total_ranked_users - $ur) / $total_ranked_users)));
}

$next_tier_at   = $trust >= 85 ? 100 : ($trust >= 70 ? 85 : 70);
$points_to_tier = max(0, $next_tier_at - $trust);

// ----- Streak: consecutive calendar days with attempts; must include today or yesterday -----
$streak        = 0;
$distinctDates = [];
$dStmt         = $conn->prepare(
    'SELECT DATE(created_at) AS d FROM results WHERE user_id = ? GROUP BY d ORDER BY d DESC'
);
if ($dStmt) {
    $dStmt->bind_param('i', $user_id);
    $dStmt->execute();
    $dRes = $dStmt->get_result();
    while ($dr = $dRes->fetch_assoc()) {
        $distinctDates[] = (string) $dr['d'];
    }
    $dStmt->close();
}
if ($distinctDates !== []) {
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $latest    = $distinctDates[0];
    if ($latest === $today || $latest === $yesterday) {
        $streak = 1;
        $prev   = new DateTimeImmutable($latest);
        for ($i = 1, $n = count($distinctDates); $i < $n; $i++) {
            $cur = new DateTimeImmutable($distinctDates[$i]);
            if ($prev->diff($cur)->days === 1) {
                $streak++;
                $prev = $cur;
            } else {
                break;
            }
        }
    }
}

// ----- Recent activity (last 5): title, %, pass/fail, time ago -----
$activities = null;
$aStmt      = $conn->prepare(
    'SELECT t.title AS title, r.percentage, r.created_at,
            COALESCE(t.passing_score, 60) AS passing_score
     FROM results r
     INNER JOIN tests t ON t.id = r.test_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 5'
);
if ($aStmt) {
    $aStmt->bind_param('i', $user_id);
    $aStmt->execute();
    $activities = $aStmt->get_result();
}

// ----- Upcoming tests: not yet attempted, limit 3 -----
$upcoming_tests = [];
$upStmt         = $conn->prepare(
    'SELECT t.title, t.difficulty, t.duration
     FROM tests t
     WHERE NOT EXISTS (
         SELECT 1 FROM results r WHERE r.test_id = t.id AND r.user_id = ?
     )
     ORDER BY t.id ASC
     LIMIT 3'
);
if ($upStmt) {
    $upStmt->bind_param('i', $user_id);
    $upStmt->execute();
    $upRes = $upStmt->get_result();
    while ($ur = $upRes->fetch_assoc()) {
        $dur       = isset($ur['duration']) ? (int) $ur['duration'] : 0;
        $durLabel  = $dur > 0 ? $dur . ' min' : '-';
        $upcoming_tests[] = [
            'title'      => (string) ($ur['title'] ?? 'Test'),
            'difficulty' => (string) ($ur['difficulty'] ?? 'Beginner'),
            'duration'   => $durLabel,
        ];
    }
    $upStmt->close();
}

// ----- Upcoming interviews ----- 
$upcoming_interviews = [];
$interviewScheduleColumn = db_table_exists($conn, 'interviews')
    ? (db_column_exists($conn, 'interviews', 'interview_datetime') ? 'interview_datetime' : (db_column_exists($conn, 'interviews', 'scheduled_at') ? 'scheduled_at' : ''))
    : '';
$interviewLinkColumn = db_table_exists($conn, 'interviews') && db_column_exists($conn, 'interviews', 'meeting_link');
$interviewPublicIdColumn = db_table_exists($conn, 'interviews') && db_column_exists($conn, 'interviews', 'interview_id');

if ($interviewScheduleColumn !== '') {
    $selectColumns = [
        'i.id',
        'i.status',
        'i.' . $interviewScheduleColumn . ' AS scheduled_at',
        'j.title AS job_title',
    ];
    if ($interviewLinkColumn) {
        $selectColumns[] = 'i.meeting_link';
    }
    if ($interviewPublicIdColumn) {
        $selectColumns[] = 'i.interview_id';
    }

    $interviewStmt = $conn->prepare(
        sprintf(
            'SELECT %s
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE a.user_id = ?
               AND i.status = "scheduled"
               AND i.%s >= NOW()
             ORDER BY i.%s ASC
             LIMIT 3',
            implode(', ', $selectColumns),
            $interviewScheduleColumn,
            $interviewScheduleColumn
        )
    );
    if ($interviewStmt) {
        $interviewStmt->bind_param('i', $user_id);
        $interviewStmt->execute();
        $interviewRows = $interviewStmt->get_result();
        while ($ir = $interviewRows->fetch_assoc()) {
            $meetingLink = (string) ($ir['meeting_link'] ?? '');
            $publicId = (string) ($ir['interview_id'] ?? '');
            if ($meetingLink === '' && $publicId !== '') {
                $meetingLink = 'https://meet.jit.si/' . rawurlencode($publicId);
            }

            $upcoming_interviews[] = [
                'id' => (int) ($ir['id'] ?? 0),
                'status' => (string) ($ir['status'] ?? 'scheduled'),
                'scheduled_at' => (string) ($ir['scheduled_at'] ?? ''),
                'job_title' => (string) ($ir['job_title'] ?? 'Interview'),
                'meeting_link' => $meetingLink,
            ];
        }
        $interviewStmt->close();
    }
}

// ----- Weekly chart: last 7 calendar days, avg % per day (0 if none) -----
$dayScores = [];
$wStmt     = $conn->prepare(
    'SELECT DATE(created_at) AS d, AVG(percentage) AS sc
     FROM results
     WHERE user_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)'
);
if ($wStmt) {
    $wStmt->bind_param('i', $user_id);
    $wStmt->execute();
    $wRes = $wStmt->get_result();
    while ($wr = $wRes->fetch_assoc()) {
        $dayScores[(string) $wr['d']] = (float) ($wr['sc'] ?? 0);
    }
    $wStmt->close();
}

$weekly_chart = [];
for ($i = 6; $i >= 0; $i--) {
    $ts    = strtotime('-' . $i . ' days');
    $dKey  = date('Y-m-d', $ts);
    $score = isset($dayScores[$dKey]) ? (int) round($dayScores[$dKey]) : 0;
    $weekly_chart[] = [
        'week'  => date('D', $ts),
        'score' => $score,
    ];
}

$chartData = array_column($weekly_chart, 'score');

$rating_label = $trust >= 85 ? 'Excellent Rating' : ($trust >= 70 ? 'Strong Rating' : ($trust >= 50 ? 'Building Rating' : 'Getting Started'));

// Template compatibility: $student used in existing markup
$student = [
    'name'         => $userName,
    'email'        => $userEmail,
    'avatar'       => $avatar,
    'streak'       => $streak,
    'trust_score'  => $trust,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Layout Wrapper -->
<div class="flex min-h-screen">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar fixed left-0 top-0 h-full w-64 z-50 flex flex-col
                  transform -translate-x-full lg:translate-x-0 transition-transform duration-300"
           id="sidebar">

        <!-- Logo -->
        <div class="px-6 py-5 border-b border-brand-900/30">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                            flex items-center justify-center shadow-lg shadow-brand-500/30">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0
                              014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42
                              3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0
                              00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0
                              01-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0
                              01-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0
                              01-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0
                              01.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-display font-800 text-white text-lg tracking-tight">SkillTrust</span>
                    <div class="text-xs text-brand-400 font-medium -mt-0.5">Student Panel</div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            <div class="px-3 mb-3">
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-widest">Main Menu</span>
            </div>

            <a href="dashboard.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <div class="w-8 h-8 rounded-lg bg-brand-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0
                              01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0
                              01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0
                              011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0
                              011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                    </svg>
                </div>
                <span>Dashboard</span>
            </a>

            <a href="tests.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0
                              002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0
                              002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3
                              4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <span>Tests</span>
                <span class="ml-auto bg-brand-500/20 text-brand-400 text-xs font-semibold px-2 py-0.5 rounded-full">12</span>
            </a>

            <a href="jobs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 7h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2zm3-3h6a2 2 0 012 2v1H7V6a2 2 0 012-2z"/>
                    </svg>
                </div>
                <span>Jobs</span>
            </a>

            <a href="applied_jobs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6m-6 4h6m-6-8h6"/>
                    </svg>
                </div>
                <span>Applied Jobs</span>
            </a>

            <a href="results.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0
                              002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0
                              012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2
                              2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span>Results</span>
            </a>

            <a href="profile.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <span>Profile</span>
            </a>

            <div class="px-3 pt-4 mb-2">
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-widest">Settings</span>
            </div>

            <a href="#" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0
                              002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0
                              001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0
                              00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0
                              00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0
                              00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0
                              00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0
                              001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07
                              2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span>Settings</span>
            </a>
        </nav>

        <!-- User block -->
        <div class="px-4 py-4 border-t border-brand-900/30">
            <div class="student-shell-user flex items-center gap-3 p-3 rounded-xl bg-slate-800/60 hover:bg-slate-800
                        transition-colors cursor-pointer">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                            flex items-center justify-center text-white font-display font-bold text-sm flex-shrink-0">
                    <?php echo htmlspecialchars($student['avatar'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                </svg>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

        <!-- ===== NAVBAR ===== -->
        <header class="navbar sticky top-0 z-30 px-4 lg:px-8 h-16 flex items-center justify-between gap-4">

            <!-- Hamburger (mobile) -->
            <button onclick="toggleSidebar()"
                    class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <!-- Page title -->
            <div class="hidden lg:block">
                <h2 class="font-display font-700 text-white text-lg">
                    <?php
$_greetHour = (int)date('G');
$_greeting = $_greetHour < 12 ? 'Good morning' : ($_greetHour < 17 ? 'Good afternoon' : 'Good evening');
echo htmlspecialchars($_greeting, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
?>
                </h2>
                <p class="text-xs text-slate-500">
                    <?php echo date('l, F j, Y'); ?>
                </p>
            </div>

            <!-- Right actions -->
            <div class="flex items-center gap-2 ml-auto">

                <!-- Search -->
                <div class="hidden md:flex items-center gap-2 bg-slate-800/60 border border-slate-700/50
                            rounded-xl px-3 py-2 focus-within:border-brand-500/50 transition-all">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search..."
                           class="bg-transparent text-sm text-slate-300 placeholder-slate-600
                                  outline-none w-36 focus:w-48 transition-all duration-300">
                </div>

                <!-- Notifications -->
                <button class="relative p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800
                               transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002
                              0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0
                              .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot absolute top-2 right-2 w-2 h-2 rounded-full bg-brand-500"></span>
                </button>

                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                    <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <!-- Profile dropdown -->
                <div class="relative" id="profileDropdown">
                    <button onclick="toggleDropdown()"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                                    flex items-center justify-center text-white font-display font-bold text-xs">
                            <?php echo htmlspecialchars($student['avatar'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-500 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <div class="dropdown-menu absolute right-0 top-full mt-2 w-52
                                bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl
                                overflow-hidden z-50" id="dropdownMenu">
                        <div class="px-4 py-3 border-b border-slate-700/50">
                            <div class="font-semibold text-white text-sm"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300
                                                          hover:bg-slate-700/50 hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="#" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300
                                               hover:bg-slate-700/50 hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Settings
                            </a>
                        </div>
                        <div class="border-t border-slate-700/50 py-1">
                            <a href="../auth/login.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-400
                                                        hover:bg-red-500/10 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0
                                          01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ===== PAGE CONTENT ===== -->
        <main class="flex-1 px-4 lg:px-8 py-8 space-y-8">

            <!-- ===== HERO / WELCOME BANNER ===== -->
            <section class="opacity-0-start animate-fade-up stagger-1 relative rounded-3xl overflow-hidden
                            bg-gradient-to-br from-brand-800 via-brand-700 to-violet-800 p-8 lg:p-10">
                <!-- Background pattern -->
                <div class="absolute inset-0 opacity-10"
                     style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.3) 1px, transparent 0);
                            background-size: 32px 32px;"></div>
                <!-- Glows -->
                <div class="absolute -top-10 -right-10 w-64 h-64 rounded-full opacity-20
                            bg-violet-400 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-10 left-20 w-48 h-48 rounded-full opacity-15
                            bg-brand-300 blur-3xl pointer-events-none"></div>

                <div class="relative flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center gap-2 bg-white/10 text-white/80
                                    text-xs font-semibold px-3 py-1.5 rounded-full mb-4 border border-white/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                            <?php echo (int) $student['streak']; ?> Day Streak - Keep going
                        </div>
                        <h1 class="font-display text-3xl lg:text-4xl font-800 text-white mb-2">
                            Your Trust Score is <span class="text-yellow-300"><?php echo (int) $student['trust_score']; ?></span>
                        </h1>
                        <p class="text-brand-200 text-sm lg:text-base max-w-md">
                            <?php if ($total_ranked_users > 0 && $user_rank !== '-'): ?>
                            You're performing better than <span class="text-white font-semibold"><?php echo $beat_pct; ?>%</span> of students. Keep pushing - you're
                            <strong class="text-white"><?php echo $points_to_tier; ?> points</strong> away from the next rank tier.
                            <?php else: ?>
                            Take your first test to join the leaderboard and build your Trust Score.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex gap-3 flex-shrink-0">
                        <a href="tests.php"
                           class="glow-btn inline-flex items-center gap-2 px-6 py-3 rounded-2xl
                                  text-white font-semibold text-sm shadow-xl">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Start a Test
                        </a>
                        <a href="results.php"
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl
                                  bg-white/10 border border-white/20 text-white font-semibold text-sm
                                  hover:bg-white/20 transition-all">
                            View Results
                        </a>
                    </div>
                </div>

                <!-- Quick stats strip -->
                <div class="relative mt-8 grid grid-cols-3 gap-4 lg:gap-6">
                    <?php
                    $quick_stats = [
    ['label' => 'Trust Score', 'value' => $trust, 'suffix' => '/100', 'color' => 'text-brand-300'],
    ['label' => 'Tests Done', 'value' => $total_tests, 'suffix' => '', 'color' => 'text-emerald-300'],
    ['label' => 'Global Rank', 'value' => ($user_rank === '-' ? '-' : '#' . $user_rank), 'suffix' => '', 'color' => 'text-yellow-300'],
];
                    foreach ($quick_stats as $stat): ?>
                        <div class="text-center">
                            <div class="font-display font-800 text-2xl lg:text-3xl <?php echo $stat['color']; ?>">
                                <?php echo $stat['value']; ?><span class="text-sm opacity-70"><?php echo $stat['suffix']; ?></span>
                            </div>
                            <div class="text-xs text-white/50 mt-0.5"><?php echo $stat['label']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ===== STAT CARDS ===== -->
            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <?php
                $rank_display = ($user_rank === '-' ? '-' : '#' . $user_rank);
                $stat_cards   = [
                    [
                        'label' => 'Trust Score',
                        'value' => $trust,
                        'suffix' => '/100',
                        'change' => $total_tests > 0 ? 'Live avg' : 'No tests yet',
                        'up' => true,
                        'class' => 'trust',
                        'gradient' => 'from-brand-500/20 to-violet-500/10',
                        'icon_bg' => 'bg-brand-500/20',
                        'icon_color' => 'text-brand-400',
                        'delay' => 'stagger-1',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                    ],
                    [
                        'label' => 'Tests Completed',
                        'value' => $total_tests,
                        'suffix' => '',
                        'change' => $total_tests > 0 ? 'Recorded' : 'Start now',
                        'up' => true,
                        'class' => 'tests',
                        'gradient' => 'from-emerald-500/20 to-teal-500/10',
                        'icon_bg' => 'bg-emerald-500/20',
                        'icon_color' => 'text-emerald-400',
                        'delay' => 'stagger-2',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                    ],
                    [
                        'label' => 'Day Streak',
                        'value' => $streak,
                        'suffix' => '',
                        'change' => $streak > 0 ? 'On fire' : 'Build a streak',
                        'up' => $streak > 0,
                        'class' => 'rank',
                        'gradient' => 'from-amber-500/20 to-orange-500/10',
                        'icon_bg' => 'bg-amber-500/20',
                        'icon_color' => 'text-amber-400',
                        'delay' => 'stagger-3',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
                    ],
                    [
                        'label' => 'Global Rank',
                        'value' => $rank_display,
                        'suffix' => '',
                        'change' => $user_rank !== '-' ? ('of ' . $total_ranked_users) : 'Take a test',
                        'up' => $user_rank !== '-',
                        'class' => 'streak',
                        'gradient' => 'from-red-500/20 to-rose-500/10',
                        'icon_bg' => 'bg-red-500/20',
                        'icon_color' => 'text-red-400',
                        'delay' => 'stagger-4',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/>',
                    ],
                ];
                foreach ($stat_cards as $stat): ?>
                    <div class="glass-card stat-card <?php echo $stat['class']; ?> opacity-0-start animate-fade-up <?php echo $stat['delay']; ?>
                                rounded-2xl p-6 bg-gradient-to-br <?php echo $stat['gradient']; ?>">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl <?php echo $stat['icon_bg']; ?>
                                        flex items-center justify-center">
                                <svg class="w-5 h-5 <?php echo $stat['icon_color']; ?>"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <?php echo $stat['icon']; ?>
                                </svg>
                            </div>
                            <span class="text-xs font-medium <?php echo $stat['up'] ? 'text-emerald-400' : 'text-red-400'; ?>
                                         bg-emerald-400/10 px-2 py-1 rounded-full">
                                <?php echo $stat['change']; ?>
                            </span>
                        </div>
                        <div class="font-display font-800 text-3xl text-white mb-1">
                            <?php echo $stat['value']; ?><span class="text-sm font-body text-slate-400"><?php echo $stat['suffix']; ?></span>
                        </div>
                        <div class="text-sm text-slate-400 font-medium"><?php echo $stat['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- ===== MAIN 2-COL GRID ===== -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

             <!-- Recent Activity (2/3) -->
<div class="xl:col-span-2 opacity-0-start animate-fade-up stagger-3">
    <div class="glass-card rounded-2xl overflow-hidden">

        <!-- Header -->
        <div class="px-6 py-5 border-b border-slate-700/40 flex items-center justify-between">
            <div>
                <h3 class="font-display font-700 text-white text-base">Recent Activity</h3>
                <p class="text-xs text-slate-500 mt-0.5">Your latest test attempts</p>
            </div>
            <a href="results.php"
               class="text-xs text-brand-400 hover:text-brand-300 font-semibold
                      flex items-center gap-1 transition-colors">
                View all
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- Activity list -->
        <div class="divide-y divide-slate-700/30">

        <?php
        if ($activities && $activities->num_rows > 0):
            while ($row = $activities->fetch_assoc()):
                $score         = (int) ($row['percentage'] ?? 0);
                $passing_score = (int) ($row['passing_score'] ?? 60);
                $passed        = $score >= $passing_score;
                $status        = $passed ? 'Passed' : 'Failed';

                $score_color = $score >= 80 ? 'text-emerald-400' :
                               ($score >= 60 ? 'text-amber-400' : 'text-red-400');

                $badge_class = $passed
                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                    : 'bg-red-500/10 text-red-400 border border-red-500/20';

                $bar_color = $score >= 80 ? '#10b981' :
                             ($score >= 60 ? '#f59e0b' : '#ef4444');

                $title_safe = (string) ($row['title'] ?? 'Test');
                $icon       = strtoupper(strlen($title_safe) >= 2 ? substr($title_safe, 0, 2) : substr($title_safe, 0, 1) . '?');
                $time_ago   = dashboard_time_ago(isset($row['created_at']) ? (string) $row['created_at'] : null);
            ?>

            <div class="px-6 py-4 flex items-center gap-4 hover:bg-slate-800/30
                        transition-colors group">

                <!-- Icon -->
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-violet-600
                            flex items-center justify-center text-white font-display font-bold
                            text-xs flex-shrink-0 group-hover:scale-105 transition-transform">
                    <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-slate-200 truncate">
                        <?php echo htmlspecialchars($title_safe, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        <?php echo htmlspecialchars($time_ago, ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <!-- Score bar -->
                    <div class="score-bar mt-2 w-full">
                        <div class="score-bar-fill"
                             style="width:0%;background:<?php echo $bar_color; ?>"
                             data-width="<?php echo $score; ?>%">
                        </div>
                    </div>
                </div>

                <!-- Score & badge -->
                <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                    <span class="font-display font-800 text-lg <?php echo $score_color; ?>">
                        <?php echo $score; ?>%
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

            </div>

        <?php
            endwhile;
        endif;
        ?>

        </div>
    </div>
</div>

                <!-- Right column -->
                <div class="space-y-6 opacity-0-start animate-fade-up stagger-4">

                    <!-- Trust Score Ring -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="font-display font-700 text-white text-base mb-1">Trust Score</h3>
                        <p class="text-xs text-slate-500 mb-5">Your credibility index</p>

                        <div class="flex flex-col items-center">
                            <!-- SVG Ring -->
                            <div class="relative w-36 h-36">
                                <svg class="w-36 h-36 -rotate-90" viewBox="0 0 100 100">
                                    <!-- Track -->
                                    <circle cx="50" cy="50" r="40"
                                            fill="none" stroke="rgba(99,102,241,0.1)"
                                            stroke-width="8"/>
                                    <!-- Progress -->
                                    <circle cx="50" cy="50" r="40"
                                            fill="none"
                                            stroke="url(#ringGrad)"
                                            stroke-width="8"
                                            stroke-linecap="round"
                                            stroke-dasharray="251.2"
                                            stroke-dashoffset="251.2"
                                            id="trustRing"
                                            class="progress-ring__circle"/>
                                    <defs>
                                        <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#6366f1"/>
                                            <stop offset="100%" stop-color="#8b5cf6"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="font-display font-800 text-3xl text-white" id="trustNum">0</span>
                                    <span class="text-xs text-slate-500">/ 100</span>
                                </div>
                            </div>

                            <!-- Rating label -->
                            <div class="mt-3 px-4 py-1.5 rounded-full bg-brand-500/15 border border-brand-500/20">
                                <span class="text-xs font-semibold text-brand-400"><?php echo htmlspecialchars($rating_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <!-- Sub metrics -->
                            <div class="mt-5 w-full space-y-3">
                                <?php foreach ($submetrics as $m): ?>
                                    <div>
                                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                                            <span><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="font-semibold text-slate-300"><?php echo (int) $m['val']; ?>%</span>
                                        </div>
                                        <div class="score-bar">
                                            <div class="score-bar-fill"
                                                 style="width:0%;background:<?php echo htmlspecialchars($m['color'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 data-width="<?php echo (int) $m['val']; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Tests -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-display font-700 text-white text-base">Upcoming Tests</h3>
                            <a href="tests.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold transition-colors">
                                Browse all
                            </a>
                        </div>

                        <div class="space-y-3">
                            <?php
                            $diff_colors = [
                                'Beginner' => 'text-emerald-400 bg-emerald-500/10',
                                'Intermediate' => 'text-amber-400 bg-amber-500/10',
                                'Advanced' => 'text-red-400 bg-red-500/10',
                            ];
                            foreach ($upcoming_tests as $test):
                                $dc = $diff_colors[$test['difficulty']] ?? 'text-slate-400 bg-slate-500/10';
                            ?>
                                <div class="group flex items-center gap-3 p-3 rounded-xl bg-slate-800/50
                                            hover:bg-slate-800 border border-transparent hover:border-slate-700/60
                                            transition-all cursor-pointer">
                                    <div class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-slate-200 truncate">
                                            <?php echo htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs <?php echo $dc; ?> px-1.5 py-0.5 rounded-md font-medium">
                                                <?php echo htmlspecialchars($test['difficulty'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($test['duration'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <svg class="w-4 h-4 text-slate-600 group-hover:text-brand-400 group-hover:translate-x-1
                                                transition-all flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <a href="tests.php"
                           class="glow-btn mt-4 w-full inline-flex items-center justify-center gap-2
                                  px-4 py-3 rounded-xl text-white font-semibold text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Start a Test Now
                        </a>
                    </div>

                </div>
            </div>

            <section class="opacity-0-start animate-fade-up stagger-5">
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-display font-700 text-white text-base">Upcoming Interviews</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Join your scheduled interviews securely from here</p>
                        </div>
                        <a href="applied_jobs.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold transition-colors">View all</a>
                    </div>

                    <?php if ($upcoming_interviews === []): ?>
                        <div class="rounded-2xl border border-dashed border-slate-700/60 bg-slate-900/35 px-5 py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h4 class="mt-4 font-display text-lg font-bold text-white">No upcoming interviews</h4>
                            <p class="mt-2 text-sm text-slate-400">Once a recruiter schedules you, the join link will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid gap-4 lg:grid-cols-3">
                            <?php foreach ($upcoming_interviews as $interview): ?>
                                <article class="rounded-2xl border border-slate-700/60 bg-slate-900/45 p-4 hover:border-brand-500/30 transition-all">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-300">Scheduled</p>
                                            <h4 class="mt-2 text-sm font-semibold text-white"><?php echo htmlspecialchars($interview['job_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                        </div>
                                        <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-300">
                                            <?php echo htmlspecialchars(ucfirst($interview['status']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <p class="mt-3 text-sm text-slate-400"><?php echo htmlspecialchars(format_datetime_label($interview['scheduled_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <a href="interview.php?link=<?php echo urlencode((string) $interview['meeting_link']); ?>&id=<?php echo (int) $interview['id']; ?>" class="glow-btn mt-4 inline-flex w-full items-center justify-center gap-2 px-4 py-3 rounded-xl text-white font-semibold text-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14m-6 4h4a2 2 0 002-2V8a2 2 0 00-2-2H9a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        Join Interview
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ===== PERFORMANCE CHART (simple bar chart) ===== -->
            <section class="opacity-0-start animate-fade-up stagger-5">
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="font-display font-700 text-white text-base">Weekly Performance</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Test scores over the past 7 weeks</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-full bg-brand-500"></div>
                                <span class="text-xs text-slate-400">Score</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                                <span class="text-xs text-slate-400">Avg Pass</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bar chart -->
                    <div class="flex items-end gap-3 h-40" id="barChart">
                        <?php foreach ($weekly_chart as $i => $w):
                            $is_last = $i === count($weekly_chart) - 1;
                            $bar_class = $is_last ? 'from-brand-500 to-violet-500' : 'from-brand-700/60 to-brand-600/60';
                        ?>
                            <div class="flex-1 flex flex-col items-center gap-2">
                                <span class="text-xs font-display font-bold
                                             <?php echo $is_last ? 'text-white' : 'text-slate-500'; ?>">
                                    <?php echo (int) $w['score']; ?>
                                </span>
                                <div class="w-full rounded-t-lg bg-gradient-to-t <?php echo $bar_class; ?>
                                            chart-bar transition-all duration-700 relative overflow-hidden
                                            <?php echo $is_last ? 'shadow-lg shadow-brand-500/30' : ''; ?>"
                                     style="height:0px"
                                     data-height="<?php echo ((int) $w['score'] / 100) * 130; ?>px">
                                    <?php if ($is_last): ?>
                                        <div class="absolute inset-0 bg-white/10 opacity-0
                                                    hover:opacity-100 transition-opacity"></div>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-slate-500"><?php echo htmlspecialchars((string) $w['week'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <!-- Avg line reference (60% pass line) -->
                    </div>
                </div>
            </section>

        </main>

        <!-- Footer -->
        <footer class="px-8 py-4 border-t border-slate-800/60 flex items-center justify-between">
            <span class="text-xs text-slate-600">(c) 2025 SkillTrust - Student Panel</span>
            <span class="text-xs text-slate-600">v2.4.0</span>
        </footer>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script type="application/json" id="studentDashboardData"><?php echo json_encode(['chartData' => $chartData, 'trustScore' => (int) $student['trust_score']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/student/dashboard.js"></script>

</body>
</html>
