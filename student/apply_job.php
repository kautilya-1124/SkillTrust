<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/whatsapp.php';

header('Content-Type: application/json; charset=utf-8');

function apply_job_respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    apply_job_respond(401, [
        'success' => false,
        'error' => 'Please sign in to apply for jobs.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apply_job_respond(405, [
        'success' => false,
        'error' => 'POST request required.',
    ]);
}

validate_csrf_or_die();

$userId = (int) $_SESSION['user_id'];
$jobId = (int) ($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    apply_job_respond(422, [
        'success' => false,
        'error' => 'Invalid job selected.',
    ]);
}

$requiredTables = ['jobs', 'applications', 'results', 'users', 'recruiters'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

if ($missingTables !== []) {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Application system is not fully configured yet.',
        'details' => [
            'missing_tables' => $missingTables,
        ],
    ]);
}

$jobMinAverageColumn = '';
if (db_column_exists($conn, 'jobs', 'min_average_score')) {
    $jobMinAverageColumn = 'min_average_score';
} elseif (db_column_exists($conn, 'jobs', 'min_avg_score')) {
    $jobMinAverageColumn = 'min_avg_score';
}

if ($jobMinAverageColumn === '') {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Jobs table is missing a supported minimum score column.',
    ]);
}

$hasRecruiterStatusColumn = db_column_exists($conn, 'recruiters', 'status');
$hasAverageSnapshotColumn = db_column_exists($conn, 'applications', 'average_score_snapshot');
$hasRequiredTestColumns = db_column_exists($conn, 'jobs', 'required_test_id') && db_column_exists($conn, 'jobs', 'min_test_score');
$hasJobRequiredTestsTable = db_table_exists($conn, 'job_required_tests');
$hasUserPhoneColumn = db_column_exists($conn, 'users', 'phone');

$jobSelect = [
    'j.id',
    'j.recruiter_id',
    'j.title',
    'j.expiry_date',
    'j.' . $jobMinAverageColumn . ' AS min_average_score',
    'r.company_name',
];

if ($hasRecruiterStatusColumn) {
    $jobSelect[] = 'r.status AS recruiter_status';
}

if ($hasRequiredTestColumns) {
    $jobSelect[] = 'j.required_test_id';
    $jobSelect[] = 'j.min_test_score';
}

$jobSql = sprintf(
    'SELECT %s
     FROM jobs j
     INNER JOIN recruiters r ON r.id = j.recruiter_id
     WHERE j.id = ? AND j.expiry_date >= CURDATE()
     LIMIT 1',
    implode(', ', $jobSelect)
);

$jobStmt = $conn->prepare($jobSql);
if (!$jobStmt) {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Unable to validate the selected job right now.',
    ]);
}

$jobStmt->bind_param('i', $jobId);
$jobStmt->execute();
$job = db_fetch_one($jobStmt);
$jobStmt->close();

if (!$job) {
    apply_job_respond(404, [
        'success' => false,
        'error' => 'Job not found or already expired.',
    ]);
}

$recruiterStatus = strtolower(trim((string) ($job['recruiter_status'] ?? 'approved')));
if ($hasRecruiterStatusColumn && $recruiterStatus !== 'approved') {
    apply_job_respond(403, [
        'success' => false,
        'error' => 'Applications for this job are currently unavailable.',
    ]);
}

$duplicateStmt = $conn->prepare('SELECT id, status FROM applications WHERE job_id = ? AND user_id = ? LIMIT 1');
if (!$duplicateStmt) {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Unable to verify your existing application status.',
    ]);
}

$duplicateStmt->bind_param('ii', $jobId, $userId);
$duplicateStmt->execute();
$duplicate = db_fetch_one($duplicateStmt);
$duplicateStmt->close();

if ($duplicate) {
    apply_job_respond(409, [
        'success' => false,
        'error' => 'You have already applied for this job.',
        'details' => [
            'application_status' => (string) ($duplicate['status'] ?? 'applied'),
            'job_id' => $jobId,
        ],
    ]);
}

$avgStmt = $conn->prepare('SELECT AVG(score) AS avg_score FROM results WHERE user_id = ?');
if (!$avgStmt) {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Unable to validate your average score.',
    ]);
}

$avgStmt->bind_param('i', $userId);
$avgStmt->execute();
$avgRow = db_fetch_one($avgStmt);
$avgStmt->close();

$avgScore = normalize_score($avgRow['avg_score'] ?? 0);
$requiredAverage = normalize_score($job['min_average_score'] ?? 0);

if ($avgScore < $requiredAverage) {
    apply_job_respond(422, [
        'success' => false,
        'error' => 'Not eligible for this job yet.',
        'details' => [
            'reason' => 'average_score',
            'your_average_score' => $avgScore,
            'required_average_score' => $requiredAverage,
            'job_id' => $jobId,
        ],
    ]);
}

$userSelect = ['id', 'name'];
if ($hasUserPhoneColumn) {
    $userSelect[] = 'phone';
}

$userStmt = $conn->prepare(
    sprintf(
        'SELECT %s FROM users WHERE id = ? LIMIT 1',
        implode(', ', $userSelect)
    )
);
if (!$userStmt) {
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Unable to load your profile right now.',
    ]);
}

$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = db_fetch_one($userStmt);
$userStmt->close();

if (!$user) {
    apply_job_respond(404, [
        'success' => false,
        'error' => 'Student profile not found.',
    ]);
}

$requiredTests = [];
if ($hasJobRequiredTestsTable) {
    $requiredTestsStmt = $conn->prepare(
        'SELECT jrt.test_id, jrt.min_score, t.title
         FROM job_required_tests jrt
         INNER JOIN tests t ON t.id = jrt.test_id
         WHERE jrt.job_id = ?
         ORDER BY jrt.id ASC'
    );
    if ($requiredTestsStmt) {
        $requiredTestsStmt->bind_param('i', $jobId);
        $requiredTestsStmt->execute();
        $requiredTests = array_map(
            static function (array $row): array {
                return [
                    'test_id' => (int) ($row['test_id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'min_score' => normalize_score($row['min_score'] ?? 0),
                ];
            },
            db_fetch_all($requiredTestsStmt)
        );
        $requiredTestsStmt->close();
    }
} elseif ($hasRequiredTestColumns && (int) ($job['required_test_id'] ?? 0) > 0) {
    $requiredTestStmt = $conn->prepare('SELECT id, title FROM tests WHERE id = ? LIMIT 1');
    if ($requiredTestStmt) {
        $requiredTestId = (int) $job['required_test_id'];
        $requiredTestStmt->bind_param('i', $requiredTestId);
        $requiredTestStmt->execute();
        $requiredTest = db_fetch_one($requiredTestStmt);
        $requiredTestStmt->close();

        if ($requiredTest) {
            $requiredTests[] = [
                'test_id' => (int) ($requiredTest['id'] ?? 0),
                'title' => (string) ($requiredTest['title'] ?? ''),
                'min_score' => normalize_score($job['min_test_score'] ?? 0),
            ];
        }
    }
}

$conn->begin_transaction();

try {
    $insertSql = $hasAverageSnapshotColumn
        ? 'INSERT INTO applications (job_id, user_id, average_score_snapshot, status, applied_at)
           VALUES (?, ?, ?, "applied", NOW())'
        : 'INSERT INTO applications (job_id, user_id, status, applied_at)
           VALUES (?, ?, "applied", NOW())';

    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare the application insert.');
    }

    if ($hasAverageSnapshotColumn) {
        $insertStmt->bind_param('iid', $jobId, $userId, $avgScore);
    } else {
        $insertStmt->bind_param('ii', $jobId, $userId);
    }
    $insertStmt->execute();
    $applicationId = (int) $conn->insert_id;
    $insertStmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $exception) {
    $conn->rollback();

    if ((int) $exception->getCode() === 1062) {
        apply_job_respond(409, [
            'success' => false,
            'error' => 'You have already applied for this job.',
            'details' => [
                'job_id' => $jobId,
            ],
        ]);
    }

    apply_job_respond(500, [
        'success' => false,
        'error' => 'Application failed. Please try again.',
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    apply_job_respond(500, [
        'success' => false,
        'error' => 'Application failed. Please try again.',
    ]);
}

set_flash_toast('success', 'Application submitted successfully.');

$notification = null;
$userPhone = trim((string) ($user['phone'] ?? ''));
if ($userPhone !== '' && $requiredTests !== []) {
    $testLines = array_map(
        static function (array $test): string {
            return '- ' . trim((string) ($test['title'] ?? 'Test')) . ' (minimum score: ' . number_format((float) ($test['min_score'] ?? 0), 2) . '%)';
        },
        $requiredTests
    );

    $notificationMessage = 'Hello ' . trim((string) ($user['name'] ?? 'Candidate')) . ",\n\n"
        . 'A test has been assigned for your application to ' . trim((string) ($job['title'] ?? 'SkillTrust')) . ".\n"
        . "Required test details:\n" . implode("\n", $testLines) . "\n\n"
        . "Please complete it as soon as possible.\n\n"
        . '- SkillTrust';

    $notification = sendWhatsApp($userPhone, $notificationMessage, [
        'db' => $conn,
        'context_type' => 'test_assigned',
        'related_id' => (string) $applicationId,
        'reference' => 'APP_' . $applicationId,
    ]);
}

apply_job_respond(200, [
    'success' => true,
    'message' => 'Application submitted. Track updates from Applied Jobs.',
    'data' => [
        'application_id' => $applicationId,
        'job_id' => $jobId,
        'job_title' => (string) ($job['title'] ?? ''),
        'company_name' => (string) ($job['company_name'] ?? ''),
        'average_score_snapshot' => $avgScore,
        'required_average_score' => $requiredAverage,
        'required_tests' => $requiredTests,
        'status' => 'applied',
        'whatsapp_notification' => $notification,
        'redirect_url' => 'applied_jobs.php',
    ],
]);
