<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// ── CSRF check ───────────────────────────────────────────────────────────────
validate_csrf_or_die();

// ── Collect & sanitise inputs ─────────────────────────────────────────────────
$name     = trim((string) ($_POST['name']     ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email    = strtolower(trim((string) ($_POST['email']    ?? '')));
$password = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['confirm']  ?? '');
$phone    = preg_replace('/\D+/', '', trim((string) ($_POST['phone'] ?? '')));

// ── Server-side validation ────────────────────────────────────────────────────
$errors = [];

if ($name === '') {
    $errors[] = 'Full name is required.';
}

if (!preg_match('/^[a-zA-Z0-9_]{3,18}$/', $username)) {
    $errors[] = 'Username must be 3–18 characters (letters, digits, _ only).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}

if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if ($phone !== '' && strlen($phone) < 7) {
    $errors[] = 'Enter a valid phone number.';
}

if ($errors !== []) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: ../register.php?error={$msg}");
    exit;
}

// ── Check duplicate email ─────────────────────────────────────────────────────
$checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$checkStmt) {
    header('Location: ../register.php?error=Server+error.+Please+try+again.');
    exit;
}
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $checkStmt->close();
    header('Location: ../register.php?error=An+account+with+this+email+already+exists.');
    exit;
}
$checkStmt->close();

// ── Check duplicate username ──────────────────────────────────────────────────
if ($username !== '') {
    $unStmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if ($unStmt) {
        $unStmt->bind_param('s', $username);
        $unStmt->execute();
        $unStmt->store_result();
        if ($unStmt->num_rows > 0) {
            $unStmt->close();
            header('Location: ../register.php?error=Username+is+already+taken.');
            exit;
        }
        $unStmt->close();
    }
}

// ── Hash password ─────────────────────────────────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// ── Insert user — prepared statement, no SQL injection ───────────────────────
// Build query based on whether username column exists
$hasUsername = db_column_exists($conn, 'users', 'username');
$hasPhone    = db_column_exists($conn, 'users', 'phone');

if ($hasUsername && $hasPhone) {
    $stmt = $conn->prepare(
        'INSERT INTO users (name, username, email, password, phone) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        header('Location: ../register.php?error=Server+error.+Please+try+again.');
        exit;
    }
    $phoneVal = $phone !== '' ? $phone : null;
    $stmt->bind_param('sssss', $name, $username, $email, $passwordHash, $phoneVal);

} elseif ($hasPhone) {
    $stmt = $conn->prepare(
        'INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        header('Location: ../register.php?error=Server+error.+Please+try+again.');
        exit;
    }
    $phoneVal = $phone !== '' ? $phone : null;
    $stmt->bind_param('ssss', $name, $email, $passwordHash, $phoneVal);

} else {
    // Fallback — only base columns
    $stmt = $conn->prepare(
        'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
    );
    if (!$stmt) {
        header('Location: ../register.php?error=Server+error.+Please+try+again.');
        exit;
    }
    $stmt->bind_param('sss', $name, $email, $passwordHash);
}

if ($stmt->execute()) {
    $stmt->close();
    header('Location: ../login.php?success=Account+created!+You+can+now+log+in.');
} else {
    $stmt->close();
    header('Location: ../register.php?error=Could+not+create+account.+Please+try+again.');
}
exit;
