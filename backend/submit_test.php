<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/test-attempts.php';
require_once __DIR__ . '/../includes/whatsapp.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

function skilltrust_fail_submission(string $message, int $statusCode = 400): never
{
    http_response_code($statusCode);
    exit($message);
}

$userId = (int) $_SESSION['user_id'];
$testId = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
$elapsedSeconds = filter_input(INPUT_POST, 'elapsed_seconds', FILTER_VALIDATE_INT);
$violationCount = filter_input(INPUT_POST, 'violation_count', FILTER_VALIDATE_INT);
$autoSubmitted = filter_input(INPUT_POST, 'auto_submitted', FILTER_VALIDATE_INT);
$submitReason = trim((string) ($_POST['submit_reason'] ?? ''));
$attemptToken = trim((string) ($_POST['attempt_token'] ?? ''));
$answers = $_POST['answers'] ?? null;

if (!$testId || !is_array($answers)) {
    skilltrust_fail_submission('Invalid submission.');
}

if (!skilltrust_test_attempts_ready($conn)) {
    skilltrust_fail_submission('Test attempts system is not configured.', 500);
}

$attempt = $_SESSION['skilltrust_test_attempt'] ?? null;
if (
    !is_array($attempt) ||
    ($attempt['submitted'] ?? false) === true ||
    (int) ($attempt['user_id'] ?? 0) !== $userId ||
    (int) ($attempt['test_id'] ?? 0) !== (int) $testId ||
    !hash_equals((string) ($attempt['token'] ?? ''), $attemptToken)
) {
    skilltrust_fail_submission('Duplicate or expired submission.');
}

$sanitizedAnswers = [];
foreach ($answers as $questionId => $selectedOption) {
    $questionId = filter_var($questionId, FILTER_VALIDATE_INT);
    $selectedOption = filter_var($selectedOption, FILTER_VALIDATE_INT);
    if (!$questionId || !$selectedOption || $selectedOption < 1 || $selectedOption > 4) {
        continue;
    }
    $sanitizedAnswers[(int) $questionId] = (int) $selectedOption;
}

if ($sanitizedAnswers === []) {
    skilltrust_fail_submission('Please answer at least one question before submitting.');
}

$testStmt = $conn->prepare('SELECT id, duration FROM tests WHERE id = ? LIMIT 1');
if (!$testStmt) {
    skilltrust_fail_submission('Could not validate test.');
}
$testStmt->bind_param('i', $testId);
$testStmt->execute();
$testRow = $testStmt->get_result()->fetch_assoc();
$testStmt->close();

if (!$testRow) {
    skilltrust_fail_submission('Test not found.');
}

$questionStmt = $conn->prepare('SELECT id, correct_option FROM questions WHERE test_id = ?');
if (!$questionStmt) {
    skilltrust_fail_submission('Could not validate answers.');
}
$questionStmt->bind_param('i', $testId);
$questionStmt->execute();
$result = $questionStmt->get_result();

$correctByQuestion = [];
while ($row = $result->fetch_assoc()) {
    $correctByQuestion[(int) $row['id']] = (int) $row['correct_option'];
}
$questionStmt->close();

if ($correctByQuestion === []) {
    skilltrust_fail_submission('No valid answers were submitted.');
}

$answeredCount = 0;
$score = 0;
foreach ($sanitizedAnswers as $questionId => $selectedOption) {
    if (!isset($correctByQuestion[$questionId])) {
        continue;
    }
    $answeredCount++;
    if ((int) $correctByQuestion[$questionId] === $selectedOption) {
        $score++;
    }
}

if ($answeredCount === 0) {
    skilltrust_fail_submission('No valid answers were submitted.');
}

$totalQuestions = count($correctByQuestion);
$percentage = round(($score / max(1, $totalQuestions)) * 100, 2);
$durationSeconds = max(60, ((int) ($testRow['duration'] ?? 1)) * 60);
$elapsedSeconds = is_int($elapsedSeconds) ? max(0, min($elapsedSeconds, $durationSeconds)) : 0;
$violationCount = is_int($violationCount) ? max(0, $violationCount) : 0;
$autoSubmitted = $autoSubmitted === 1 ? 1 : 0;
$submitReason = substr($submitReason, 0, 50);

$insertStmt = $conn->prepare('INSERT INTO results (user_id, test_id, score, percentage) VALUES (?, ?, ?, ?)');
if (!$insertStmt) {
    skilltrust_fail_submission('Could not save result.');
}

$_SESSION['skilltrust_test_attempt']['submitted'] = true;

$conn->begin_transaction();

try {
    $attemptLockStmt = $conn->prepare(
        'SELECT id, user_id, test_id, attempts, max_attempts, is_blocked, admin_unlocked, last_attempt_at
         FROM test_attempts
         WHERE user_id = ? AND test_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    if (!$attemptLockStmt) {
        throw new RuntimeException('Could not validate attempt status.');
    }
    $attemptLockStmt->bind_param('ii', $userId, $testId);
    $attemptLockStmt->execute();
    $attemptRow = db_fetch_one($attemptLockStmt);
    $attemptLockStmt->close();

    if (!$attemptRow) {
        $attemptSeedStmt = $conn->prepare(
            'INSERT INTO test_attempts (user_id, test_id, attempts, max_attempts, is_blocked, admin_unlocked, last_attempt_at)
             VALUES (?, ?, 0, 3, 0, 0, NULL)'
        );
        if (!$attemptSeedStmt) {
            throw new RuntimeException('Could not initialize attempt status.');
        }
        $attemptSeedStmt->bind_param('ii', $userId, $testId);
        if (!$attemptSeedStmt->execute()) {
            $attemptSeedStmt->close();
            throw new RuntimeException('Could not initialize attempt status.');
        }
        $attemptSeedStmt->close();

        $attemptLockStmt = $conn->prepare(
            'SELECT id, user_id, test_id, attempts, max_attempts, is_blocked, admin_unlocked, last_attempt_at
             FROM test_attempts
             WHERE user_id = ? AND test_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        if (!$attemptLockStmt) {
            throw new RuntimeException('Could not validate attempt status.');
        }
        $attemptLockStmt->bind_param('ii', $userId, $testId);
        $attemptLockStmt->execute();
        $attemptRow = db_fetch_one($attemptLockStmt);
        $attemptLockStmt->close();
    }

    $attemptRecord = $attemptRow ? skilltrust_test_attempts_normalize_row($attemptRow) : null;
    if ($attemptRecord === null) {
        throw new RuntimeException('Attempt record is unavailable.');
    }

    $attemptGate = skilltrust_test_attempts_can_start($attemptRecord);
    if (!$attemptGate['allowed']) {
        throw new RuntimeException($attemptGate['message']);
    }

    $insertStmt->bind_param('iiid', $userId, $testId, $score, $percentage);
    if (!$insertStmt->execute()) {
        throw new RuntimeException('Failed to save result.');
    }
    $insertStmt->close();
    $insertStmt = null;

    $nextAttempts = $attemptRecord['attempts'] + 1;
    $maxAttempts = max(1, $attemptRecord['max_attempts']);
    $shouldBlock = $nextAttempts >= $maxAttempts ? 1 : 0;

    $attemptUpdateStmt = $conn->prepare(
        'UPDATE test_attempts
         SET attempts = ?, is_blocked = ?, admin_unlocked = 0, last_attempt_at = NOW()
         WHERE id = ?
         LIMIT 1'
    );
    if (!$attemptUpdateStmt) {
        throw new RuntimeException('Could not update attempt status.');
    }
    $attemptUpdateStmt->bind_param('iii', $nextAttempts, $shouldBlock, $attemptRecord['id']);
    if (!$attemptUpdateStmt->execute()) {
        $attemptUpdateStmt->close();
        throw new RuntimeException('Could not update attempt status.');
    }
    $attemptUpdateStmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['skilltrust_test_attempt']['submitted'] = false;
    if ($insertStmt instanceof mysqli_stmt) {
        $insertStmt->close();
    }
    skilltrust_fail_submission($e->getMessage(), 400);
}

unset($_SESSION['skilltrust_test_attempt']);

$notification = null;
if (db_column_exists($conn, 'users', 'phone')) {
    $userStmt = $conn->prepare('SELECT name, phone FROM users WHERE id = ? LIMIT 1');
    $testMetaStmt = $conn->prepare('SELECT title, passing_score FROM tests WHERE id = ? LIMIT 1');

    if ($userStmt && $testMetaStmt) {
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRow = db_fetch_one($userStmt);
        $userStmt->close();

        $testMetaStmt->bind_param('i', $testId);
        $testMetaStmt->execute();
        $testMeta = db_fetch_one($testMetaStmt);
        $testMetaStmt->close();

        $userPhone = trim((string) ($userRow['phone'] ?? ''));
        if ($userPhone !== '') {
            $studentName = trim((string) ($userRow['name'] ?? 'Candidate'));
            $testTitle = trim((string) ($testMeta['title'] ?? 'your test'));
            $passingScore = normalize_score($testMeta['passing_score'] ?? 0);
            $resultLabel = $percentage >= $passingScore ? 'passed' : 'completed';

            $notificationMessage = "Hello {$studentName},\n\n"
                . "Your result has been declared for {$testTitle}.\n"
                . 'Score: ' . $score . '/' . $totalQuestions . "\n"
                . 'Percentage: ' . number_format($percentage, 2) . "%\n"
                . 'Status: ' . ucfirst($resultLabel) . "\n\n"
                . "Check your dashboard for full details.\n\n"
                . '- SkillTrust';

            $notification = sendWhatsApp($userPhone, $notificationMessage, [
                'db' => $conn,
                'context_type' => 'result_declared',
                'related_id' => (string) $testId,
                'reference' => 'RESULT_' . $userId . '_' . $testId,
            ]);
        }
    } else {
        if ($userStmt instanceof mysqli_stmt) {
            $userStmt->close();
        }
        if ($testMetaStmt instanceof mysqli_stmt) {
            $testMetaStmt->close();
        }
    }
}

if (is_array($notification) && !$notification['success']) {
    $_SESSION['flash_toast'] = [
        'type' => 'error',
        'message' => 'Test submitted, but WhatsApp notification could not be delivered.',
    ];
}

header('Location: ../results.php');
exit;
