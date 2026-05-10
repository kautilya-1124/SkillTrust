<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/test-attempts.php';

$currentPage = 'test-attempt-locks.php';
$pageTitle = 'Test Attempt Locks';
$toast = consume_flash_toast();
$q = trim((string) ($_GET['q'] ?? ''));

$records = [];
$systemReady = skilltrust_test_attempts_ready($conn);

if ($systemReady) {
    $sql = '
        SELECT
            ta.user_id,
            ta.test_id,
            ta.attempts,
            ta.max_attempts,
            ta.is_blocked,
            ta.admin_unlocked,
            ta.last_attempt_at,
            u.name AS student_name,
            u.email AS student_email,
            t.title AS test_title
        FROM test_attempts ta
        INNER JOIN users u ON u.id = ta.user_id
        INNER JOIN tests t ON t.id = ta.test_id
        WHERE (ta.is_blocked = 1 OR ta.attempts >= ta.max_attempts)
          AND ta.admin_unlocked = 0';

    $types = '';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR t.title LIKE ?)';
        $like = '%' . $q . '%';
        $types = 'sss';
        $params = [$like, $like, $like];
    }
    $sql .= ' ORDER BY ta.last_attempt_at DESC, ta.id DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $records = db_fetch_all($stmt);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Attempt Locks - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/manage-tests.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="flex min-h-screen">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">&#9776;</button>
                <div>
                    <h2 class="font-display font-bold text-white text-lg">Test Attempt Locks</h2>
                    <p class="text-xs text-slate-500">Unlock students who exhausted their test attempts</p>
                </div>
            </div>
            <a href="manage-tests.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Back to Tests</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <?php if (!$systemReady): ?>
                <section class="glass-card rounded-2xl p-5 border border-rose-500/25 bg-rose-500/10">
                    <h3 class="font-display text-lg font-bold text-white">System not configured</h3>
                    <p class="mt-2 text-sm text-rose-100">Run the `sql/add_test_attempt_restrictions.sql` migration before using this admin screen.</p>
                </section>
            <?php else: ?>
                <section class="glass-card rounded-2xl p-4 sm:p-5">
                    <form method="get" class="flex flex-col gap-3 sm:flex-row">
                        <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search student, email, or test..." class="flex-1 rounded-xl bg-slate-900/50 border border-slate-700 px-3 py-2.5 text-slate-200 placeholder-slate-500 outline-none focus:border-indigo-400">
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/30 transition-all duration-300">Search</button>
                        <a href="test-attempt-locks.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Reset</a>
                    </form>
                </section>

                <section class="glass-card rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-slate-500 text-xs">
                            <tr>
                                <th class="text-left font-medium px-4 py-3">Student</th>
                                <th class="text-left font-medium px-4 py-3">Test</th>
                                <th class="text-left font-medium px-4 py-3">Attempts</th>
                                <th class="text-left font-medium px-4 py-3">Last Attempt</th>
                                <th class="text-right font-medium px-4 py-3">Action</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                            <?php if ($records === []): ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No locked test attempts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-200"><?php echo e($row['student_name'] ?? 'Student'); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo e($row['student_email'] ?? ''); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-300"><?php echo e($row['test_title'] ?? 'Test'); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full border border-rose-500/25 bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200">
                                                <?php echo (int) ($row['attempts'] ?? 0); ?> / <?php echo (int) ($row['max_attempts'] ?? 3); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-400"><?php echo e(format_datetime_label((string) ($row['last_attempt_at'] ?? ''))); ?></td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="post" action="unlock_test.php" class="inline-flex">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) ($row['user_id'] ?? 0); ?>">
                                                <input type="hidden" name="test_id" value="<?php echo (int) ($row['test_id'] ?? 0); ?>">
                                                <button type="submit" class="px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/25 transition-all duration-300">Unlock Test</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    <?php if (is_array($toast) && ($toast['message'] ?? '') !== ''): ?>
    (function () {
        var toast = document.getElementById('toast');
        var type = <?php echo json_encode((string) ($toast['type'] ?? 'info')); ?>;
        var message = <?php echo json_encode((string) ($toast['message'] ?? '')); ?>;
        var classes = {
            success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
            error: 'bg-rose-500/15 border-rose-500/30 text-rose-300',
            info: 'bg-indigo-500/15 border-indigo-500/30 text-indigo-300'
        };
        toast.textContent = message;
        toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border ' + (classes[type] || classes.info);
        toast.classList.remove('hidden');
        setTimeout(function () { toast.classList.add('hidden'); }, 4000);
    }());
    <?php endif; ?>
</script>
</body>
</html>
