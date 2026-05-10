<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
validate_csrf_or_die();

$toastType = 'error';
$toastMsg = 'Invalid request.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['recruiter_id'] ?? 0);
    if ($id > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE recruiters SET status='approved' WHERE id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $toastType = 'success';
            $toastMsg = 'Recruiter approved.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE recruiters SET status='rejected' WHERE id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $toastType = 'success';
            $toastMsg = 'Recruiter rejected.';
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare('DELETE FROM recruiters WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $toastType = 'success';
            $toastMsg = 'Recruiter deleted.';
        }
    }
}

$qs = (string) ($_POST['return_qs'] ?? '');
$extra = http_build_query(array_filter(['toast_type' => $toastType, 'toast_msg' => $toastMsg]));
$allQs = trim($qs . ($qs !== '' && $extra !== '' ? '&' : '') . $extra, '&');
header('Location: ../manage-recruiters.php' . ($allQs !== '' ? '?' . $allQs : ''));
exit;
