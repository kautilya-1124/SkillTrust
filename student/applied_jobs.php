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

$studentName = 'Student';
$studentEmail = '';
$initials = 'ST';

$userStmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
if (!$userStmt) {
    die('SQL Error: ' . $conn->error);
}

$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRow = db_fetch_one($userStmt);
$userStmt->close();

$studentName = trim((string) ($userRow['name'] ?? 'Student'));
$studentEmail = (string) ($userRow['email'] ?? '');
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $studentName) ?: 'ST', 0, 2));

$avgScore = 0.0;
$avgStmt = $conn->prepare('SELECT AVG(score) AS avg_score FROM results WHERE user_id = ?');
if (!$avgStmt) {
    die('SQL Error: ' . $conn->error);
}

$avgStmt->bind_param('i', $userId);
$avgStmt->execute();
$avgRow = db_fetch_one($avgStmt);
$avgStmt->close();
$avgScore = normalize_score($avgRow['avg_score'] ?? 0);

$jobMinScoreColumn = db_column_exists($conn, 'jobs', 'min_avg_score')
    ? 'min_avg_score'
    : (db_column_exists($conn, 'jobs', 'min_average_score') ? 'min_average_score' : '');

if ($jobMinScoreColumn === '') {
    die('SQL Error: jobs table is missing min_avg_score or min_average_score column.');
}

$applicationsSql = 'SELECT jobs.*, applications.status, applications.applied_at
     FROM applications
     JOIN jobs ON applications.job_id = jobs.id
     WHERE applications.user_id = ?
     ORDER BY applications.applied_at DESC';

$applicationsStmt = $conn->prepare($applicationsSql);
if (!$applicationsStmt) {
    die('SQL Error: ' . $conn->error);
}

$applicationsStmt->bind_param('i', $userId);
$applicationsStmt->execute();
$applications = db_fetch_all($applicationsStmt);
$applicationsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applied Jobs | SkillTrust</title>
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
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white shadow-lg shadow-brand-500/30">
                    <i data-lucide="badge-check" class="h-4 w-4"></i>
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
            <a href="jobs.php" class="nav-item flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/14">
                    <svg class="h-4 w-4 text-violet-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2zm3-3h6a2 2 0 012 2v1H7V6a2 2 0 012-2z"/>
                    </svg>
                </div>
                <span>Jobs</span>
            </a>
            <a href="applied_jobs.php" class="nav-item active flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/14">
                    <svg class="h-4 w-4 text-amber-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-xs font-bold text-white shadow-lg shadow-brand-500/20"><?php echo e($initials); ?></div>
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
                    <h2 class="font-display text-lg font-bold text-white">Applied Jobs</h2>
                    <p class="text-xs text-slate-500">Track every role you have applied for</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white">
                    <i data-lucide="moon-star" class="h-4 w-4"></i>
                    <span id="themeLabel">Dark</span>
                </button>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-xs font-bold text-white shadow-lg shadow-brand-500/20"><?php echo e($initials); ?></div>
            </div>
        </header>

        <main class="flex-1 px-3 py-6 sm:px-4 lg:px-8">
            <section class="glass-card stagger rounded-[30px] p-6" style="animation-delay: 0.05s;">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-bold text-white">Your Applications</h1>
                        <p class="mt-2 text-sm text-slate-400">Track submitted roles, compare them against your score profile, and jump back into the job board whenever you are ready.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <div class="rounded-2xl border border-brand-500/20 bg-brand-500/10 px-4 py-3 text-sm">
                            <div class="text-slate-400">Your Avg Score</div>
                            <div class="mt-1 font-display text-2xl font-bold text-white"><?php echo e(number_format($avgScore, 2)); ?></div>
                        </div>
                        <a href="jobs.php" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15">
                            <i data-lucide="briefcase-business" class="h-4 w-4"></i>
                            <span>Browse Jobs</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="mt-6 grid grid-cols-1 gap-5">
                <?php if ($applications === []): ?>
                    <div class="glass-card stagger rounded-[28px] p-10 text-center" style="animation-delay: 0.12s;">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-500/10 text-brand-300">
                            <i data-lucide="inbox" class="h-8 w-8"></i>
                        </div>
                        <h3 class="mt-5 font-display text-2xl font-bold text-white">No applications yet</h3>
                        <p class="mt-3 text-sm text-slate-400">Browse jobs and apply to start building your hiring pipeline.</p>
                        <a href="jobs.php" class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand-500 to-violet-600 px-5 py-3 text-sm font-semibold text-white">
                            <i data-lucide="briefcase-business" class="h-4 w-4"></i>
                            <span>Find Jobs</span>
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $index => $application): ?>
                        <?php
                        $status = strtolower((string) ($application['status'] ?? 'applied'));
                        $statusClasses = match ($status) {
                            'selected' => 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30',
                            'shortlisted' => 'bg-amber-500/15 text-amber-300 border border-amber-500/30',
                            'rejected' => 'bg-rose-500/15 text-rose-300 border border-rose-500/30',
                            default => 'bg-slate-700/60 text-slate-200 border border-slate-600/70',
                        };
                        $requiredScore = normalize_score($application[$jobMinScoreColumn] ?? 0);
                        $isEligibleByAverage = $avgScore >= $requiredScore;
                        ?>
                        <article class="glass-card stagger rounded-[28px] p-6" style="animation-delay: <?php echo e((string) (120 + ($index * 50))); ?>ms;">
                            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="rounded-full <?php echo e($statusClasses); ?> px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em]">
                                            <?php echo e(ucfirst($status)); ?>
                                        </span>
                                        <span class="text-xs text-slate-500">Applied <?php echo e(time_ago_label((string) ($application['applied_at'] ?? ''))); ?></span>
                                    </div>

                                    <h3 class="mt-4 font-display text-2xl font-bold text-white"><?php echo e((string) ($application['title'] ?? 'Job')); ?></h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300/90"><?php echo e((string) ($application['description'] ?? '')); ?></p>
                                </div>

                                <div class="w-full xl:max-w-xs">
                                    <div class="rounded-[24px] border border-slate-700/70 bg-slate-950/72 p-5">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <div class="text-slate-500">Required Score</div>
                                                <div class="mt-1 font-semibold text-white"><?php echo e(number_format($requiredScore, 2)); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-slate-500">Your Avg Score</div>
                                                <div class="mt-1 font-semibold text-brand-300"><?php echo e(number_format($avgScore, 2)); ?></div>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-2xl <?php echo $isEligibleByAverage ? 'border border-emerald-500/25 bg-emerald-500/10 text-emerald-200' : 'border border-rose-500/25 bg-rose-500/10 text-rose-200'; ?> px-4 py-3 text-sm">
                                            <?php echo $isEligibleByAverage ? 'Eligible by average score' : 'Below required average score'; ?>
                                        </div>

                                        <?php if (!empty($application['expiry_date'])): ?>
                                            <div class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                                                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                                                <span>Expires <?php echo e(format_date_label((string) $application['expiry_date'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script type="application/json" id="pageData"><?php echo json_encode(['toast' => $toast], JSON_UNESCAPED_UNICODE); ?></script>
<script>
    lucide.createIcons();

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    const root = document.documentElement;
    const themeToggle = document.getElementById('themeToggle');
    const themeLabel = document.getElementById('themeLabel');

    function syncTheme() {
        themeLabel.textContent = root.classList.contains('dark') ? 'Light' : 'Dark';
    }

    syncTheme();

    themeToggle.addEventListener('click', () => {
        const isDark = root.classList.contains('dark');
        root.classList.toggle('dark', !isDark);
        localStorage.setItem('skilltrust-theme', !isDark ? 'dark' : 'light');
        syncTheme();
        lucide.createIcons();
    });

    function showToast(type, msg) {
        const toast = document.getElementById('toast');
        const classes = {
            success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
            error: 'bg-rose-500/15 border-rose-500/30 text-rose-300',
            info: 'bg-brand-500/15 border-brand-500/30 text-brand-200'
        };
        toast.textContent = msg;
        toast.className = 'fixed bottom-6 right-6 z-[100] rounded-xl border px-4 py-2.5 text-sm font-semibold ' + (classes[type] || classes.info);
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 4000);
    }

    const pageData = JSON.parse(document.getElementById('pageData').textContent);
    if (pageData.toast && pageData.toast.message) {
        showToast(pageData.toast.type || 'info', pageData.toast.message);
    }
</script>
</body>
</html>
