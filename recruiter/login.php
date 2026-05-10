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
    <title>Recruiter Login | SkillTrust</title>
    <script>tailwind=window.tailwind||{};tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}};</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100" style="font-family:Inter,sans-serif;">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.22),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.16),_transparent_25%)] px-4 py-10">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-2">
            <section class="hidden rounded-[32px] border border-white/10 bg-white/5 p-10 lg:block">
                <span class="inline-flex rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">Recruiter access</span>
                <h1 class="mt-6 text-4xl font-semibold leading-tight">Modern hiring, powered by average test performance.</h1>
                <p class="mt-4 max-w-xl text-sm leading-7 text-slate-300">SkillTrust recruiters screen applicants using consistent scores across attempts, not a single isolated result.</p>
            </section>
            <section class="rounded-[32px] border border-white/10 bg-white p-6 text-slate-900 shadow-2xl sm:p-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-emerald-400 font-extrabold text-white">ST</div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Recruiter Panel</p>
                        <h2 class="text-lg font-semibold">Sign in</h2>
                    </div>
                </div>
                <?php if ($success !== ''): ?><div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?php echo e($success); ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?php echo e($error); ?></div><?php endif; ?>
                <form method="post" class="mt-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo e((string) $_SESSION['recruiter_login_csrf']); ?>">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Email</label>
                        <input name="email" type="email" required value="<?php echo e($email); ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none ring-0 transition focus:border-blue-400">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Password</label>
                        <input name="password" type="password" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none ring-0 transition focus:border-blue-400">
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Sign in to recruiter dashboard</button>
                </form>

                <p class="mt-5 text-center text-xs text-slate-500">
                    Need a recruiter account?
                    <a href="register.php" class="font-semibold text-blue-600 hover:text-blue-700">Register here</a>
                </p>
            </section>
        </div>
    </div>
</body>
</html>
