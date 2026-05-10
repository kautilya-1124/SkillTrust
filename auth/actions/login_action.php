<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$email    = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ../login.php?error=' . urlencode('Email and password are required.'));
    exit;
}

$stmt = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    header('Location: ../login.php?error=' . urlencode('Database error. Please try again.'));
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, (string) $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['name']    = (string) ($user['name'] ?? '');
    $_SESSION['email']   = (string) ($user['email'] ?? '');
    header('Location: ../../student/dashboard.php');
    exit;
}

header('Location: ../login.php?error=' . urlencode('Invalid email or password.'));
exit;
