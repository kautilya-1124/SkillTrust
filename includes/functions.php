<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_or_die')) {
    function validate_csrf_or_die(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

if (!function_exists('set_flash_toast')) {
    function set_flash_toast(string $type, string $message): void
    {
        $_SESSION['flash_toast'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('consume_flash_toast')) {
    function consume_flash_toast(): ?array
    {
        if (!isset($_SESSION['flash_toast']) || !is_array($_SESSION['flash_toast'])) {
            return null;
        }
        $toast = $_SESSION['flash_toast'];
        unset($_SESSION['flash_toast']);
        return $toast;
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(mysqli $conn, string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }

        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$result) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->free();

        return $exists;
    }
}

if (!function_exists('db_column_exists')) {
    function db_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$result) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->free();

        return $exists;
    }
}

if (!function_exists('db_fetch_one')) {
    function db_fetch_one(mysqli_stmt $stmt): ?array
    {
        $result = $stmt->get_result();
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all(mysqli_stmt $stmt): array
    {
        $result = $stmt->get_result();
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();

        return $rows;
    }
}

if (!function_exists('format_date_label')) {
    function format_date_label(?string $value, string $format = 'M j, Y'): string
    {
        if ($value === null || trim($value) === '') {
            return 'N/A';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 'N/A';
        }

        return date($format, $timestamp);
    }
}

if (!function_exists('format_datetime_label')) {
    function format_datetime_label(?string $value, string $format = 'M j, Y g:i A'): string
    {
        return format_date_label($value, $format);
    }
}

if (!function_exists('time_ago_label')) {
    function time_ago_label(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'Just now';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 'Just now';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            return (string) floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return (string) floor($diff / 3600) . ' hr ago';
        }
        if ($diff < 604800) {
            return (string) floor($diff / 86400) . ' days ago';
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('normalize_score')) {
    function normalize_score($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}
