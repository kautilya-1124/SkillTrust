<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['recruiter_id'])) {
    header('Location: dashboard.php');
    exit;
}

$values = [
    'company_name' => '',
    'recruiter_name' => '',
    'email' => '',
    'phone' => '',
    'company_description' => '',
];
$errors = [];
$toast = null;

if (empty($_SESSION['recruiter_register_csrf'])) {
    $_SESSION['recruiter_register_csrf'] = bin2hex(random_bytes(32));
}

if (!function_exists('normalize_phone_number')) {
    function normalize_phone_number(string $phone): string
    {
        return preg_replace('/\s+/', ' ', trim($phone)) ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $values['company_name'] = trim((string) ($_POST['company_name'] ?? ''));
    $values['recruiter_name'] = trim((string) ($_POST['recruiter_name'] ?? ''));
    $values['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $values['phone'] = normalize_phone_number((string) ($_POST['phone'] ?? ''));
    $values['company_description'] = trim((string) ($_POST['company_description'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!hash_equals((string) $_SESSION['recruiter_register_csrf'], $token)) {
        $errors['general'] = 'Invalid request. Please refresh the page and try again.';
    }

    if (!db_table_exists($conn, 'recruiters')) {
        $errors['general'] = 'Recruiters table is missing. Run the recruiter schema before registering.';
    }

    if ($values['company_name'] === '') {
        $errors['company_name'] = 'Company name is required.';
    } elseif (mb_strlen($values['company_name']) < 2 || mb_strlen($values['company_name']) > 160) {
        $errors['company_name'] = 'Company name must be between 2 and 160 characters.';
    }

    if ($values['recruiter_name'] === '') {
        $errors['recruiter_name'] = 'Recruiter name is required.';
    } elseif (mb_strlen($values['recruiter_name']) < 2 || mb_strlen($values['recruiter_name']) > 120) {
        $errors['recruiter_name'] = 'Recruiter name must be between 2 and 120 characters.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Business email is required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid business email address.';
    } elseif (mb_strlen($values['email']) > 190) {
        $errors['email'] = 'Email address is too long.';
    }

    if ($values['phone'] === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Enter a valid phone number using digits and standard symbols.';
    }

    if ($values['company_description'] !== '' && mb_strlen($values['company_description']) > 1000) {
        $errors['company_description'] = 'Company description must be 1000 characters or fewer.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Use at least one uppercase letter, one lowercase letter, and one number.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm the password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    $nameColumn = null;
    if ($errors === [] && db_table_exists($conn, 'recruiters')) {
        if (db_column_exists($conn, 'recruiters', 'recruiter_name')) {
            $nameColumn = 'recruiter_name';
        } elseif (db_column_exists($conn, 'recruiters', 'contact_name')) {
            $nameColumn = 'contact_name';
        } else {
            $errors['general'] = 'Recruiters table is missing recruiter name column.';
        }
    }

    if ($errors === [] && db_table_exists($conn, 'recruiters')) {
        $check = $conn->prepare('SELECT id FROM recruiters WHERE email = ? LIMIT 1');
        if (!$check) {
            $errors['general'] = 'Unable to validate recruiter email right now.';
        } else {
            $check->bind_param('s', $values['email']);
            $check->execute();
            $existing = db_fetch_one($check);
            $check->close();

            if ($existing) {
                $errors['email'] = 'A recruiter account already exists with this email.';
            }
        }
    }

    if ($errors === [] && $nameColumn !== null) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $hasPhoneColumn = db_column_exists($conn, 'recruiters', 'phone');
        $hasDescriptionColumn = db_column_exists($conn, 'recruiters', 'company_description');
        $hasStatusColumn = db_column_exists($conn, 'recruiters', 'status');

        $insert = null;

        if ($nameColumn === 'recruiter_name' && $hasPhoneColumn && $hasDescriptionColumn && $hasStatusColumn) {
            $insert = $conn->prepare(
                'INSERT INTO recruiters (company_name, recruiter_name, email, phone, password, company_description, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if ($insert) {
                $description = $values['company_description'];
                $status = 'pending';
                $insert->bind_param(
                    'sssssss',
                    $values['company_name'],
                    $values['recruiter_name'],
                    $values['email'],
                    $values['phone'],
                    $passwordHash,
                    $description,
                    $status
                );
            }
        } elseif ($nameColumn === 'contact_name' && !$hasPhoneColumn && !$hasDescriptionColumn && $hasStatusColumn) {
            $insert = $conn->prepare(
                'INSERT INTO recruiters (company_name, contact_name, email, password, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if ($insert) {
                $status = 'pending';
                $insert->bind_param(
                    'sssss',
                    $values['company_name'],
                    $values['recruiter_name'],
                    $values['email'],
                    $passwordHash,
                    $status
                );
            }
        } elseif ($nameColumn === 'contact_name' && $hasPhoneColumn && $hasDescriptionColumn && $hasStatusColumn) {
            $insert = $conn->prepare(
                'INSERT INTO recruiters (company_name, contact_name, email, phone, password, company_description, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if ($insert) {
                $description = $values['company_description'];
                $status = 'pending';
                $insert->bind_param(
                    'sssssss',
                    $values['company_name'],
                    $values['recruiter_name'],
                    $values['email'],
                    $values['phone'],
                    $passwordHash,
                    $description,
                    $status
                );
            }
        } elseif ($nameColumn === 'recruiter_name' && !$hasPhoneColumn && !$hasDescriptionColumn && $hasStatusColumn) {
            $insert = $conn->prepare(
                'INSERT INTO recruiters (company_name, recruiter_name, email, password, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if ($insert) {
                $status = 'pending';
                $insert->bind_param(
                    'sssss',
                    $values['company_name'],
                    $values['recruiter_name'],
                    $values['email'],
                    $passwordHash,
                    $status
                );
            }
        } else {
            $errors['general'] = 'Recruiters table schema is not compatible with this registration form. Please update the recruiters table.';
        }

        if ($errors === [] && !$insert) {
            $errors['general'] = 'Unable to prepare recruiter registration right now.';
        } elseif ($errors === [] && $insert) {
            if ($insert->execute()) {
                $insert->close();
                unset($_SESSION['recruiter_register_csrf']);
                session_regenerate_id(true);
                set_flash_toast('success', 'Registration submitted. Your recruiter account is now pending admin approval.');
                header('Location: login.php?registered=1');
                exit;
            }

            $errors['general'] = 'Registration failed: ' . $insert->error;
            $insert->close();
        }
    }

    if ($errors !== []) {
        $toast = ['type' => 'error', 'message' => 'Please fix the highlighted recruiter fields and try again.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
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
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.16),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.16),_transparent_32%),linear-gradient(180deg,_rgba(255,255,255,1),_rgba(241,245,249,1))] dark:bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.22),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.12),_transparent_25%),linear-gradient(180deg,_rgba(2,6,23,1),_rgba(15,23,42,1))]"></div>
        <div class="absolute inset-x-0 top-0 -z-10 h-64 bg-gradient-to-b from-white/50 to-transparent dark:from-white/5"></div>

        <div id="toast" class="pointer-events-none fixed right-5 top-5 z-50 hidden min-w-[280px] rounded-2xl border px-4 py-3 text-sm shadow-glow backdrop-blur"></div>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between py-4">
                <a href="../index.php" class="inline-flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 via-sky-500 to-emerald-400 text-base font-extrabold text-white shadow-soft">ST</span>
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.32em] text-brand-600 dark:text-brand-100">SkillTrust</span>
                        <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Recruiter Hiring Panel</span>
                    </span>
                </a>

                <button
                    type="button"
                    id="themeToggle"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 shadow-soft backdrop-blur transition hover:border-brand-200 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:text-white"
                >
                    <i data-lucide="moon-star" class="h-4 w-4"></i>
                    <span id="themeToggleLabel">Dark mode</span>
                </button>
            </header>

            <main class="flex flex-1 items-center py-8">
                <div class="grid w-full items-stretch gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="relative overflow-hidden rounded-[32px] border border-white/60 bg-white/75 p-8 shadow-glow backdrop-blur dark:border-white/10 dark:bg-white/5 lg:p-10">
                        <div class="absolute -right-10 top-0 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl dark:bg-brand-500/20"></div>
                        <div class="absolute bottom-0 left-0 h-36 w-36 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-400/15"></div>

                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.24em] text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-100">
                            <i data-lucide="building-2" class="h-3.5 w-3.5"></i>
                            Recruiter onboarding
                        </span>

                        <h1 class="mt-6 max-w-xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl">
                            Hire with confidence using average test performance.
                        </h1>
                        <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300 sm:text-base">
                            Create your recruiter account, wait for approval, and unlock a hiring workflow built around student average scores across all tests instead of one-off results.
                        </p>
                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <article class="rounded-3xl border border-slate-200/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-slate-950/40">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                        <i data-lucide="shield-check" class="h-5 w-5"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Admin-reviewed access</p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Every new recruiter is stored with pending status for safer onboarding.</p>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-slate-200/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-slate-950/40">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        <i data-lucide="bar-chart-3" class="h-5 w-5"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Average-score filtering</p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Eligibility will be driven by <code class="rounded-lg bg-slate-100 px-2 py-1 text-[12px] dark:bg-slate-800">AVG(results.score)</code>.</p>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="mt-8 rounded-[28px] border border-slate-200/80 bg-gradient-to-br from-slate-50 to-white p-6 dark:border-white/10 dark:from-slate-900/80 dark:to-slate-950/80">
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 font-semibold text-white dark:bg-white dark:text-slate-900">
                                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                                    Register
                                </span>
                                <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 font-medium dark:border-slate-700">
                                    <i data-lucide="clock-3" class="h-4 w-4"></i>
                                    Pending approval
                                </span>
                                <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 font-medium dark:border-slate-700">
                                    <i data-lucide="briefcase-business" class="h-4 w-4"></i>
                                    Post jobs
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[32px] border border-white/60 bg-white/90 p-6 shadow-glow backdrop-blur dark:border-white/10 dark:bg-slate-900/80 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.26em] text-brand-600 dark:text-brand-100">Create account</p>
                                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Recruiter registration</h2>
                                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    Fill in your company and recruiter details. Your access will stay pending until approved by an admin.
                                </p>
                            </div>
                            <span class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300 sm:inline-flex">
                                Production-ready auth
                            </span>
                        </div>

                        <?php if (isset($errors['general'])): ?>
                            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                                <?php echo e($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="mt-6 space-y-5" id="recruiterRegisterForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo e((string) $_SESSION['recruiter_register_csrf']); ?>">

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="company_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Company name</label>
                                    <input
                                        id="company_name"
                                        name="company_name"
                                        type="text"
                                        required
                                        maxlength="160"
                                        value="<?php echo e($values['company_name']); ?>"
                                        placeholder="Acme Talent Labs"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['company_name']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <?php if (isset($errors['company_name'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['company_name']); ?></p><?php endif; ?>
                                </div>

                                <div>
                                    <label for="recruiter_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Recruiter name</label>
                                    <input
                                        id="recruiter_name"
                                        name="recruiter_name"
                                        type="text"
                                        required
                                        maxlength="120"
                                        value="<?php echo e($values['recruiter_name']); ?>"
                                        placeholder="Riya Sharma"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['recruiter_name']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <?php if (isset($errors['recruiter_name'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['recruiter_name']); ?></p><?php endif; ?>
                                </div>

                                <div>
                                    <label for="phone" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Phone number</label>
                                    <input
                                        id="phone"
                                        name="phone"
                                        type="text"
                                        required
                                        maxlength="20"
                                        value="<?php echo e($values['phone']); ?>"
                                        placeholder="+91 98765 43210"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['phone']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <?php if (isset($errors['phone'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['phone']); ?></p><?php endif; ?>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Business email</label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        maxlength="190"
                                        value="<?php echo e($values['email']); ?>"
                                        placeholder="hiring@acme.com"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['email']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <?php if (isset($errors['email'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['email']); ?></p><?php endif; ?>
                                </div>

                                <div>
                                    <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Password</label>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        required
                                        minlength="8"
                                        placeholder="At least 8 characters"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['password']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use uppercase, lowercase, and a number.</p>
                                    <?php if (isset($errors['password'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['password']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="confirm_password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Confirm password</label>
                                    <input
                                        id="confirm_password"
                                        name="confirm_password"
                                        type="password"
                                        required
                                        minlength="8"
                                        placeholder="Repeat your password"
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['confirm_password']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    >
                                    <?php if (isset($errors['confirm_password'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['confirm_password']); ?></p><?php endif; ?>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="company_description" class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Company description <span class="normal-case tracking-normal text-slate-400">Optional</span></label>
                                    <textarea
                                        id="company_description"
                                        name="company_description"
                                        rows="4"
                                        maxlength="1000"
                                        placeholder="Briefly describe your company, hiring goals, or team focus."
                                        class="w-full rounded-2xl border px-4 py-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 dark:focus:ring-brand-500/20 <?php echo isset($errors['company_description']) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/80'; ?>"
                                    ><?php echo e($values['company_description']); ?></textarea>
                                    <?php if (isset($errors['company_description'])): ?><p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"><?php echo e($errors['company_description']); ?></p><?php endif; ?>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">
                                New recruiter accounts are stored with <span class="font-semibold text-amber-600 dark:text-amber-300">pending</span> status and can sign in only after admin approval.
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white shadow-soft transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                <i data-lucide="user-round-plus" class="h-4 w-4"></i>
                                Register recruiter account
                            </button>
                        </form>

                        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                            Already have an approved recruiter account?
                            <a href="login.php" class="font-semibold text-brand-600 transition hover:text-brand-700 dark:text-brand-100 dark:hover:text-white">Sign in</a>
                        </p>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script type="application/json" id="toastData"><?php echo json_encode($toast, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
    <script>
        (function () {
            if (window.lucide) {
                window.lucide.createIcons();
            }

            var root = document.documentElement;
            var toggleButton = document.getElementById('themeToggle');
            var toggleLabel = document.getElementById('themeToggleLabel');
            var form = document.getElementById('recruiterRegisterForm');
            var toastNode = document.getElementById('toast');

            function syncThemeLabel() {
                toggleLabel.textContent = root.classList.contains('dark') ? 'Light mode' : 'Dark mode';
            }

            function showToast(type, message) {
                var palette = {
                    success: 'border-emerald-200 bg-white/95 text-emerald-700 dark:border-emerald-500/20 dark:bg-slate-900/95 dark:text-emerald-300',
                    error: 'border-rose-200 bg-white/95 text-rose-700 dark:border-rose-500/20 dark:bg-slate-900/95 dark:text-rose-300',
                    info: 'border-brand-200 bg-white/95 text-brand-700 dark:border-brand-500/20 dark:bg-slate-900/95 dark:text-brand-100'
                };
                toastNode.className = 'pointer-events-none fixed right-5 top-5 z-50 min-w-[280px] rounded-2xl border px-4 py-3 text-sm shadow-glow backdrop-blur ' + (palette[type] || palette.info);
                toastNode.textContent = message;
                toastNode.classList.remove('hidden');
                window.setTimeout(function () {
                    toastNode.classList.add('hidden');
                }, 3600);
            }

            syncThemeLabel();
            toggleButton.addEventListener('click', function () {
                root.classList.toggle('dark');
                localStorage.setItem('skilltrust-theme', root.classList.contains('dark') ? 'dark' : 'light');
                syncThemeLabel();
            });

            form.addEventListener('submit', function (event) {
                var company = document.getElementById('company_name').value.trim();
                var recruiter = document.getElementById('recruiter_name').value.trim();
                var phone = document.getElementById('phone').value.trim();
                var email = document.getElementById('email').value.trim();
                var password = document.getElementById('password').value;
                var confirmPassword = document.getElementById('confirm_password').value;
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                var phonePattern = /^[0-9+\-\s()]{7,20}$/;
                var strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

                if (
                    company.length < 2 ||
                    recruiter.length < 2 ||
                    !phonePattern.test(phone) ||
                    !emailPattern.test(email) ||
                    !strongPassword.test(password) ||
                    password !== confirmPassword
                ) {
                    event.preventDefault();
                    showToast('error', 'Please complete all recruiter details correctly before continuing.');
                }
            });

            var toast = null;
            try {
                toast = JSON.parse(document.getElementById('toastData').textContent || 'null');
            } catch (error) {
                toast = null;
            }

            if (toast && toast.message) {
                showToast(toast.type || 'info', toast.message);
            }
        }());
    </script>
</body>
</html>
