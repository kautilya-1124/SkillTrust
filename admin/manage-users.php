<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';




if (!isset($conn) || !$conn) {
    die("Database connection error");
}

$currentPage = 'manage-users.php';
$pageTitle = 'Manage Students';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

// Student counts from `users` table (full table â€” not affected by search/filter on the list below)
$countAllUsers = 0;
$countActiveUsers = 0;
$countBlockedUsers = 0;
$resStats = $conn->query(
    'SELECT
        COUNT(*) AS total_all,
        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(status, \'\'))) = \'blocked\' THEN 1 ELSE 0 END), 0) AS total_blocked,
        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(status, \'\'))) <> \'blocked\' THEN 1 ELSE 0 END), 0) AS total_active
     FROM users'
);
if ($resStats) {
    $rowS = $resStats->fetch_assoc();
    $resStats->free();
    if ($rowS) {
        $countAllUsers = (int) ($rowS['total_all'] ?? 0);
        $countBlockedUsers = (int) ($rowS['total_blocked'] ?? 0);
        $countActiveUsers = (int) ($rowS['total_active'] ?? 0);
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
if ($statusFilter === 'active') {
    $where[] = "LOWER(TRIM(COALESCE(u.status,'active'))) != 'blocked'";
}
if ($statusFilter === 'blocked') {
    $where[] = "LOWER(TRIM(COALESCE(u.status,'active'))) = 'blocked'";
}
$whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$countSql = 'SELECT COUNT(*) AS c FROM users u' . $whereSql;
$cstmt = $conn->prepare($countSql);
if (!$cstmt) {
    die("Count Query Error: " . $conn->error);
}
if ($types !== '') {
    $bind = [$types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$cstmt, 'bind_param'], $bind);
}
$cstmt->execute();
$total = (int) (($cstmt->get_result()->fetch_assoc()['c'] ?? 0));
$cstmt->close();

$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$hasResultsTable = false;
$tr = $conn->query("SHOW TABLES LIKE 'results'");
if ($tr) {
    $hasResultsTable = $tr->num_rows > 0;
    $tr->free();
}

$users = [];
$attemptsSelect = $hasResultsTable
    ? '(SELECT COUNT(*) FROM results r WHERE r.user_id = u.id) AS attempts_count'
    : '0 AS attempts_count';
$listSql = '
    SELECT u.id, u.name, u.email, u.status, ' . $attemptsSelect . '
    FROM users u
' . $whereSql . '
    ORDER BY u.id DESC
    LIMIT ? OFFSET ?';
$stmt = $conn->prepare($listSql);
if (!$stmt) {
    die("Main Query Error: " . $conn->error);
}
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind = [$listTypes];
    foreach ($listParams as $k => $v) {
        $bind[] = &$listParams[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

$baseQuery = ['q' => $q, 'status' => $statusFilter];
$qsForReturn = http_build_query(array_filter($baseQuery, static fn($v) => $v !== '' && $v !== 'all'));

$linkAll = 'manage-users.php?' . http_build_query(array_filter(['q' => $q, 'status' => 'all'], static fn($v) => $v !== ''));
$linkActive = 'manage-users.php?' . http_build_query(array_filter(['q' => $q, 'status' => 'active'], static fn($v) => $v !== ''));
$linkBlocked = 'manage-users.php?' . http_build_query(array_filter(['q' => $q, 'status' => 'blocked'], static fn($v) => $v !== ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/manage-users.css">
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
                    <h2 class="font-display font-bold text-white text-lg">Manage Students</h2>
                    <p class="text-xs text-slate-500">Search, filter, block or remove student accounts</p>
                </div>
            </div>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="<?php echo e($linkAll); ?>" class="glass-card rounded-2xl p-5 block hover:border-indigo-500/30 transition-all duration-300 <?php echo $statusFilter === 'all' ? 'ring-1 ring-indigo-500/40' : ''; ?>">
                    <p class="text-xs uppercase tracking-wider text-slate-500">All students</p>
                    <p class="font-display font-extrabold text-3xl text-white mt-1"><?php echo (int) $countAllUsers; ?></p>
                    <p class="text-xs text-slate-500 mt-2">All registered students</p>
                </a>
                <a href="<?php echo e($linkActive); ?>" class="glass-card rounded-2xl p-5 block hover:border-emerald-500/30 transition-all duration-300 <?php echo $statusFilter === 'active' ? 'ring-1 ring-emerald-500/40' : ''; ?>">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Active</p>
                    <p class="font-display font-extrabold text-3xl text-emerald-300 mt-1"><?php echo (int) $countActiveUsers; ?></p>
                    <p class="text-xs text-slate-500 mt-2">Not blocked</p>
                </a>
                <a href="<?php echo e($linkBlocked); ?>" class="glass-card rounded-2xl p-5 block hover:border-rose-500/30 transition-all duration-300 <?php echo $statusFilter === 'blocked' ? 'ring-1 ring-rose-500/40' : ''; ?>">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Blocked</p>
                    <p class="font-display font-extrabold text-3xl text-rose-300 mt-1"><?php echo (int) $countBlockedUsers; ?></p>
                    <p class="text-xs text-slate-500 mt-2">Cannot sign in / access</p>
                </a>
            </section>

            <section class="glass-card rounded-2xl p-4 sm:p-5">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input class="field md:col-span-2" type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search by name or email...">
                    <select class="field" name="status">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                    <div class="flex flex-wrap gap-2 items-center">
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/30 transition-all duration-300">Apply</button>
                        <a href="manage-users.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Reset</a>
                        <span class="text-xs text-slate-500 ml-auto">Total: <?php echo (int) $total; ?></span>
                    </div>
                </form>
            </section>

            <section class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs">
                        <tr>
                            <th class="text-left font-medium px-4 py-3">Student</th>
                            <th class="text-left font-medium px-4 py-3">Email</th>
                            <th class="text-left font-medium px-4 py-3">Status</th>
                            <th class="text-left font-medium px-4 py-3">Test attempts</th>
                            <th class="text-right font-medium px-4 py-3">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                        <?php if ($users === []): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No students found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u):
                                $st = strtolower((string) ($u['status'] ?? 'active'));
                                $attempts = (int) ($u['attempts_count'] ?? 0);
                                ?>
                                <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                    <td class="px-4 py-3 text-slate-200 font-medium"><?php echo e((string) ($u['name'] ?? '')); ?></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo e((string) ($u['email'] ?? '')); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $st === 'blocked' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25' : 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'; ?>">
                                            <?php echo $st === 'blocked' ? 'Blocked' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo $attempts; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2 flex-wrap">
                                            <form method="post" action="actions/manage-users-action.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="toggle_block">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn !== '' ? '&' : '') . 'page=' . $page); ?>">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-500/15 border border-amber-500/30 text-amber-300 hover:bg-amber-500/25 transition-all duration-300"><?php echo $st === 'blocked' ? 'Unblock' : 'Block'; ?></button>
                                            </form>
                                            <form method="post" action="actions/manage-users-action.php" onsubmit="return confirm('Delete this student and their test results? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn !== '' ? '&' : '') . 'page=' . $page); ?>">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 border border-rose-500/30 text-rose-300 hover:bg-rose-500/25 transition-all duration-300">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-700/40 flex items-center justify-between">
                    <p class="text-xs text-slate-500">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
                    <?php
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                    $qp = array_filter(array_merge($baseQuery, ['page' => (string) $prev]), static fn($v) => $v !== '' && $v !== 'all');
                    $qn = array_filter(array_merge($baseQuery, ['page' => (string) $next]), static fn($v) => $v !== '' && $v !== 'all');
                    ?>
                    <div class="flex gap-2">
                        <a href="?<?php echo e(http_build_query($qp)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 hover:bg-slate-700 transition-all duration-300 <?php echo $page <= 1 ? 'pointer-events-none opacity-40' : ''; ?>">Prev</a>
                        <a href="?<?php echo e(http_build_query($qn)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 hover:bg-slate-700 transition-all duration-300 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-40' : ''; ?>">Next</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script type="application/json" id="adminManageUsersData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script src="../assets/js/admin/manage-users.js"></script>
</body>
</html>
