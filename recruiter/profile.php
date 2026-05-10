<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_recruiter_login();

$recruiterId = current_recruiter_id();
$currentPage = 'profile.php';
$toast = consume_flash_toast();
$recruiterName = (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name());
$companyName = (string) ($_SESSION['recruiter_company'] ?? $recruiterName);
$recruiterEmail = (string) ($_SESSION['recruiter_email'] ?? '');
$recruiterStatus = strtolower(trim((string) ($_SESSION['recruiter_status'] ?? 'approved')));
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');

$profile = [
    'company_name' => $companyName,
    'recruiter_name' => $recruiterName,
    'email' => $recruiterEmail,
    'status' => $recruiterStatus,
];

if (db_table_exists($conn, 'recruiters')) {
    $nameColumn = db_column_exists($conn, 'recruiters', 'recruiter_name') ? 'recruiter_name' : (db_column_exists($conn, 'recruiters', 'contact_name') ? 'contact_name' : '');
    $selectFields = ['company_name', 'email', 'status'];
    if ($nameColumn !== '') {
        $selectFields[] = $nameColumn . ' AS recruiter_display_name';
    }

    $stmt = $conn->prepare(sprintf('SELECT %s FROM recruiters WHERE id = ? LIMIT 1', implode(', ', $selectFields)));
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $row = db_fetch_one($stmt);
        $stmt->close();

        if ($row) {
            $profile['company_name'] = trim((string) ($row['company_name'] ?? '')) !== '' ? (string) $row['company_name'] : $profile['company_name'];
            $profile['email'] = (string) ($row['email'] ?? $profile['email']);
            $profile['status'] = strtolower(trim((string) ($row['status'] ?? $profile['status'])));
            if (isset($row['recruiter_display_name']) && trim((string) $row['recruiter_display_name']) !== '') {
                $profile['recruiter_name'] = (string) $row['recruiter_display_name'];
            }

            $_SESSION['recruiter_company'] = $profile['company_name'];
            $_SESSION['recruiter_email'] = $profile['email'];
            $_SESSION['recruiter_status'] = $profile['status'];
            $_SESSION['recruiter_name'] = $profile['recruiter_name'];

            $companyName = $profile['company_name'];
            $recruiterName = $profile['recruiter_name'];
            $recruiterEmail = $profile['email'];
            $recruiterStatus = $profile['status'];
            $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');
        }
    }
}

$statusMeta = [
    'approved' => ['Approved', 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'],
    'pending' => ['Pending approval', 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'],
    'blocked' => ['Blocked', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
    'rejected' => ['Rejected', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
];
[$statusLabel, $statusClass] = $statusMeta[$recruiterStatus] ?? ['Unknown', 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200'];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Profile | SkillTrust</title>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('skilltrust-theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        }());
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['DM Sans', 'sans-serif'],
                        display: ['Syne', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
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

    <div class="recruiter-main flex min-w-0 flex-1 flex-col">
        <header class="navbar sticky top-0 z-30 flex items-center justify-between gap-2 px-3 py-2.5 sm:px-4 lg:h-16 lg:px-8 lg:py-0">
            <div class="flex min-w-0 items-center gap-2">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800">Menu</button>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-300">Recruiter workspace</p>
                    <h1 class="font-display text-lg font-bold text-white">Profile</h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button id="themeToggle" type="button" class="hidden md:inline-flex rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-all duration-300">
                    <span id="themeToggleLabel">Dark mode</span>
                </button>
                <div class="flex items-center gap-2 rounded-xl border border-slate-700/60 bg-slate-900/60 px-3 py-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-display font-bold text-white"><?php echo e($initials); ?></div>
                    <span class="hidden md:inline text-sm text-slate-300"><?php echo e($recruiterName); ?></span>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <?php if ($toast !== null): ?>
                    <div data-toast-type="<?php echo e((string) ($toast['type'] ?? '')); ?>" data-toast-message="<?php echo e((string) ($toast['message'] ?? '')); ?>"></div>
                <?php endif; ?>

                <section class="fade-up relative overflow-hidden rounded-3xl border border-indigo-500/25 bg-gradient-to-br from-indigo-900/35 via-slate-900/80 to-violet-900/30 p-6 sm:p-8">
                    <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 bottom-0 h-44 w-44 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
                    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-5">
                            <div class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-white/15 bg-gradient-to-br from-indigo-500 via-violet-500 to-sky-400 text-2xl font-black tracking-[0.18em] text-white shadow-glow">
                                <?php echo e($initials); ?>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Recruiter Profile</p>
                                <h2 class="mt-2 font-display text-3xl font-extrabold text-white"><?php echo e($recruiterName); ?></h2>
                                <p class="mt-2 text-sm text-slate-400"><?php echo e($companyName); ?></p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-4 py-2 text-xs font-semibold <?php echo e($statusClass); ?>">
                            <?php echo e($statusLabel); ?>
                        </span>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                    <div class="glass-card rounded-3xl p-6">
                        <h3 class="font-display text-xl font-bold text-white">Account Details</h3>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-700/50 bg-slate-900/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Recruiter Name</p>
                                <p class="mt-2 text-base font-semibold text-white"><?php echo e($recruiterName); ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-700/50 bg-slate-900/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Company</p>
                                <p class="mt-2 text-base font-semibold text-white"><?php echo e($companyName); ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-700/50 bg-slate-900/50 p-4 sm:col-span-2">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Email</p>
                                <p class="mt-2 text-base font-semibold text-white"><?php echo e($recruiterEmail !== '' ? $recruiterEmail : 'No email available'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-3xl p-6">
                        <h3 class="font-display text-xl font-bold text-white">Quick Actions</h3>
                        <div class="mt-6 space-y-3">
                            <a href="dashboard.php" class="block rounded-2xl border border-indigo-500/25 bg-indigo-500/10 px-4 py-3 text-sm font-semibold text-indigo-200 transition-all duration-300 hover:bg-indigo-500/20">Open dashboard</a>
                            <a href="create_job.php" class="block rounded-2xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-800">Post a new job</a>
                            <a href="manage_jobs.php" class="block rounded-2xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-800">Review my jobs</a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay) {
            return;
        }
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('active');
    }

    (function () {
        const btn = document.getElementById('themeToggle');
        const label = document.getElementById('themeToggleLabel');

        function syncLabel() {
            if (label) {
                label.textContent = document.documentElement.classList.contains('dark') ? 'Dark mode' : 'Light mode';
            }
        }

        syncLabel();

        if (btn) {
            btn.addEventListener('click', function () {
                const nextDark = !document.documentElement.classList.contains('dark');
                document.documentElement.classList.toggle('dark', nextDark);
                localStorage.setItem('skilltrust-theme', nextDark ? 'dark' : 'light');
                syncLabel();
            });
        }
    }());
</script>
</body>
</html>
