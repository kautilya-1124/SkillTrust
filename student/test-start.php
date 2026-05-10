<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/test-attempts.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

function skilltrust_shuffle_assoc_list(array &$items): void
{
    $count = count($items);
    if ($count < 2) {
        return;
    }

    for ($i = $count - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
}

$test_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($test_id <= 0) {
    header('Location: tests.php');
    exit;
}

if (!skilltrust_test_attempts_ready($conn)) {
    http_response_code(500);
    die('Test attempts system is not configured. Please run the required database migration.');
}

$meta = [
    'title'          => 'Skill assessment',
    'difficulty'     => 'Beginner',
    'duration'       => 30,
    'passing_score'  => 60,
    'category'       => 'General',
];

$tstmt = $conn->prepare('SELECT * FROM tests WHERE id = ? LIMIT 1');
if (!$tstmt) {
    header('Location: tests.php');
    exit;
}
$tstmt->bind_param('i', $test_id);
$tstmt->execute();
$tres = $tstmt->get_result();
$trow = $tres ? $tres->fetch_assoc() : null;
$tstmt->close();
if (!$trow) {
    header('Location: tests.php');
    exit;
}
$meta['title']         = $trow['title'] ?? $meta['title'];
$meta['difficulty']    = $trow['difficulty'] ?? $meta['difficulty'];
$meta['duration']      = isset($trow['duration']) ? (int) $trow['duration'] : $meta['duration'];
$meta['passing_score'] = isset($trow['passing_score']) ? (int) $trow['passing_score'] : ($meta['passing_score']);
$meta['category']      = $trow['category'] ?? $meta['category'];

try {
    $attemptRecord = skilltrust_test_attempts_get_or_create($conn, (int) $_SESSION['user_id'], $test_id);
    $attemptGate = skilltrust_test_attempts_can_start($attemptRecord);
} catch (Throwable $e) {
    http_response_code(500);
    die('Unable to validate your test access right now.');
}

if (!$attemptGate['allowed']) {
    header('Location: tests.php?lock_message=' . urlencode($attemptGate['message']) . '&lock_test_id=' . $test_id);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM questions WHERE test_id = ? ORDER BY id ASC');
if (!$stmt) {
    header('Location: tests.php');
    exit;
}
$questions = [];
$stmt->bind_param('i', $test_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

if ($questions === []) {
    header('Location: tests.php');
    exit;
}

$formattedQuestions = [];
foreach ($questions as $row) {
    $qid = (int) ($row['id'] ?? 0);
    $co  = isset($row['correct_option']) ? (int) $row['correct_option'] : 1;
    if ($co < 1) {
        $co = 1;
    }
    if ($co > 4) {
        $co = 4;
    }
    $options = [
        ['text' => (string) ($row['option1'] ?? ''), 'is_correct' => $co === 1],
        ['text' => (string) ($row['option2'] ?? ''), 'is_correct' => $co === 2],
        ['text' => (string) ($row['option3'] ?? ''), 'is_correct' => $co === 3],
        ['text' => (string) ($row['option4'] ?? ''), 'is_correct' => $co === 4],
    ];
    skilltrust_shuffle_assoc_list($options);

    $correctIndex = 0;
    $optionTexts = [];
    foreach ($options as $optionIndex => $option) {
        $optionTexts[] = $option['text'];
        if (!empty($option['is_correct'])) {
            $correctIndex = $optionIndex;
        }
    }

    $formattedQuestions[] = [
        'id'          => $qid,
        'text'        => (string) ($row['question'] ?? ''),
        'code'        => null,
        'options'     => $optionTexts,
        'correct'     => $correctIndex,
        'explanation' => (string) ($row['explanation'] ?? ''),
        'category'    => (string) ($row['category'] ?? $meta['category']),
        'difficulty'  => isset($row['difficulty']) ? (string) $row['difficulty'] : 'medium',
    ];
}
skilltrust_shuffle_assoc_list($formattedQuestions);

$attemptToken = bin2hex(random_bytes(32));
$_SESSION['skilltrust_test_attempt'] = [
    'token' => $attemptToken,
    'test_id' => $test_id,
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'started_at' => time(),
    'submitted' => false,
];

$questionsJson = json_encode(
    $formattedQuestions,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE
);
if ($questionsJson === false) {
    $questionsJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?> - SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:ital,wght@0,400;0,500;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/test-start.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body>

<form id="submitForm" action="actions/submit_test.php" method="POST" class="contents"
      onsubmit="return false;">
    <input type="hidden" name="test_id" value="<?php echo (int) $test_id; ?>">
    <input type="hidden" name="elapsed_seconds" id="elapsedSecondsField" value="0">
    <input type="hidden" name="attempt_token" value="<?php echo htmlspecialchars($attemptToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="violation_count" id="violationCountField" value="0">
    <input type="hidden" name="auto_submitted" id="autoSubmittedField" value="0">
    <input type="hidden" name="submit_reason" id="submitReasonField" value="">
    <div id="answersContainer"></div>

<!-- ============== CONFIRM EXIT MODAL ============== -->
<div class="modal-overlay" id="exitModal">
    <div class="modal-box">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-500/25
                    flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h3 class="font-display font-700 text-white text-xl text-center mb-2">Exit Test?</h3>
        <p class="text-slate-400 text-sm text-center mb-6">
            Your progress will be lost. Answers are only saved after submission.
        </p>
        <div class="flex gap-3">
            <button type="button" onclick="closeModal('exitModal')"
                    class="flex-1 py-3 rounded-xl border border-slate-700/60 text-slate-300
                           hover:bg-slate-800 transition-all text-sm font-semibold">
                Keep Going
            </button>
            <a href="tests.php"
               class="flex-1 py-3 rounded-xl bg-red-500/15 border border-red-500/30
                      text-red-400 hover:bg-red-500/22 transition-all text-sm font-semibold text-center">
                Exit Test
            </a>
        </div>
    </div>
</div>

<!-- ============== CONFIRM SUBMIT MODAL ============== -->
<div class="modal-overlay" id="submitModal">
    <div class="modal-box">
        <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 border border-indigo-500/25
                    flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h3 class="font-display font-700 text-white text-xl text-center mb-2">Submit Test?</h3>
        <p class="text-slate-400 text-sm text-center mb-1" id="submitModalMsg">
            You've answered <span class="text-white font-semibold" id="submitAnsweredCount">0</span>
            out of <span class="text-white font-semibold" id="submitTotalCount">0</span> questions.
        </p>
        <p class="text-slate-500 text-xs text-center mb-6" id="submitUnansweredNote"></p>
        <div class="flex gap-3">
            <button type="button" onclick="closeModal('submitModal')"
                    class="flex-1 py-3 rounded-xl border border-slate-700/60 text-slate-300
                           hover:bg-slate-800 transition-all text-sm font-semibold">
                Review
            </button>
            <button type="button" onclick="finalSubmit()"
                    class="flex-1 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600
                           text-white hover:shadow-lg hover:shadow-indigo-500/30
                           transition-all text-sm font-semibold">
                Submit Now
            </button>
        </div>
    </div>
</div>

<!-- ============== RESULT OVERLAY ============== -->
<div class="result-overlay" id="resultOverlay">
    <div class="result-card">
        <!-- Trophy / Fail icon -->
        <div id="resultIcon" class="w-20 h-20 rounded-3xl flex items-center justify-center mx-auto mb-6
                                    bg-gradient-to-br from-amber-500/20 to-orange-500/10
                                    border border-amber-500/25">
            <span class="text-4xl" id="resultEmoji">T</span>
        </div>

        <div id="resultBadge" class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full
                                      text-sm font-semibold mb-4 border">
            <span id="resultBadgeText">Passed</span>
        </div>

        <h2 class="font-display font-800 text-3xl text-white mb-1" id="resultTitle">Excellent!</h2>
        <p class="text-slate-400 text-sm mb-8" id="resultSubtitle">You've completed the test</p>

        <!-- Score ring -->
        <div class="result-ring mb-6">
            <svg class="result-ring-svg" width="140" height="140" viewBox="0 0 140 140">
                <circle class="result-ring-track" cx="70" cy="70" r="58"/>
                <circle class="result-ring-fill" id="resultRingFill"
                        cx="70" cy="70" r="58"
                        stroke-dasharray="364.4"
                        stroke-dashoffset="364.4"/>
                <defs>
                    <linearGradient id="resultGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#6366f1"/>
                        <stop offset="100%" stop-color="#8b5cf6"/>
                    </linearGradient>
                    <linearGradient id="resultGradPass" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#10b981"/>
                        <stop offset="100%" stop-color="#34d399"/>
                    </linearGradient>
                    <linearGradient id="resultGradFail" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ef4444"/>
                        <stop offset="100%" stop-color="#f87171"/>
                    </linearGradient>
                </defs>
            </svg>
            <div class="result-ring-center">
                <span class="font-display font-800 text-4xl text-white" id="resultScoreNum">0</span>
                <span class="text-slate-400 text-xs">/ 100</span>
            </div>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-3 mb-8">
            <div class="bg-slate-800/60 rounded-2xl p-3">
                <div class="font-display font-700 text-white text-xl" id="resCorrect">0</div>
                <div class="text-xs text-slate-500 mt-0.5">Correct</div>
            </div>
            <div class="bg-slate-800/60 rounded-2xl p-3">
                <div class="font-display font-700 text-white text-xl" id="resWrong">0</div>
                <div class="text-xs text-slate-500 mt-0.5">Wrong</div>
            </div>
            <div class="bg-slate-800/60 rounded-2xl p-3">
                <div class="font-display font-700 text-white text-xl" id="resTime">0m</div>
                <div class="text-xs text-slate-500 mt-0.5">Time</div>
            </div>
        </div>

        <!-- CTA buttons -->
        <div class="flex gap-3">
            <a href="results.php"
               class="flex-1 py-3 rounded-xl border border-slate-700/60 text-slate-300
                      hover:bg-slate-800 transition-all text-sm font-semibold">
                View Results
            </a>
            <button type="button" onclick="reviewAnswers()"
                    class="flex-1 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600
                           text-white hover:shadow-lg hover:shadow-indigo-500/30
                           transition-all text-sm font-semibold">
                Review Answers
            </button>
        </div>
    </div>
</div>

<!-- ============== LAYOUT ============== -->
<div class="flex flex-col min-h-screen">

    <!-- TOP BAR -->
    <header class="top-bar sticky top-0 z-30 px-4 lg:px-8 h-16 flex items-center gap-4">

        <!-- Exit -->
        <button type="button" onclick="openModal('exitModal')"
                class="flex items-center gap-2 text-slate-400 hover:text-white
                       transition-colors text-sm font-medium group">
            <div class="w-8 h-8 rounded-xl bg-slate-800/60 border border-slate-700/50
                        flex items-center justify-center group-hover:border-red-500/30
                        group-hover:bg-red-500/08 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </div>
            <span class="hidden sm:inline">Exit</span>
        </button>

        <!-- Test info -->
        <div class="flex-1 min-w-0 px-2">
            <div class="flex items-center gap-2">
                <h1 class="font-display font-700 text-white text-base truncate">
                    <?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded-lg
                             text-xs font-semibold bg-indigo-500/15 text-indigo-400 flex-shrink-0">
                    <?php echo htmlspecialchars($meta['difficulty'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
            <!-- Progress bar -->
            <div class="flex items-center gap-3 mt-1.5">
                <div class="prog-track flex-1 max-w-xs">
                    <div class="prog-fill" id="progressBar" style="width:0%"></div>
                </div>
                <span class="text-xs text-slate-500 flex-shrink-0">
                    Q<span id="currentQNum">1</span> / <span id="totalQNum">10</span>
                </span>
            </div>
        </div>

        <!-- Timer -->
        <div class="flex items-center gap-3 flex-shrink-0">
            <!-- Answered count chip -->
            <div class="hidden md:flex items-center gap-1.5 bg-slate-800/60
                        border border-slate-700/50 rounded-xl px-3 py-1.5">
                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                <span class="text-xs text-slate-400">
                    <span id="answeredCount" class="text-white font-semibold">0</span>
                    <span> answered</span>
                </span>
            </div>

            <!-- Timer ring -->
            <div class="relative timer-normal" id="timerWrapper">
                <svg width="52" height="52" viewBox="0 0 52 52">
                    <circle class="timer-ring-track" cx="26" cy="26" r="22"/>
                    <circle class="timer-ring-fill" id="timerRing"
                            cx="26" cy="26" r="22"
                            stroke-dasharray="138.2"
                            stroke-dashoffset="0"/>
                    <defs>
                        <linearGradient id="timerGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#6366f1"/>
                            <stop offset="100%" stop-color="#8b5cf6"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-mono text-xs font-600 text-white" id="timerDisplay">--:--</span>
                </div>
            </div>
        </div>
    </header>

    <div id="antiCheatBanner" class="hidden mx-4 mt-4 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 lg:mx-8">
        <span class="font-semibold">Warning:</span>
        <span id="antiCheatBannerText">Stay on this tab and remain in fullscreen mode during the test.</span>
    </div>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col lg:flex-row gap-0">

        <!-- QUESTION AREA -->
        <div class="flex-1 px-4 lg:px-10 py-8 flex flex-col max-w-3xl mx-auto w-full">

            <!-- Question card -->
            <div class="question-card rounded-3xl p-6 lg:p-8 mb-6 anim-fade-up flex-1" id="questionCard">

                <!-- Header: number + flag -->
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-mono text-xs text-indigo-400 font-600" id="qLabel">Question 01</span>
                            <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                            <span class="text-xs text-slate-500" id="qCategory">JavaScript</span>
                        </div>
                        <div class="flex gap-1.5" id="qDots">
                            <!-- filled dynamically -->
                        </div>
                    </div>
                    <button type="button" id="flagBtn" onclick="toggleFlag()" data-tip="Flag for review"
                            class="btn-flag flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold flex-shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                        </svg>
                        <span id="flagBtnText">Flag</span>
                    </button>
                </div>

                <!-- Question text -->
                <div class="mb-6" id="questionArea">
                    <h2 class="font-display font-700 text-white text-xl lg:text-2xl leading-snug mb-3"
                        id="questionText">Loading...</h2>
                    <div id="questionCode" class="hidden mb-4"></div>
                </div>

                <!-- Options -->
                <div class="space-y-3" id="optionsArea">
                    <!-- filled dynamically -->
                </div>

                <!-- Explanation panel (post-review) -->
                <div id="explanationPanel" class="hidden mt-6 p-4 rounded-2xl
                     bg-indigo-500/05 border border-indigo-500/20">
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-indigo-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-indigo-400 mb-1">Explanation</p>
                            <p class="text-sm text-slate-300 leading-relaxed" id="explanationText"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex items-center justify-between gap-3">
                <button type="button" id="prevBtn" onclick="navigate(-1)"
                        class="btn-nav flex items-center gap-2 px-5 py-3 rounded-2xl font-semibold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Previous
                </button>

                <div class="flex items-center gap-2">
                    <!-- Skip -->
                    <button type="button" onclick="skipQuestion()"
                            class="hidden lg:flex items-center gap-1.5 px-4 py-3 rounded-2xl
                                   text-slate-500 hover:text-slate-300 text-sm font-medium transition-colors"
                            id="skipBtn">
                        Skip
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Next / Submit -->
                    <button type="button" id="nextBtn" onclick="navigate(1)"
                            class="btn-next flex items-center gap-2 px-6 py-3 rounded-2xl font-semibold text-sm">
                        <span>Next</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <button type="button" id="submitBtn" onclick="openSubmitModal()"
                            class="btn-submit hidden items-center gap-2 px-6 py-3 rounded-2xl font-semibold text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Submit</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- SIDE PANEL -->
        <aside class="lg:w-72 xl:w-80 side-panel border-t lg:border-t-0 lg:border-l
                      border-indigo-900/20 p-5 lg:sticky lg:top-16 lg:h-[calc(100vh-4rem)]
                      lg:overflow-y-auto">

            <!-- Question Map -->
            <div class="mb-6">
                <h3 class="font-display font-700 text-white text-sm mb-3">Question Map</h3>
                <div class="grid grid-cols-5 gap-1.5" id="questionMap">
                    <!-- filled dynamically -->
                </div>
                <!-- Legend -->
                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1.5">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <div class="w-3 h-3 rounded bg-slate-700/80 border border-slate-600/50"></div>
                        Unanswered
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <div class="w-3 h-3 rounded bg-indigo-500/25 border border-indigo-500/40"></div>
                        Answered
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <div class="w-3 h-3 rounded bg-amber-500/15 border border-amber-500/35"></div>
                        Flagged
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <div class="w-3 h-3 rounded bg-gradient-to-br from-indigo-500 to-violet-500"></div>
                        Current
                    </div>
                </div>
            </div>

            <!-- Progress stats -->
            <div class="bg-slate-800/40 rounded-2xl p-4 mb-5 border border-slate-700/30">
                <div class="text-xs text-slate-500 mb-3 font-semibold uppercase tracking-wide">Progress</div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400">Answered</span>
                        <span class="text-xs font-semibold text-white">
                            <span id="sideAnswered">0</span> / <span id="sideTotal">10</span>
                        </span>
                    </div>
                    <div class="prog-track">
                        <div class="prog-fill" id="sideProgressBar" style="width:0%"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 text-amber-400">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                            <span><span id="sideFlagged">0</span> flagged</span>
                        </span>
                        <span class="text-slate-500" id="sideRemaining">10 remaining</span>
                    </div>
                </div>
            </div>

            <!-- Passing score info -->
            <div class="bg-slate-800/40 rounded-2xl p-4 mb-5 border border-slate-700/30">
                <div class="text-xs text-slate-500 mb-2 font-semibold uppercase tracking-wide">Test Info</div>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Passing score</span>
                        <span class="text-white font-semibold"><?php echo htmlspecialchars((string) $meta['passing_score'], ENT_QUOTES, 'UTF-8'); ?>%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Duration</span>
                        <span class="text-white font-semibold"><?php echo htmlspecialchars((string) $meta['duration'], ENT_QUOTES, 'UTF-8'); ?> min</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Category</span>
                        <span class="text-indigo-400 font-semibold"><?php echo htmlspecialchars($meta['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Difficulty</span>
                        <span class="text-white font-semibold"><?php echo htmlspecialchars($meta['difficulty'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Submit button (side) -->
            <button type="button" onclick="openSubmitModal()"
                    class="btn-submit w-full flex items-center justify-center gap-2
                           py-3 rounded-2xl font-semibold text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Submit Test</span>
            </button>

        </aside>
    </div>
</div>

</form>

<!-- ============== SCRIPTS ============== -->
<script src="../frontend/timer.js"></script>
<script src="../frontend/fullscreen.js"></script>
<script src="../frontend/test.js"></script>
<script type="application/json" id="studentTestStartData"><?php echo json_encode(['questions' => $formattedQuestions, 'durationSeconds' => max(1, (int) $meta['duration']) * 60, 'passingScore' => (int) $meta['passing_score'], 'testConfig' => ['warningLimit' => 3, 'fullscreenRequired' => true, 'testId' => (int) $test_id], 'storageKey' => 'st_test_' . (string) $test_id], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/student/test-start-page.js"></script>
</body>
</html>
