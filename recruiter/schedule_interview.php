<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/interview_helpers.php';

require_recruiter_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

validate_csrf_or_die();

// interview_helpers.php expects date + time as separate fields.
$result = skilltrust_schedule_interview($conn, [
    'application_id' => (int) ($_POST['application_id'] ?? 0),
    'recruiter_id' => current_recruiter_id(),
    'interview_date' => trim((string) ($_POST['interview_date'] ?? '')),
    'interview_time' => trim((string) ($_POST['interview_time'] ?? '')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
]);

if ($result['success']) {
    $wa = $result['data']['whatsapp_notification'] ?? null;
    $msg = 'Interview scheduled'
        . ($wa === null
            ? ' (candidate has no phone - WhatsApp skipped)'
            : ($wa['success'] ? ' and WhatsApp sent!' : ' (WhatsApp delivery failed)'));
    set_flash_toast('success', $msg);
} else {
    set_flash_toast('error', (string) ($result['message'] ?? 'Unable to schedule interview right now.'));
}

header('Location: interview.php');
exit;
