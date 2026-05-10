<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/judge0.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

validate_csrf_or_die();

$questionId = (int) ($_POST['coding_question_id'] ?? 0);
$languageKey = strtolower(trim((string) ($_POST['language'] ?? '')));
$sourceCode = trim((string) ($_POST['source_code'] ?? ''));

if ($questionId <= 0 || $languageKey === '' || $sourceCode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Question, language, and source code are required.']);
    exit;
}

if (!skilltrust_judge0_is_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Judge0 API is not configured. Add JUDGE0_API_KEY to your .env file.']);
    exit;
}

// Use the same adaptive column detection as coding-test.php to handle
// different DB schemas (time_limit vs time_limit_seconds, etc.)
$timeLimitCol  = db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds';
$memLimitCol   = db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb';
$activeClause  = db_column_exists($conn, 'coding_questions', 'status') ? "status = 'active'" : 'is_active = 1';

$questionStmt = $conn->prepare(
    "SELECT id, allowed_languages,
            {$timeLimitCol} AS time_limit_seconds,
            {$memLimitCol}  AS memory_limit_kb
     FROM coding_questions
     WHERE id = ? AND {$activeClause}
     LIMIT 1"
);
if (!$questionStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load coding question. DB error: ' . $conn->error]);
    exit;
}
$questionStmt->bind_param('i', $questionId);
$questionStmt->execute();
$question = db_fetch_one($questionStmt);
$questionStmt->close();

if (!$question) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Coding question not found.']);
    exit;
}

$allowedLanguages = skilltrust_coding_allowed_languages((string) ($question['allowed_languages'] ?? ''));
if (!isset($allowedLanguages[$languageKey])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Selected language is not allowed for this question.']);
    exit;
}

$testCasesStmt = $conn->prepare(
    'SELECT id, input_data, expected_output, is_sample, weight
     FROM coding_question_test_cases
     WHERE coding_question_id = ?
     ORDER BY is_sample DESC, id ASC'
);
if (!$testCasesStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load coding test cases.']);
    exit;
}
$testCasesStmt->bind_param('i', $questionId);
$testCasesStmt->execute();
$testCases = db_fetch_all($testCasesStmt);
$testCasesStmt->close();

if ($testCases === []) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'This coding question has no test cases configured.']);
    exit;
}

try {
    $evaluation = skilltrust_coding_run_against_cases(
        $sourceCode,
        $languageKey,
        $testCases,
        isset($question['time_limit_seconds']) ? (float) $question['time_limit_seconds'] : 2.0,
        isset($question['memory_limit_kb']) ? (int) $question['memory_limit_kb'] : 131072
    );

    echo json_encode([
        'success' => true,
        'message' => 'Code executed successfully.',
        'result' => $evaluation,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
