<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

skilltrust_env_load(dirname(__DIR__));

$host = (string) (skilltrust_env_get('DB_HOST', 'localhost') ?? 'localhost');
$user = (string) (skilltrust_env_get('DB_USERNAME', 'root') ?? 'root');
$pass = (string) (skilltrust_env_get('DB_PASSWORD', '') ?? '');
$db   = (string) (skilltrust_env_get('DB_DATABASE', 'skilltrust') ?? 'skilltrust');
$port = (int) (skilltrust_env_get('DB_PORT', '3306') ?? '3306');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    // If we're in an API/JSON context, emit clean JSON instead of raw text
    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'db_connect_failed']);
        exit;
    }
    die('Database Error: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
