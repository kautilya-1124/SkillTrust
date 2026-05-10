<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/test_helpers.php';

$currentPage = 'tests-list.php';
$pageTitle = 'Tests List';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

$q = trim((string) ($_GET['q'] ?? ''));
$difficultyFilter = strtolower(trim((string) ($_GET['difficulty'] ?? 'all')));
$allowedDifficulty = ['all', 'easy', 'medium', 'hard'];
if (!in_array($difficultyFilter, $allowedDifficulty, true)) {
    $difficultyFilter = 'all';
}
$viewId = (int) ($_GET['view'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(t.title LIKE ?)';
    $types .= 's';
    $params[] = '%' . $q . '%';
}
if ($difficultyFilter !== 'all') {
    $where[] = 'LOWER(t.difficulty) = ?';
    $types .= 's';
    $params[] = $difficultyFilter;
}
$whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';

$totalRows = 0;
$countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM tests t' . $whereSql);
if ($countStmt) {
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
        call_user_func_array([$countStmt, 'bind_param'], $bind);
    }
    $countStmt->execute();
    $row = $countStmt->get_result()->fetch_assoc();
    $totalRows = (int) ($row['c'] ?? 0);
    $countStmt->close();
}
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$tests = [];
$sql = '
    SELECT t.id, t.title, t.duration, t.difficulty, t.featured, t.category, t.passing_score
    FROM tests t
' . $whereSql . '
    ORDER BY t.id DESC
    LIMIT ? OFFSET ?';
$listStmt = $conn->prepare($sql);
if ($listStmt) {
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind = [$listTypes];
    foreach ($listParams as $k => $v) { $bind[] = &$listParams[$k]; }
    call_user_func_array([$listStmt, 'bind_param'], $bind);
    $listStmt->execute();
    $res = $listStmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $tests[] = [
            'id' => (int) ($r['id'] ?? 0),
            'title' => (string) ($r['title'] ?? ''),
            'category' => (string) ($r['category'] ?? ''),
            'duration' => (int) ($r['duration'] ?? 0),
            'difficulty' => strtolower((string) ($r['difficulty'] ?? 'medium')),
            'featured' => (int) ($r['featured'] ?? 0),
            'passing_score' => (int) ($r['passing_score'] ?? 0),
        ];
    }
    $listStmt->close();
}

$hasSchedule = has_column($conn, 'tests', 'start_datetime') && has_column($conn, 'tests', 'expiry_datetime');
$runningTests = [];
$expiredTests = [];
if ($hasSchedule) {
    $runningRes = $conn->query(
        'SELECT id, title, category, duration FROM tests
         WHERE start_datetime <= NOW() AND expiry_datetime >= NOW()
         ORDER BY id DESC LIMIT 8'
    );
    if ($runningRes) {
        while ($row = $runningRes->fetch_assoc()) { $runningTests[] = $row; }
        $runningRes->free();
    }
    $expiredRes = $conn->query(
        'SELECT id, title, category, duration FROM tests
         WHERE expiry_datetime < NOW()
         ORDER BY id DESC LIMIT 8'
    );
    if ($expiredRes) {
        while ($row = $expiredRes->fetch_assoc()) { $expiredTests[] = $row; }
        $expiredRes->free();
    }
} else {
    $runningTests = $tests;
}

$viewTest = null;
if ($viewId > 0) {
    $vStmt = $conn->prepare(
        'SELECT id, title, duration, difficulty, featured, category, passing_score, start_datetime, expiry_datetime
         FROM tests
         WHERE id = ?
         LIMIT 1'
    );
    if ($vStmt) {
        $vStmt->bind_param('i', $viewId);
        $vStmt->execute();
        $row = $vStmt->get_result()->fetch_assoc();
        $vStmt->close();
        if ($row) {
            $viewTest = $row;
        }
    }
}

$baseQuery = ['q' => $q, 'difficulty' => $difficultyFilter];
$qsForReturn = http_build_query(array_filter($baseQuery, static function ($v) { return $v !== '' && $v !== 'all'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests List - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/tests-list.css">
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
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">&#9776;</button>
                <div><h2 class="font-display font-bold text-white text-lg">Tests List</h2><p class="text-xs text-slate-500">Browse all tests and running/expired sections</p></div>
            </div>
            <a href="create-test.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-indigo-500 to-violet-500 text-white">+ New Test</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <section class="glass-card rounded-2xl p-4">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input class="field md:col-span-2" type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search by test title...">
                    <select class="field" name="difficulty">
                        <option value="all" <?php echo $difficultyFilter === 'all' ? 'selected' : ''; ?>>All Difficulty</option>
                        <option value="easy" <?php echo $difficultyFilter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $difficultyFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $difficultyFilter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300">Apply</button>
                        <a href="tests-list.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300">Reset</a>
                        <span class="ml-auto text-xs text-slate-500 self-center">Total: <?php echo (int) $totalRows; ?></span>
                    </div>
                </form>
            </section>

            <section class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs">
                        <tr>
                            <th class="text-left font-medium px-4 py-3">Test</th>
                            <th class="text-left font-medium px-4 py-3">Category</th>
                            <th class="text-left font-medium px-4 py-3">Difficulty</th>
                            <th class="text-left font-medium px-4 py-3">Duration</th>
                            <th class="text-left font-medium px-4 py-3">Passing Score</th>
                            <th class="text-right font-medium px-4 py-3">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                        <?php if ($tests === []): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No tests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tests as $t):
                                $d = $t['difficulty'];
                                $dBadge = $d === 'easy'
                                    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'
                                    : ($d === 'hard'
                                        ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25'
                                        : 'bg-amber-500/15 text-amber-300 border border-amber-500/25');
                            ?>
                                <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-200"><?php echo e($t['title']); ?></div>
                                        <?php if ((int) $t['featured'] === 1): ?>
                                            <div class="text-xs text-fuchsia-300">Featured</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo e($t['category']); ?></td>
                                    <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $dBadge; ?>"><?php echo e(ucfirst($d)); ?></span></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $t['duration']; ?> min</td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $t['passing_score']; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="?<?php echo e(http_build_query(array_filter(array_merge($baseQuery, ['page' => (string) $page, 'view' => (string) $t['id']]), static function ($v) { return $v !== '' && $v !== 'all'; }))); ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-700/80 border border-slate-600 text-slate-300">View</a>
                                            <a href="create-test.php?edit=<?php echo (int) $t['id']; ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 border border-indigo-500/30 text-indigo-300">Edit</a>
                                            <form method="post" action="actions/manage-tests-action.php" onsubmit="return confirm('Delete this test?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_test">
                                                <input type="hidden" name="test_id" value="<?php echo (int) $t['id']; ?>">
                                                <input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn !== '' ? '&' : '') . 'page=' . $page); ?>">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 border border-rose-500/30 text-rose-300">Delete</button>
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
                    <div class="flex gap-2">
                        <?php
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                        $qsPrev = array_filter(array_merge($baseQuery, ['page' => (string) $prev]), static function ($v) { return $v !== '' && $v !== 'all'; });
                        $qsNext = array_filter(array_merge($baseQuery, ['page' => (string) $next]), static function ($v) { return $v !== '' && $v !== 'all'; });
                        ?>
                        <a href="?<?php echo e(http_build_query($qsPrev)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 <?php echo $page <= 1 ? 'pointer-events-none opacity-40' : ''; ?>">Prev</a>
                        <a href="?<?php echo e(http_build_query($qsNext)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-40' : ''; ?>">Next</a>
                    </div>
                </div>
            </section>

            <section class="glass-card rounded-2xl p-5">
                <h3 class="font-display font-bold text-white text-lg mb-3">Current Running Tests</h3>
                <?php if ($runningTests === []): ?>
                    <p class="text-sm text-slate-500">No running tests found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($runningTests as $rt): ?>
                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3">
                                <div class="text-slate-100 font-semibold"><?php echo e((string) ($rt['title'] ?? 'Untitled')); ?></div>
                                <div class="text-xs text-slate-400 mt-1"><?php echo e((string) ($rt['category'] ?? '')); ?> â€¢ <?php echo (int) ($rt['duration'] ?? 0); ?> min</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!$hasSchedule): ?>
                    <p class="text-xs text-amber-300 mt-3">Schedule columns are missing in `tests`, so all listed tests are treated as running.</p>
                <?php endif; ?>
            </section>

            <section class="glass-card rounded-2xl p-5">
                <h3 class="font-display font-bold text-white text-lg mb-3">Expired Tests</h3>
                <?php if ($expiredTests === []): ?>
                    <p class="text-sm text-slate-500">No expired tests found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($expiredTests as $et): ?>
                            <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3">
                                <div class="text-slate-100 font-semibold"><?php echo e((string) ($et['title'] ?? 'Untitled')); ?></div>
                                <div class="text-xs text-slate-400 mt-1"><?php echo e((string) ($et['category'] ?? '')); ?> â€¢ <?php echo (int) ($et['duration'] ?? 0); ?> min</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!$hasSchedule): ?>
                    <p class="text-xs text-amber-300 mt-3">Expired detection needs `start_datetime` and `expiry_datetime` columns in `tests`.</p>
                <?php endif; ?>
            </section>

            <?php if ($viewTest !== null): ?>
                <section class="glass-card rounded-2xl p-5">
                    <h3 class="font-display font-bold text-white text-lg mb-3">Test Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div><span class="text-slate-500">Name:</span> <span class="text-slate-200"><?php echo e((string) $viewTest['title']); ?></span></div>
                        <div><span class="text-slate-500">Category:</span> <span class="text-slate-200"><?php echo e((string) ($viewTest['category'] ?? '')); ?></span></div>
                        <div><span class="text-slate-500">Duration:</span> <span class="text-slate-200"><?php echo (int) ($viewTest['duration'] ?? 0); ?> min</span></div>
                        <div><span class="text-slate-500">Passing Score:</span> <span class="text-slate-200"><?php echo (int) ($viewTest['passing_score'] ?? 0); ?></span></div>
                        <div><span class="text-slate-500">Difficulty:</span> <span class="text-slate-200"><?php echo e(ucfirst((string) ($viewTest['difficulty'] ?? 'medium'))); ?></span></div>
                        <div><span class="text-slate-500">Featured:</span> <span class="text-slate-200"><?php echo ((int) ($viewTest['featured'] ?? 0) === 1) ? 'Yes' : 'No'; ?></span></div>
                        <?php if (!empty($viewTest['start_datetime']) || !empty($viewTest['expiry_datetime'])): ?>
                            <div><span class="text-slate-500">Start:</span> <span class="text-slate-200"><?php echo e((string) ($viewTest['start_datetime'] ?? '')); ?></span></div>
                            <div><span class="text-slate-500">Expiry:</span> <span class="text-slate-200"><?php echo e((string) ($viewTest['expiry_datetime'] ?? '')); ?></span></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>
<script type="application/json" id="adminTestsListData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script src="../assets/js/admin/tests-list.js"></script>
</body>
</html>
