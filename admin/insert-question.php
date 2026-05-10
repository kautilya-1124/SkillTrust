<?php
declare(strict_types=1);

// FIXED: Removed error_reporting(E_ALL) and ini_set('display_errors','1')
// Errors are logged server-side only — never exposed to the browser.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Invalid request method.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])
) {
    http_response_code(419);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Invalid CSRF token.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'DB connection failed',
        'sql_error' => $conn ? $conn->connect_error : 'Database connection unavailable.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tableExists = db_table_exists($conn, 'coding_questions');
if (!$tableExists) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'coding_questions table does not exist.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$title = trim((string) ($_POST['title'] ?? ''));
$problemStatement = trim((string) ($_POST['problem_statement'] ?? ''));
$problem = $problemStatement;
$category = trim((string) ($_POST['category'] ?? ''));
$difficulty = strtolower(trim((string) ($_POST['difficulty'] ?? 'medium')));
$timeLimit = trim((string) ($_POST['time_limit'] ?? ''));
$memoryLimit = trim((string) ($_POST['memory_limit'] ?? ''));
// FIXED: preserve float precision — was (int) round((float) $timeLimit) which truncated 1.5 → 2
$time_limit = (float) $timeLimit;
$memory_limit = (int) $memoryLimit;
$inputFormat = trim((string) ($_POST['input_format'] ?? ''));
$outputFormat = trim((string) ($_POST['output_format'] ?? ''));
$sampleInput = trim((string) ($_POST['sample_input'] ?? ''));
$sampleOutput = trim((string) ($_POST['sample_output'] ?? ''));
$allowedLanguages = $_POST['allowed_languages'] ?? [];
$editId = max(0, (int) ($_POST['edit_id'] ?? 0));

if ($title === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Title required',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    $problemStatement === '' || $category === '' || $timeLimit === '' || $memoryLimit === '' ||
    $inputFormat === '' || $outputFormat === '' || $sampleInput === '' || $sampleOutput === ''
) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'All required fields must be filled.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Difficulty must be Easy, Medium, or Hard.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_numeric($timeLimit) || (float) $timeLimit <= 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Time limit must be a positive number.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ctype_digit($memoryLimit) || (int) $memoryLimit <= 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Memory limit must be a positive integer.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$normalizedLanguages = [];
if (is_array($allowedLanguages)) {
    foreach ($allowedLanguages as $language) {
        $value = strtolower(trim((string) $language));
        if ($value !== '') {
            $normalizedLanguages[] = $value;
        }
    }
}
$normalizedLanguages = array_values(array_unique($normalizedLanguages));

if ($normalizedLanguages === []) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Select at least one allowed language.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedLanguagesJson = json_encode($normalizedLanguages, JSON_UNESCAPED_UNICODE);
if ($allowedLanguagesJson === false) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Unable to encode allowed languages.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$columnMap = [
    'title' => $title,
    db_column_exists($conn, 'coding_questions', 'problem_statement') ? 'problem_statement' : 'question' => $problemStatement,
    'category' => $category,
    'difficulty' => $difficulty,
    db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds' => (float) $timeLimit,
    db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb' => (int) $memoryLimit,
];

// BUG FIX: is_active was never inserted — questions got is_active = 0 by default.
// Both coding-test.php and get-coding-tests.php query WHERE is_active = 1,
// so every question created through this endpoint was invisible to students.
if (db_column_exists($conn, 'coding_questions', 'is_active') && $editId === 0) {
    $columnMap['is_active'] = 1;
}

if (db_column_exists($conn, 'coding_questions', 'input_format')) {
    $columnMap['input_format'] = $inputFormat;
}
if (db_column_exists($conn, 'coding_questions', 'output_format')) {
    $columnMap['output_format'] = $outputFormat;
}
if (db_column_exists($conn, 'coding_questions', 'sample_input')) {
    $columnMap['sample_input'] = $sampleInput;
}
if (db_column_exists($conn, 'coding_questions', 'sample_output')) {
    $columnMap['sample_output'] = $sampleOutput;
}
if (db_column_exists($conn, 'coding_questions', 'allowed_languages')) {
    $columnMap['allowed_languages'] = $allowedLanguagesJson;
}

$types = '';
$values = [];
foreach ($columnMap as $value) {
    if (is_float($value)) {
        $types .= 'd';
    } elseif (is_int($value)) {
        $types .= 'i';
    } else {
        $types .= 's';
    }
    $values[] = $value;
}

$hasLegacyInsertColumns = false;
if ($editId > 0) {
    $assignments = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($columnMap)));
    $sql = 'UPDATE coding_questions SET ' . $assignments . ' WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Update prepare failed.',
            'sql_error' => $conn->error,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $types .= 'i';
    $values[] = $editId;
} else {
    $hasLegacyInsertColumns =
        db_column_exists($conn, 'coding_questions', 'problem_statement') &&
        db_column_exists($conn, 'coding_questions', 'time_limit_seconds') &&
        db_column_exists($conn, 'coding_questions', 'memory_limit_kb');

    if ($hasLegacyInsertColumns) {
        $stmt = $conn->prepare(
            'INSERT INTO coding_questions
            (title, problem_statement, category, difficulty, time_limit_seconds, memory_limit_kb)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Insert prepare failed.',
                'sql_error' => $conn->error,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // FIXED: type string was 'ssssii' — 5th param is float time_limit, must be 'd' not 'i'
        $stmt->bind_param('ssssdi', $title, $problem, $category, $difficulty, $time_limit, $memory_limit);
    } else {
        $sql = 'INSERT INTO coding_questions (' . implode(', ', array_keys($columnMap)) . ')
                VALUES (' . implode(', ', array_fill(0, count($columnMap), '?')) . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Insert prepare failed.',
                'sql_error' => $conn->error,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if ($editId > 0 || !$hasLegacyInsertColumns) {
    $stmt->bind_param($types, ...$values);
}

if ($stmt->execute()) {
    $newId = $editId > 0 ? $editId : (int) $stmt->insert_id;
    $stmt->close();
    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => $editId > 0 ? 'Coding question updated successfully.' : 'Inserted Successfully',
        'question_id' => $newId,
        'redirect' => 'manage-coding-tests.php?toast_type=success&toast_msg=' . urlencode($editId > 0 ? 'Coding question updated successfully.' : 'Coding question created successfully.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$error = $stmt->error;
$stmt->close();
http_response_code(500);
echo json_encode([
    'status' => 'error',
    'success' => false,
    'message' => $error !== '' ? $error : 'Insert failed.',
    'sql_error' => $error,
], JSON_UNESCAPED_UNICODE);
exit;
