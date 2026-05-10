<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Use in user routes.
 */
if (!function_exists('require_user_login')) {
    function require_user_login(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../auth/login.php');
            exit;
        }
    }
}

/**
 * Use in admin routes.
 */
if (!function_exists('require_admin_login')) {
    function require_admin_login(): void
    {
        if (!isset($_SESSION['admin_id'])) {
            header('Location: ../admin/login.php');
            exit;
        }
    }
}

/**
 * Use in recruiter routes.
 */
if (!function_exists('require_recruiter_login')) {
    function require_recruiter_login(): void
    {
        if (!isset($_SESSION['recruiter_id'])) {
            header('Location: ../recruiter/login.php');
            exit;
        }
    }
}

if (!function_exists('current_recruiter_id')) {
    function current_recruiter_id(): int
    {
        return (int) ($_SESSION['recruiter_id'] ?? 0);
    }
}

if (!function_exists('current_recruiter_display_name')) {
    function current_recruiter_display_name(): string
    {
        $name = (string) ($_SESSION['recruiter_name'] ?? $_SESSION['recruiter_company'] ?? 'Recruiter');
        return trim($name) !== '' ? $name : 'Recruiter';
    }
}
