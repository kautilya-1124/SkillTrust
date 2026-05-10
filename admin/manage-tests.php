<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'manage-tests.php';
$pageTitle = 'Manage Tests';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

$q = trim((string) ($_GET['q'] ?? ''));
$difficulty = strtolower(trim((string) ($_GET['difficulty'] ?? 'all')));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'latest')));
$allowedDifficulty = ['all', 'easy', 'medium', 'hard'];
if (!in_array($difficulty, $allowedDifficulty, true)) {
    $difficulty = 'all';
}
$allowedSort = ['latest', 'oldest'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'latest';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = 't.title LIKE ?';
    $types .= 's';
    $params[] = '%' . $q . '%';
}
if ($difficulty !== 'all') {
    $where[] = 'LOWER(t.difficulty) = ?';
    $types .= 's';
    $params[] = $difficulty;
}
$whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';
$orderSql = $sort === 'oldest' ? ' ORDER BY t.id ASC ' : ' ORDER BY t.id DESC ';

$totalRows = 0;
$countSql = 'SELECT COUNT(*) AS c FROM tests t' . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$countStmt, 'bind_param'], $bind);
    }
    $countStmt->execute();
    $totalRows = (int) (($countStmt->get_result()->fetch_assoc()['c'] ?? 0));
    $countStmt->close();
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$tests = [];
$listSql = '
    SELECT
        t.id,
        t.title,
        t.duration,
        t.difficulty,
        t.featured,
        t.category,
        t.passing_score,
        (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS total_questions,
        (SELECT COUNT(*) FROM results r WHERE r.test_id = t.id) AS total_attempts
    FROM tests t
' . $whereSql . $orderSql . '
    LIMIT ? OFFSET ?';
$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind = [$listTypes];
    foreach ($listParams as $k => $v) {
        $bind[] = &$listParams[$k];
    }
    call_user_func_array([$listStmt, 'bind_param'], $bind);
    $listStmt->execute();
    $res = $listStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tests[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'duration' => (int) ($row['duration'] ?? 0),
            'difficulty' => strtolower((string) ($row['difficulty'] ?? 'medium')),
            'featured' => (int) ($row['featured'] ?? 0),
            'category' => (string) ($row['category'] ?? ''),
            'passing_score' => (int) ($row['passing_score'] ?? 0),
            'total_questions' => (int) ($row['total_questions'] ?? 0),
            'total_attempts' => (int) ($row['total_attempts'] ?? 0),
        ];
    }
    $listStmt->close();
}

$baseQuery = [
    'q' => $q,
    'difficulty' => $difficulty,
    'sort' => $sort,
];
$qsForReturn = http_build_query(array_filter($baseQuery, static fn($v) => $v !== '' && $v !== 'all'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tests - SkillTrust Admin</title>
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
                    <h2 class="font-display font-bold text-white text-lg">Manage Tests</h2>
                    <p class="text-xs text-slate-500">Search, filter, and manage tests</p>
                </div>
            </div>
            <a href="create-test.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-indigo-500 to-violet-500 text-white hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300">+ Create Test</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <section class="glass-card rounded-2xl p-4 sm:p-5">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search by title..." class="md:col-span-2 w-full px-3 py-2.5 rounded-xl bg-slate-900/50 border border-slate-700 text-slate-200 placeholder-slate-500 outline-none focus:border-indigo-400">
                    <select name="difficulty" class="w-full px-3 py-2.5 rounded-xl bg-slate-900/50 border border-slate-700 text-slate-200 outline-none focus:border-indigo-400">
                        <option value="all" <?php echo $difficulty === 'all' ? 'selected' : ''; ?>>All Difficulties</option>
                        <option value="easy" <?php echo $difficulty === 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $difficulty === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $difficulty === 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                    <select name="sort" class="w-full px-3 py-2.5 rounded-xl bg-slate-900/50 border border-slate-700 text-slate-200 outline-none focus:border-indigo-400">
                        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/30 transition-all duration-300">Apply Filters</button>
                        <a href="manage-tests.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Reset</a>
                        <span class="ml-auto text-xs text-slate-500 self-center">Total: <?php echo (int) $totalRows; ?></span>
                    </div>
                </form>
            </section>

            <section class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs">
                        <tr>
                            <th class="text-left font-medium px-4 py-3">Test Title</th>
                            <th class="text-left font-medium px-4 py-3">Category</th>
                            <th class="text-left font-medium px-4 py-3">Difficulty</th>
                            <th class="text-left font-medium px-4 py-3">Duration</th>
                            <th class="text-left font-medium px-4 py-3">Passing Score</th>
                            <th class="text-left font-medium px-4 py-3">Total Questions</th>
                            <th class="text-left font-medium px-4 py-3">Total Attempts</th>
                            <th class="text-right font-medium px-4 py-3">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                        <?php if ($tests === []): ?>
                            <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No tests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tests as $test):
                                $d = $test['difficulty'];
                                $dClass = $d === 'easy'
                                    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'
                                    : ($d === 'hard'
                                        ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25'
                                        : 'bg-amber-500/15 text-amber-300 border border-amber-500/25');
                            ?>
                                <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-200"><?php echo e($test['title']); ?></div>
                                        <?php if ((int) $test['featured'] === 1): ?>
                                            <div class="text-xs text-fuchsia-300 mt-1">Featured</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo e($test['category']); ?></td>
                                    <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $dClass; ?>"><?php echo e(ucfirst($d)); ?></span></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $test['duration']; ?> min</td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $test['passing_score']; ?></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $test['total_questions']; ?></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo (int) $test['total_attempts']; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="create-test.php?edit=<?php echo (int) $test['id']; ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/25 transition-all duration-300">Edit</a>
                                            <form method="post" action="actions/manage-tests-action.php" onsubmit="return confirm('Delete this test? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_test">
                                                <input type="hidden" name="test_id" value="<?php echo (int) $test['id']; ?>">
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
                    <div class="flex gap-2">
                        <?php
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                        $qsPrev = $baseQuery;
                        $qsPrev['page'] = (string) $prev;
                        $qsNext = $baseQuery;
                        $qsNext['page'] = (string) $next;
                        ?>
                        <a href="?<?php echo e(http_build_query(array_filter($qsPrev, static function ($v) { return $v !== '' && $v !== 'all'; }))); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 hover:bg-slate-700 transition-all duration-300 <?php echo $page <= 1 ? 'pointer-events-none opacity-40' : ''; ?>">Prev</a>
                        <a href="?<?php echo e(http_build_query(array_filter($qsNext, static function ($v) { return $v !== '' && $v !== 'all'; }))); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 hover:bg-slate-700 transition-all duration-300 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-40' : ''; ?>">Next</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script type="application/json" id="adminManageTestsData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script src="../assets/js/admin/manage-tests.js"></script>
</body>
</html>
