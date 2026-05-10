<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

unset(
    $_SESSION['recruiter_id'],
    $_SESSION['recruiter_company'],
    $_SESSION['recruiter_name'],
    $_SESSION['recruiter_email'],
    $_SESSION['recruiter_status'],
    $_SESSION['recruiter_login_csrf']
);

header('Location: login.php');
exit;
