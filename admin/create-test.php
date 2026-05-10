<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'create-test.php';
$pageTitle = 'Create Test';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

$form = [
    'title' => '',
    'duration' => '30',
    'difficulty' => 'medium',
    'featured' => '0',
    'category' => '',
    'passing_score' => '40',
    'start_datetime' => '',
    'expiry_datetime' => '',
];

// Category is VARCHAR in tests table; build dropdown from existing values with fallback options.
$categoryOptions = ['General', 'Aptitude', 'Technical', 'Programming'];
$catRes = $conn->query("SELECT DISTINCT category FROM tests WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $name = trim((string) ($row['category'] ?? ''));
        if ($name !== '' && !in_array($name, $categoryOptions, true)) {
            $categoryOptions[] = $name;
        }
    }
    $catRes->free();
}
sort($categoryOptions);

$editId = (int) ($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$isEditMode = false;

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sel = $conn->prepare(
        'SELECT id, title, duration, difficulty, featured, category, passing_score, start_datetime, expiry_datetime
         FROM tests WHERE id = ? LIMIT 1'
    );
    if ($sel) {
        $sel->bind_param('i', $editId);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        if ($row) {
            $isEditMode = true;
            $form['title'] = (string) ($row['title'] ?? '');
            $form['duration'] = (string) (int) ($row['duration'] ?? 30);
            $form['difficulty'] = strtolower((string) ($row['difficulty'] ?? 'medium'));
            $form['featured'] = ((int) ($row['featured'] ?? 0) === 1) ? '1' : '0';
            $form['category'] = trim((string) ($row['category'] ?? ''));
            $form['passing_score'] = (string) (int) ($row['passing_score'] ?? 0);
            $form['start_datetime'] = !empty($row['start_datetime'])
                ? date('Y-m-d\TH:i', strtotime((string) $row['start_datetime']))
                : '';
            $form['expiry_datetime'] = !empty($row['expiry_datetime'])
                ? date('Y-m-d\TH:i', strtotime((string) $row['expiry_datetime']))
                : '';
            if ($form['category'] !== '' && !in_array($form['category'], $categoryOptions, true)) {
                $categoryOptions[] = $form['category'];
                sort($categoryOptions);
            }
        }
    }
}

if ($form['start_datetime'] === '' && $form['expiry_datetime'] === '' && !$isEditMode) {
    $form['start_datetime'] = date('Y-m-d\TH:i', strtotime('+1 hour'));
    $form['expiry_datetime'] = date('Y-m-d\TH:i', strtotime('+7 days'));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/create-test.css">
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
                    <h2 class="font-display font-bold text-white text-lg">Create Test</h2>
                    <p class="text-xs text-slate-500">Create test and import CSV questions</p>
                </div>
            </div>
            <a href="manage-tests.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Back to Manage Tests</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6">
            <section class="glass-card rounded-2xl p-5 sm:p-6">
                <form method="post" action="actions/create-test-action.php" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="edit_id" value="<?php echo (int) $editId; ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5"><?php echo $isEditMode ? 'Edit Test ID' : 'Auto Test Code'; ?></label>
                            <input type="text" class="field opacity-70" value="<?php echo $isEditMode ? ('Editing #' . $editId) : 'Auto-increment ID'; ?>" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Test Title</label>
                            <input class="field" type="text" name="title" required value="<?php echo e($form['title']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                            <select class="field" name="category" required>
                                <option value="">Select category</option>
                                <?php foreach ($categoryOptions as $cat): ?>
                                    <option value="<?php echo e($cat); ?>" <?php echo $form['category'] === $cat ? 'selected' : ''; ?>>
                                        <?php echo e($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-slate-500 mt-2">Category options come from existing tests and fallback defaults.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Difficulty</label>
                            <select class="field" name="difficulty" required>
                                <option value="easy" <?php echo $form['difficulty'] === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $form['difficulty'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $form['difficulty'] === 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Duration (minutes)</label>
                            <input class="field" type="number" min="1" name="duration" required value="<?php echo e($form['duration']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Passing Score</label>
                            <input class="field" type="number" min="0" name="passing_score" required value="<?php echo e($form['passing_score']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Start (schedule)</label>
                            <input class="field" type="datetime-local" name="start_datetime" required value="<?php echo e($form['start_datetime']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Expiry (schedule)</label>
                            <input class="field" type="datetime-local" name="expiry_datetime" required value="<?php echo e($form['expiry_datetime']); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-slate-700/50 bg-slate-900/40 p-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="accent-indigo-500" name="featured" value="1" <?php echo $form['featured'] === '1' ? 'checked' : ''; ?>>
                                <span class="text-sm text-slate-300">Featured Test</span>
                            </label>
                            <p class="text-xs text-slate-500 mt-2">Enable to highlight this test in admin and student listings.</p>
                        </div>
                        <div class="rounded-xl border border-slate-700/50 bg-slate-900/40 p-4">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Questions CSV (optional)</label>
                            <input class="field" type="file" name="questions_csv" accept=".csv,text/csv">
                            <p class="text-xs text-slate-500 mt-2">Header: question, option1, option2, option3, option4, correct_option, difficulty</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-violet-500 hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300"><?php echo $isEditMode ? 'Update Test' : 'Create Test'; ?></button>
                        <a href="<?php echo $isEditMode ? 'tests-list.php' : 'manage-tests.php'; ?>" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-600 text-slate-300 hover:bg-slate-800 transition-all duration-300">Cancel</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<script type="application/json" id="adminCreateTestData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/admin/common-page.js"></script>
<script src="../assets/js/admin/create-test.js"></script>
</body>
</html>
