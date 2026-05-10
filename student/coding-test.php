<?php
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/judge0.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$questionId = (int) ($_GET['id'] ?? 0);
if ($questionId <= 0) {
    http_response_code(404);
    die('Coding question not found.');
}

$userId = (int) $_SESSION['user_id'];
$userStmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$studentName = 'Student';
$studentEmail = '';
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userRow = db_fetch_one($userStmt);
    $userStmt->close();
    $studentName = trim((string) ($userRow['name'] ?? $_SESSION['name'] ?? 'Student'));
    $studentEmail = (string) ($userRow['email'] ?? $_SESSION['email'] ?? '');
}

$nameParts = preg_split('/\s+/', $studentName, -1, PREG_SPLIT_NO_EMPTY);
$studentInitials = 'ST';
if (is_array($nameParts) && $nameParts !== []) {
    $studentInitials = count($nameParts) >= 2
        ? strtoupper(substr((string) $nameParts[0], 0, 1) . substr((string) $nameParts[1], 0, 1))
        : strtoupper(substr((string) $nameParts[0], 0, 2));
}

$questionStmt = $conn->prepare(
    'SELECT id, title, ' . (db_column_exists($conn, 'coding_questions', 'problem_statement') ? 'problem_statement' : 'question') . ' AS question,
            difficulty, category, starter_code, allowed_languages,
            ' . (db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds') . ' AS time_limit_seconds,
            ' . (db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb') . ' AS memory_limit_kb
     FROM coding_questions
     WHERE id = ? AND ' . (db_column_exists($conn, 'coding_questions', 'status') ? "status = 'active'" : 'is_active = 1') . '
     LIMIT 1'
);
if (!$questionStmt) {
    http_response_code(500);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Coding Test | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 text-slate-300 flex items-center justify-center min-h-screen">
    <div class="text-center p-8">
        <div class="text-rose-400 text-5xl mb-4">⚠</div>
        <h1 class="text-xl font-bold text-white mb-2">Unable to load coding question</h1>
        <p class="text-slate-400 text-sm mb-6">The database query could not be prepared. Please check your DB schema includes the <code class="bg-slate-800 px-1 rounded">coding_questions</code> table.</p>
        <a href="tests.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-sm hover:bg-indigo-500/30 transition-all">← Back to Tests</a>
    </div></body></html>
    <?php
    exit;
}
$questionStmt->bind_param('i', $questionId);
$questionStmt->execute();
$question = db_fetch_one($questionStmt);
$questionStmt->close();

if (!$question) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 text-slate-300 flex items-center justify-center min-h-screen">
    <div class="text-center p-8">
        <div class="text-amber-400 text-5xl mb-4">🔍</div>
        <h1 class="text-xl font-bold text-white mb-2">Coding question not found</h1>
        <p class="text-slate-400 text-sm mb-2">Question ID <strong class="text-white"><?php echo (int) $questionId; ?></strong> does not exist or has not been activated yet.</p>
        <p class="text-slate-500 text-xs mb-6">If you just created this question, run this SQL to activate all questions:<br>
        <code class="bg-slate-800 px-2 py-1 rounded text-emerald-300 mt-2 inline-block">UPDATE coding_questions SET is_active = 1 WHERE is_active = 0;</code></p>
        <a href="tests.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-sm hover:bg-indigo-500/30 transition-all">← Back to Tests</a>
    </div></body></html>
    <?php
    exit;
}

$testCaseStmt = $conn->prepare(
    'SELECT id, input_data, expected_output, is_sample
     FROM coding_question_test_cases
     WHERE coding_question_id = ?
     ORDER BY is_sample DESC, id ASC'
);
$sampleCases = [];
if ($testCaseStmt) {
    $testCaseStmt->bind_param('i', $questionId);
    $testCaseStmt->execute();
    $cases = db_fetch_all($testCaseStmt);
    $testCaseStmt->close();
    foreach ($cases as $case) {
        if ((int) ($case['is_sample'] ?? 0) === 1) {
            $sampleCases[] = $case;
        }
    }
}

$languages = skilltrust_coding_allowed_languages((string) ($question['allowed_languages'] ?? ''));
$defaultLanguageKey = array_key_first($languages) ?: 'python';
$defaultSourceCode = trim((string) ($question['starter_code'] ?? '')) !== ''
    ? (string) $question['starter_code']
    : (string) ($languages[$defaultLanguageKey]['starter_code'] ?? '');
$judge0Ready = skilltrust_judge0_is_configured();

$currentPage = '';
$studentSidebarName = $studentName;
$studentSidebarEmail = $studentEmail;
$studentSidebarInitials = $studentInitials;
$studentSidebarTestCount = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e((string) $question['title']); ?> | Coding Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student_sidebar.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <style>
        .coding-editor {
            min-height: 420px;
            resize: vertical;
            font-family: 'DM Mono', monospace;
            line-height: 1.6;
        }
        .coding-console {
            min-height: 180px;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: 'DM Mono', monospace;
        }
    </style>
</head>
<body class="text-slate-300 student-shell-page">
<div class="flex min-h-screen">
    <?php require __DIR__ . '/../includes/student_sidebar.php'; ?>

    <div class="student-main flex-1 flex min-h-screen min-w-0 flex-col">
        <header class="navbar sticky top-0 z-30 px-4 lg:px-8 py-3 lg:py-0 lg:h-16 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden flex-shrink-0 p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0">
                    <h1 class="font-display text-lg font-bold text-white truncate"><?php echo e((string) $question['title']); ?></h1>
                    <p class="text-xs text-slate-500">Coding challenge workspace</p>
                </div>
            </div>
            <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                <span data-theme-label>Dark</span>
            </button>
            <a href="tests.php" class="inline-flex items-center rounded-xl border border-slate-700/60 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800">Back to Tests</a>
        </header>

        <main class="flex-1 min-w-0 px-4 lg:px-8 py-8 space-y-8 overflow-x-hidden">
            <section class="relative overflow-hidden rounded-3xl border border-brand-500/25 bg-gradient-to-br from-brand-900/40 via-slate-900/85 to-violet-900/30 p-6 lg:p-8">
                <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-violet-500/15 blur-3xl"></div>
                <div class="absolute -left-12 bottom-0 h-52 w-52 rounded-full bg-brand-500/10 blur-3xl"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-brand-500/25 bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-300"><?php echo e(ucfirst((string) ($question['difficulty'] ?? 'medium'))); ?></span>
                            <span class="rounded-full border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs font-semibold text-slate-300"><?php echo e((string) ($question['category'] ?? 'Programming')); ?></span>
                        </div>
                        <h2 class="mt-4 font-display text-3xl font-extrabold text-white"><?php echo e((string) $question['title']); ?></h2>
                        <div class="mt-4 text-sm leading-7 text-slate-300"><?php echo nl2br(e((string) $question['question'])); ?></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 lg:min-w-[220px]">
                        <div class="glass-card rounded-2xl p-4">
                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Time Limit</div>
                            <div class="mt-2 text-xl font-display font-bold text-white"><?php echo e((string) $question['time_limit_seconds']); ?>s</div>
                        </div>
                        <div class="glass-card rounded-2xl p-4">
                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Memory</div>
                            <div class="mt-2 text-xl font-display font-bold text-white"><?php echo e((string) ((int) $question['memory_limit_kb'] / 1024)); ?> MB</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)]">
                <div class="glass-card rounded-2xl p-5 sm:p-6 space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-display text-lg font-bold text-white">Code Editor</h3>
                            <p class="text-xs text-slate-500">Use `Run Code` to test, then `Submit Solution` for scoring.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" for="languageSelect">Language</label>
                            <select id="languageSelect" class="rounded-xl border border-slate-700/60 bg-slate-900/70 px-3 py-2 text-sm text-slate-200">
                                <?php foreach ($languages as $key => $language): ?>
                                    <option value="<?php echo e($key); ?>" <?php echo $key === $defaultLanguageKey ? 'selected' : ''; ?>><?php echo e((string) $language['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <textarea id="sourceCode" class="coding-editor w-full rounded-2xl border border-slate-700/60 bg-slate-950/85 px-4 py-4 text-sm text-slate-100 outline-none focus:border-brand-500"><?php echo e($defaultSourceCode); ?></textarea>

                    <div class="flex flex-wrap gap-3">
                        <button id="runCodeBtn" type="button" class="glow-btn relative inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold text-white <?php echo !$judge0Ready ? 'opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$judge0Ready ? 'disabled' : ''; ?>>
                            <span class="relative z-10">Run Code</span>
                        </button>
                        <button id="submitCodeBtn" type="button" class="inline-flex items-center gap-2 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-5 py-3 text-sm font-semibold text-emerald-300 transition-all duration-300 hover:bg-emerald-500/20 <?php echo !$judge0Ready ? 'opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$judge0Ready ? 'disabled' : ''; ?>>
                            Submit Solution
                        </button>
                    </div>
                    <?php if (!$judge0Ready): ?>
                        <p class="text-xs text-amber-300">Judge0 is not configured yet. Add `JUDGE0_API_KEY` to your `.env` file before using Run or Submit.</p>
                    <?php endif; ?>
                </div>

                <div class="space-y-6">
                    <section class="glass-card rounded-2xl p-5 sm:p-6">
                        <h3 class="font-display text-lg font-bold text-white">Sample Test Cases</h3>
                        <div class="mt-4 space-y-4">
                            <?php if ($sampleCases === []): ?>
                                <p class="text-sm text-slate-400">No sample cases configured yet.</p>
                            <?php else: ?>
                                <?php foreach ($sampleCases as $index => $sampleCase): ?>
                                    <div class="rounded-2xl border border-slate-700/60 bg-slate-950/40 p-4">
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sample <?php echo (int) $index + 1; ?></div>
                                        <div class="mt-3 grid gap-3">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.16em] text-brand-300">Input</div>
                                                <pre class="mt-1 text-sm text-slate-200 whitespace-pre-wrap"><?php echo e((string) ($sampleCase['input_data'] ?? '')); ?></pre>
                                            </div>
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.16em] text-emerald-300">Expected Output</div>
                                                <pre class="mt-1 text-sm text-slate-200 whitespace-pre-wrap"><?php echo e((string) ($sampleCase['expected_output'] ?? '')); ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="glass-card rounded-2xl p-5 sm:p-6">
                        <h3 class="font-display text-lg font-bold text-white">Console</h3>
                        <div id="consoleOutput" class="coding-console mt-4 rounded-2xl border border-slate-700/60 bg-slate-950/80 p-4 text-sm text-slate-200">Ready.</div>
                    </section>

                    <section class="glass-card rounded-2xl p-5 sm:p-6">
                        <h3 class="font-display text-lg font-bold text-white">Result</h3>
                        <div id="resultSummary" class="mt-4 text-sm text-slate-400">Run or submit your solution to see detailed results.</div>
                        <div id="caseResults" class="mt-4 space-y-3"></div>
                    </section>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const codingQuestionId = <?php echo (int) $questionId; ?>;
    const csrfToken = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE); ?>;
    const runEndpoint = 'actions/run_coding_test.php';
    const submitEndpoint = 'actions/submit_coding_test.php';

    const sourceCodeField = document.getElementById('sourceCode');
    const languageSelect = document.getElementById('languageSelect');
    const consoleOutput = document.getElementById('consoleOutput');
    const resultSummary = document.getElementById('resultSummary');
    const caseResults = document.getElementById('caseResults');
    const runCodeBtn = document.getElementById('runCodeBtn');
    const submitCodeBtn = document.getElementById('submitCodeBtn');

    function setBusy(isBusy) {
        runCodeBtn.disabled = isBusy;
        submitCodeBtn.disabled = isBusy;
        runCodeBtn.classList.toggle('opacity-60', isBusy);
        submitCodeBtn.classList.toggle('opacity-60', isBusy);
    }

    function renderCases(result) {
        caseResults.innerHTML = '';
        (result.case_results || []).forEach(function (item, index) {
            const card = document.createElement('div');
            card.className = 'rounded-2xl border p-4 ' + (item.passed ? 'border-emerald-500/25 bg-emerald-500/10' : 'border-rose-500/25 bg-rose-500/10');
            card.innerHTML =
                '<div class="flex items-center justify-between gap-3">' +
                    '<div class="text-sm font-semibold text-white">Test Case ' + (index + 1) + '</div>' +
                    '<div class="text-xs font-semibold ' + (item.passed ? 'text-emerald-300' : 'text-rose-300') + '">' + item.status_label + '</div>' +
                '</div>' +
                '<div class="mt-3 text-xs text-slate-400">Input</div>' +
                '<pre class="mt-1 whitespace-pre-wrap text-sm text-slate-200">' + (item.stdin || '') + '</pre>' +
                '<div class="mt-3 text-xs text-slate-400">Expected Output</div>' +
                '<pre class="mt-1 whitespace-pre-wrap text-sm text-slate-200">' + (item.expected_output || '') + '</pre>' +
                '<div class="mt-3 text-xs text-slate-400">Actual Output</div>' +
                '<pre class="mt-1 whitespace-pre-wrap text-sm text-slate-200">' + (item.actual_output || item.stderr || item.compile_output || item.message || '') + '</pre>';
            caseResults.appendChild(card);
        });
    }

    function renderResult(data, mode) {
        const result = data.result;
        consoleOutput.textContent = data.message + '\n\n'
            + 'Passed: ' + result.passed_test_cases + '/' + result.total_test_cases + '\n'
            + 'Score: ' + result.score + '%\n'
            + 'Execution Time: ' + (result.execution_time || 0) + 's\n'
            + 'Memory: ' + (result.memory_usage_kb || 0) + ' KB';

        resultSummary.innerHTML =
            '<div class="rounded-2xl border border-slate-700/60 bg-slate-950/40 p-4">' +
                '<div class="text-white font-semibold">' + (mode === 'submit' ? 'Submission saved' : 'Execution finished') + '</div>' +
                '<div class="mt-2 text-sm text-slate-300">Passed ' + result.passed_test_cases + ' of ' + result.total_test_cases + ' test cases.</div>' +
                '<div class="mt-1 text-sm text-slate-300">Score: ' + result.score + '%</div>' +
            '</div>';

        renderCases(result);
    }

    async function sendCode(endpoint, mode) {
        setBusy(true);
        consoleOutput.textContent = (mode === 'submit' ? 'Submitting solution...' : 'Running code...');
        resultSummary.textContent = 'Waiting for Judge0 response...';
        caseResults.innerHTML = '';

        const body = new URLSearchParams();
        body.set('csrf_token', csrfToken);
        body.set('coding_question_id', String(codingQuestionId));
        body.set('language', languageSelect.value);
        body.set('source_code', sourceCodeField.value);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Request failed.');
            }
            renderResult(data, mode);
        } catch (error) {
            consoleOutput.textContent = error.message;
            resultSummary.textContent = 'Unable to evaluate the code.';
            caseResults.innerHTML = '';
        } finally {
            setBusy(false);
        }
    }

    runCodeBtn.addEventListener('click', function () {
        sendCode(runEndpoint, 'run');
    });

    submitCodeBtn.addEventListener('click', function () {
        sendCode(submitEndpoint, 'submit');
    });
</script>
</body>
</html>
