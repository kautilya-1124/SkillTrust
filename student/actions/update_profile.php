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

$name     = trim((string) ($_POST['name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email    = trim((string) ($_POST['email'] ?? ''));
$phone    = trim((string) ($_POST['phone'] ?? ''));
$bio      = trim((string) ($_POST['bio'] ?? ''));
$skills   = trim((string) ($_POST['skills'] ?? ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'message' => 'Name is required']);
    exit;
}

if ($username === '') {
    echo json_encode(['ok' => false, 'message' => 'Username is required']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Valid email is required']);
    exit;
}

$check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
if ($check) {
    $check->bind_param('si', $email, $userId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        echo json_encode(['ok' => false, 'message' => 'Email is already in use']);
        exit;
    }
    $check->close();
}

$check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
if ($check) {
    $check->bind_param('si', $username, $userId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        echo json_encode(['ok' => false, 'message' => 'Username is already taken']);
        exit;
    }
    $check->close();
}

$sql = 'UPDATE users SET name = ?, username = ?, email = ?, phone = ?, bio = ?, skills = ? WHERE id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('ssssssi', $name, $username, $email, $phone, $bio, $skills, $userId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Could not update profile']);
    exit;
}

$_SESSION['name']  = $name;
$_SESSION['email'] = $email;

echo json_encode(['ok' => true, 'message' => 'Profile updated']);
