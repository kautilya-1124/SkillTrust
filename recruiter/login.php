<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['recruiter_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';
$success = '';
if (empty($_SESSION['recruiter_login_csrf'])) {
    $_SESSION['recruiter_login_csrf'] = bin2hex(random_bytes(32));
}

if (isset($_GET['registered']) && (string) $_GET['registered'] === '1') {
    $success = 'Registration submitted. Wait for admin approval before signing in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals((string) $_SESSION['recruiter_login_csrf'], $token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Valid email and password are required.';
    } elseif (!db_table_exists($conn, 'recruiters')) {
        $error = 'Recruiters table is missing. Run sql/recruiter_hiring_panel.sql first.';
    } else {
        $nameColumn = null;
        if (db_column_exists($conn, 'recruiters', 'recruiter_name')) {
            $nameColumn = 'recruiter_name';
        } elseif (db_column_exists($conn, 'recruiters', 'contact_name')) {
            $nameColumn = 'contact_name';
        }

        if ($nameColumn === null) {
            $error = 'Recruiters table is missing the recruiter name column.';
        } else {
            $sql = sprintf(
                'SELECT id, company_name, %s AS recruiter_display_name, email, password, status FROM recruiters WHERE email = ? LIMIT 1',
                $nameColumn
            );
            $stmt = $conn->prepare($sql);
        }

        if ($error === '' && !$stmt) {
            $error = 'Database error. Please try again.';
        } elseif ($error === '') {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = db_fetch_one($stmt);
            $stmt->close();

            if (!$row || !password_verify($password, (string) $row['password'])) {
                $error = 'Invalid recruiter credentials.';
            } else {
                $status = strtolower(trim((string) ($row['status'] ?? 'pending')));

                if ($status === 'pending') {
                    $error = 'Your recruiter account is pending admin approval.';
                } elseif (in_array($status, ['blocked', 'rejected'], true)) {
                    $error = 'Your recruiter account is not allowed to sign in. Please contact the admin.';
                }
            }

            if ($error === '') {
                session_regenerate_id(true);
                $_SESSION['recruiter_id'] = (int) $row['id'];
                $_SESSION['recruiter_company'] = (string) ($row['company_name'] ?? '');
                $_SESSION['recruiter_name'] = (string) (($row['recruiter_display_name'] ?? '') !== '' ? $row['recruiter_display_name'] : $row['company_name']);
                $_SESSION['recruiter_email'] = (string) ($row['email'] ?? '');
                $_SESSION['recruiter_status'] = (string) ($row['status'] ?? 'approved');
                unset($_SESSION['recruiter_login_csrf']);
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Register | SkillTrust</title>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('skilltrust-theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        }());
        tailwind = window.tailwind || {};
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 32px 90px -40px rgba(15, 23, 42, 0.55)',
                        soft: '0 14px 40px -24px rgba(15, 23, 42, 0.25)'
                    },
                    colors: {
                        brand: {
                            50: '#eef6ff',
                            100: '#d9eaff',
                            500: '#2563eb',
                            600: '#1d4ed8',
                            700: '#1e40af'
                        }
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
<style>
.signin-btn{display:flex!important;width:100%!important;align-items:center!important;justify-content:center!important;gap:.5rem!important;padding:1rem 1.25rem!important;border-radius:1rem!important;border:0!important;background:#2563eb!important;background-image:linear-gradient(90deg,#1d4ed8,#2563eb,#10b981)!important;color:#fff!important;font-size:1rem!important;font-weight:700!important;box-shadow:0 12px 30px rgba(37,99,235,.3)!important;cursor:pointer!important}
.signin-btn:hover{background:#1d4ed8!important;background-image:linear-gradient(90deg,#1e40af,#1d4ed8,#059669)!important;transform:translateY(-1px)!important}
</style></head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.16),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.16),_transparent_32%),linear-gradient(180deg,_rgba(255,255,255,1),_rgba(241,245,249,1))] dark:bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.22),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.12),_transparent_25%),linear-gradient(180deg,_rgba(2,6,23,1),_rgba(15,23,42,1))]"></div>
        <div class="absolute inset-x-0 top-0 -z-10 h-64 bg-gradient-to-b from-white/50 to-transparent dark:from-white/5"></div>

        <div id="toast" class="pointer-events-none fixed right-5 top-5 z-50 hidden min-w-[280px] rounded-2xl border px-4 py-3 text-sm shadow-glow backdrop-blur"></div>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <header class="sticky top-0 z-40 -mx-4 mb-6 border-b border-slate-200/70 bg-white/85 px-4 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/85">
                <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 py-4" aria-label="Main navigation">
                    <a href="../index.php" class="inline-flex shrink-0 items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 via-sky-500 to-emerald-400 text-base font-extrabold text-white shadow-soft">ST</span>
                        <span>
                            <span class="block text-sm font-extrabold tracking-wide text-slate-900 dark:text-white">SkillTrust</span>
                            <span class="hidden text-xs text-slate-500 dark:text-slate-400 sm:block">Recruiter Hiring Panel</span>
                        </span>
                    </a>
                    <div class="flex items-center gap-1 sm:gap-3">
                        <a href="../index.php" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">Home</a>
                        <a href="login.php" aria-current="page" class="rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Login</a>
                        <a href="register.php" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">Sign up</a>
                        <button type="button" id="themeToggle" class="hidden sm:inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/80 px-3 py-2 text-sm font-medium text-slate-700 shadow-soft transition hover:border-brand-200 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:text-white">
                            <i data-lucide="moon-star" class="h-4 w-4"></i><span id="themeToggleLabel">Dark mode</span>
                        </button>
                    </div>
                </nav>
            </header>

            <main class="flex flex-1 items-center py-8">
                <div class="grid w-full items-stretch gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="relative overflow-hidden rounded-[32px] border border-white/60 bg-white/75 p-8 shadow-glow backdrop-blur dark:border-white/10 dark:bg-white/5 lg:p-10">
                        <div class="absolute -right-10 top-0 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl dark:bg-brand-500/20"></div>
                        <div class="absolute bottom-0 left-0 h-36 w-36 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-400/15"></div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.24em] text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-100">
                            <i data-lucide="building-2" class="h-3.5 w-3.5"></i> Recruiter access
                        </span>
                        <h1 class="mt-6 max-w-xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl">Welcome back to SkillTrust.</h1>
                        <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300 sm:text-base">Sign in to manage job postings, review applicants, and shortlist candidates using average test performance.</p>
                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <article class="rounded-3xl border border-slate-200/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-slate-950/40">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><i data-lucide="shield-check" class="h-5 w-5"></i></span>
                                    <div><p class="text-sm font-semibold text-slate-900 dark:text-white">Secure recruiter access</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Sign in after your recruiter account has been approved.</p></div>
                                </div>
                            </article>
                            <article class="rounded-3xl border border-slate-200/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-slate-950/40">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><i data-lucide="bar-chart-3" class="h-5 w-5"></i></span>
                                    <div><p class="text-sm font-semibold text-slate-900 dark:text-white">Performance-based hiring</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Review candidates with consistent test-score insights.</p></div>
                                </div>
                            </article>
                        </div>
                        <div class="mt-8 rounded-[28px] border border-slate-200/80 bg-gradient-to-br from-slate-50 to-white p-6 dark:border-white/10 dark:from-slate-900/80 dark:to-slate-950/80">
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 font-semibold text-white dark:bg-white dark:text-slate-900"><i data-lucide="log-in" class="h-4 w-4"></i> Sign in</span>
                                <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 font-medium dark:border-slate-700"><i data-lucide="users" class="h-4 w-4"></i> Review applicants</span>
                                <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 font-medium dark:border-slate-700"><i data-lucide="briefcase-business" class="h-4 w-4"></i> Hire talent</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[32px] border border-white/60 bg-white/90 p-6 shadow-glow backdrop-blur dark:border-white/10 dark:bg-slate-900/80 sm:p-8">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 via-sky-500 to-emerald-400 text-white shadow-soft"><i data-lucide="log-in" class="h-5 w-5"></i></div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-300">Recruiter Panel</p>
                                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Sign in to your account</h2>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Enter your credentials to continue.</p>
                            </div>
                        </div>
                        <?php if ($success !== ''): ?><div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200"><?php echo e($success); ?></div><?php endif; ?>
                        <?php if ($error !== ''): ?><div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"><?php echo e($error); ?></div><?php endif; ?>
                        <form method="post" class="space-y-5">
                            <input type="hidden" name="csrf_token" value="<?php echo e((string) $_SESSION['recruiter_login_csrf']); ?>">
                            <div>
                                <label for="email" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
                                <input id="email" name="email" type="email" autocomplete="email" required value="<?php echo e($email); ?>" placeholder="you@company.com" class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-white dark:placeholder:text-slate-500">
                            </div>
                            <div>
                                <label for="password" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
                                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-white dark:placeholder:text-slate-500">
                            </div>
                            <button type="submit" class="signin-btn"><i data-lucide="log-in" class="h-5 w-5"></i><span>Sign In</span></button>
                        </form>
                        <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-300">Need a recruiter account? <a href="register.php" class="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">Create account</a></p>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (function () {
            var root = document.documentElement;
            var toggleButton = document.getElementById('themeToggle');
            var toggleLabel = document.getElementById('themeToggleLabel');
            function updateThemeButton() {
                if (!toggleButton || !toggleLabel) return;
                var isDark = root.classList.contains('dark');
                toggleLabel.textContent = isDark ? 'Light mode' : 'Dark mode';
                var icon = toggleButton.querySelector('i');
                if (icon) icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon-star');
                if (window.lucide) window.lucide.createIcons();
            }
            if (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    var isDark = root.classList.toggle('dark');
                    localStorage.setItem('skilltrust-theme', isDark ? 'dark' : 'light');
                    updateThemeButton();
                });
            }
            if (window.lucide) window.lucide.createIcons();
            updateThemeButton();
        }());
    </script>
</body>
</html>
