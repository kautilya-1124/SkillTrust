<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
validate_csrf_or_die();

$toastType = 'error';
$toastMsg = 'Invalid request.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $testId = (int) ($_POST['test_id'] ?? 0);

    if ($testId > 0) {
        if ($action === 'delete_test') {
            $conn->begin_transaction();
            try {
                $qDel = $conn->prepare('DELETE FROM questions WHERE test_id = ?');
                if (!$qDel) {
                    throw new RuntimeException('Failed to prepare questions deletion.');
                }
                $qDel->bind_param('i', $testId);
                if (!$qDel->execute()) {
                    $qDel->close();
                    throw new RuntimeException('Failed to delete related questions.');
                }
                $qDel->close();

                $rDel = $conn->prepare('DELETE FROM results WHERE test_id = ?');
                if (!$rDel) {
                    throw new RuntimeException('Failed to prepare results deletion.');
                }
                $rDel->bind_param('i', $testId);
                if (!$rDel->execute()) {
                    $rDel->close();
                    throw new RuntimeException('Failed to delete related results.');
                }
                $rDel->close();

                $tDel = $conn->prepare('DELETE FROM tests WHERE id = ? LIMIT 1');
                if (!$tDel) {
                    throw new RuntimeException('Failed to prepare test deletion.');
                }
                $tDel->bind_param('i', $testId);
                if (!$tDel->execute()) {
                    $tDel->close();
                    throw new RuntimeException('Failed to delete test.');
                }
                $affected = $tDel->affected_rows;
                $tDel->close();

                if ($affected < 1) {
                    throw new RuntimeException('Test not found.');
                }

                $conn->commit();
                $toastType = 'success';
                $toastMsg = 'Test deleted successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $toastType = 'error';
                $toastMsg = $e->getMessage();
            }
        }
    } else {
        $toastMsg = 'Invalid test id.';
    }
}

$qs = (string) ($_POST['return_qs'] ?? '');
$extra = http_build_query(array_filter(['toast_type' => $toastType, 'toast_msg' => $toastMsg]));
$allQs = trim($qs . ($qs !== '' && $extra !== '' ? '&' : '') . $extra, '&');
header('Location: ../manage-tests.php' . ($allQs !== '' ? '?' . $allQs : ''));
exit;
