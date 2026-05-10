<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'admin-profile.php';
$pageTitle = 'Admin Profile';

$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    header('Location: login.php');
    exit;
}


$admin = ['name' => (string) ($_SESSION['admin_name'] ?? 'Admin'), 'email' => ''];
$sel = $conn->prepare('SELECT name, email FROM admins WHERE id = ? LIMIT 1');
if ($sel) {
    $sel->bind_param('i', $adminId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if ($row) {
        $admin['name'] = (string) ($row['name'] ?? $admin['name']);
        $admin['email'] = (string) ($row['email'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin-profile.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="flex min-h-screen">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800">&#9776;</button>
                <div><h2 class="font-display font-bold text-white text-lg">Admin Profile</h2><p class="text-xs text-slate-500">Manage your account information and password</p></div>
            </div>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <section class="glass-card rounded-2xl p-5 sm:p-6">
                <h3 class="font-display font-bold text-white text-lg mb-4">Profile Details</h3>
                <form method="post" action="actions/admin-profile-action.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Name</label>
                        <input class="field" type="text" name="name" required value="<?php echo e($admin['name']); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                        <input class="field" type="email" name="email" required value="<?php echo e($admin['email']); ?>">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-violet-500 hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300">Save Profile</button>
                    </div>
                </form>
            </section>

            <section class="glass-card rounded-2xl p-5 sm:p-6">
                <h3 class="font-display font-bold text-white text-lg mb-4">Change Password</h3>
                <form method="post" action="actions/admin-profile-action.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Current Password</label>
                        <input class="field" type="password" name="current_password" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">New Password</label>
                        <input class="field" type="password" name="new_password" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Confirm New Password</label>
                        <input class="field" type="password" name="confirm_password" required>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/10 transition-all duration-300">Update Password</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<script type="application/json" id="adminProfileData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script src="../assets/js/admin/admin-profile.js"></script>
</body>
</html>
