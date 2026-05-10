<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

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

$current = (string) ($_POST['current_password'] ?? '');
$new     = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['ok' => false, 'message' => 'Fill all password fields']);
    exit;
}

if ($new !== $confirm) {
    echo json_encode(['ok' => false, 'message' => 'New passwords do not match']);
    exit;
}

if (strlen($new) < 6) {
    echo json_encode(['ok' => false, 'message' => 'New password must be at least 6 characters']);
    exit;
}

$stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['password'])) {
    echo json_encode(['ok' => false, 'message' => 'Account not found']);
    exit;
}

if (!password_verify($current, $row['password'])) {
    echo json_encode(['ok' => false, 'message' => 'Current password is incorrect']);
    exit;
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$upd  = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
if (!$upd) {
    echo json_encode(['ok' => false, 'message' => 'Database error']);
    exit;
}

$upd->bind_param('si', $hash, $userId);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Could not update password']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Password updated']);
