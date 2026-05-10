<?php
declare(strict_types=1);

// FIXED: Removed error_reporting(E_ALL) and ini_set('display_errors','1')
// Errors are logged server-side via coding_question_log_error() only.

require_once __DIR__ . '/../includes/auth.php';

validate_csrf_or_die();

header('Content-Type: application/json; charset=utf-8');

function coding_question_respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function coding_question_log_error(string $message): void
{
    error_log('[coding-question-action] ' . $message);
}

function coding_question_is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || (string) ($_POST['ajax'] ?? '') === '1';
}

function coding_question_redirect_error(string $message, int $editId = 0): void
{
    if (coding_question_is_ajax()) {
        coding_question_respond(422, ['success' => false, 'message' => $message]);
    }
    header('Location: ../create-coding-question.php?toast_type=error&toast_msg=' . urlencode($message) . ($editId > 0 ? '&edit=' . $editId : ''));
    exit;
}

$dbCheck = $conn->query('SELECT DATABASE()');
if (!$dbCheck) {
    coding_question_log_error($conn->error);
    coding_question_respond(500, [
        'success' => false,
        'message' => 'Unable to verify database connection.',
        'sql_error' => $conn->error,
    ]);
}
$activeDatabase = (string) (($dbCheck->fetch_row()[0] ?? '') ?: '');
$dbCheck->free();

if (!db_table_exists($conn, 'coding_questions')) {
    coding_question_respond(500, [
        'success' => false,
        'message' => 'coding_questions table does not exist.',
        'active_database' => $activeDatabase,
    ]);
}

$action = trim((string) ($_POST['action'] ?? 'save'));
$editId = max(0, (int) ($_POST['edit_id'] ?? 0));

if ($action === 'delete') {
    $questionId = max(0, (int) ($_POST['question_id'] ?? 0));
    if ($questionId <= 0) {
        coding_question_respond(422, ['success' => false, 'message' => 'Invalid question id.']);
    }

    $deleteStmt = $conn->prepare('DELETE FROM coding_questions WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
        coding_question_log_error($conn->error);
        coding_question_respond(500, [
            'success' => false,
            'message' => 'Delete prepare failed.',
            'sql_error' => $conn->error,
        ]);
    }
    $deleteStmt->bind_param('i', $questionId);
    if (!$deleteStmt->execute()) {
        $error = $deleteStmt->error;
        coding_question_log_error($error);
        $deleteStmt->close();
        coding_question_respond(500, [
            'success' => false,
            'message' => 'Delete failed.',
            'sql_error' => $error,
        ]);
    }
    $deleteStmt->close();

    if (coding_question_is_ajax()) {
        coding_question_respond(200, ['success' => true, 'message' => 'Coding question deleted.']);
    }
    header('Location: ../manage-coding-tests.php?toast_type=success&toast_msg=' . urlencode('Coding question deleted.'));
    exit;
}

$title = trim((string) ($_POST['title'] ?? ''));
$problemStatement = trim((string) ($_POST['problem_statement'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$difficulty = strtolower(trim((string) ($_POST['difficulty'] ?? 'medium')));
$timeLimit = trim((string) ($_POST['time_limit'] ?? ''));
$memoryLimit = trim((string) ($_POST['memory_limit'] ?? ''));
$inputFormat = trim((string) ($_POST['input_format'] ?? ''));
$outputFormat = trim((string) ($_POST['output_format'] ?? ''));
$sampleInput = trim((string) ($_POST['sample_input'] ?? ''));
$sampleOutput = trim((string) ($_POST['sample_output'] ?? ''));
$allowedLanguages = $_POST['allowed_languages'] ?? [];

if (
    $title === '' || $problemStatement === '' || $category === '' || $timeLimit === '' ||
    $memoryLimit === '' || $inputFormat === '' || $outputFormat === '' || $sampleInput === '' || $sampleOutput === ''
) {
    coding_question_redirect_error('All required fields must be filled.', $editId);
}

if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    coding_question_redirect_error('Difficulty must be Easy, Medium, or Hard.', $editId);
}

if (!is_numeric($timeLimit) || (float) $timeLimit <= 0) {
    coding_question_redirect_error('Time limit must be a positive number.', $editId);
}

if (!ctype_digit($memoryLimit) || (int) $memoryLimit <= 0) {
    coding_question_redirect_error('Memory limit must be a positive integer.', $editId);
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
    coding_question_redirect_error('Select at least one allowed language.', $editId);
}

$allowedLanguagesJson = json_encode($normalizedLanguages, JSON_UNESCAPED_UNICODE);
if ($allowedLanguagesJson === false) {
    coding_question_respond(500, [
        'success' => false,
        'message' => 'Unable to encode allowed languages.',
        'active_database' => $activeDatabase,
    ]);
}

$columnMap = [
    'title' => $title,
    db_column_exists($conn, 'coding_questions', 'problem_statement') ? 'problem_statement' : 'question' => $problemStatement,
    'category' => $category,
    'difficulty' => $difficulty,
    (db_column_exists($conn, 'coding_questions', 'time_limit') ? 'time_limit' : 'time_limit_seconds') => (float) $timeLimit,
    (db_column_exists($conn, 'coding_questions', 'memory_limit') ? 'memory_limit' : 'memory_limit_kb') => (int) $memoryLimit,
];

// BUG FIX: is_active was never set on INSERT — new questions got is_active = 0 (DB default).
// coding-test.php and get-coding-tests.php both query WHERE is_active = 1,
// so every newly created question was invisible to students.
// Only add it on INSERT (editId == 0); we don't want to force-activate on every edit.
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
foreach ($columnMap as $column => $value) {
    if (is_float($value)) {
        $types .= 'd';
    } elseif (is_int($value)) {
        $types .= 'i';
    } else {
        $types .= 's';
    }
    $values[] = $value;
}

if ($editId > 0) {
    $assignments = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($columnMap)));
    $sql = 'UPDATE coding_questions SET ' . $assignments . ' WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        coding_question_log_error($conn->error);
        coding_question_respond(500, [
            'success' => false,
            'message' => 'Update prepare failed.',
            'sql_error' => $conn->error,
            'active_database' => $activeDatabase,
        ]);
    }
    $types .= 'i';
    $values[] = $editId;
} else {
    $sql = 'INSERT INTO coding_questions (' . implode(', ', array_keys($columnMap)) . ')
            VALUES (' . implode(', ', array_fill(0, count($columnMap), '?')) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        coding_question_log_error($conn->error);
        coding_question_respond(500, [
            'success' => false,
            'message' => 'Insert prepare failed.',
            'sql_error' => $conn->error,
            'active_database' => $activeDatabase,
        ]);
    }
}

$stmt->bind_param($types, ...$values);
if (!$stmt->execute()) {
    $error = $stmt->error;
    coding_question_log_error($error);
    $stmt->close();
    coding_question_respond(500, [
        'success' => false,
        'message' => $editId > 0 ? 'Update failed.' : 'Insert failed.',
        'sql_error' => $error,
        'active_database' => $activeDatabase,
    ]);
}

$newId = $editId > 0 ? $editId : (int) $stmt->insert_id;
$stmt->close();

$successMessage = $editId > 0 ? 'Coding question updated successfully.' : 'Coding question created successfully.';

if (coding_question_is_ajax()) {
    coding_question_respond(200, [
        'success' => true,
        'message' => $successMessage,
        'id' => $newId,
        'redirect' => '../manage-coding-tests.php?toast_type=success&toast_msg=' . urlencode($successMessage),
        'active_database' => $activeDatabase,
    ]);
}

header('Location: ../manage-coding-tests.php?toast_type=success&toast_msg=' . urlencode($successMessage));
exit;
