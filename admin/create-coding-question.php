<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'create-coding-question.php';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$isEditMode = false;

$problemStatementColumn = db_column_exists($conn, 'coding_questions', 'problem_statement') ? 'problem_statement' : 'question';
$timeLimitColumn = db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds';
$memoryLimitColumn = db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb';
$inputFormatColumn = db_column_exists($conn, 'coding_questions', 'input_format');
$outputFormatColumn = db_column_exists($conn, 'coding_questions', 'output_format');
$sampleInputColumn = db_column_exists($conn, 'coding_questions', 'sample_input');
$sampleOutputColumn = db_column_exists($conn, 'coding_questions', 'sample_output');
$allowedLanguagesColumn = db_column_exists($conn, 'coding_questions', 'allowed_languages');

$form = [
    'title' => '',
    'problem_statement' => '',
    'category' => 'Programming',
    'difficulty' => 'medium',
    'time_limit' => '2',
    'memory_limit' => '262144',
    'allowed_languages' => ['php', 'python', 'cpp'],
    'input_format' => '',
    'output_format' => '',
    'sample_input' => '',
    'sample_output' => '',
];

$difficultyOptions = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
$languageOptions = [
    'php' => 'PHP',
    'python' => 'Python',
    'cpp' => 'C++',
    'java' => 'Java',
    'javascript' => 'JavaScript',
    'c' => 'C',
];

if ($editId > 0) {
    $selectColumns = [
        'id',
        'title',
        $problemStatementColumn . ' AS problem_statement',
        'category',
        'difficulty',
        $timeLimitColumn . ' AS time_limit',
        $memoryLimitColumn . ' AS memory_limit',
    ];
    if ($allowedLanguagesColumn) {
        $selectColumns[] = 'allowed_languages';
    }
    if ($inputFormatColumn) {
        $selectColumns[] = 'input_format';
    }
    if ($outputFormatColumn) {
        $selectColumns[] = 'output_format';
    }
    if ($sampleInputColumn) {
        $selectColumns[] = 'sample_input';
    }
    if ($sampleOutputColumn) {
        $selectColumns[] = 'sample_output';
    }

    $stmt = $conn->prepare(
        'SELECT ' . implode(', ', $selectColumns) . '
         FROM coding_questions
         WHERE id = ?
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $row = db_fetch_one($stmt);
        $stmt->close();

        if ($row) {
            $isEditMode = true;
            $form['title'] = (string) ($row['title'] ?? '');
            $form['problem_statement'] = (string) ($row['problem_statement'] ?? '');
            $form['category'] = (string) ($row['category'] ?? 'Programming');
            $form['difficulty'] = strtolower((string) ($row['difficulty'] ?? 'medium'));
            $form['time_limit'] = (string) ($row['time_limit'] ?? '2');
            $form['memory_limit'] = (string) ($row['memory_limit'] ?? '262144');
            $form['input_format'] = (string) ($row['input_format'] ?? '');
            $form['output_format'] = (string) ($row['output_format'] ?? '');
            $form['sample_input'] = (string) ($row['sample_input'] ?? '');
            $form['sample_output'] = (string) ($row['sample_output'] ?? '');

            $languages = json_decode((string) ($row['allowed_languages'] ?? '[]'), true);
            if (is_array($languages) && $languages !== []) {
                $form['allowed_languages'] = array_values(array_filter(array_map('strval', $languages)));
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
    <title><?php echo $isEditMode ? 'Edit Coding Question' : 'Create Coding Question'; ?> - SkillTrust Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/create-test.css">
</head>
<body class="text-slate-300">
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] max-w-md rounded-xl border px-4 py-3 text-sm font-semibold"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="flex min-h-screen">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">&#9776;</button>
                <div>
                    <h2 class="font-display font-bold text-white text-lg"><?php echo $isEditMode ? 'Edit Coding Question' : 'Create Coding Question'; ?></h2>
                    <p class="text-xs text-slate-500">Create production-ready coding problems with AJAX save flow</p>
                </div>
            </div>
            <a href="manage-coding-tests.php" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 transition-all duration-300">Back to Coding Questions</a>
        </header>

        <main class="flex-1 px-3 sm:px-4 lg:px-8 py-6">
            <section class="glass-card rounded-2xl p-5 sm:p-6">
                <form id="questionForm" method="post" action="insert-question.php" class="space-y-5" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="edit_id" value="<?php echo (int) $editId; ?>">
                    <input type="hidden" name="ajax" value="1">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Question Title</label>
                            <input class="field" type="text" name="title" required value="<?php echo e($form['title']); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Problem Statement</label>
                            <textarea class="field min-h-[220px]" name="problem_statement" required><?php echo e($form['problem_statement']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                            <input class="field" type="text" name="category" required value="<?php echo e($form['category']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Difficulty</label>
                            <select class="field" name="difficulty" required>
                                <?php foreach ($difficultyOptions as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>" <?php echo $form['difficulty'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Time Limit</label>
                            <input class="field" type="number" step="0.1" min="0.1" name="time_limit" required value="<?php echo e($form['time_limit']); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Memory Limit</label>
                            <input class="field" type="number" min="1024" name="memory_limit" required value="<?php echo e($form['memory_limit']); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Allowed Languages</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <?php foreach ($languageOptions as $value => $label): ?>
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                                        <input type="checkbox" name="allowed_languages[]" value="<?php echo e($value); ?>" class="accent-indigo-500" <?php echo in_array($value, $form['allowed_languages'], true) ? 'checked' : ''; ?>>
                                        <span class="text-sm text-slate-200"><?php echo e($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Input Format</label>
                            <textarea class="field min-h-[120px]" name="input_format" required><?php echo e($form['input_format']); ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Output Format</label>
                            <textarea class="field min-h-[120px]" name="output_format" required><?php echo e($form['output_format']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Sample Input</label>
                            <textarea class="field min-h-[120px] font-mono text-sm" name="sample_input" required><?php echo e($form['sample_input']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Sample Output</label>
                            <textarea class="field min-h-[120px] font-mono text-sm" name="sample_output" required><?php echo e($form['sample_output']); ?></textarea>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-violet-500 hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300">
                            <?php echo $isEditMode ? 'Update Coding Question' : 'Create Coding Question'; ?>
                        </button>
                        <a href="manage-coding-tests.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-600 text-slate-300 hover:bg-slate-800 transition-all duration-300">Cancel</a>
                    </div>
                </form>
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

    const toastNode = document.getElementById('toast');
    const form = document.getElementById('questionForm');

    function showToast(type, message) {
        const palette = type === 'success'
            ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
            : 'bg-rose-500/15 border-rose-500/30 text-rose-300';
        toastNode.className = 'fixed bottom-6 right-6 z-[100] max-w-md rounded-xl border px-4 py-3 text-sm font-semibold ' + palette;
        toastNode.textContent = message;
        toastNode.classList.remove('hidden');
        window.setTimeout(function () {
            toastNode.classList.add('hidden');
        }, 3600);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const fd = new FormData(form);
        const requiredNames = ['title', 'problem_statement', 'category', 'difficulty', 'time_limit', 'memory_limit', 'input_format', 'output_format', 'sample_input', 'sample_output'];
        for (const name of requiredNames) {
            if (!String(fd.get(name) || '').trim()) {
                showToast('error', 'Please fill all required fields.');
                return;
            }
        }

        if (!fd.getAll('allowed_languages[]').length) {
            showToast('error', 'Select at least one allowed language.');
            return;
        }

        try {
            const response = await fetch('insert-question.php', {
                method: 'POST',
                body: fd
            });

            const rawText = await response.text();
            let data;

            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                console.error('Invalid JSON response:', rawText);
                throw new Error('Server did not return valid JSON.');
            }

            if (!response.ok || data.status !== 'success') {
                showToast('error', data.message || data.error || 'Unable to save coding question.');
                if (data.sql_error) {
                    console.error(data.sql_error);
                }
                return;
            }

            showToast('success', data.message || 'Coding question saved successfully.');
            if (data.redirect) {
                window.setTimeout(function () {
                    window.location.href = data.redirect;
                }, 900);
            }
        } catch (error) {
            console.error(error);
            showToast('error', 'Request failed. Check console for details.');
        }
    });

    <?php if ($toastMsg !== ''): ?>
    showToast(<?php echo json_encode($toastType !== '' ? $toastType : 'error'); ?>, <?php echo json_encode($toastMsg); ?>);
    <?php endif; ?>
</script>
</body>
</html>
