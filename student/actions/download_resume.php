<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/resume_helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not logged in';
    exit;
}

$userId = (int) $_SESSION['user_id'];
$type   = (string) ($_GET['type'] ?? '');

if (!in_array($type, RESUME_TYPES, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid type';
    exit;
}

$column = resume_type_to_column($type);
if ($column === null) {
    http_response_code(400);
    exit;
}

$sel = $conn->prepare("SELECT `{$column}` FROM users WHERE id = ? LIMIT 1");
if (!$sel) {
    http_response_code(500);
    exit;
}
$sel->bind_param('i', $userId);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$sel->close();

$stored = ($row[$column] ?? null) !== null ? (string) $row[$column] : '';
if ($stored === '' || !resume_is_safe_stored_path($stored)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found';
    exit;
}

$abs = resume_absolute_from_stored($stored);
if (!is_file($abs)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found';
    exit;
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'pdf') {
    $mime = 'application/pdf';
} elseif ($ext === 'doc') {
    $mime = 'application/msword';
} elseif ($ext === 'docx') {
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

$inline = isset($_GET['inline']) && $_GET['inline'] === '1';
$disp   = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($abs));
header('Content-Disposition: ' . $disp . '; filename="' . basename($stored) . '"');
header('X-Content-Type-Options: nosniff');
readfile($abs);
exit;
