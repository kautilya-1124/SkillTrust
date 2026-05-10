<?php
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$toast = consume_flash_toast();

$userStmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$studentName = 'Student';
$studentEmail = '';
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userRow = db_fetch_one($userStmt);
    $userStmt->close();

    $studentName = trim((string) ($userRow['name'] ?? $_SESSION['name'] ?? 'Student'));
    $studentEmail = (string) ($userRow['email'] ?? $_SESSION['email'] ?? '');
}

$nameParts = preg_split('/\s+/', $studentName, -1, PREG_SPLIT_NO_EMPTY);
$studentInitials = 'ST';
if (is_array($nameParts) && $nameParts !== []) {
    $studentInitials = count($nameParts) >= 2
        ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
        : strtoupper(substr($nameParts[0], 0, 2));
}

$requiredTables = ['jobs', 'recruiters', 'applications', 'results'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}
$schemaReady = $missingTables === [];

$jobMinAverageColumn = '';
if (db_column_exists($conn, 'jobs', 'min_average_score')) {
    $jobMinAverageColumn = 'min_average_score';
} elseif (db_column_exists($conn, 'jobs', 'min_avg_score')) {
    $jobMinAverageColumn = 'min_avg_score';
}

$hasRequiredTestColumns = false;

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;
$q = trim((string) ($_GET['q'] ?? ''));
$tab = strtolower(trim((string) ($_GET['tab'] ?? 'all')));
if (!in_array($tab, ['all', 'eligible'], true)) {
    $tab = 'all';
}

$jobs = [];
$totalJobs = 0;
$eligibleJobs = 0;
$appliedJobsCount = 0;
$studentAverageScore = 0.0;
$testScoreMap = [];
$currentDate = date('Y-m-d');

if ($schemaReady && $jobMinAverageColumn !== '') {
    $avgStmt = $conn->prepare('SELECT AVG(score) AS avg_score FROM results WHERE user_id = ?');
    if ($avgStmt) {
        $avgStmt->bind_param('i', $userId);
        $avgStmt->execute();
        $avgRow = db_fetch_one($avgStmt);
        $avgStmt->close();
        $studentAverageScore = normalize_score($avgRow['avg_score'] ?? 0);
    }

    $appliedCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM applications WHERE user_id = ?');
    if ($appliedCountStmt) {
        $appliedCountStmt->bind_param('i', $userId);
        $appliedCountStmt->execute();
        $appliedCountRow = db_fetch_one($appliedCountStmt);
        $appliedCountStmt->close();
        $appliedJobsCount = (int) ($appliedCountRow['total'] ?? 0);
    }

    $params = [];
    $types = '';
    $where = ['j.expiry_date >= CURDATE()'];
    if ($q !== '') {
        $where[] = '(j.title LIKE ? OR j.description LIKE ? OR r.company_name LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    $selectColumns = [
        'j.id',
        'j.recruiter_id',
        'j.title',
        'j.description',
        'j.expiry_date',
        'j.created_at',
        'j.' . $jobMinAverageColumn . ' AS min_average_score',
        'r.company_name',
        'COUNT(DISTINCT a.id) AS applicant_count',
        'MAX(CASE WHEN ua.user_id IS NOT NULL THEN 1 ELSE 0 END) AS has_applied',
        'MAX(ua.status) AS application_status',
    ];
    if ($hasRequiredTestColumns) {
        $selectColumns[] = 'j.required_test_id';
        $selectColumns[] = 'j.min_test_score';
        $selectColumns[] = 't.title AS required_test_title';
    }

    $jobsSql = sprintf(
        'SELECT %s
         FROM jobs j
         INNER JOIN recruiters r ON r.id = j.recruiter_id
         LEFT JOIN applications a ON a.job_id = j.id
         LEFT JOIN applications ua ON ua.job_id = j.id AND ua.user_id = ?
         %s
         WHERE %s
         GROUP BY j.id
         ORDER BY j.created_at DESC, j.id DESC',
        implode(', ', $selectColumns),
        $hasRequiredTestColumns ? 'LEFT JOIN tests t ON t.id = j.required_test_id' : '',
        implode(' AND ', $where)
    );

    $jobsStmt = $conn->prepare($jobsSql);
    if ($jobsStmt) {
        $bindTypes = 'i' . $types;
        $bindValues = array_merge([$userId], $params);
        $jobsStmt->bind_param($bindTypes, ...$bindValues);
        $jobsStmt->execute();
        $jobRows = db_fetch_all($jobsStmt);
        $jobsStmt->close();

        foreach ($jobRows as $jobRow) {
            $minAverageScore = normalize_score($jobRow['min_average_score'] ?? 0);
            $meetsAverage = $studentAverageScore >= $minAverageScore;
            $meetsRequiredTest = true;
            $isEligible = $meetsAverage;
            $expiryTimestamp = strtotime((string) ($jobRow['expiry_date'] ?? ''));
            $daysUntilExpiry = $expiryTimestamp !== false ? max(0, (int) ceil(($expiryTimestamp - strtotime($currentDate)) / 86400)) : 0;

            $job = [
                'id' => (int) ($jobRow['id'] ?? 0),
                'title' => (string) ($jobRow['title'] ?? ''),
                'description' => (string) ($jobRow['description'] ?? ''),
                'company_name' => (string) ($jobRow['company_name'] ?? 'Company'),
                'expiry_date' => (string) ($jobRow['expiry_date'] ?? ''),
                'created_at' => (string) ($jobRow['created_at'] ?? ''),
                'min_average_score' => $minAverageScore,
                'applicant_count' => (int) ($jobRow['applicant_count'] ?? 0),
                'applied' => (bool) ((int) ($jobRow['has_applied'] ?? 0) === 1),
                'status' => (string) ($jobRow['application_status'] ?? ''),
                'eligible' => $isEligible,
                'meets_average' => $meetsAverage,
                'meets_required_test' => $meetsRequiredTest,
                'avg_score' => $studentAverageScore,
                'required_test_id' => 0,
                'min_test_score' => 0.0,
                'required_test_avg' => 0.0,
                'required_test_title' => '',
                'is_expiring_soon' => $daysUntilExpiry > 0 && $daysUntilExpiry <= 3,
                'days_until_expiry' => $daysUntilExpiry,
            ];

            $jobs[] = $job;
            $totalJobs++;
            if ($job['eligible']) {
                $eligibleJobs++;
            }
        }
    }
}

if ($tab === 'eligible') {
    $jobs = array_values(array_filter($jobs, static fn(array $job): bool => $job['eligible']));
}

$filteredCount = count($jobs);
$totalPages = max(1, (int) ceil($filteredCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$paginatedJobs = array_slice($jobs, $offset, $perPage);

$queryBase = array_filter(
    [
        'q' => $q,
        'tab' => $tab !== 'all' ? $tab : '',
    ],
    static fn($value): bool => $value !== ''
);

$allQuery = http_build_query(array_merge($queryBase, ['tab' => 'all', 'page' => 1]));
$eligibleQuery = http_build_query(array_merge($queryBase, ['tab' => 'eligible', 'page' => 1]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <style>
        .page-shell {
            background-image:
                radial-gradient(ellipse 70% 48% at 12% -10%, rgba(99,102,241,0.14) 0%, transparent 58%),
                radial-gradient(ellipse 55% 38% at 88% 110%, rgba(139,92,246,0.11) 0%, transparent 62%);
        }
        .glass-card {
            position: relative;
            overflow: hidden;
        }
        .glass-card::after {
            content: '';
            position: absolute;
            right: -42px;
            bottom: -54px;
            width: 156px;
            height: 156px;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(99,102,241,0.16), transparent 68%);
            pointer-events: none;
        }
        .hero-card {
            background:
                radial-gradient(circle at top left, rgba(99,102,241,0.24), transparent 26%),
                radial-gradient(circle at bottom right, rgba(139,92,246,0.18), transparent 28%),
                linear-gradient(135deg, rgba(30,41,59,0.86), rgba(15,23,42,0.96));
        }
        .hero-grid {
            background-image:
                linear-gradient(rgba(148,163,184,0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,0.08) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: linear-gradient(180deg, rgba(255,255,255,0.75), transparent);
        }
        .metric-card {
            background:
                radial-gradient(circle at top right, rgba(99,102,241,0.16), transparent 38%),
                linear-gradient(180deg, rgba(30,41,59,0.72), rgba(15,23,42,0.88));
        }
        .metric-ring {
            position: absolute;
            right: -22px;
            bottom: -22px;
            width: 90px;
            height: 90px;
            border-radius: 9999px;
            border: 1px solid rgba(129,140,248,0.16);
            background: radial-gradient(circle, rgba(99,102,241,0.2), transparent 66%);
        }
        .jobs-filter-pill,
        .job-chip {
            backdrop-filter: blur(10px);
            transition: all 0.25s ease;
        }
        .jobs-filter-pill:hover,
        .job-chip:hover {
            transform: translateY(-1px);
        }
        .glow-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            transition: all 0.3s ease;
        }
        .glow-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #818cf8, #a78bfa);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .glow-btn:hover::before {
            opacity: 1;
        }
        .glow-btn:hover {
            box-shadow: 0 0 30px rgba(99,102,241,0.38);
            transform: translateY(-2px);
        }
        .glow-btn > * {
            position: relative;
            z-index: 1;
        }
        .job-card {
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
        }
        .job-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(99,102,241,0.08), transparent 40%, rgba(139,92,246,0.06));
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .job-card:hover::before {
            opacity: 1;
        }
        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 26px 60px -30px rgba(79,70,229,0.42);
        }
        .stagger {
            opacity: 0;
            animation: fadeUp 0.65s ease forwards;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<script>
    const storedTheme = localStorage.getItem('skilltrust-theme');
    document.documentElement.classList.toggle('dark', storedTheme ? storedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
</script>

<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] rounded-xl border px-4 py-2.5 text-sm font-semibold"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="page-shell flex min-h-screen">
    <aside class="sidebar fixed left-0 top-0 z-50 flex h-full w-64 -translate-x-full flex-col transition-transform duration-300 lg:translate-x-0" id="sidebar">
        <div class="border-b border-brand-900/30 px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 shadow-lg shadow-brand-500/30">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 01-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 01-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 01-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 01.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-display text-lg font-extrabold tracking-tight text-white">SkillTrust</span>
                    <div class="text-xs font-medium text-brand-400 -mt-0.5">Student Panel</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
            <div class="mb-3 px-3">
                <span class="text-xs font-semibold uppercase tracking-widest text-slate-600">Main Menu</span>
            </div>

            <a href="dashboard.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10">
                    <svg class="h-4 w-4 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                    </svg>
                </div>
                <span>Dashboard</span>
            </a>
            <a href="tests.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10">
                    <svg class="h-4 w-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <span>Tests</span>
            </a>
            <a href="jobs.php" class="nav-item active flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/14">
                    <svg class="h-4 w-4 text-violet-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2zm3-3h6a2 2 0 012 2v1H7V6a2 2 0 012-2z"/>
                    </svg>
                </div>
                <span>Jobs</span>
            </a>
            <a href="applied_jobs.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10">
                    <svg class="h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6m-6 4h6m-6-8h6"/>
                    </svg>
                </div>
                <span>Applied Jobs</span>
            </a>
            <a href="results.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10">
                    <svg class="h-4 w-4 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span>Results</span>
            </a>
            <a href="profile.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-700/70">
                    <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <span>Profile</span>
            </a>

            <div class="mb-2 px-3 pt-4">
                <span class="text-xs font-semibold uppercase tracking-widest text-slate-600">Settings</span>
            </div>
            <a href="#" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-700/70">
                    <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span>Settings</span>
            </a>
        </nav>

        <div class="border-t border-brand-900/30 px-4 py-4">
            <div class="student-shell-user flex items-center gap-3 rounded-xl bg-slate-800/60 p-3 transition-colors hover:bg-slate-800">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-sm font-bold text-white shadow-lg shadow-brand-500/20">
                    <?php echo e($studentInitials); ?>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-white"><?php echo e($studentName); ?></div>
                    <div class="truncate text-xs text-slate-500"><?php echo e($studentEmail); ?></div>
                </div>
                <i data-lucide="chevrons-up-down" class="h-4 w-4 flex-shrink-0 text-slate-500"></i>
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:ml-64">
        <header class="navbar sticky top-0 z-30 flex items-center justify-between px-3 py-3 sm:px-4 lg:h-16 lg:px-8 lg:py-0">
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleSidebar()" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white lg:hidden">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
                <div>
                    <h2 class="font-display text-lg font-bold text-white">Jobs</h2>
                    <p class="text-xs text-slate-500">Discover roles matched to your performance profile</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="applied_jobs.php" class="hidden sm:inline-flex items-center gap-2 rounded-xl border border-brand-500/25 bg-brand-500/10 px-3 py-2 text-xs font-semibold text-brand-100 transition hover:border-brand-400/40 hover:bg-brand-500/15 hover:text-white">
                    <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                    <span>Applied Jobs</span>
                </a>
                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white">
                    <i data-lucide="moon-star" class="h-4 w-4"></i>
                    <span id="themeLabel">Dark</span>
                </button>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-xs font-bold text-white shadow-lg shadow-brand-500/20">
                    <?php echo e($studentInitials); ?>
                </div>
            </div>
        </header>

        <main class="flex-1 px-3 py-6 sm:px-4 lg:px-8">
            <?php if (!$schemaReady || $jobMinAverageColumn === ''): ?>
                <section class="glass-card stagger mx-auto max-w-3xl rounded-[28px] p-8 text-center" style="animation-delay: 0.05s;">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-300">
                        <i data-lucide="shield-alert" class="h-7 w-7"></i>
                    </div>
                    <h2 class="mt-5 font-display text-2xl font-bold text-white">Jobs are not ready yet</h2>
                    <p class="mt-3 text-sm text-slate-400">
                        <?php if (!$schemaReady): ?>
                            Missing tables: <?php echo e(implode(', ', $missingTables)); ?>.
                        <?php else: ?>
                            Add `min_average_score` or `min_avg_score` to the `jobs` table to enable eligibility.
                        <?php endif; ?>
                    </p>
                    <p class="mt-4 text-xs text-slate-500">Run `sql/recruiter_hiring_panel.sql` and refresh this page.</p>
                </section>
            <?php else: ?>
                <section class="hero-card glass-card stagger rounded-[32px] p-6 lg:p-8" style="animation-delay: 0.04s;">
                    <div class="hero-grid absolute inset-0 opacity-50"></div>
                    <div class="relative grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                        <div>
                            <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-brand-500/20 bg-brand-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-100">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                Personalized Hiring Feed
                            </span>
                            <h1 class="mt-5 max-w-3xl font-display text-3xl font-bold leading-tight text-white lg:text-[2.65rem]">
                                Find roles that align with your dashboard performance and recruiter requirements.
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300/85">
                                Your job board uses the same trust-first logic as the platform. We compare your average test score across all attempts, then validate any required test threshold before letting you apply.
                            </p>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/70 bg-slate-950/45 px-4 py-2 text-sm text-slate-300">
                                    <i data-lucide="target" class="h-4 w-4 text-brand-300"></i>
                                    Average score: <?php echo e(number_format($studentAverageScore, 2)); ?>
                                </span>
                                <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/70 bg-slate-950/45 px-4 py-2 text-sm text-slate-300">
                                    <i data-lucide="briefcase-business" class="h-4 w-4 text-violet-300"></i>
                                    Active roles: <?php echo (int) $totalJobs; ?>
                                </span>
                                <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/70 bg-slate-950/45 px-4 py-2 text-sm text-slate-300">
                                    <i data-lucide="badge-check" class="h-4 w-4 text-emerald-300"></i>
                                    Eligible now: <?php echo (int) $eligibleJobs; ?>
                                </span>
                            </div>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <a href="?<?php echo e($eligibleQuery); ?>" class="glow-btn inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold text-white">
                                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                                    <span>Show Eligible Jobs</span>
                                </a>
                                <a href="applied_jobs.php" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15">
                                    <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                                    <span>View Applied Jobs</span>
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 xl:grid-cols-1">
                            <article class="metric-card glass-card rounded-[28px] p-5">
                                <div class="metric-ring"></div>
                                <div class="relative flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Active jobs</p>
                                        <p class="mt-3 font-display text-3xl font-bold text-white"><?php echo (int) $totalJobs; ?></p>
                                        <p class="mt-2 text-sm text-slate-400">Fresh opportunities from recruiters.</p>
                                    </div>
                                    <div class="rounded-2xl bg-brand-500/15 p-3 text-brand-300">
                                        <i data-lucide="briefcase-business" class="h-5 w-5"></i>
                                    </div>
                                </div>
                            </article>

                            <article class="metric-card glass-card rounded-[28px] p-5">
                                <div class="metric-ring"></div>
                                <div class="relative flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Eligible now</p>
                                        <p class="mt-3 font-display text-3xl font-bold text-white"><?php echo (int) $eligibleJobs; ?></p>
                                        <p class="mt-2 text-sm text-slate-400">You can apply instantly to these roles.</p>
                                    </div>
                                    <div class="rounded-2xl bg-emerald-500/15 p-3 text-emerald-300">
                                        <i data-lucide="sparkles" class="h-5 w-5"></i>
                                    </div>
                                </div>
                            </article>

                            <article class="metric-card glass-card rounded-[28px] p-5">
                                <div class="metric-ring"></div>
                                <div class="relative flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Applications</p>
                                        <p class="mt-3 font-display text-3xl font-bold text-white"><?php echo (int) $appliedJobsCount; ?></p>
                                        <p class="mt-2 text-sm text-slate-400">Track all recruiter updates in one place.</p>
                                    </div>
                                    <div class="rounded-2xl bg-violet-500/15 p-3 text-violet-300">
                                        <i data-lucide="clipboard-check" class="h-5 w-5"></i>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="glass-card stagger mt-6 rounded-[28px] p-5 lg:p-6" style="animation-delay: 0.1s;">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <form method="get" class="flex flex-1 flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"></i>
                                <input
                                    type="text"
                                    name="q"
                                    value="<?php echo e($q); ?>"
                                    placeholder="Search jobs, companies, or keywords"
                                    class="w-full rounded-2xl border border-slate-700 bg-slate-950/65 py-3 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-brand-500/50 focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
                            <button type="submit" class="glow-btn inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold text-white">
                                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                                <span>Search Jobs</span>
                            </button>
                        </form>

                        <div class="inline-flex rounded-2xl border border-slate-700/70 bg-slate-950/70 p-1">
                            <a href="?<?php echo e($allQuery); ?>" class="rounded-xl px-4 py-2 text-sm font-semibold transition <?php echo $tab === 'all' ? 'bg-brand-500/15 text-brand-100 shadow-sm' : 'text-slate-400 hover:text-white'; ?>">All Jobs</a>
                            <a href="?<?php echo e($eligibleQuery); ?>" class="rounded-xl px-4 py-2 text-sm font-semibold transition <?php echo $tab === 'eligible' ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/25' : 'text-slate-400 hover:text-white'; ?>">Eligible Only</a>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-slate-400">
                        <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/60 bg-slate-900/70 px-3 py-1.5">
                            <i data-lucide="radar" class="h-3.5 w-3.5 text-brand-300"></i>
                            Showing <?php echo (int) $filteredCount; ?> job<?php echo $filteredCount === 1 ? '' : 's'; ?>
                        </span>
                        <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/60 bg-slate-900/70 px-3 py-1.5">
                            <i data-lucide="target" class="h-3.5 w-3.5 text-emerald-300"></i>
                            Your score: <?php echo e(number_format($studentAverageScore, 2)); ?>
                        </span>
                        <?php if ($hasRequiredTestColumns): ?>
                            <span class="jobs-filter-pill inline-flex items-center gap-2 rounded-full border border-slate-700/60 bg-slate-900/70 px-3 py-1.5">
                                <i data-lucide="file-badge-2" class="h-3.5 w-3.5 text-violet-300"></i>
                                Required-test filters are enabled
                            </span>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($paginatedJobs === []): ?>
                    <section class="glass-card stagger mt-6 rounded-[28px] p-10 text-center" style="animation-delay: 0.16s;">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-500/10 text-brand-300">
                            <i data-lucide="search-x" class="h-8 w-8"></i>
                        </div>
                        <h2 class="mt-5 font-display text-2xl font-bold text-white">No matching jobs found</h2>
                        <p class="mt-3 text-sm text-slate-400">Try another keyword or switch back to all jobs.</p>
                        <a href="jobs.php" class="glow-btn mt-6 inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold text-white">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            <span>Reset filters</span>
                        </a>
                    </section>
                <?php else: ?>
                    <section class="mt-6 grid grid-cols-1 gap-5">
                        <?php foreach ($paginatedJobs as $index => $job): ?>
                            <?php
                            $jobDescription = trim((string) $job['description']);
                            $jobSummary = strlen($jobDescription) > 220 ? substr($jobDescription, 0, 220) . '...' : $jobDescription;
                            $statusLabel = $job['status'] !== '' ? ucfirst($job['status']) : 'Applied';
                            ?>
                            <article class="job-card glass-card stagger rounded-[30px] p-6" style="animation-delay: <?php echo e((string) (170 + ($index * 60))); ?>ms;">
                                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap gap-3">
                                            <span class="job-chip inline-flex items-center gap-2 rounded-full border border-brand-500/20 bg-brand-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-brand-100">
                                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                                Posted <?php echo e(time_ago_label($job['created_at'])); ?>
                                            </span>
                                            <?php if ($job['is_expiring_soon']): ?>
                                                <span class="job-chip inline-flex items-center gap-2 rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-200">
                                                    <i data-lucide="timer" class="h-3.5 w-3.5"></i>
                                                    Closing in <?php echo (int) $job['days_until_expiry']; ?> day<?php echo (int) $job['days_until_expiry'] === 1 ? '' : 's'; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($job['applied']): ?>
                                                <span class="job-chip inline-flex items-center gap-2 rounded-full border border-violet-500/20 bg-violet-500/10 px-3 py-1.5 text-xs font-semibold text-violet-200">
                                                    <i data-lucide="badge-check" class="h-3.5 w-3.5"></i>
                                                    <?php echo e($statusLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-5 flex items-start gap-4">
                                            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-3xl border border-slate-700/70 bg-slate-900/75">
                                                <i data-lucide="building-2" class="h-6 w-6 text-brand-300"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <h3 class="font-display text-2xl font-bold text-white"><?php echo e($job['title']); ?></h3>
                                                <p class="mt-1 text-sm text-slate-400"><?php echo e($job['company_name']); ?></p>
                                                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300/90"><?php echo e($jobSummary); ?></p>
                                            </div>
                                        </div>

                                        <div class="mt-6 flex flex-wrap gap-3">
                                            <div class="rounded-2xl border <?php echo $job['eligible'] ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-200' : 'border-rose-500/25 bg-rose-500/10 text-rose-200'; ?> px-4 py-3">
                                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em]">
                                                    <i data-lucide="<?php echo $job['eligible'] ? 'check-circle-2' : 'circle-off'; ?>" class="h-4 w-4"></i>
                                                    <span><?php echo $job['eligible'] ? 'Eligible' : 'Not Eligible'; ?></span>
                                                </div>
                                                <p class="mt-2 text-sm text-white">Your average <?php echo e(number_format($job['avg_score'], 2)); ?> / Required <?php echo e(number_format($job['min_average_score'], 2)); ?></p>
                                            </div>

                                            <div class="rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-3">
                                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                                                    <i data-lucide="calendar-range" class="h-4 w-4 text-violet-300"></i>
                                                    <span>Expiry</span>
                                                </div>
                                                <p class="mt-2 text-sm text-white"><?php echo e(format_date_label($job['expiry_date'])); ?></p>
                                            </div>

                                            <div class="rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-3">
                                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                                                    <i data-lucide="users" class="h-4 w-4 text-brand-300"></i>
                                                    <span>Applicants</span>
                                                </div>
                                                <p class="mt-2 text-sm text-white"><?php echo (int) $job['applicant_count']; ?> candidate<?php echo (int) $job['applicant_count'] === 1 ? '' : 's'; ?></p>
                                            </div>

                                            <?php if ($job['required_test_id'] > 0): ?>
                                                <div class="rounded-2xl border <?php echo $job['meets_required_test'] ? 'border-brand-500/25 bg-brand-500/10' : 'border-amber-500/25 bg-amber-500/10'; ?> px-4 py-3">
                                                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] <?php echo $job['meets_required_test'] ? 'text-brand-100' : 'text-amber-200'; ?>">
                                                        <i data-lucide="file-badge-2" class="h-4 w-4"></i>
                                                        <span>Required Test</span>
                                                    </div>
                                                    <p class="mt-2 text-sm text-white">
                                                        <?php echo e($job['required_test_title'] !== '' ? $job['required_test_title'] : 'Selected test'); ?>
                                                        <?php echo ' '; ?><?php echo e(number_format($job['required_test_avg'], 2)); ?> / <?php echo e(number_format($job['min_test_score'], 2)); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="w-full xl:max-w-xs">
                                        <div class="rounded-[28px] border border-slate-700/70 bg-slate-950/72 p-5">
                                            <h4 class="text-sm font-semibold text-white">Hiring Requirements</h4>
                                            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                                                <li class="flex items-start gap-3">
                                                    <i data-lucide="target" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-300"></i>
                                                    <span>Minimum average score: <strong class="text-white"><?php echo e(number_format($job['min_average_score'], 2)); ?></strong></span>
                                                </li>
                                                <?php if ($job['required_test_id'] > 0): ?>
                                                    <li class="flex items-start gap-3">
                                                        <i data-lucide="badge-check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-violet-300"></i>
                                                        <span><?php echo e($job['required_test_title'] !== '' ? $job['required_test_title'] : 'Required test'); ?> score must be at least <strong class="text-white"><?php echo e(number_format($job['min_test_score'], 2)); ?></strong></span>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="flex items-start gap-3">
                                                        <i data-lucide="layers-3" class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300"></i>
                                                        <span>No extra test threshold. Eligibility uses your overall average only.</span>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>

                                            <div class="mt-5">
                                                <?php if ($job['applied']): ?>
                                                    <div class="flex items-center justify-center gap-2 rounded-2xl border border-slate-700/70 bg-slate-800/90 px-4 py-3 text-sm font-semibold text-slate-200">
                                                        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                                                        <span>Already applied</span>
                                                    </div>
                                                <?php elseif ($job['eligible']): ?>
                                                    <button type="button" onclick="applyForJob(<?php echo (int) $job['id']; ?>)" class="glow-btn inline-flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-3.5 text-sm font-semibold text-white">
                                                        <i data-lucide="send" class="h-4 w-4"></i>
                                                        <span>Apply Now</span>
                                                    </button>
                                                <?php else: ?>
                                                    <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                                        <?php if (!$job['meets_average']): ?>
                                                            Increase your average score to <?php echo e(number_format($job['min_average_score'], 2)); ?> or above.
                                                        <?php else: ?>
                                                            Improve your <?php echo e($job['required_test_title'] !== '' ? $job['required_test_title'] : 'required test'); ?> score to <?php echo e(number_format($job['min_test_score'], 2)); ?> or above.
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <?php if ($totalPages > 1): ?>
                        <?php
                        $prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
                        $nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));
                        ?>
                        <section class="glass-card stagger mt-6 flex flex-col items-center justify-between gap-3 rounded-[28px] p-5 text-sm text-slate-400 sm:flex-row" style="animation-delay: 0.24s;">
                            <p>Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
                            <div class="flex items-center gap-2">
                                <a href="?<?php echo e($prevQuery); ?>" class="rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-2 font-semibold text-slate-300 transition hover:border-brand-500/30 hover:bg-slate-800 <?php echo $page <= 1 ? 'pointer-events-none opacity-40' : ''; ?>">Prev</a>
                                <a href="?<?php echo e($nextQuery); ?>" class="rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-2 font-semibold text-slate-300 transition hover:border-brand-500/30 hover:bg-slate-800 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-40' : ''; ?>">Next</a>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<script type="application/json" id="pageData"><?php echo json_encode(['toast' => $toast], JSON_UNESCAPED_UNICODE); ?></script>
<script>
    lucide.createIcons();

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('active');
    }

    const root = document.documentElement;
    const themeToggle = document.getElementById('themeToggle');
    const themeLabel = document.getElementById('themeLabel');

    function syncThemeLabel() {
        themeLabel.textContent = root.classList.contains('dark') ? 'Light' : 'Dark';
    }

    syncThemeLabel();

    themeToggle.addEventListener('click', () => {
        const isDark = root.classList.contains('dark');
        root.classList.toggle('dark', !isDark);
        localStorage.setItem('skilltrust-theme', !isDark ? 'dark' : 'light');
        syncThemeLabel();
        lucide.createIcons();
    });

    function showToast(type, message) {
        const toast = document.getElementById('toast');
        const classes = {
            success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
            error: 'bg-rose-500/15 border-rose-500/30 text-rose-300',
            info: 'bg-brand-500/15 border-brand-500/30 text-brand-200'
        };

        toast.textContent = message;
        toast.className = 'fixed bottom-6 right-6 z-[100] rounded-xl border px-4 py-2.5 text-sm font-semibold ' + (classes[type] || classes.info);
        toast.classList.remove('hidden');

        window.clearTimeout(window.skillTrustToastTimer);
        window.skillTrustToastTimer = window.setTimeout(() => {
            toast.classList.add('hidden');
        }, 4200);
    }

    function applyForJob(jobId) {
        if (!window.confirm('Apply for this job? You can only apply once.')) {
            return;
        }

        fetch('apply_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                csrf_token: '<?php echo e(csrf_token()); ?>',
                job_id: String(jobId)
            }).toString()
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                showToast('success', data.message || 'Application submitted.');
                window.setTimeout(() => window.location.reload(), 900);
                return;
            }
            showToast('error', data.error || 'Unable to apply for this job.');
        })
        .catch(() => showToast('error', 'Something went wrong while applying.'));
    }

    const pageDataNode = document.getElementById('pageData');
    if (pageDataNode) {
        const pageData = JSON.parse(pageDataNode.textContent);
        if (pageData.toast && pageData.toast.message) {
            showToast(pageData.toast.type || 'info', pageData.toast.message);
        }
    }
</script>
</body>
</html>
