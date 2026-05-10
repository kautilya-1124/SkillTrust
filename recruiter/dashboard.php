<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_recruiter_login();

$recruiterId = current_recruiter_id();
$toast = consume_flash_toast();
$requiredTables = ['recruiters', 'jobs', 'applications', 'users', 'results'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}
$schemaReady = $missingTables === [];
$jobMinAverageColumn = '';
$hasAverageSnapshotColumn = db_column_exists($conn, 'applications', 'average_score_snapshot');
$interviewDateColumn = db_table_exists($conn, 'interviews')
    ? (db_column_exists($conn, 'interviews', 'interview_datetime') ? 'interview_datetime' : (db_column_exists($conn, 'interviews', 'scheduled_at') ? 'scheduled_at' : ''))
    : '';

if ($schemaReady) {
    if (db_column_exists($conn, 'jobs', 'min_average_score')) {
        $jobMinAverageColumn = 'min_average_score';
    } elseif (db_column_exists($conn, 'jobs', 'min_avg_score')) {
        $jobMinAverageColumn = 'min_avg_score';
    }
}

$profile = [
    'company_name' => (string) ($_SESSION['recruiter_company'] ?? current_recruiter_display_name()),
    'contact_name' => (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name()),
    'recruiter_name' => (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name()),
    'email' => (string) ($_SESSION['recruiter_email'] ?? ''),
    'status' => (string) ($_SESSION['recruiter_status'] ?? 'approved'),
];
if ($schemaReady) {
    $selectNameColumn = db_column_exists($conn, 'recruiters', 'recruiter_name') ? 'recruiter_name' : 'contact_name';
    $stmt = $conn->prepare(sprintf('SELECT company_name, %s, email, status FROM recruiters WHERE id = ? LIMIT 1', $selectNameColumn));
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $row = db_fetch_one($stmt);
        $stmt->close();
        if ($row) {
            $profile = array_merge($profile, $row);
        }
    }
}

$stats = ['total_jobs' => 0, 'active_jobs' => 0, 'expired_jobs' => 0, 'total_applicants' => 0, 'selected' => 0, 'interviews' => 0];
$recentJobs = [];
$recentApplications = [];
$activeJobs = [];
$expiredJobs = [];
$topCandidates = [];
$avgApplicantScore = 0.0;

if ($schemaReady) {
    $queries = [
        'jobs' => 'SELECT COUNT(*) total_jobs, SUM(CASE WHEN expiry_date >= CURDATE() THEN 1 ELSE 0 END) active_jobs, SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END) expired_jobs FROM jobs WHERE recruiter_id = ?',
        'apps' => $hasAverageSnapshotColumn
            ? 'SELECT COUNT(a.id) total_applicants, SUM(CASE WHEN a.status = "selected" THEN 1 ELSE 0 END) selected, AVG(COALESCE((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id), a.average_score_snapshot)) avg_score FROM applications a INNER JOIN jobs j ON j.id = a.job_id WHERE j.recruiter_id = ?'
            : 'SELECT COUNT(a.id) total_applicants, SUM(CASE WHEN a.status = "selected" THEN 1 ELSE 0 END) selected, AVG((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id)) avg_score FROM applications a INNER JOIN jobs j ON j.id = a.job_id WHERE j.recruiter_id = ?',
    ];
    foreach ($queries as $key => $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $recruiterId);
            $stmt->execute();
            $row = db_fetch_one($stmt);
            $stmt->close();
            if ($row) {
                if ($key === 'jobs') {
                    $stats['total_jobs'] = (int) ($row['total_jobs'] ?? 0);
                    $stats['active_jobs'] = (int) ($row['active_jobs'] ?? 0);
                    $stats['expired_jobs'] = (int) ($row['expired_jobs'] ?? 0);
                } else {
                    $stats['total_applicants'] = (int) ($row['total_applicants'] ?? 0);
                    $stats['selected'] = (int) ($row['selected'] ?? 0);
                    $avgApplicantScore = normalize_score($row['avg_score'] ?? 0);
                }
            }
        }
    }
    if (db_table_exists($conn, 'interviews')) {
        $stmt = $interviewDateColumn !== '' ? $conn->prepare(
            'SELECT COUNT(*) interviews
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE j.recruiter_id = ? AND i.status = "scheduled" AND i.' . $interviewDateColumn . ' >= NOW()'
        ) : false;
        if ($stmt) {
            $stmt->bind_param('i', $recruiterId);
            $stmt->execute();
            $row = db_fetch_one($stmt);
            $stmt->close();
            $stats['interviews'] = (int) ($row['interviews'] ?? 0);
        }
    }

    $jobScoreSelect = $jobMinAverageColumn !== '' ? 'j.' . $jobMinAverageColumn . ' AS min_average_score' : '0 AS min_average_score';
    $jobScoreGroupBy = $jobMinAverageColumn !== '' ? 'j.' . $jobMinAverageColumn : '0';
    $listSql = sprintf('SELECT j.id, j.title, %s, j.expiry_date, j.created_at, COUNT(a.id) applicant_count FROM jobs j LEFT JOIN applications a ON a.job_id = j.id WHERE j.recruiter_id = ? %%s GROUP BY j.id, j.title, %s, j.expiry_date, j.created_at ORDER BY %%s LIMIT 6', $jobScoreSelect, $jobScoreGroupBy);
    $lists = [
        'recent' => ['sql' => sprintf('SELECT j.id, j.title, %s, j.expiry_date, j.created_at, COUNT(a.id) applicant_count FROM jobs j LEFT JOIN applications a ON a.job_id = j.id WHERE j.recruiter_id = ? GROUP BY j.id, j.title, %s, j.expiry_date, j.created_at ORDER BY j.created_at DESC LIMIT 5', $jobScoreSelect, $jobScoreGroupBy)],
        'active' => ['sql' => sprintf($listSql, 'AND j.expiry_date >= CURDATE()', 'j.expiry_date ASC, j.created_at DESC')],
        'expired' => ['sql' => sprintf($listSql, 'AND j.expiry_date < CURDATE()', 'j.expiry_date DESC, j.created_at DESC')],
    ];
    foreach ($lists as $key => $config) {
        $stmt = $conn->prepare($config['sql']);
        if ($stmt) {
            $stmt->bind_param('i', $recruiterId);
            $stmt->execute();
            $rows = db_fetch_all($stmt);
            $stmt->close();
            if ($key === 'recent') {
                $recentJobs = $rows;
            } elseif ($key === 'active') {
                $activeJobs = $rows;
            } else {
                $expiredJobs = $rows;
            }
        }
    }

    $recentApplicationsSql = $hasAverageSnapshotColumn
        ? 'SELECT a.status, a.applied_at, a.average_score_snapshot, u.name candidate_name, u.email candidate_email, j.title job_title FROM applications a INNER JOIN jobs j ON j.id = a.job_id INNER JOIN users u ON u.id = a.user_id WHERE j.recruiter_id = ? ORDER BY a.applied_at DESC LIMIT 8'
        : 'SELECT a.status, a.applied_at, (SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id) AS average_score_snapshot, u.name candidate_name, u.email candidate_email, j.title job_title FROM applications a INNER JOIN jobs j ON j.id = a.job_id INNER JOIN users u ON u.id = a.user_id WHERE j.recruiter_id = ? ORDER BY a.applied_at DESC LIMIT 8';
    $stmt = $conn->prepare($recentApplicationsSql);
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $recentApplications = db_fetch_all($stmt);
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT u.name, u.email, AVG(r.score) average_score, COUNT(r.id) attempts_count FROM applications a INNER JOIN jobs j ON j.id = a.job_id INNER JOIN users u ON u.id = a.user_id INNER JOIN results r ON r.user_id = u.id WHERE j.recruiter_id = ? GROUP BY u.id, u.name, u.email ORDER BY average_score DESC, attempts_count DESC LIMIT 5');
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $topCandidates = db_fetch_all($stmt);
        $stmt->close();
    }
}

function jobState(string $expiryDate): array {
    return $expiryDate >= date('Y-m-d')
        ? ['Active', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200']
        : ['Expired', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
}
function appState(string $status): array {
    $map = [
        'applied' => ['Applied', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'],
        'shortlisted' => ['Shortlisted', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200'],
        'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200'],
        'selected' => ['Selected', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200'],
    ];
    return $map[strtolower($status)] ?? ['Unknown', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
}

$profileName = trim((string) ($profile['recruiter_name'] ?? '')) !== ''
    ? (string) $profile['recruiter_name']
    : (string) ($profile['contact_name'] ?? '');
$name = trim($profileName) !== '' ? $profileName : (string) $profile['company_name'];
$company = trim((string) $profile['company_name']) !== '' ? (string) $profile['company_name'] : 'SkillTrust Recruiter';
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company), 0, 2) ?: 'ST');
$companyWords = preg_split('/\s+/', trim($company), -1, PREG_SPLIT_NO_EMPTY);
$companyLogoText = 'ST';
if ($companyWords !== false && $companyWords !== []) {
    if (count($companyWords) >= 2) {
        $companyLogoText = strtoupper(substr((string) $companyWords[0], 0, 1) . substr((string) $companyWords[1], 0, 1));
    } else {
        $lettersOnly = preg_replace('/[^A-Za-z]/', '', (string) $companyWords[0]);
        $companyLogoText = strtoupper(substr($lettersOnly !== '' ? $lettersOnly : (string) $companyWords[0], 0, 2) ?: 'ST');
    }
}
$selectionRate = $stats['total_applicants'] > 0 ? round(($stats['selected'] / $stats['total_applicants']) * 100, 1) : 0.0;
$pipelineHealth = $stats['total_jobs'] > 0 ? round(($stats['active_jobs'] / $stats['total_jobs']) * 100, 1) : 0.0;
$profileStatus = strtolower(trim((string) ($profile['status'] ?? 'approved')));
$statusMeta = [
    'approved' => ['Approved', 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'],
    'pending' => ['Pending', 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'],
    'rejected' => ['Rejected', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
    'blocked' => ['Blocked', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
];
[$statusLabel, $statusClass] = $statusMeta[$profileStatus] ?? ['Unknown', 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200'];

$weeklyLabels = [];
$weeklyCounts = [];
$jobMap = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $jobMap[$day] = 0;
}
foreach ($recentJobs as $job) {
    $day = date('Y-m-d', strtotime((string) ($job['created_at'] ?? '')));
    if (isset($jobMap[$day])) {
        $jobMap[$day]++;
    }
}
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $weeklyLabels[] = date('D', strtotime($day));
    $weeklyCounts[] = (int) ($jobMap[$day] ?? 0);
}
$statusCounts = [
    (int) $stats['active_jobs'],
    (int) $stats['expired_jobs'],
    (int) $stats['selected'],
    (int) $stats['interviews'],
];
$currentPage = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border"></div>
<div class="flex min-h-screen">
    <?php require_once __DIR__ . '/../includes/recruiter_sidebar.php'; ?>

    <div class="recruiter-main flex-1 flex flex-col min-h-screen min-w-0 max-w-full">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">&#9776;</button>
                <div>
                    <h2 class="font-display font-bold text-white text-lg">Recruiter Dashboard</h2>
                    <p class="text-xs text-slate-500"><?php echo e(date('l, d M Y')); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button id="themeToggle" type="button" class="hidden md:inline-flex rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-all duration-300">
                    <span id="themeToggleLabel">Dark mode</span>
                </button>
                <div class="relative" id="recruiterDropdown">
                    <button type="button" id="recruiterMenuBtn" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all duration-300">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-xs"><?php echo e($initials); ?></div>
                        <span class="hidden md:inline text-sm text-slate-300"><?php echo e($name); ?></span>
                    </button>
                    <div id="recruiterMenu" class="hidden absolute right-0 mt-2 w-52 bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-[60]">
                        <div class="px-4 py-3 border-b border-slate-700/60">
                            <p class="text-sm font-semibold text-white"><?php echo e($company); ?></p>
                            <p class="text-xs text-slate-400"><?php echo e((string) $profile['email']); ?></p>
                        </div>
                        <a href="create_job.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Create Job</a>
                        <a href="manage_jobs.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Manage Jobs</a>
                        <a href="logout.php" class="block px-4 py-2.5 text-sm text-rose-400 hover:bg-rose-500/10 transition-colors">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 min-w-0 px-3 sm:px-4 lg:px-8 py-6 sm:py-8 space-y-6 overflow-x-hidden">
    <section class="fade-up relative rounded-3xl overflow-hidden border border-indigo-500/25 bg-gradient-to-br from-indigo-900/35 via-slate-900/80 to-violet-900/30 p-6 sm:p-8">
        <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 bottom-0 w-44 h-44 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex items-start gap-5">
                <div class="relative hidden sm:block">
                    <div class="absolute inset-0 rounded-[1.75rem] bg-indigo-500/20 blur-2xl"></div>
                    <div class="relative flex h-24 w-24 items-center justify-center rounded-[1.75rem] border border-white/15 bg-gradient-to-br from-indigo-500 via-violet-500 to-sky-400 text-2xl font-black tracking-[0.18em] text-white shadow-glow">
                        <?php echo e($companyLogoText); ?>
                    </div>
                </div>
                <div>
                <h1 class="font-display font-extrabold text-2xl sm:text-3xl text-white">Welcome back, <?php echo e($name); ?></h1>
                <p class="text-sm text-slate-400 mt-2 max-w-2xl">Your recruiter workspace tracks jobs, applications, interviews, and candidate quality using average test performance across all attempts.</p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs">
                    <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 text-emerald-300">Average-score hiring enabled</span>
                    <span class="rounded-full bg-indigo-500/10 border border-indigo-500/20 px-3 py-1 text-indigo-200"><?php echo e($company); ?></span>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?php echo e($profileStatus === 'approved' ? 'bg-emerald-500/15 border-emerald-500/25 text-emerald-300' : ($profileStatus === 'pending' ? 'bg-amber-500/15 border-amber-500/25 text-amber-300' : 'bg-rose-500/15 border-rose-500/25 text-rose-300')); ?>"><?php echo e($statusLabel); ?></span>
                </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="create_job.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-200 hover:bg-indigo-500/25 transition-all duration-300">Create Job</a>
                <a href="manage_jobs.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700/80 transition-all duration-300">Manage Jobs</a>
                <a href="applicants.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700/80 transition-all duration-300">Applicants</a>
            </div>
        </div>
    </section>

    <?php if (!$schemaReady): ?>
        <section class="rounded-[24px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-500/10">
            <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Database setup required</h3>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">Run <code class="rounded bg-white px-1.5 py-0.5 dark:bg-slate-900/60">sql/recruiter_hiring_panel.sql</code>. Missing tables: <?php echo e(implode(', ', $missingTables)); ?>.</p>
        </section>
    <?php endif; ?>

    <section class="fade-up grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php $cards = [
            ['Total Jobs', $stats['total_jobs'], 'Roles posted by your company', 'text-indigo-300'],
            ['Active Jobs', $stats['active_jobs'], 'Currently open roles', 'text-emerald-300'],
            ['Interviews', $stats['interviews'], 'Upcoming scheduled interviews', 'text-amber-300'],
        ]; ?>
        <?php foreach ($cards as $card): ?>
            <div class="glass-card metric-card rounded-2xl p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500"><?php echo e($card[0]); ?></p>
                <p class="stat-value mt-2 <?php echo e($card[3]); ?>"><?php echo e((string) $card[1]); ?></p>
                <p class="mt-2 text-xs text-slate-500"><?php echo e($card[2]); ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="fade-up grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 glass-card rounded-2xl p-5 sm:p-6">
            <h2 class="font-display font-bold text-white text-lg mb-4">Jobs Created (Last 7 Days)</h2>
            <canvas id="jobsChart" height="120"></canvas>
        </div>
        <div class="glass-card rounded-2xl p-5 sm:p-6">
            <h2 class="font-display font-bold text-white text-lg mb-4">Hiring Snapshot</h2>
            <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-2 py-1 text-emerald-300">Avg Score: <?php echo e(number_format($avgApplicantScore, 2)); ?></div>
                <div class="rounded-lg bg-indigo-500/10 border border-indigo-500/20 px-2 py-1 text-indigo-300">Pipeline Health: <?php echo e(number_format($pipelineHealth, 1)); ?>%</div>
            </div>
            <canvas id="recruiterStatusChart" height="170"></canvas>
        </div>
    </section>

    <section class="fade-up grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
                <div>
                    <h3 class="font-display font-bold text-white text-base">Recent Jobs</h3>
                    <p class="text-xs text-slate-500 mt-1">Latest roles published by your company</p>
                </div>
                <a href="manage_jobs.php" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200 transition-colors">View all</a>
            </div>
            <div class="p-5 space-y-4">
                <?php if ($recentJobs === []): ?>
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-400">No jobs posted yet.</div>
                <?php else: foreach ($recentJobs as $job): [$stateLabel, $stateClass] = jobState((string) $job['expiry_date']); ?>
                    <article class="rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 dark:border-white/10 dark:from-slate-900 dark:to-slate-950/80">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e((string) $job['title']); ?></h4>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($stateClass); ?>"><?php echo e($stateLabel); ?></span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 dark:border-white/10 dark:bg-slate-900/70">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Minimum avg</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-white"><?php echo e(number_format((float) ($job['min_average_score'] ?? 0), 2)); ?></p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 dark:border-white/10 dark:bg-slate-900/70">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Applicants</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-white"><?php echo e((string) ((int) ($job['applicant_count'] ?? 0))); ?></p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 dark:border-white/10 dark:bg-slate-900/70">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Expiry</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-white"><?php echo e(format_date_label((string) $job['expiry_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                                    <span>Posted <?php echo e(time_ago_label((string) $job['created_at'])); ?></span>
                                    <span>Average-score filtered applicants only</span>
                                </div>
                            </div>
                            <a href="job_details.php?id=<?php echo (int) ($job['id'] ?? 0); ?>" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-blue-400/30 dark:hover:text-white">Details</a>
                        </div>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h3 class="font-display font-bold text-white text-base">Top Candidates</h3>
                <p class="text-xs text-slate-500 mt-1">Highest average performers across applications</p>
            </div>
            <div class="p-5 space-y-4">
                <?php if ($topCandidates === []): ?>
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-400">No ranked candidates yet.</div>
                <?php else: foreach ($topCandidates as $index => $candidate): ?>
                    <div class="flex items-center gap-4 rounded-[26px] border border-slate-200 bg-gradient-to-r from-white to-slate-50 p-4 dark:border-white/10 dark:from-slate-900 dark:to-slate-950/70">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-sm font-bold text-white dark:bg-white dark:text-slate-900"><?php echo e((string) ($index + 1)); ?></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-950 dark:text-white"><?php echo e((string) $candidate['name']); ?></p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400"><?php echo e((string) $candidate['email']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold text-slate-950 dark:text-white"><?php echo e(number_format((float) ($candidate['average_score'] ?? 0), 2)); ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e((string) ((int) ($candidate['attempts_count'] ?? 0))); ?> attempts</p>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <section class="fade-up grid grid-cols-1 xl:grid-cols-3 gap-4">
        <?php $jobColumns = [['Active Jobs', $activeJobs, 'Inspect role'], ['Expired Jobs', $expiredJobs, 'Open summary']]; ?>
        <?php foreach ($jobColumns as $column): ?>
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-700/50">
                    <h3 class="font-display font-bold text-white text-base"><?php echo e($column[0]); ?></h3>
                </div>
                <div class="p-5 space-y-4">
                    <?php if ($column[1] === []): ?>
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-400">No jobs in this section yet.</div>
                    <?php else: foreach ($column[1] as $job): ?>
                        <div class="rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 dark:border-white/10 dark:from-slate-900 dark:to-slate-950/80">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e((string) $job['title']); ?></h4>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Min average score: <?php echo e(number_format((float) ($job['min_average_score'] ?? 0), 2)); ?></p>
                                </div>
                                <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"><?php echo e((string) ((int) ($job['applicant_count'] ?? 0))); ?> applicants</span>
                            </div>
                            <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span><?php echo e($column[0] === 'Active Jobs' ? 'Expires ' : 'Expired '); ?><?php echo e(format_date_label((string) $job['expiry_date'])); ?></span>
                                <a href="job_details.php?id=<?php echo (int) ($job['id'] ?? 0); ?>" class="font-semibold text-slate-700 transition hover:text-blue-700 dark:text-slate-200 dark:hover:text-white"><?php echo e($column[2]); ?></a>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/50 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="font-display font-bold text-white text-base">Recent Applications</h3>
                <p class="text-xs text-slate-500 mt-1">Latest candidates entering your hiring funnel</p>
            </div>
            <a href="applicants.php" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200 transition-colors">Open applicants panel</a>
        </div>
        <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:bg-slate-950/70 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4 font-semibold">Candidate</th>
                            <th class="px-5 py-4 font-semibold">Job</th>
                            <th class="px-5 py-4 font-semibold">Average Score</th>
                            <th class="px-5 py-4 font-semibold">Applied</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-white/10 dark:bg-slate-900/40">
                        <?php if ($recentApplications === []): ?>
                            <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No applications yet. Eligible candidates will appear here with their average-score snapshot.</td></tr>
                        <?php else: foreach ($recentApplications as $application): [$appLabel, $appClass] = appState((string) $application['status']); ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-950/70">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-xs font-bold text-white dark:bg-white dark:text-slate-900"><?php echo e(strtoupper(substr((string) $application['candidate_name'], 0, 2))); ?></div>
                                        <div>
                                            <p class="font-semibold text-slate-950 dark:text-white"><?php echo e((string) $application['candidate_name']); ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e((string) $application['candidate_email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-slate-700 dark:text-slate-200"><?php echo e((string) $application['job_title']); ?></td>
                                <td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200"><?php echo e(number_format((float) ($application['average_score_snapshot'] ?? 0), 2)); ?></span></td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400"><?php echo e(format_datetime_label((string) $application['applied_at'])); ?></td>
                                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($appClass); ?>"><?php echo e($appLabel); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
        </div>
        </div>
    </section>
        </main>
    </div>
</div>

<script type="application/json" id="recruiterDashboardToastData"><?php echo json_encode(['toastType' => $toast['type'] ?? '', 'toastMsg' => $toast['message'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<script type="application/json" id="recruiterDashboardData"><?php echo json_encode(['weeklyLabels' => $weeklyLabels, 'weeklyCounts' => $weeklyCounts, 'statusCounts' => $statusCounts], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script>
(function () {
    window.toggleSidebar = window.AdminCommonPage.toggleSidebar;
    window.AdminCommonPage.initPage('recruiterDashboardToastData');

    const recruiterMenuBtn = document.getElementById('recruiterMenuBtn');
    const recruiterMenu = document.getElementById('recruiterMenu');
    if (recruiterMenuBtn && recruiterMenu) {
        recruiterMenuBtn.addEventListener('click', function () {
            recruiterMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function (event) {
            if (!recruiterMenu.contains(event.target) && !recruiterMenuBtn.contains(event.target)) {
                recruiterMenu.classList.add('hidden');
            }
        });
    }

    const dashboardData = JSON.parse(document.getElementById('recruiterDashboardData').textContent || '{}');
    const jobsChartCtx = document.getElementById('jobsChart');
    if (jobsChartCtx) {
        new Chart(jobsChartCtx, {
            type: 'line',
            data: {
                labels: dashboardData.weeklyLabels || [],
                datasets: [{
                    label: 'Jobs',
                    data: dashboardData.weeklyCounts || [],
                    borderColor: '#818cf8',
                    backgroundColor: 'rgba(129, 140, 248, 0.18)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.08)' } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.08)' } }
                }
            }
        });
    }

    const recruiterStatusChart = document.getElementById('recruiterStatusChart');
    if (recruiterStatusChart) {
        new Chart(recruiterStatusChart, {
            type: 'doughnut',
            data: {
                labels: ['Active Jobs', 'Expired Jobs', 'Selected', 'Interviews'],
                datasets: [{
                    data: dashboardData.statusCounts || [],
                    backgroundColor: ['#34d399', '#fb7185', '#60a5fa', '#fbbf24'],
                    borderColor: ['#0f172a', '#0f172a', '#0f172a', '#0f172a'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8' } },
                    tooltip: { backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#cbd5e1' }
                },
                animation: { duration: 1300, easing: 'easeOutQuart' }
            }
        });
    }
}());
</script>
</body>
</html>
