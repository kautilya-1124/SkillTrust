<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../student/profile.php');
    exit;
}

$confirm = (string) ($_POST['confirm'] ?? '');
if ($confirm !== '1') {
    header('Location: ../student/profile.php');
    exit;
}

$delResults = $conn->prepare('DELETE FROM results WHERE user_id = ?');
if ($delResults) {
    $delResults->bind_param('i', $userId);
    $delResults->execute();
    $delResults->close();
}

$delUser = $conn->prepare('DELETE FROM users WHERE id = ?');
if ($delUser) {
    $delUser->bind_param('i', $userId);
    $delUser->execute();
    $delUser->close();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();

header('Location: ../auth/login.php');
exit;
