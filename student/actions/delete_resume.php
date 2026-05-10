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

$sel = $conn->prepare("SELECT `{$column}` FROM users WHERE id = ? LIMIT 1");
$stored = null;
if ($sel) {
    $sel->bind_param('i', $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if ($row && isset($row[$column]) && $row[$column] !== null) {
        $stored = (string) $row[$column];
    }
}

if ($stored === null || $stored === '') {
    echo json_encode(['ok' => true, 'message' => 'No file to remove.']);
    exit;
}

resume_safe_unlink($stored);

$sql = "UPDATE users SET `{$column}` = NULL WHERE id = ?";
$upd = $conn->prepare($sql);
if (!$upd) {
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
    exit;
}

$upd->bind_param('i', $userId);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Could not update profile.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Resume removed.']);
