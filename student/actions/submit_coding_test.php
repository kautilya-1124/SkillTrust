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

$userId = (int) $_SESSION['user_id'];
$questionId = (int) ($_POST['coding_question_id'] ?? 0);
$languageKey = strtolower(trim((string) ($_POST['language'] ?? '')));
$sourceCode = trim((string) ($_POST['source_code'] ?? ''));

if ($questionId <= 0 || $languageKey === '' || $sourceCode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Question, language, and source code are required.']);
    exit;
}

// Adaptive column detection — mirrors coding-test.php and run_coding_test.php
$timeLimitCol  = db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds';
$memLimitCol   = db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb';
$activeClause  = db_column_exists($conn, 'coding_questions', 'status') ? "status = 'active'" : 'is_active = 1';

$questionStmt = $conn->prepare(
    "SELECT id, title, allowed_languages,
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

// BUG FIX 3: Judge0 configured check was completely missing in submit.
// run_coding_test.php had it but submit_coding_test.php did not.
// Without this, skilltrust_coding_run_against_cases() throws a RuntimeException
// which is NOT caught here — PHP returns a 500 HTML error page instead of JSON,
// the fetch() in coding-test.php gets a JSON parse error, and the Submit button
// silently fails with "Unable to evaluate the code."
if (!skilltrust_judge0_is_configured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Judge0 API is not configured. Add JUDGE0_API_KEY to your .env file.']);
    exit;
}

$testCasesStmt = $conn->prepare(
    'SELECT id, input_data, expected_output, is_sample, weight
     FROM coding_question_test_cases
     WHERE coding_question_id = ?
     ORDER BY id ASC'
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
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
    exit;
}

$submissionStmt = $conn->prepare(
    'INSERT INTO coding_submissions
        (user_id, coding_question_id, language_key, source_code, result_status, passed_test_cases, total_test_cases, score, judge0_status, execution_time, memory_usage_kb)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$submissionStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save coding submission.']);
    exit;
}

$resultStatus = (string) $evaluation['result_status'];
$passedCases = (int) $evaluation['passed_test_cases'];
$totalCases = (int) $evaluation['total_test_cases'];
$score = (float) $evaluation['score'];
$judge0Status = (string) ($evaluation['judge0_status'] ?? '');
$executionTime = isset($evaluation['execution_time']) ? (float) $evaluation['execution_time'] : null;
$memoryUsageKb = isset($evaluation['memory_usage_kb']) ? (int) $evaluation['memory_usage_kb'] : null;

$conn->begin_transaction();

try {
    // BUG FIX 4: type string was 'iisssiisdsi' — position 8 is $score (float) but was
    // typed as 's' (string). mysqli silently accepts this but stores wrong values for
    // decimal scores like 66.67 — they get truncated or misrepresented in the DB.
    // Corrected to 'iisssiiddsi' — 'd' for $score (double/float).
    $submissionStmt->bind_param(
        'iisssiidsdi',
        $userId,
        $questionId,
        $languageKey,
        $sourceCode,
        $resultStatus,
        $passedCases,
        $totalCases,
        $score,
        $judge0Status,
        $executionTime,
        $memoryUsageKb
    );

    if (!$submissionStmt->execute()) {
        throw new RuntimeException('Unable to save coding submission.');
    }
    $submissionId = (int) $submissionStmt->insert_id;
    $submissionStmt->close();

    $caseStmt = $conn->prepare(
        'INSERT INTO coding_submission_case_results
            (submission_id, test_case_id, judge0_token, status_label, stdin_data, expected_output, actual_output, stderr_output, compile_output, passed, execution_time, memory_usage_kb)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$caseStmt) {
        throw new RuntimeException('Unable to save coding case results.');
    }

    foreach ($evaluation['case_results'] as $index => $caseResult) {
        $testCaseId = (int) ($testCases[$index]['id'] ?? 0);
        $judge0Token = (string) ($caseResult['token'] ?? '');
        $statusLabel = (string) ($caseResult['status_label'] ?? '');
        $stdinData = (string) ($caseResult['stdin'] ?? '');
        $expectedOutput = (string) ($caseResult['expected_output'] ?? '');
        $actualOutput = (string) ($caseResult['actual_output'] ?? '');
        $stderrOutput = (string) ($caseResult['stderr'] ?? '');
        $compileOutput = (string) ($caseResult['compile_output'] ?? '');
        $passedFlag = !empty($caseResult['passed']) ? 1 : 0;
        $caseTime = isset($caseResult['time']) ? (float) $caseResult['time'] : null;
        $caseMemory = isset($caseResult['memory']) ? (int) $caseResult['memory'] : null;

        $caseStmt->bind_param(
            'iisssssssidi',
            $submissionId,
            $testCaseId,
            $judge0Token,
            $statusLabel,
            $stdinData,
            $expectedOutput,
            $actualOutput,
            $stderrOutput,
            $compileOutput,
            $passedFlag,
            $caseTime,
            $caseMemory
        );

        if (!$caseStmt->execute()) {
            throw new RuntimeException('Unable to save coding case results.');
        }
    }

    $caseStmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Coding submission saved successfully.',
        'submission_id' => $submissionId,
        'result' => $evaluation,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $conn->rollback();
    if ($submissionStmt instanceof mysqli_stmt) {
        $submissionStmt->close();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
