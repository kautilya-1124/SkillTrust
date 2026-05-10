<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['login_csrf'], $token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email.';
        } else {
            $stmt = $conn->prepare('SELECT id, name, password FROM admins WHERE email = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Database error. Please try again.';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($admin && password_verify($password, (string) $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_name'] = (string) ($admin['name'] ?? 'Admin');
                    unset($_SESSION['login_csrf']);
                    header('Location: dashboard.php');
                    exit;
                }

                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/login.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="h-full bg-slate-100">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="../index.php" class="inline-flex items-center gap-3 text-slate-900">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 font-bold text-white shadow-lg shadow-indigo-200">S</span>
                <span>
                    <span class="block text-lg font-semibold tracking-tight">SkillTrust</span>
                    <span class="block text-[11px] text-slate-500">Admin Access</span>
                </span>
            </a>
            <nav class="flex items-center gap-2 sm:gap-3">
                <a href="../index.php" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-white sm:text-sm">Home</a>
                <a href="../auth/login.php" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-white sm:text-sm">User Login</a>
                <a href="login.php" class="rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 sm:text-sm">Admin Login</a>
            </nav>
        </div>
    </header>

    <div class="min-h-[calc(100vh-81px)] grid lg:grid-cols-2">
        <section class="hidden lg:flex items-center justify-center relative p-10 bg-slate-950 text-white">
            <div class="absolute inset-0 opacity-20" style="background: radial-gradient(circle at 30% 20%, #6366f1 0%, transparent 45%), radial-gradient(circle at 80% 80%, #a78bfa 0%, transparent 35%);"></div>
            <div class="relative max-w-md">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 text-xs mb-4">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Secure Admin Access
                </div>
                <h1 class="text-4xl font-bold leading-tight">SkillTrust Admin Console</h1>
                <p class="text-slate-300 mt-4 text-sm leading-relaxed">
                    Manage tests, students, recruiters, and analytics from one premium dashboard.
                </p>
            </div>
        </section>

        <section class="flex items-center justify-center p-5 lg:p-10">
            <div class="w-full max-w-md fade-in glass rounded-3xl shadow-xl p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white font-bold flex items-center justify-center">S</div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Admin Login</h2>
                        <p class="text-xs text-slate-500">Sign in to continue</p>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mb-4 px-3 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="space-y-4" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-900 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-300"
                            placeholder="admin@example.com"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-900 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-300"
                            placeholder="Enter password"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full px-4 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-r from-indigo-500 to-violet-500 hover:from-indigo-600 hover:to-violet-600 active:scale-[0.99] transition-all duration-300 shadow-lg shadow-indigo-200"
                    >
                        Sign In
                    </button>
                </form>

                <p class="text-center text-xs text-slate-500 mt-5">
                    Protected by secure session authentication.
                </p>
            </div>
        </section>
    </div>
</body>
</html>
