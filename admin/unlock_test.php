<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/test-attempts.php';

validate_csrf_or_die();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: test-attempt-locks.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$testId = (int) ($_POST['test_id'] ?? 0);

if ($userId <= 0 || $testId <= 0) {
    set_flash_toast('error', 'Invalid unlock request.');
    header('Location: test-attempt-locks.php');
    exit;
}

if (!skilltrust_test_attempts_ready($conn)) {
    set_flash_toast('error', 'Test attempts system is not configured.');
    header('Location: test-attempt-locks.php');
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'UPDATE test_attempts
         SET attempts = 0, is_blocked = 0, admin_unlocked = 1, last_attempt_at = NULL
         WHERE user_id = ? AND test_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare unlock statement.');
    }
    $stmt->bind_param('ii', $userId, $testId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to unlock the selected test.');
    }
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        throw new RuntimeException('No blocked attempt record was found for that student/test.');
    }

    $conn->commit();
    set_flash_toast('success', 'Test unlock completed. The student can retry now.');
} catch (Throwable $e) {
    $conn->rollback();
    set_flash_toast('error', $e->getMessage());
}

header('Location: test-attempt-locks.php');
exit;
