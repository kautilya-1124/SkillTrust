<?php
declare(strict_types=1);
ob_start(); // Capture any accidental output (PHP notices, warnings) so JSON is never corrupted

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli) || !db_table_exists($conn, 'coding_questions')) {
    echo json_encode([]);
    exit;
}

$problemColumn = db_column_exists($conn, 'coding_questions', 'problem_statement')
    ? 'problem_statement'
    : 'question';
$timeLimitColumn = db_column_exists($conn, 'coding_questions', 'time_limit')
    ? 'time_limit'
    : 'time_limit_seconds';

$select = [
    'id',
    'title',
    $problemColumn . ' AS problem_statement',
    'category',
    'difficulty',
    $timeLimitColumn . ' AS time_limit_seconds',
];

$where = '';
if (db_column_exists($conn, 'coding_questions', 'status')) {
    $where = " WHERE status = 'active'";
} elseif (db_column_exists($conn, 'coding_questions', 'is_active')) {
    $where = ' WHERE is_active = 1';
}

$sql = 'SELECT ' . implode(', ', $select) . ' FROM coding_questions' . $where . ' ORDER BY id DESC';
$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'problem_statement' => (string) ($row['problem_statement'] ?? ''),
            'category' => (string) ($row['category'] ?? 'Programming'),
            'difficulty' => (string) ($row['difficulty'] ?? 'Medium'),
            'time_limit_seconds' => (float) ($row['time_limit_seconds'] ?? 0),
        ];
    }
    $result->free();
}

ob_end_clean(); // Discard any PHP notices/warnings captured above
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
