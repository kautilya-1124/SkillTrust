<?php
// tests.php helper utilities.
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// BUG FIX 1: Missing login check — unauthenticated users could open this page freely.
// require_user_login() is defined in includes/auth.php but auth.php was never included here.
// We replicate the check directly since config.php doesn't include auth.php.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

/**
 * Safe defaults and CSV tags for one test row.
 */
function skilltrust_normalize_test_row(array $row): array
{
    $row['difficulty'] = $row['difficulty'] ?? 'Beginner';
    $row['category']   = $row['category'] ?? 'General';
    $row['rating']     = isset($row['rating']) ? (float) $row['rating'] : 4.5;
    $row['attempts']   = isset($row['attempts']) ? (int) $row['attempts'] : 0;
    $row['duration']   = isset($row['duration']) ? (int) $row['duration'] : 0;
    $row['questions']  = isset($row['questions']) ? (int) $row['questions'] : 0;
    $row['completed']  = isset($row['completed'])
        && ($row['completed'] === 1 || $row['completed'] === '1' || $row['completed'] === true);
    $row['featured']   = isset($row['featured']) ? (int) $row['featured'] : 0;

    $tagStr = isset($row['tags']) ? (string) $row['tags'] : '';
    $tags   = array_values(array_filter(array_map('trim', explode(',', $tagStr)), static function ($t) {
        return $t !== '';
    }));
    if ($tags === []) {
        $tags = ['General'];
    }
    $row['tags'] = $tags;

    return $row;
}

/**
 * FIX #1: Define the missing e() helper that was being called but never existed.
 * This was causing an undefined function fatal error / XSS vulnerability.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$tests = [];
if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query('SELECT * FROM tests');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tests[] = skilltrust_normalize_test_row($row);
        }
        $result->free();
    }
}

$studentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$totalAssessmentCount = count($tests);

/**
 * FIX #2: Build dynamic category list from actual DB data
 * instead of a hardcoded array that breaks when categories differ.
 */
$categories = array_values(array_unique(array_column($tests, 'category')));
sort($categories);

// Debug: inspect loaded data (hidden; view page source or devtools)

$studentName     = trim((string) ($_SESSION['name']  ?? 'Alex Johnson'));
$studentEmail    = trim((string) ($_SESSION['email'] ?? 'alex.johnson@email.com'));
$studentInitials = 'AJ';
$studentWords    = preg_split('/\s+/', $studentName, -1, PREG_SPLIT_NO_EMPTY);
if (is_array($studentWords) && $studentWords !== []) {
    $studentInitials = count($studentWords) >= 2
        ? strtoupper(substr((string) $studentWords[0], 0, 1) . substr((string) $studentWords[1], 0, 1))
        : strtoupper(substr((string) $studentWords[0], 0, 2));
}
$student = [
    'name'   => $studentName,
    'email'  => $studentEmail,
    'avatar' => $studentInitials,
];

$lockMessage = trim((string) ($_GET['lock_message'] ?? ''));
$lockTestId  = (int) ($_GET['lock_test_id'] ?? 0);

/**
 * Helper: map a category string to a CSS class.
 */
function skilltrust_cat_class(string $category): string
{
    $slug = strtolower(str_replace(['.', '#', ' '], ['-', 's', '-'], $category));
    $map  = [
        'javascript' => 'cat-js',
        'node-js'    => 'cat-node',
        'nodejs'     => 'cat-node',
        'typescript' => 'cat-ts',
        'database'   => 'cat-db',
        'css'        => 'cat-css',
        'react'      => 'cat-react',
    ];
    return $map[$slug] ?? ('cat-' . $slug);
}

/**
 * Helper: map a difficulty string to a CSS class.
 */
function skilltrust_diff_class(string $difficulty): string
{
    return 'badge-' . strtolower($difficulty);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests Library | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/tests.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="sidebar fixed left-0 top-0 h-full w-64 z-50 flex flex-col
                  transform -translate-x-full lg:translate-x-0 transition-transform duration-300"
           id="sidebar">
        <!-- Logo -->
        <div class="px-6 py-5 border-b border-indigo-900/30">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600
                            flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 01-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 01-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 01-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 01.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-display font-800 text-white text-lg tracking-tight">SkillTrust</span>
                    <div class="text-xs text-indigo-400 font-medium -mt-0.5">Student Panel</div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            <div class="px-3 mb-3">
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-widest">Main Menu</span>
            </div>

            <a href="dashboard.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                    </svg>
                </div>
                <span>Dashboard</span>
            </a>

            <a href="tests.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <span>Tests</span>
                <span class="ml-auto bg-indigo-500/20 text-indigo-400 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo count($tests); ?></span>
            </a>

            <a href="jobs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2zm3-3h6a2 2 0 012 2v1H7V6a2 2 0 012-2z"/>
                    </svg>
                </div>
                <span>Jobs</span>
            </a>

            <a href="applied_jobs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6m-6 4h6m-6-8h6"/>
                    </svg>
                </div>
                <span>Applied Jobs</span>
            </a>

            <a href="results.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span>Results</span>
            </a>

            <a href="profile.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400">
                <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span>Settings</span>
            </a>
        </nav>

        <!-- User block -->
        <div class="px-4 py-4 border-t border-indigo-900/30">
            <div class="student-shell-user flex items-center gap-3 p-3 rounded-xl bg-slate-800/60 hover:bg-slate-800 transition-colors cursor-pointer">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-sm flex-shrink-0">
                    <?php echo e($student['avatar']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-white truncate"><?php echo e($student['name']); ?></div>
                    <div class="text-xs text-slate-500 truncate"><?php echo e($student['email']); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen min-w-0 max-w-full">

        <!-- NAVBAR -->
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex flex-wrap sm:flex-nowrap items-center justify-between gap-y-2 gap-x-2 min-w-0">
            <div class="flex items-center gap-2 min-w-0 flex-1 lg:flex-initial lg:max-w-none overflow-hidden">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu"
                        class="lg:hidden flex-shrink-0 p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="lg:hidden min-w-0">
                    <h2 class="font-display font-bold text-white text-sm truncate">Tests Library</h2>
                    <p class="text-[10px] text-slate-500 truncate">Skill assessments</p>
                </div>
            </div>

            <div class="hidden lg:flex flex-col flex-shrink-0 order-2 lg:order-none">
                <h2 class="font-display font-700 text-white text-lg">Tests Library</h2>
                <p class="text-xs text-slate-500">Browse and attempt skill assessments</p>
            </div>

            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0 basis-full sm:basis-auto justify-end sm:ml-auto sm:w-auto">
                <!-- Search -->
                <div class="hidden md:flex items-center gap-2 bg-slate-800/60 border border-slate-700/50 rounded-xl px-3 py-2 focus-within:border-indigo-500/50 transition-all duration-300 max-w-[12rem] lg:max-w-none">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search tests..." id="navSearch"
                           class="bg-transparent text-sm text-slate-300 placeholder-slate-600 outline-none w-36 focus:w-52 transition-all duration-300">
                </div>

                <button type="button" class="hidden sm:inline-flex sm:items-center sm:justify-center relative p-2 sm:p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot absolute top-2 right-2 w-2 h-2 rounded-full bg-indigo-500"></span>
                </button>

                <!-- FIX #5: toggleDropdown() is defined inline here as a fallback -->
                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                    <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <div class="relative" id="profileDropdown">
                    <button onclick="toggleDropdown()" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-xs">
                            <?php echo e($student['avatar']); ?>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-500 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu absolute right-0 top-full mt-2 w-52 max-w-[min(13rem,calc(100vw-1.5rem))] sm:max-w-none bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-[60]" id="dropdownMenu">
                        <div class="px-4 py-3 border-b border-slate-700/50">
                            <div class="font-semibold text-white text-sm"><?php echo e($student['name']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo e($student['email']); ?></div>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                My Profile
                            </a>
                        </div>
                        <div class="border-t border-slate-700/50 py-1">
                            <a href="../auth/login.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <main class="flex-1 min-w-0 px-3 sm:px-4 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8 overflow-x-hidden">

            <?php if ($lockMessage !== ''): ?>
            <section class="glass-card rounded-2xl border border-rose-500/25 bg-rose-500/10 p-4 sm:p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-display text-base font-bold text-white">Test access locked</h3>
                        <!-- FIX #1 applied: e() is now defined above, safe to use -->
                        <p class="mt-1 text-sm text-rose-100"><?php echo e($lockMessage); ?></p>
                    </div>
                    <?php if ($lockTestId > 0): ?>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-rose-400/20 bg-slate-950/40 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-100">
                        Test ID <?php echo $lockTestId; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Hero section -->
            <section class="anim-fade-up" style="animation-delay:.05s">
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 bg-indigo-500/10 border border-indigo-500/20
                                    text-indigo-400 text-xs font-semibold px-3 py-1.5 rounded-full mb-3">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <?php echo $totalAssessmentCount; ?> Assessments Available
                        </div>
                        <h1 class="font-display font-800 text-2xl sm:text-3xl text-white mb-1 leading-tight break-words">Skills Assessment Library</h1>
                        <p class="text-slate-400 text-sm max-w-prose">Prove your expertise. Earn your trust score. Advance your career.</p>
                    </div>

                    <!-- View toggle + sort -->
                    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 w-full lg:w-auto lg:flex-shrink-0">
                        <select id="sortSelect" onchange="sortTests(this.value)"
                                class="bg-slate-800/80 border border-slate-700/60 text-slate-300 text-sm
                                       rounded-xl px-3 py-2 outline-none focus:border-indigo-500/50 cursor-pointer
                                       transition-all duration-300 hover:border-slate-600 w-full sm:w-auto min-w-0 sm:min-w-[11rem]">
                            <option value="default">Sort: Default</option>
                            <option value="rating">Highest Rated</option>
                            <option value="popular">Most Popular</option>
                            <option value="newest">Newest</option>
                            <option value="duration-asc">Duration: Short</option>
                            <option value="duration-desc">Duration: Long</option>
                        </select>

                        <!-- Grid / List toggle -->
                        <div class="flex items-center justify-center sm:justify-start bg-slate-800/60 border border-slate-700/50 rounded-xl p-1 flex-shrink-0">
                            <button id="gridViewBtn" onclick="setView('grid')"
                                    class="view-btn active p-2 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                            </button>
                            <button id="listViewBtn" onclick="setView('list')"
                                    class="view-btn p-2 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Search and filters -->
            <section class="anim-fade-up" style="animation-delay:.1s">
                <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">

                    <!-- Search -->
                    <div class="relative w-full lg:w-72">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="searchInput" placeholder="Search tests, topics..."
                               class="search-input w-full min-w-0 pl-10 pr-10 py-2.5 rounded-xl text-sm text-slate-300 placeholder-slate-600">
                        <button id="clearSearch" onclick="clearSearchInput()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300
                                       transition-colors hidden">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Filters scroll row -->
                    <div class="filter-strip flex items-center gap-2 overflow-x-auto pb-2 pt-0.5 flex-nowrap w-full min-w-0 lg:w-auto lg:flex-1
                                [-webkit-overflow-scrolling:touch] scroll-smooth"
                         id="filterRow" role="region" aria-label="Test filters">

                        <!-- Difficulty filters -->
                        <div class="flex items-center gap-1.5 bg-slate-800/60 border border-slate-700/40 rounded-xl p-1 flex-shrink-0">
                            <button class="filter-btn active px-3 py-1.5 rounded-lg text-xs font-semibold border border-transparent text-white"
                                    data-filter="all" onclick="setFilter('difficulty','all',this)"><span>All Levels</span></button>
                            <button class="filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600/50 text-slate-400"
                                    data-filter="Beginner" onclick="setFilter('difficulty','Beginner',this)"><span>Beginner</span></button>
                            <button class="filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600/50 text-slate-400"
                                    data-filter="Intermediate" onclick="setFilter('difficulty','Intermediate',this)"><span>Intermediate</span></button>
                            <button class="filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600/50 text-slate-400"
                                    data-filter="Advanced" onclick="setFilter('difficulty','Advanced',this)"><span>Advanced</span></button>
                        </div>

                        <span class="text-slate-700 text-lg flex-shrink-0">|</span>

                        <!-- FIX #2: Category buttons built dynamically from actual DB data -->
                        <div class="flex items-center gap-1.5 flex-nowrap flex-shrink-0">
                            <button class="filter-btn active px-3 py-1.5 rounded-xl text-xs font-semibold border border-transparent text-white"
                                    data-cat="all" onclick="setFilter('category','all',this)"><span>All</span></button>
                            <?php foreach ($categories as $cat): ?>
                            <button class="filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-600/50 text-slate-400 flex-shrink-0"
                                    data-cat="<?php echo e($cat); ?>"
                                    onclick="setFilter('category',<?php echo json_encode($cat); ?>,this)">
                                <span><?php echo e($cat); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Status filter -->
                        <span class="text-slate-700 text-lg flex-shrink-0">|</span>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button class="filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-600/50 text-slate-400"
                                    data-status="completed" onclick="setFilter('status','completed',this)"><span>Completed</span></button>
                            <button class="filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-600/50 text-slate-400"
                                    data-status="new" onclick="setFilter('status','new',this)"><span>New</span></button>
                        </div>
                    </div>
                </div>

                <!-- Active filters + result count -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-3">
                    <div class="flex items-center gap-2 flex-wrap min-w-0" id="activeFiltersRow"></div>
                    <span class="text-xs text-slate-500 flex-shrink-0" id="resultCount">
                        Showing <span class="text-slate-300 font-semibold" id="countNum"><?php echo count($tests); ?></span> tests
                    </span>
                </div>
            </section>

            <!-- Featured tests -->
            <section id="featuredSection" class="anim-fade-up" style="animation-delay:.15s">
                <div class="flex items-center gap-2 sm:gap-3 mb-4 min-w-0">
                    <div class="w-6 h-6 rounded-lg bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <div class="text-base sm:text-lg font-semibold text-white truncate">Featured</div>
                    <div class="h-px flex-1 min-w-[2rem] bg-gradient-to-r from-slate-700/60 to-transparent"></div>
                </div>

                <?php foreach ($tests as $test):
                    if (($test['featured'] ?? 0) != 1) continue;

                    $difficulty = $test['difficulty'];
                    $category   = $test['category'];
                    $rating     = $test['rating'];
                    $attempts   = $test['attempts'];
                    $duration   = $test['duration'];
                    $questions  = $test['questions'];
                    // FIX #3: consistent bool check — normalized to bool by skilltrust_normalize_test_row()
                    $completed  = $test['completed'] === true;
                    $tags       = $test['tags'];
                    $diffClass  = skilltrust_diff_class($difficulty);
                    $catClass   = skilltrust_cat_class($category);
                ?>
                <div class="test-card featured-card rounded-2xl p-5 card-enter"
                     data-id="<?php echo e((string) ($test['id'] ?? '')); ?>"
                     data-difficulty="<?php echo e($difficulty); ?>"
                     data-category="<?php echo e($category); ?>"
                     data-completed="<?php echo $completed ? 'true' : 'false'; ?>"
                     data-title="<?php echo e(strtolower((string) ($test['title'] ?? ''))); ?>"
                     data-tags="<?php echo e(implode(' ', array_map('strtolower', $tags))); ?>"
                     data-rating="<?php echo e((string) $rating); ?>"
                     data-attempts="<?php echo e((string) $attempts); ?>"
                     data-duration="<?php echo e((string) $duration); ?>">

                    <div class="featured-glow"></div>
                    <div class="absolute top-3 left-3">
                        <span class="inline-flex items-center gap-1 bg-amber-500/15 border border-amber-500/25 text-amber-400 text-xs font-semibold px-2 py-1 rounded-lg">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            Featured
                        </span>
                    </div>

                    <!-- Category + rating -->
                    <div class="flex items-center justify-between mt-6 mb-4">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-lg <?php echo e($catClass); ?>">
                            <?php echo e($category); ?>
                        </span>
                        <!-- FIX #4: single star SVG icon, no duplicate emoji -->
                        <div class="flex items-center gap-1">
                            <svg class="w-3 h-3 star-fill" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span class="text-xs font-semibold text-slate-300"><?php echo e((string) $rating); ?></span>
                        </div>
                    </div>

                    <!-- Title -->
                    <h3 class="font-display font-700 text-white text-base mb-2">
                        <?php echo e((string) ($test['title'] ?? '')); ?>
                    </h3>

                    <!-- Tags -->
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <?php foreach ($tags as $tag): ?>
                            <span class="text-xs bg-slate-700/60 text-slate-400 px-2 py-0.5 rounded-md">
                                <?php echo e(trim((string) $tag)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Meta — FIX #2b: use SVG icons consistently, no raw text -->
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500 mb-4">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php echo e((string) $duration); ?> min
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php echo e((string) $questions); ?> questions
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <?php echo e(number_format($attempts)); ?>
                        </span>
                    </div>

                    <!-- Button -->
                    <div class="border-t border-slate-700/40 pt-4 flex justify-between items-center">
                        <span class="text-xs px-2 py-1 rounded-full <?php echo e($diffClass); ?>">
                            <?php echo e($difficulty); ?>
                        </span>
                        <a href="test-start.php?id=<?php echo e((string) ($test['id'] ?? '')); ?>"
                           class="<?php echo $completed ? 'retry-btn' : 'start-btn'; ?> px-4 py-2 rounded-xl text-xs text-white inline-flex items-center gap-1.5">
                            <?php if ($completed): ?>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Retry
                            <?php else: ?>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Start Test
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </section>

            <!-- Coding challenges -->
            <section class="anim-fade-up" style="animation-delay:.2s">
                <div class="flex items-center gap-2 sm:gap-3 mb-4 min-w-0">
                    <div class="w-6 h-6 rounded-lg bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                        <span class="font-display font-bold text-xs text-emerald-400">C</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-display font-700 text-white text-sm sm:text-base min-w-0 leading-snug">Coding Challenges</h2>
                        <p class="text-xs sm:text-sm text-slate-400">Solve database-driven coding problems and improve your profile.</p>
                    </div>
                    <div class="h-px flex-1 min-w-[2rem] bg-gradient-to-r from-slate-700/60 to-transparent"></div>
                </div>

                <div id="codingChallenges" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                    <div class="test-card rounded-2xl p-5 text-sm text-slate-400">Loading coding challenges...</div>
                </div>
            </section>

            <!-- All tests -->
            <section class="anim-fade-up" style="animation-delay:.2s">
                <div class="flex items-center gap-2 sm:gap-3 mb-4 min-w-0">
                    <div class="w-6 h-6 rounded-lg bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <h2 class="font-display font-700 text-white text-sm sm:text-base min-w-0 leading-snug">All Tests</h2>
                    <div class="h-px flex-1 min-w-[2rem] bg-gradient-to-r from-slate-700/60 to-transparent"></div>
                </div>

                <!-- GRID VIEW -->
                <div id="gridView" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                    <?php foreach ($tests as $i => $test):
                        $difficulty = $test['difficulty'];
                        $category   = $test['category'];
                        $rating     = $test['rating'];
                        $attempts   = $test['attempts'];
                        $duration   = $test['duration'];
                        $questions  = $test['questions'];
                        $tags       = $test['tags'];
                        // FIX #3: consistent bool check
                        $completed  = $test['completed'] === true;
                        $diffClass  = skilltrust_diff_class($difficulty);
                        $catClass   = skilltrust_cat_class($category);
                    ?>
                    <div class="test-card rounded-2xl p-5 card-enter"
                         style="transition-delay:<?php echo ($i % 6) * 60; ?>ms"
                         data-id="<?php echo e((string) ($test['id'] ?? '')); ?>"
                         data-difficulty="<?php echo e($difficulty); ?>"
                         data-category="<?php echo e($category); ?>"
                         data-completed="<?php echo $completed ? 'true' : 'false'; ?>"
                         data-title="<?php echo e(strtolower((string) ($test['title'] ?? ''))); ?>"
                         data-tags="<?php echo e(implode(' ', array_map('strtolower', $tags))); ?>"
                         data-rating="<?php echo e((string) $rating); ?>"
                         data-attempts="<?php echo e((string) $attempts); ?>"
                         data-duration="<?php echo e((string) $duration); ?>">

                        <?php if ($completed):
                            $sc   = (int) ($test['score'] ?? 0);
                            $pass = $sc >= 60;
                            $gWrap = $pass ? 'bg-emerald-500/15 border-emerald-500/25' : 'bg-red-500/15 border-red-500/25';
                            $gTxt  = $pass ? 'text-emerald-400' : 'text-red-400';
                        ?>
                        <div class="score-completed">
                            <div class="flex items-center gap-1 <?php echo $gWrap; ?> border px-2 py-1 rounded-lg">
                                <svg class="w-3 h-3 <?php echo $gTxt; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <?php if ($pass): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    <?php endif; ?>
                                </svg>
                                <span class="text-xs font-bold <?php echo $gTxt; ?>"><?php echo e((string) $sc); ?>%</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Category + rating -->
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg <?php echo e($catClass); ?>"><?php echo e($category); ?></span>
                            <!-- FIX #4: single star SVG, no duplicate emoji -->
                            <div class="flex items-center gap-1">
                                <svg class="w-3 h-3 star-fill" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span class="text-xs text-slate-300"><?php echo e((string) $rating); ?></span>
                            </div>
                        </div>

                        <!-- Title -->
                        <h3 class="font-display font-700 text-white text-sm mb-2 leading-snug"><?php echo e((string) ($test['title'] ?? '')); ?></h3>

                        <!-- Tags -->
                        <div class="flex flex-wrap gap-1 mb-4">
                            <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                            <span class="text-xs bg-slate-700/50 text-slate-500 px-2 py-0.5 rounded-md"><?php echo e((string) $tag); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($tags) > 2): ?>
                            <span class="text-xs text-slate-600 px-1">+<?php echo count($tags) - 2; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Meta — FIX #2b: consistent SVG icons -->
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-500 mb-4">
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?php echo e((string) $duration); ?> min
                            </span>
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?php echo e((string) $questions); ?> questions
                            </span>
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?php echo e(number_format($attempts)); ?>
                            </span>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-slate-700/40 pt-3.5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?php echo e($diffClass); ?> w-fit"><?php echo e($difficulty); ?></span>
                            <?php if ($completed): ?>
                                <a href="test-start.php?id=<?php echo e((string) ($test['id'] ?? '')); ?>"
                                   class="retry-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold w-full sm:w-auto transition-all duration-300">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Retry
                                </a>
                            <?php else: ?>
                                <a href="test-start.php?id=<?php echo e((string) ($test['id'] ?? '')); ?>"
                                   class="start-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white w-full sm:w-auto transition-all duration-300">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span>Start</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- LIST VIEW (hidden by default) -->
                <div id="listView" class="hidden space-y-3">
                    <?php foreach ($tests as $i => $test):
                        $difficulty = $test['difficulty'];
                        $category   = $test['category'];
                        $rating     = $test['rating'];
                        $attempts   = $test['attempts'];
                        $duration   = $test['duration'];
                        $questions  = $test['questions'];
                        $tags       = $test['tags'];
                        // FIX #3: consistent bool check
                        $completed  = $test['completed'] === true;
                        $diffClass  = skilltrust_diff_class($difficulty);
                        $catClass   = skilltrust_cat_class($category);
                    ?>
                    <div class="list-card rounded-2xl p-4 sm:p-5 card-enter flex flex-col md:flex-row md:items-center gap-4 md:gap-6"
                         style="transition-delay:<?php echo ($i % 8) * 40; ?>ms"
                         data-id="<?php echo e((string) ($test['id'] ?? '')); ?>"
                         data-difficulty="<?php echo e($difficulty); ?>"
                         data-category="<?php echo e($category); ?>"
                         data-completed="<?php echo $completed ? 'true' : 'false'; ?>"
                         data-title="<?php echo e(strtolower((string) ($test['title'] ?? ''))); ?>"
                         data-tags="<?php echo e(implode(' ', array_map('strtolower', $tags))); ?>"
                         data-rating="<?php echo e((string) $rating); ?>"
                         data-attempts="<?php echo e((string) $attempts); ?>"
                         data-duration="<?php echo e((string) $duration); ?>">

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-lg <?php echo e($catClass); ?>"><?php echo e($category); ?></span>
                                <span class="text-xs font-semibold px-2 py-1 rounded-full <?php echo e($diffClass); ?>"><?php echo e($difficulty); ?></span>
                                <!-- FIX #4: single star SVG -->
                                <div class="flex items-center gap-1 ml-auto md:ml-0">
                                    <svg class="w-3 h-3 star-fill" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span class="text-xs text-slate-300"><?php echo e((string) $rating); ?></span>
                                </div>
                            </div>
                            <h3 class="font-display font-700 text-white text-sm mb-2 leading-snug"><?php echo e((string) ($test['title'] ?? '')); ?></h3>
                            <div class="flex flex-wrap gap-1 mb-2 md:mb-0">
                                <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                                <span class="text-xs bg-slate-700/50 text-slate-500 px-2 py-0.5 rounded-md"><?php echo e((string) $tag); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($tags) > 4): ?>
                                <span class="text-xs text-slate-600 px-1">+<?php echo count($tags) - 4; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Meta — FIX #2b: consistent SVG icons -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500 md:flex-shrink-0">
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?php echo e((string) $duration); ?> min
                            </span>
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?php echo e((string) $questions); ?> questions
                            </span>
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?php echo e(number_format($attempts)); ?>
                            </span>
                        </div>

                        <div class="flex md:flex-shrink-0">
                            <?php if ($completed): ?>
                            <a href="test-start.php?id=<?php echo e((string) ($test['id'] ?? '')); ?>"
                               class="retry-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold w-full md:w-auto transition-all duration-300">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Retry
                            </a>
                            <?php else: ?>
                            <a href="test-start.php?id=<?php echo e((string) ($test['id'] ?? '')); ?>"
                               class="start-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white w-full md:w-auto transition-all duration-300">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span>Start</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- EMPTY STATE -->
                <div id="emptyState" class="hidden empty-state text-center py-20">
                    <div class="w-20 h-20 rounded-2xl bg-slate-800/60 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-9 h-9 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-display font-700 text-white text-lg mb-2">No tests found</h3>
                    <p class="text-slate-500 text-sm mb-5">Try adjusting your search or filters</p>
                    <button onclick="resetFilters()"
                            class="start-btn inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Reset Filters
                    </button>
                </div>
            </section>

        </main>

        <footer class="px-4 sm:px-8 py-4 border-t border-slate-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
            <span class="text-xs text-slate-600">SkillTrust Student Panel</span>
            <span class="text-xs text-slate-600">v2.4.0</span>
        </footer>
    </div>
</div>

<!-- Scripts -->
<script>
/**
 * toggleDropdown() fallback — if student-shell.js doesn't define it, this will.
 * The if-check prevents a double-definition error when student-shell.js does define it.
 */
if (typeof toggleDropdown === 'undefined') {
    function toggleDropdown() {
        const menu = document.getElementById('dropdownMenu');
        if (!menu) return;
        menu.classList.toggle('hidden');
    }
    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('profileDropdown');
        const menu     = document.getElementById('dropdownMenu');
        if (menu && dropdown && !dropdown.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
}
</script>
<script src="../assets/js/student/tests.js"></script>
</body>
</html>