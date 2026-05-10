<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
validate_csrf_or_die();

$toastType = 'error';
$toastMsg = 'Invalid request.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['user_id'] ?? 0);
    if ($id > 0) {
        if ($action === 'toggle_block') {
            $stmt = $conn->prepare("UPDATE users SET status = CASE WHEN LOWER(status)='blocked' THEN 'active' ELSE 'blocked' END WHERE id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $toastType = 'success';
                    $toastMsg = 'Student status updated.';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete_user') {
            $conn->begin_transaction();
            try {
                $tr = $conn->query("SHOW TABLES LIKE 'results'");
                $hasResults = $tr && $tr->num_rows > 0;
                if ($tr) {
                    $tr->free();
                }
                if ($hasResults) {
                    $delR = $conn->prepare('DELETE FROM results WHERE user_id = ?');
                    if (!$delR) {
                        throw new RuntimeException('Could not prepare results cleanup.');
                    }
                    $delR->bind_param('i', $id);
                    if (!$delR->execute()) {
                        $delR->close();
                        throw new RuntimeException('Failed to delete student results.');
                    }
                    $delR->close();
                }

                $delU = $conn->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
                if (!$delU) {
                    throw new RuntimeException('Could not prepare student delete.');
                }
                $delU->bind_param('i', $id);
                if (!$delU->execute() || $delU->affected_rows < 1) {
                    $delU->close();
                    throw new RuntimeException('Student not found or could not be deleted.');
                }
                $delU->close();
                $conn->commit();
                $toastType = 'success';
                $toastMsg = 'Student deleted.';
            } catch (Throwable $e) {
                $conn->rollback();
                $toastType = 'error';
                $toastMsg = $e->getMessage();
            }
        }
    }
}

$qs = (string) ($_POST['return_qs'] ?? '');
$extra = http_build_query(array_filter(['toast_type' => $toastType, 'toast_msg' => $toastMsg]));
$allQs = trim($qs . ($qs !== '' && $extra !== '' ? '&' : '') . $extra, '&');
header('Location: ../manage-users.php' . ($allQs !== '' ? '?' . $allQs : ''));
exit;
