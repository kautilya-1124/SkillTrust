<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/resume_helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$type = (string) ($_POST['resume_type'] ?? '');
if (!in_array($type, RESUME_TYPES, true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid resume type.']);
    exit;
}

$column = resume_type_to_column($type);
if ($column === null) {
    echo json_encode(['ok' => false, 'message' => 'Invalid resume type.']);
    exit;
}

if (!isset($_FILES['resume'])) {
    echo json_encode(['ok' => false, 'message' => 'No file uploaded.']);
    exit;
}

$v = resume_validate_uploaded_file($_FILES['resume']);
if (!$v['ok']) {
    echo json_encode(['ok' => false, 'message' => $v['message']]);
    exit;
}
$ext = $v['ext'];

$dir = resume_upload_dir();
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    echo json_encode(['ok' => false, 'message' => 'Could not create upload directory.']);
    exit;
}

$newBase = uniqid((string) time(), true) . '.' . $ext;
$destAbs = $dir . DIRECTORY_SEPARATOR . $newBase;
$relative = resume_relative_path($newBase);

if (!move_uploaded_file($_FILES['resume']['tmp_name'], $destAbs)) {
    echo json_encode(['ok' => false, 'message' => 'Could not save file.']);
    exit;
}

$sel = $conn->prepare("SELECT `{$column}` FROM users WHERE id = ? LIMIT 1");
$oldRelative = null;
if ($sel) {
    $sel->bind_param('i', $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if ($row && isset($row[$column])) {
        $oldRelative = $row[$column] !== null ? (string) $row[$column] : null;
    }
}

$sql = "UPDATE users SET `{$column}` = ? WHERE id = ?";
$upd = $conn->prepare($sql);
if (!$upd) {
    @unlink($destAbs);
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
    exit;
}

$upd->bind_param('si', $relative, $userId);
if (!$upd->execute()) {
    $upd->close();
    @unlink($destAbs);
    echo json_encode(['ok' => false, 'message' => 'Could not update profile.']);
    exit;
}
$upd->close();

if ($oldRelative !== null && $oldRelative !== '' && $oldRelative !== $relative) {
    resume_safe_unlink($oldRelative);
}

echo json_encode([
    'ok'       => true,
    'message'  => 'Resume uploaded.',
    'path'     => $relative,
    'filename' => basename($relative),
]);
