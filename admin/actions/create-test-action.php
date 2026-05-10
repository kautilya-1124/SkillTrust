<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
validate_csrf_or_die();

function normalize_correct_option(string $value): ?string
{
    $v = strtolower(trim($value));
    if (in_array($v, ['1', '2', '3', '4'], true)) {
        return $v;
    }
    if (in_array($v, ['option1', 'option_1', 'a'], true)) { return '1'; }
    if (in_array($v, ['option2', 'option_2', 'b'], true)) { return '2'; }
    if (in_array($v, ['option3', 'option_3', 'c'], true)) { return '3'; }
    if (in_array($v, ['option4', 'option_4', 'd'], true)) { return '4'; }
    return null;
}

$editId = (int) ($_POST['edit_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$difficulty = strtolower(trim((string) ($_POST['difficulty'] ?? 'medium')));
$duration = (int) ($_POST['duration'] ?? 0);
$passingScore = (int) ($_POST['passing_score'] ?? 0);
$featured = isset($_POST['featured']) && (string) $_POST['featured'] === '1' ? 1 : 0;
$startInput = trim((string) ($_POST['start_datetime'] ?? ''));
$expiryInput = trim((string) ($_POST['expiry_datetime'] ?? ''));

$toastType = 'error';
$toastMsg = '';

$startDt = $startInput !== '' ? date('Y-m-d H:i:s', strtotime($startInput)) : '';
$endDt = $expiryInput !== '' ? date('Y-m-d H:i:s', strtotime($expiryInput)) : '';

if ($title === '') {
    $toastMsg = 'Title is required.';
} elseif ($category === '') {
    $toastMsg = 'Category is required.';
} elseif (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    $toastMsg = 'Difficulty must be easy, medium, or hard.';
} elseif ($duration <= 0) {
    $toastMsg = 'Duration must be greater than zero.';
} elseif ($passingScore < 0) {
    $toastMsg = 'Passing score cannot be negative.';
} elseif ($startInput === '' || $expiryInput === '' || strtotime($startInput) === false || strtotime($expiryInput) === false) {
    $toastMsg = 'Please provide valid start and expiry schedule.';
} elseif ($startDt === '' || $endDt === '' || strtotime($startDt) >= strtotime($endDt)) {
    $toastMsg = 'Expiry must be later than start.';
} else {
    $conn->begin_transaction();
    try {
        if ($editId > 0) {
            $stmt = $conn->prepare(
                'UPDATE tests
                 SET title = ?, duration = ?, difficulty = ?, featured = ?, category = ?, passing_score = ?,
                     start_datetime = ?, expiry_datetime = ?
                 WHERE id = ?
                 LIMIT 1'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare test update.');
            }
            $stmt->bind_param('sisissssi', $title, $duration, $difficulty, $featured, $category, $passingScore, $startDt, $endDt, $editId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to update test.');
            }
            $stmt->close();
            $testId = $editId;
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO tests (title, duration, difficulty, featured, category, passing_score, start_datetime, expiry_datetime)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare test insert.');
            }
            $stmt->bind_param('sisisiss', $title, $duration, $difficulty, $featured, $category, $passingScore, $startDt, $endDt);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to create test.');
            }
            $testId = (int) $stmt->insert_id;
            $stmt->close();
        }

        $insertedQuestions = 0;
        $csvUploaded = isset($_FILES['questions_csv']) && (int) ($_FILES['questions_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($csvUploaded) {
            $file = $_FILES['questions_csv'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('CSV upload failed.');
            }

            $origName = (string) ($file['name'] ?? '');
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new RuntimeException('Only CSV file is allowed.');
            }

            $uploadDir = __DIR__ . '/../../uploads/csv';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('Could not create CSV upload directory.');
            }

            $savedName = 'questions_' . time() . '_' . bin2hex(random_bytes(4)) . '.csv';
            $savedPath = $uploadDir . DIRECTORY_SEPARATOR . $savedName;
            if (!move_uploaded_file((string) $file['tmp_name'], $savedPath)) {
                throw new RuntimeException('Could not store uploaded CSV.');
            }

            $handle = fopen($savedPath, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Could not read uploaded CSV.');
            }

            $header = fgetcsv($handle);
            if (!is_array($header)) {
                fclose($handle);
                throw new RuntimeException('CSV header is missing.');
            }

            $expected = ['question', 'option1', 'option2', 'option3', 'option4', 'correct_option', 'difficulty'];
            $normalizedHeader = array_map(static fn($h): string => strtolower(trim((string) $h)), $header);
            if ($normalizedHeader !== $expected) {
                fclose($handle);
                throw new RuntimeException('CSV header must be: question, option1, option2, option3, option4, correct_option, difficulty');
            }

            $qStmt = $conn->prepare(
                'INSERT INTO questions (test_id, question, option1, option2, option3, option4, correct_option, difficulty)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$qStmt) {
                fclose($handle);
                throw new RuntimeException('Could not prepare question insert.');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 7) {
                    continue;
                }
                $question = trim((string) $row[0]);
                $option1 = trim((string) $row[1]);
                $option2 = trim((string) $row[2]);
                $option3 = trim((string) $row[3]);
                $option4 = trim((string) $row[4]);
                $correctOption = normalize_correct_option((string) $row[5]);
                $qDifficulty = strtolower(trim((string) $row[6]));

                if (
                    $question === '' || $option1 === '' || $option2 === '' || $option3 === '' || $option4 === '' ||
                    $correctOption === null || !in_array($qDifficulty, ['easy', 'medium', 'hard'], true)
                ) {
                    continue;
                }

                $qStmt->bind_param('isssssss', $testId, $question, $option1, $option2, $option3, $option4, $correctOption, $qDifficulty);
                if ($qStmt->execute()) {
                    $insertedQuestions++;
                }
            }

            fclose($handle);
            $qStmt->close();
        }

        $conn->commit();
        $toastType = 'success';
        $toastMsg = ($editId > 0 ? 'Test updated successfully.' : 'Test created successfully.')
            . ($insertedQuestions > 0 ? ' Imported ' . $insertedQuestions . ' questions.' : '');
    } catch (Throwable $e) {
        $conn->rollback();
        $toastType = 'error';
        $toastMsg = $e->getMessage();
    }
}

$params = ['toast_type' => $toastType, 'toast_msg' => $toastMsg];
if ($editId > 0) {
    $params['edit'] = (string) $editId;
}
$qs = http_build_query(array_filter($params, static fn($v) => $v !== ''));
header('Location: ../create-test.php' . ($qs !== '' ? '?' . $qs : ''));
exit;
