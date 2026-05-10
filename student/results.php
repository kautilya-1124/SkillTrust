<?php
// results.php - SkillTrust Student Panel
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$userName  = trim((string) ($_SESSION['name'] ?? ''));
$userEmail = (string) ($_SESSION['email'] ?? '');
$uStmt     = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
if ($uStmt) {
    $uStmt->bind_param('i', $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        if (isset($uRow['name']) && trim((string) $uRow['name']) !== '') {
            $userName = trim((string) $uRow['name']);
        }
        if (isset($uRow['email']) && (string) $uRow['email'] !== '') {
            $userEmail = (string) $uRow['email'];
        }
    }
}
if ($userName === '') {
    $userName = 'Student';
}

$parts  = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$avatar = '?';
if ($parts !== false && $parts !== []) {
    $avatar = count($parts) >= 2
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
        : strtoupper(strlen($parts[0]) >= 2 ? substr($parts[0], 0, 2) : substr($parts[0], 0, 1) . '?');
}

$trust = 0;
$tStmt = $conn->prepare('SELECT AVG(percentage) AS avg_pct FROM results WHERE user_id = ?');
if ($tStmt) {
    $tStmt->bind_param('i', $user_id);
    $tStmt->execute();
    $tRow = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();
    if ($tRow && $tRow['avg_pct'] !== null) {
        $trust = (int) round((float) $tRow['avg_pct']);
    }
}

$student = [
    'name'        => $userName,
    'email'       => $userEmail,
    'avatar'      => $avatar,
    'streak'      => 0,
    'trust_score' => $trust,
];

$pass_threshold = 60;

$results = [];
$total_tests = 0;

$countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM results WHERE user_id = ?');
if ($countStmt) {
    $countStmt->bind_param('i', $user_id);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $total_tests = (int) ($countRow['c'] ?? 0);
}

$listSql = 'SELECT t.title AS test_title, t.difficulty AS test_difficulty, r.score, r.percentage, r.created_at, r.id AS result_id
            FROM results r
            INNER JOIN tests t ON t.id = r.test_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC, r.id DESC';

$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->bind_param('i', $user_id);
    $listStmt->execute();
    $listRes = $listStmt->get_result();
    while ($row = $listRes->fetch_assoc()) {
        $correct = (int) ($row['score'] ?? 0);
        $pct     = (float) ($row['percentage'] ?? 0);
        $totalQ  = ($pct > 0.001) ? (int) max(1, (int) round(100 * $correct / $pct)) : ($correct > 0 ? $correct : 1);

        $created = $row['created_at'] ?? null;
        $dateFmt = '-';
        $dateShort = '-';
        if ($created !== null && $created !== '') {
            try {
                $dt = new DateTimeImmutable($created);
                $dateFmt   = $dt->format('M j, Y');
                $dateShort = $dt->format('M j');
            } catch (Exception $e) {
                $dateFmt = '-';
                $dateShort = '-';
            }
        }

        $results[] = [
            'title'        => (string) ($row['test_title'] ?? 'Test'),
            'score'        => round($pct, 2),
            'difficulty'   => (string) ($row['test_difficulty'] ?? 'Beginner'),
            'correct'      => $correct,
            'total'        => $totalQ,
            'duration_min' => 0,
            'date'         => $dateFmt,
            'date_short'   => $dateShort,
        ];
    }
    $listStmt->close();
}


$total_score = $results !== [] ? array_sum(array_column($results, 'score')) : 0;
$avg_score   = $results !== [] ? (int) round($total_score / count($results)) : 0;
$overall_pass = $avg_score >= $pass_threshold;
$passed_count = count(array_filter($results, static function ($r) use ($pass_threshold) {
    return (float) ($r['score'] ?? 0) >= $pass_threshold;
}));

if ($results === []) {
    $mini = [
        ['label' => 'Highest score', 'value' => '-', 'sub' => 'Best single attempt', 'grad' => 'from-emerald-500/20 to-teal-900/20', 'ic' => 'text-emerald-400', 'delay' => 'stagger-2'],
        ['label' => 'Lowest score', 'value' => '-', 'sub' => 'Focus area', 'grad' => 'from-amber-500/20 to-orange-900/20', 'ic' => 'text-amber-400', 'delay' => 'stagger-3'],
        ['label' => 'Total time', 'value' => '0 min', 'sub' => 'Time on recorded tests', 'grad' => 'from-brand-500/20 to-violet-900/20', 'ic' => 'text-brand-400', 'delay' => 'stagger-4'],
    ];
} else {
    $scoreCol = array_column($results, 'score');
    $mini = [
        ['label' => 'Highest score', 'value' => max($scoreCol) . '%', 'sub' => 'Best single attempt', 'grad' => 'from-emerald-500/20 to-teal-900/20', 'ic' => 'text-emerald-400', 'delay' => 'stagger-2'],
        ['label' => 'Lowest score', 'value' => min($scoreCol) . '%', 'sub' => 'Focus area', 'grad' => 'from-amber-500/20 to-orange-900/20', 'ic' => 'text-amber-400', 'delay' => 'stagger-3'],
        ['label' => 'Total time', 'value' => array_sum(array_column($results, 'duration_min')) . ' min', 'sub' => 'Time on recorded tests', 'grad' => 'from-brand-500/20 to-violet-900/20', 'ic' => 'text-brand-400', 'delay' => 'stagger-4'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/results.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="flex min-h-screen">

    <aside class="sidebar fixed left-0 top-0 h-full w-64 z-50 flex flex-col
                  transform -translate-x-full lg:translate-x-0 transition-transform duration-300"
           id="sidebar">

        <div class="px-6 py-5 border-b border-brand-900/30">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                            flex items-center justify-center shadow-lg shadow-brand-500/30">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 01-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 01-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 01-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 01.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-display font-800 text-white text-lg tracking-tight">SkillTrust</span>
                    <div class="text-xs text-brand-400 font-medium -mt-0.5">Student Panel</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            <div class="px-3 mb-3">
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-widest">Main Menu</span>
            </div>

            <a href="dashboard.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                    </svg>
                </div>
                <span>Dashboard</span>
            </a>

            <a href="tests.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
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

            <a href="results.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <div class="w-8 h-8 rounded-lg bg-brand-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
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
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span>Settings</span>
            </a>
        </nav>

        <div class="px-4 py-4 border-t border-brand-900/30">
            <div class="student-shell-user flex items-center gap-3 p-3 rounded-xl bg-slate-800/60 hover:bg-slate-800 transition-colors cursor-pointer">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                            flex items-center justify-center text-white font-display font-bold text-sm flex-shrink-0">
                    <?php echo htmlspecialchars($student['avatar']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                </svg>
            </div>
        </div>
    </aside>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

        <header class="navbar sticky top-0 z-30 px-4 lg:px-8 h-16 flex items-center justify-between gap-4">
            <button onclick="toggleSidebar()"
                    class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="hidden lg:block">
                <h2 class="font-display font-bold text-white text-lg">Test Results</h2>
                <p class="text-xs text-slate-500">Performance overview and history</p>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <div class="hidden md:flex items-center gap-2 bg-slate-800/60 border border-slate-700/50
                            rounded-xl px-3 py-2 focus-within:border-brand-500/50 transition-all duration-300">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" id="resultsSearch" placeholder="Filter tests..."
                           class="bg-transparent text-sm text-slate-300 placeholder-slate-600 outline-none w-36 focus:w-44 transition-all duration-300">
                </div>

                <button class="relative p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot absolute top-2 right-2 w-2 h-2 rounded-full bg-brand-500"></span>
                </button>

                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                    <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <div class="relative" id="profileDropdown">
                    <button onclick="toggleDropdown()"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all duration-300">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600
                                    flex items-center justify-center text-white font-display font-bold text-xs">
                            <?php echo htmlspecialchars($student['avatar']); ?>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-500 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div class="dropdown-menu absolute right-0 top-full mt-2 w-52
                                bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-50"
                         id="dropdownMenu">
                        <div class="px-4 py-3 border-b border-slate-700/50">
                            <div class="font-semibold text-white text-sm"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-300">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                My Profile
                            </a>
                        </div>
                        <div class="border-t border-slate-700/50 py-1">
                            <a href="../auth/login.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-all duration-300">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>

                <a href="../auth/login.php"
                   class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white
                          px-3 py-2 rounded-xl border border-slate-700/60 hover:border-slate-600 hover:bg-slate-800/60 transition-all duration-300">
                    Logout
                </a>
            </div>
        </header>

        <main class="flex-1 px-4 lg:px-8 py-8 space-y-8">

            <!-- Big score card + badge -->
            <section class="opacity-0-start animate-fade-up stagger-1 score-hero rounded-3xl p-8 lg:p-10 relative overflow-hidden">
                <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
                     style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.35) 1px, transparent 0); background-size: 28px 28px;"></div>
                <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-violet-500/20 blur-3xl pointer-events-none"></div>
                <div class="absolute -left-10 bottom-0 w-48 h-48 rounded-full bg-brand-500/15 blur-3xl pointer-events-none"></div>

                <div class="relative flex flex-col xl:flex-row items-center xl:items-center justify-between gap-10">
                    <div class="flex flex-col sm:flex-row items-center gap-8 text-center sm:text-left">
                        <div class="relative w-40 h-40 flex-shrink-0">
                            <svg class="w-40 h-40 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(99,102,241,0.12)" stroke-width="8"/>
                                <circle cx="50" cy="50" r="45" fill="none"
                                        stroke="url(#resultsRingGrad)" stroke-width="8" stroke-linecap="round"
                                        class="ring-score" id="resultsRing"/>
                                <defs>
                                    <linearGradient id="resultsRingGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#6366f1"/>
                                        <stop offset="100%" stop-color="#a78bfa"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="font-display font-extrabold text-4xl lg:text-5xl text-white tracking-tight" id="avgScoreNum">0</span>
                                <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Avg score</span>
                            </div>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold mb-4 border transition-all duration-300
                                <?php echo $overall_pass
                                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30 badge-pulse'
                                    : 'bg-red-500/15 text-red-300 border-red-500/30 badge-pulse-fail'; ?>">
                                <span class="w-2 h-2 rounded-full <?php echo $overall_pass ? 'bg-emerald-400' : 'bg-red-400'; ?>"></span>
                                <?php echo $overall_pass ? 'Overall pass' : 'Below pass threshold'; ?>
                            </div>
                            <h1 class="font-display text-2xl lg:text-3xl font-extrabold text-white mb-2">
                                You averaged <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-300 to-violet-300"><?php echo (int) $avg_score; ?>%</span> across <?php echo $total_tests; ?> tests
                            </h1>
                            <p class="text-slate-400 text-sm max-w-lg leading-relaxed">
                                Pass mark is <?php echo $pass_threshold; ?>%. You passed <strong class="text-white"><?php echo $passed_count; ?></strong> of
                                <strong class="text-white"><?php echo $total_tests; ?></strong> attempts. Retake weaker areas to lift your Trust Score.
                            </p>
                            <div class="flex flex-wrap gap-3 mt-6 justify-center sm:justify-start">
                                <a href="tests.php"
                                   class="glow-btn relative inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-white font-semibold text-sm shadow-xl transition-all duration-300">
                                    <span class="relative z-10 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Start a test
                                    </span>
                                </a>
                                <a href="dashboard.php"
                                   class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl font-semibold text-sm text-white
                                          bg-white/10 border border-white/15 hover:bg-white/15 transition-all duration-300 hover:scale-[1.02]">
                                    Back to dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl px-6 py-5 w-full max-w-xs xl:max-w-[14rem] flex flex-col gap-4 border border-white/5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Status</span>
                            <span id="overallBadge"
                                  class="px-3 py-1.5 rounded-xl text-sm font-bold uppercase tracking-wide transition-all duration-300 hover:scale-105
                                  <?php echo $overall_pass
                                      ? 'bg-emerald-500/20 text-emerald-400 ring-1 ring-emerald-500/30'
                                      : 'bg-red-500/20 text-red-400 ring-1 ring-red-500/30'; ?>">
                                <?php echo $overall_pass ? 'Pass' : 'Fail'; ?>
                            </span>
                        </div>
                        <div class="h-px bg-slate-700/50"></div>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Trust score</span>
                                <span class="font-display font-bold text-brand-300"><?php echo (int) $student['trust_score']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Tests logged</span>
                                <span class="font-semibold text-white"><?php echo $total_tests; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Pass rate</span>
                                <span class="font-semibold text-white"><?php echo $total_tests ? round(100 * $passed_count / $total_tests) : 0; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick stats -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php
                $mini = [
                    ['label' => 'Highest score', 'value' => max(array_column($results, 'score')) . '%', 'sub' => 'Best single attempt', 'grad' => 'from-emerald-500/20 to-teal-900/20', 'ic' => 'text-emerald-400', 'delay' => 'stagger-2'],
                    ['label' => 'Lowest score', 'value' => min(array_column($results, 'score')) . '%', 'sub' => 'Focus area', 'grad' => 'from-amber-500/20 to-orange-900/20', 'ic' => 'text-amber-400', 'delay' => 'stagger-3'],
                    ['label' => 'Total time', 'value' => array_sum(array_column($results, 'duration_min')) . ' min', 'sub' => 'Time on recorded tests', 'grad' => 'from-brand-500/20 to-violet-900/20', 'ic' => 'text-brand-400', 'delay' => 'stagger-4'],
                ];
                foreach ($mini as $m): ?>
                    <div class="glass-card opacity-0-start animate-fade-up <?php echo $m['delay']; ?> rounded-2xl p-5 bg-gradient-to-br <?php echo $m['grad']; ?>
                                hover:shadow-lg hover:shadow-black/20 hover:-translate-y-0.5 transition-all duration-300">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-800/80 flex items-center justify-center">
                                <svg class="w-5 h-5 <?php echo $m['ic']; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars($m['label']); ?></span>
                        </div>
                        <div class="font-display text-2xl font-extrabold text-white"><?php echo htmlspecialchars($m['value']); ?></div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($m['sub']); ?></div>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- Results table -->
            <section class="opacity-0-start animate-fade-up stagger-5">
                <div class="glass-card rounded-2xl overflow-hidden hover:shadow-xl hover:shadow-black/25 transition-all duration-300">
                    <div class="px-6 py-5 border-b border-slate-700/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-display font-bold text-white text-base">Detailed results</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Sortable history - search filters rows live</p>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 bg-slate-800/60 px-3 py-1.5 rounded-lg border border-slate-700/50">
                            <?php echo $total_tests; ?> records
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" id="resultsTable">
                            <thead>
                                <tr class="text-slate-500 text-xs uppercase tracking-wider border-b border-slate-700/40 bg-slate-800/30">
                                    <th class="px-6 py-4 font-semibold">Test</th>
                                    <th class="px-4 py-4 font-semibold hidden md:table-cell">Difficulty</th>
                                    <th class="px-4 py-4 font-semibold">Score</th>
                                    <th class="px-4 py-4 font-semibold">Result</th>
                                    <th class="px-4 py-4 font-semibold hidden lg:table-cell">Correct</th>
                                    <th class="px-4 py-4 font-semibold hidden xl:table-cell">Duration</th>
                                    <th class="px-6 py-4 font-semibold">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php
                                $diff_styles = [
                                    'Beginner' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
                                    'Intermediate' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                                    'Advanced' => 'text-red-400 bg-red-500/10 border-red-500/20',
                                ];
                                if ($results === []):
                                ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 text-sm">No results found</td>
                                    </tr>
                                <?php
                                else:
                                    $i = 0;
                                    $n = count($results);
                                    while ($i < $n):
                                        $r = $results[$i];
                                    $row_pass = (float) ($r['score'] ?? 0) >= $pass_threshold;
                                    $diff_class = $diff_styles[$r['difficulty'] ?? ''] ?? 'text-slate-400 bg-slate-700/40 border-slate-600/40';
                                ?>
                                    <tr class="results-row table-row-enter hover:bg-slate-800/40 transition-all duration-300 group"
                                        data-title="<?php echo htmlspecialchars(strtolower($r['title'] ?? '')); ?>">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-slate-200 group-hover:text-white transition-colors"><?php echo htmlspecialchars($r['title'] ?? ''); ?></div>
                                            <div class="text-xs text-slate-500 md:hidden mt-0.5"><?php echo htmlspecialchars($r['difficulty'] ?? ''); ?></div>
                                        </td>
                                        <td class="px-4 py-4 hidden md:table-cell">
                                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg border <?php echo $diff_class; ?>">
                                                <?php echo htmlspecialchars($r['difficulty'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="font-display font-bold text-lg <?php
                                                $sc = (float) ($r['score'] ?? 0);
                                                echo $sc >= 80 ? 'text-emerald-400' : ($sc >= $pass_threshold ? 'text-amber-400' : 'text-red-400');
                                            ?>"><?php echo (int) round($r['score'] ?? 0); ?>%</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-lg border transition-transform duration-300 group-hover:scale-105 <?php
                                                echo $row_pass
                                                    ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25'
                                                    : 'bg-red-500/15 text-red-400 border-red-500/25';
                                            ?>">
                                                <?php echo $row_pass ? 'Pass' : 'Fail'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 hidden lg:table-cell text-slate-400">
                                            <?php echo (int) ($r['correct'] ?? 0); ?> / <?php echo (int) ($r['total'] ?? 0); ?>
                                        </td>
                                        <td class="px-4 py-4 hidden xl:table-cell text-slate-500">
                                            <?php echo (int) ($r['duration_min'] ?? 0); ?> min
                                        </td>
                                        <td class="px-6 py-4 text-slate-500">
                                            <span class="hidden sm:inline"><?php echo htmlspecialchars($r['date'] ?? ''); ?></span>
                                            <span class="sm:hidden text-xs"><?php echo htmlspecialchars($r['date_short'] ?? ''); ?></span>
                                        </td>
                                    </tr>
                                <?php
                                        $i++;
                                    endwhile;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="emptyResults" class="hidden px-6 py-12 text-center text-slate-500 text-sm">
                        No tests match your search.
                    </div>
                </div>
            </section>

        </main>

        <footer class="px-8 py-4 border-t border-slate-800/60 flex items-center justify-between">
            <span class="text-xs text-slate-600">(c) 2026 SkillTrust - Student Panel</span>
            <span class="text-xs text-slate-600">Results</span>
        </footer>
    </div>
</div>

<script type="application/json" id="studentResultsData"><?php echo json_encode(['avgScore' => (int) $avg_score], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/student/results.js"></script>

</body>
</html>
