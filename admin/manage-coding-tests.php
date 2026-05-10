<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'manage-coding-tests.php';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$difficulty = strtolower(trim((string) ($_GET['difficulty'] ?? 'all')));
if (!in_array($difficulty, ['all', 'easy', 'medium', 'hard'], true)) {
    $difficulty = 'all';
}

$problemStatementColumn = db_column_exists($conn, 'coding_questions', 'problem_statement') ? 'problem_statement' : 'question';
$timeLimitColumn = db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds';
$memoryLimitColumn = db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb';

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR category LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($difficulty !== 'all') {
    $where[] = 'LOWER(difficulty) = ?';
    $types .= 's';
    $params[] = $difficulty;
}
$whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

$questions = [];
$sql = 'SELECT id, title, category, difficulty, ' . $problemStatementColumn . ' AS problem_statement, '
    . $timeLimitColumn . ' AS time_limit, ' . $memoryLimitColumn . ' AS memory_limit, created_at
       FROM coding_questions' . $whereSql . '
       ORDER BY id DESC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $questions = db_fetch_all($stmt);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coding Questions - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/manage-tests.css">
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
                    <h2 class="font-display font-bold text-white text-lg">Coding Questions</h2>
                    <p class="text-xs text-slate-500">Manage admin-created coding problems</p>
                </div>
            </div>
            <a href="create-coding-question.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-indigo-500 to-violet-500 text-white hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300">+ Create Coding Question</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
            <section class="glass-card rounded-2xl p-4 sm:p-5">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search title or category..." class="md:col-span-3 w-full px-3 py-2.5 rounded-xl bg-slate-900/50 border border-slate-700 text-slate-200 placeholder-slate-500 outline-none focus:border-indigo-400">
                    <select name="difficulty" class="w-full px-3 py-2.5 rounded-xl bg-slate-900/50 border border-slate-700 text-slate-200 outline-none focus:border-indigo-400">
                        <option value="all" <?php echo $difficulty === 'all' ? 'selected' : ''; ?>>All Difficulties</option>
                        <option value="easy" <?php echo $difficulty === 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $difficulty === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $difficulty === 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/30 transition-all duration-300">Apply Filters</button>
                        <a href="manage-coding-tests.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Reset</a>
                        <span class="ml-auto text-xs text-slate-500 self-center">Total: <?php echo count($questions); ?></span>
                    </div>
                </form>
            </section>

            <section class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs">
                        <tr>
                            <th class="text-left font-medium px-4 py-3">Title</th>
                            <th class="text-left font-medium px-4 py-3">Category</th>
                            <th class="text-left font-medium px-4 py-3">Difficulty</th>
                            <th class="text-left font-medium px-4 py-3">Limits</th>
                            <th class="text-left font-medium px-4 py-3">Preview</th>
                            <th class="text-right font-medium px-4 py-3">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                        <?php if ($questions === []): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No coding questions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($questions as $question):
                                $d = strtolower((string) ($question['difficulty'] ?? 'medium'));
                                $dClass = $d === 'easy'
                                    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'
                                    : ($d === 'hard'
                                        ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25'
                                        : 'bg-amber-500/15 text-amber-300 border border-amber-500/25');
                            ?>
                                <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                    <td class="px-4 py-3 font-semibold text-slate-200"><?php echo e((string) ($question['title'] ?? '')); ?></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo e((string) ($question['category'] ?? '')); ?></td>
                                    <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $dClass; ?>"><?php echo e(ucfirst($d)); ?></span></td>
                                    <td class="px-4 py-3 text-slate-400"><?php echo e((string) ($question['time_limit'] ?? '2')); ?>s / <?php echo e((string) ($question['memory_limit'] ?? '262144')); ?></td>
                                    <td class="px-4 py-3 text-slate-400 max-w-sm truncate"><?php echo e((string) ($question['problem_statement'] ?? '')); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="create-coding-question.php?edit=<?php echo (int) ($question['id'] ?? 0); ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/25 transition-all duration-300">Edit</a>
                                            <form method="post" action="actions/coding-question-action.php" onsubmit="return confirm('Delete this coding question?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="question_id" value="<?php echo (int) ($question['id'] ?? 0); ?>">
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
            </section>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('active');
    }

    const toast = document.getElementById('toast');
    <?php if ($toastMsg !== ''): ?>
    toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border <?php echo $toastType === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-300'; ?>';
    toast.textContent = <?php echo json_encode($toastMsg); ?>;
    toast.classList.remove('hidden');
    setTimeout(function () { toast.classList.add('hidden'); }, 3200);
    <?php endif; ?>
</script>
</body>
</html>
