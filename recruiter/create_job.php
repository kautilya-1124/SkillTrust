<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_recruiter_login();

$recruiterId = current_recruiter_id();
$editingJobId = max(0, (int) ($_GET['id'] ?? 0));
$isEditMode = false;
$toast = consume_flash_toast();
$recruiterName = (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name());
$companyName = (string) ($_SESSION['recruiter_company'] ?? $recruiterName);
$recruiterEmail = (string) ($_SESSION['recruiter_email'] ?? '');
$recruiterStatus = strtolower(trim((string) ($_SESSION['recruiter_status'] ?? 'approved')));
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');

$requiredTables = ['recruiters', 'jobs', 'tests'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}
$schemaReady = $missingTables === [];
$jobMinAverageColumn = '';

if ($schemaReady) {
    if (db_column_exists($conn, 'jobs', 'min_average_score')) {
        $jobMinAverageColumn = 'min_average_score';
    } elseif (db_column_exists($conn, 'jobs', 'min_avg_score')) {
        $jobMinAverageColumn = 'min_avg_score';
    }
}

if ($schemaReady) {
    $nameColumn = db_column_exists($conn, 'recruiters', 'recruiter_name') ? 'recruiter_name' : 'contact_name';
    $profileStmt = $conn->prepare(
        sprintf(
            'SELECT company_name, email, status, %s AS recruiter_display_name FROM recruiters WHERE id = ? LIMIT 1',
            $nameColumn
        )
    );
    if ($profileStmt) {
        $profileStmt->bind_param('i', $recruiterId);
        $profileStmt->execute();
        $profile = db_fetch_one($profileStmt);
        $profileStmt->close();

        if ($profile) {
            $recruiterName = trim((string) ($profile['recruiter_display_name'] ?? '')) !== ''
                ? (string) $profile['recruiter_display_name']
                : $recruiterName;
            $companyName = trim((string) ($profile['company_name'] ?? '')) !== ''
                ? (string) $profile['company_name']
                : $companyName;
            $recruiterEmail = (string) ($profile['email'] ?? $recruiterEmail);
            $recruiterStatus = strtolower(trim((string) ($profile['status'] ?? $recruiterStatus)));

            $_SESSION['recruiter_name'] = $recruiterName;
            $_SESSION['recruiter_company'] = $companyName;
            $_SESSION['recruiter_email'] = $recruiterEmail;
            $_SESSION['recruiter_status'] = $recruiterStatus;
            $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');
        }
    }
}

$statusMeta = [
    'approved' => ['Approved', 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-200'],
    'pending' => ['Pending approval', 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-200'],
    'blocked' => ['Blocked', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/20 dark:bg-rose-500/10 dark:text-rose-200'],
    'rejected' => ['Rejected', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/20 dark:bg-rose-500/10 dark:text-rose-200'],
];
[$statusLabel, $statusClass] = $statusMeta[$recruiterStatus] ?? ['Unknown', 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200'];

$postingLocked = in_array($recruiterStatus, ['pending', 'blocked', 'rejected'], true);
$postingLockMessage = '';
if ($recruiterStatus === 'pending') {
    $postingLockMessage = 'Your recruiter account is pending admin approval. You can review the form, but job publishing stays locked until approval.';
} elseif (in_array($recruiterStatus, ['blocked', 'rejected'], true)) {
    $postingLockMessage = 'This recruiter account cannot publish jobs right now. Please contact the admin if you think this is a mistake.';
}

$legacyJobTestColumns = $schemaReady
    && db_column_exists($conn, 'jobs', 'required_test_id')
    && db_column_exists($conn, 'jobs', 'min_test_score');
$supportsMultipleRequiredTests = $schemaReady && db_table_exists($conn, 'job_required_tests');

$tests = [];
$validTestIds = [];
if (db_table_exists($conn, 'tests')) {
    $testStmt = $conn->prepare('SELECT id, title, category FROM tests ORDER BY title ASC');
    if ($testStmt) {
        $testStmt->execute();
        $tests = db_fetch_all($testStmt);
        $testStmt->close();
    }
}
foreach ($tests as $test) {
    $validTestIds[(int) ($test['id'] ?? 0)] = true;
}

$jobStats = [
    'total_jobs' => 0,
    'active_jobs' => 0,
    'avg_threshold' => 0.0,
];
$recentJobs = [];
$jobTitleOptions = [
    'Frontend Developer',
    'Backend Developer',
    'Full Stack Developer',
    'PHP Developer',
    'Laravel Developer',
    'React Developer',
    'Node.js Developer',
    'Python Developer',
    'Java Developer',
    'Mobile App Developer',
    'UI/UX Designer',
    'QA Engineer',
    'DevOps Engineer',
    'Data Analyst',
    'Project Manager',
    'Software Engineer',
    'Senior Frontend Engineer',
    'Senior Backend Engineer',
    'Technical Support Engineer',
    'Business Analyst',
];

if ($schemaReady) {
    $statsStmt = $conn->prepare(
        sprintf(
            'SELECT COUNT(*) AS total_jobs,
                    SUM(CASE WHEN expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS active_jobs,
                    AVG(%s) AS avg_threshold
             FROM jobs
             WHERE recruiter_id = ?',
            $jobMinAverageColumn !== '' ? $jobMinAverageColumn : '0'
        )
    );
    if ($statsStmt) {
        $statsStmt->bind_param('i', $recruiterId);
        $statsStmt->execute();
        $statsRow = db_fetch_one($statsStmt);
        $statsStmt->close();
        if ($statsRow) {
            $jobStats['total_jobs'] = (int) ($statsRow['total_jobs'] ?? 0);
            $jobStats['active_jobs'] = (int) ($statsRow['active_jobs'] ?? 0);
            $jobStats['avg_threshold'] = normalize_score($statsRow['avg_threshold'] ?? 0);
        }
    }

    if ($supportsMultipleRequiredTests) {
        $recentStmt = $conn->prepare(
            'SELECT
                j.id,
                j.title,
                j.%1$s AS min_average_score,
                j.expiry_date,
                j.created_at,
                (
                    SELECT COUNT(*)
                    FROM job_required_tests jrt
                    WHERE jrt.job_id = j.id
                ) AS required_tests_count,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(t.title, " >= ", FORMAT(jrt.min_score, 2))
                        ORDER BY t.title
                        SEPARATOR " | "
                    )
                    FROM job_required_tests jrt
                    INNER JOIN tests t ON t.id = jrt.test_id
                    WHERE jrt.job_id = j.id
                ) AS required_tests_summary
             FROM jobs j
             WHERE j.recruiter_id = ?
             ORDER BY j.created_at DESC
             LIMIT 3',
            $jobMinAverageColumn
        );
    } elseif ($legacyJobTestColumns) {
        $recentStmt = $conn->prepare(
            sprintf(
                'SELECT j.id, j.title, j.%1$s AS min_average_score, j.required_test_id, j.min_test_score, j.expiry_date, j.created_at, t.title AS required_test_title
                 FROM jobs j
                 LEFT JOIN tests t ON t.id = j.required_test_id
                 WHERE j.recruiter_id = ?
                 ORDER BY j.created_at DESC
                 LIMIT 3',
                $jobMinAverageColumn
            )
        );
    } else {
        $recentStmt = $conn->prepare(
            sprintf(
                'SELECT id, title, %1$s AS min_average_score, expiry_date, created_at
                 FROM jobs
                 WHERE recruiter_id = ?
                 ORDER BY created_at DESC
                 LIMIT 3',
                $jobMinAverageColumn
            )
        );
    }
    if ($recentStmt) {
        $recentStmt->bind_param('i', $recruiterId);
        $recentStmt->execute();
        $recentJobs = db_fetch_all($recentStmt);
        $recentStmt->close();
    }
}

$values = [
    'title' => '',
    'title_custom' => '',
    'description' => '',
    'min_average_score' => '',
    'required_tests' => [],
    'expiry_date' => '',
];
$existingJob = null;
$errors = [];
$pageToast = null;

if ($schemaReady && $jobMinAverageColumn !== '' && $editingJobId > 0) {
    $editSelectColumns = [
        'j.id',
        'j.title',
        'j.description',
        'j.expiry_date',
        sprintf('j.%s AS min_average_score', $jobMinAverageColumn),
    ];
    if ($legacyJobTestColumns) {
        $editSelectColumns[] = 'j.required_test_id';
        $editSelectColumns[] = 'j.min_test_score';
    }

    $editJobStmt = $conn->prepare(
        sprintf(
            'SELECT %s
             FROM jobs j
             WHERE j.id = ? AND j.recruiter_id = ?
             LIMIT 1',
            implode(', ', $editSelectColumns)
        )
    );
    if ($editJobStmt) {
        $editJobStmt->bind_param('ii', $editingJobId, $recruiterId);
        $editJobStmt->execute();
        $existingJob = db_fetch_one($editJobStmt);
        $editJobStmt->close();
    }

    if ($existingJob) {
        $isEditMode = true;
        $values['title'] = (string) ($existingJob['title'] ?? '');
        $values['description'] = (string) ($existingJob['description'] ?? '');
        $values['min_average_score'] = isset($existingJob['min_average_score'])
            ? number_format((float) $existingJob['min_average_score'], 2, '.', '')
            : '';
        $values['expiry_date'] = (string) ($existingJob['expiry_date'] ?? '');

        if ($supportsMultipleRequiredTests) {
            $requiredTestsStmt = $conn->prepare(
                'SELECT test_id, min_score
                 FROM job_required_tests
                 WHERE job_id = ?
                 ORDER BY id ASC'
            );
            if ($requiredTestsStmt) {
                $requiredTestsStmt->bind_param('i', $editingJobId);
                $requiredTestsStmt->execute();
                $requiredTests = db_fetch_all($requiredTestsStmt);
                $requiredTestsStmt->close();

                $values['required_tests'] = array_map(
                    static function (array $row): array {
                        return [
                            'test_id' => (string) ((int) ($row['test_id'] ?? 0)),
                            'min_score' => isset($row['min_score'])
                                ? number_format((float) $row['min_score'], 2, '.', '')
                                : '',
                        ];
                    },
                    $requiredTests
                );
            }
        } elseif ($legacyJobTestColumns && (int) ($existingJob['required_test_id'] ?? 0) > 0) {
            $values['required_tests'][] = [
                'test_id' => (string) ((int) ($existingJob['required_test_id'] ?? 0)),
                'min_score' => isset($existingJob['min_test_score'])
                    ? number_format((float) $existingJob['min_test_score'], 2, '.', '')
                    : '',
            ];
        }
    } else {
        $errors['general'] = 'The requested job was not found or you do not have permission to edit it.';
        $editingJobId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $selectedTitle = trim((string) ($_POST['title'] ?? ''));
    $customTitle = trim((string) ($_POST['title_custom'] ?? ''));
    $values['title_custom'] = $customTitle;
    $values['title'] = $selectedTitle === '__custom__' ? $customTitle : $selectedTitle;
    $values['description'] = trim((string) ($_POST['description'] ?? ''));
    $values['min_average_score'] = trim((string) ($_POST['min_average_score'] ?? ''));
    $values['expiry_date'] = trim((string) ($_POST['expiry_date'] ?? ''));

    $submittedRequiredTests = $_POST['required_tests'] ?? [];
    if (is_array($submittedRequiredTests)) {
        foreach ($submittedRequiredTests as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $ruleTestId = trim((string) ($rule['test_id'] ?? ''));
            $ruleMinScore = trim((string) ($rule['min_score'] ?? ''));

            if ($ruleTestId === '' && $ruleMinScore === '') {
                continue;
            }

            $values['required_tests'][] = [
                'test_id' => $ruleTestId,
                'min_score' => $ruleMinScore,
            ];
        }
    }

    if (!$schemaReady) {
        $errors['general'] = 'Recruiter tables are missing. Run sql/recruiter_hiring_panel.sql first.';
    }

    if ($jobMinAverageColumn === '') {
        $errors['general'] = 'The jobs table is missing a supported minimum score column. Add `min_average_score` or `min_avg_score` to continue.';
    }

    if ($postingLocked) {
        $errors['general'] = $postingLockMessage;
    }

    if ($values['title'] === '') {
        $errors['title'] = 'Job title is required.';
    } elseif (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 180) {
        $errors['title'] = 'Job title must be between 3 and 180 characters.';
    }

    if ($values['description'] === '') {
        $errors['description'] = 'Job description is required.';
    } elseif (mb_strlen($values['description']) < 30) {
        $errors['description'] = 'Job description should be at least 30 characters.';
    }

    if ($values['min_average_score'] === '') {
        $errors['min_average_score'] = 'Minimum average score is required.';
    } elseif (!is_numeric($values['min_average_score'])) {
        $errors['min_average_score'] = 'Minimum average score must be numeric.';
    } else {
        $score = (float) $values['min_average_score'];
        if ($score < 0 || $score > 100) {
            $errors['min_average_score'] = 'Minimum average score must be between 0 and 100.';
        }
    }

    $validatedRequiredTests = [];
    if ($values['required_tests'] !== []) {
        if (!$supportsMultipleRequiredTests && !$legacyJobTestColumns) {
            $errors['required_tests_general'] = 'Run sql/add_job_test_requirements.sql before saving specific test requirements.';
        } elseif (!$supportsMultipleRequiredTests && count($values['required_tests']) > 1) {
            $errors['required_tests_general'] = 'This database still supports only one specific test per job. Run sql/add_job_test_requirements.sql to enable multiple test requirements.';
        }

        $seenTestIds = [];
        foreach ($values['required_tests'] as $index => $rule) {
            $ruleErrors = [];
            $ruleTestId = (string) ($rule['test_id'] ?? '');
            $ruleMinScore = (string) ($rule['min_score'] ?? '');

            if ($ruleTestId === '' || !ctype_digit($ruleTestId)) {
                $ruleErrors['test_id'] = 'Choose a valid test.';
            } else {
                $parsedTestId = (int) $ruleTestId;
                if (!isset($validTestIds[$parsedTestId])) {
                    $ruleErrors['test_id'] = 'Selected test was not found.';
                } elseif (isset($seenTestIds[$parsedTestId])) {
                    $ruleErrors['test_id'] = 'Each test can only be added once per job.';
                } else {
                    $seenTestIds[$parsedTestId] = true;
                }
            }

            if ($ruleMinScore === '') {
                $ruleErrors['min_score'] = 'Add the minimum score for this test.';
            } elseif (!is_numeric($ruleMinScore)) {
                $ruleErrors['min_score'] = 'Test score must be numeric.';
            } else {
                $parsedMinScore = (float) $ruleMinScore;
                if ($parsedMinScore < 0 || $parsedMinScore > 100) {
                    $ruleErrors['min_score'] = 'Test score must be between 0 and 100.';
                }
            }

            if ($ruleErrors !== []) {
                $errors['required_tests'][$index] = $ruleErrors;
                continue;
            }

            $validatedRequiredTests[] = [
                'test_id' => (int) $ruleTestId,
                'min_score' => round((float) $ruleMinScore, 2),
            ];
        }
    }

    if ($values['expiry_date'] === '') {
        $errors['expiry_date'] = 'Expiry date is required.';
    } else {
        $expiryTs = strtotime($values['expiry_date']);
        if ($expiryTs === false) {
            $errors['expiry_date'] = 'Please provide a valid expiry date.';
        } elseif (date('Y-m-d', $expiryTs) <= date('Y-m-d')) {
            $errors['expiry_date'] = 'Expiry date must be after today.';
        }
    }

    if ($errors === [] && $schemaReady) {
        $score = round((float) $values['min_average_score'], 2);
        $primaryRequirement = $validatedRequiredTests[0] ?? null;

        if ($isEditMode) {
            $saveStmt = $conn->prepare(
                sprintf(
                    'UPDATE jobs
                     SET title = ?, description = ?, %s = ?, expiry_date = ?
                     WHERE id = ? AND recruiter_id = ?',
                    $jobMinAverageColumn
                )
            );
        } else {
            $saveStmt = $conn->prepare(
                sprintf(
                    'INSERT INTO jobs (recruiter_id, title, description, %s, expiry_date)
                     VALUES (?, ?, ?, ?, ?)',
                    $jobMinAverageColumn
                )
            );
        }

        if (!$saveStmt) {
            $errors['general'] = $isEditMode
                ? 'Unable to start the job update request right now. Please try again.'
                : 'Unable to start the job creation request right now. Please try again.';
        } else {
            if ($isEditMode) {
                $saveStmt->bind_param(
                    'ssdsii',
                    $values['title'],
                    $values['description'],
                    $score,
                    $values['expiry_date'],
                    $editingJobId,
                    $recruiterId
                );
            } else {
                $saveStmt->bind_param(
                    'issds',
                    $recruiterId,
                    $values['title'],
                    $values['description'],
                    $score,
                    $values['expiry_date']
                );
            }

            $conn->begin_transaction();

            try {
                if (!$saveStmt->execute()) {
                    throw new RuntimeException('Job save failed.');
                }

                $jobId = $isEditMode ? $editingJobId : (int) $conn->insert_id;
                $saveStmt->close();

                if ($legacyJobTestColumns) {
                    $legacyUpdate = $conn->prepare(
                        'UPDATE jobs
                         SET required_test_id = ?, min_test_score = ?
                         WHERE id = ? AND recruiter_id = ?'
                    );
                    if (!$legacyUpdate) {
                        throw new RuntimeException('Legacy job test update prepare failed.');
                    }

                    $legacyTestId = $primaryRequirement !== null ? (int) $primaryRequirement['test_id'] : null;
                    $legacyMinScore = $primaryRequirement !== null ? (float) $primaryRequirement['min_score'] : null;
                    $legacyUpdate->bind_param('idii', $legacyTestId, $legacyMinScore, $jobId, $recruiterId);
                    if (!$legacyUpdate->execute()) {
                        $legacyUpdate->close();
                        throw new RuntimeException('Legacy job test update failed.');
                    }
                    $legacyUpdate->close();
                }

                if ($supportsMultipleRequiredTests) {
                    $cleanupStmt = $conn->prepare('DELETE FROM job_required_tests WHERE job_id = ?');
                    if (!$cleanupStmt) {
                        throw new RuntimeException('Requirement cleanup prepare failed.');
                    }
                    $cleanupStmt->bind_param('i', $jobId);
                    if (!$cleanupStmt->execute()) {
                        $cleanupStmt->close();
                        throw new RuntimeException('Requirement cleanup failed.');
                    }
                    $cleanupStmt->close();

                    if ($validatedRequiredTests !== []) {
                        $requirementInsert = $conn->prepare(
                            'INSERT INTO job_required_tests (job_id, test_id, min_score)
                             VALUES (?, ?, ?)'
                        );
                        if (!$requirementInsert) {
                            throw new RuntimeException('Requirement insert prepare failed.');
                        }

                        foreach ($validatedRequiredTests as $rule) {
                            $testId = (int) $rule['test_id'];
                            $minScore = (float) $rule['min_score'];
                            $requirementInsert->bind_param('iid', $jobId, $testId, $minScore);
                            if (!$requirementInsert->execute()) {
                                $requirementInsert->close();
                                throw new RuntimeException('Requirement insert failed.');
                            }
                        }

                        $requirementInsert->close();
                    }
                }

                $conn->commit();
                set_flash_toast('success', $isEditMode ? 'Job updated successfully.' : 'Job created successfully.');
                header('Location: ' . ($isEditMode ? 'manage_jobs.php' : 'dashboard.php'));
                exit;
            } catch (Throwable $exception) {
                if ($saveStmt instanceof mysqli_stmt) {
                    $saveStmt->close();
                }
                $conn->rollback();
                $errors['general'] = $isEditMode
                    ? 'Unable to update this job right now. Please verify the database migration for job test requirements and try again.'
                    : 'Unable to create this job right now. Please verify the database migration for job test requirements and try again.';
            }
        }
    } elseif ($errors !== []) {
        $pageToast = ['type' => 'error', 'message' => 'Please review the highlighted job fields.'];
    }
}

$selectedTitleOption = in_array($values['title'], $jobTitleOptions, true)
    ? $values['title']
    : ($values['title'] !== '' ? '__custom__' : '');
if ($selectedTitleOption === '__custom__' && $values['title_custom'] === '') {
    $values['title_custom'] = $values['title'];
}

$requiredTestRowsForUi = $values['required_tests'];
if ($requiredTestRowsForUi === [] && $tests !== []) {
    $requiredTestRowsForUi[] = ['test_id' => '', 'min_score' => ''];
}

$pageHeading = $isEditMode ? 'Edit Job' : 'Create Job';
$pageTitleText = $isEditMode ? 'Edit Job | SkillTrust Recruiter' : 'Create Job | SkillTrust Recruiter';
$submitButtonLabel = $isEditMode ? 'Save changes' : 'Publish job';
$cancelUrl = $isEditMode ? 'manage_jobs.php' : 'dashboard.php';
$currentPage = 'create_job.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitleText); ?></title>
    <script>
        tailwind = window.tailwind || {};
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['DM Sans', 'sans-serif'],
                        display: ['Syne', 'sans-serif']
                    },
                    boxShadow: {
                        soft: '0 20px 60px -24px rgba(15, 23, 42, 0.35)'
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<script>
    (function () {
        const stored = localStorage.getItem('skilltrust-theme');
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);
    }());
</script>

<div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.18),_transparent_25%),radial-gradient(circle_at_bottom_right,_rgba(139,92,246,0.12),_transparent_20%),linear-gradient(to_bottom,_rgba(2,6,23,0.98),_rgba(15,23,42,0.98))]"></div>
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] rounded-xl border px-4 py-2.5 text-sm font-semibold"></div>

<div class="flex min-h-screen">
    <?php require_once __DIR__ . '/../includes/recruiter_sidebar.php'; ?>

    <div class="recruiter-main flex min-h-screen flex-1 min-w-0 flex-col">
        <header class="navbar sticky top-0 z-30 flex items-center justify-between gap-2 px-3 py-2.5 sm:px-4 lg:h-16 lg:px-8 lg:py-0">
            <div class="flex min-w-0 items-center gap-2">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="rounded-xl p-2 text-slate-400 transition-all duration-300 hover:bg-slate-800 hover:text-white lg:hidden">
                    <span class="text-lg leading-none">&#9776;</span>
                </button>
                <div>
                    <h2 class="font-display text-lg font-bold text-white"><?php echo e($pageHeading); ?></h2>
                    <p class="text-xs text-slate-500"><?php echo e(date('l, d M Y')); ?></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="themeToggle" type="button" class="hidden rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800 md:inline-flex">
                    <span id="themeToggleLabel">Dark mode</span>
                </button>
                <div class="relative" id="recruiterDropdown">
                    <button type="button" id="recruiterMenuBtn" class="flex items-center gap-2 rounded-xl p-1.5 transition-all duration-300 hover:bg-slate-800">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-display font-bold text-white"><?php echo e($initials); ?></div>
                        <span class="hidden text-sm text-slate-300 md:inline"><?php echo e($recruiterName); ?></span>
                    </button>
                    <div id="recruiterMenu" class="hidden absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-700/60 bg-slate-800 shadow-2xl z-[60]">
                        <div class="border-b border-slate-700/60 px-4 py-3">
                            <p class="text-sm font-semibold text-white"><?php echo e($companyName); ?></p>
                            <p class="text-xs text-slate-400"><?php echo e($recruiterEmail); ?></p>
                        </div>
                        <a href="dashboard.php" class="block px-4 py-2.5 text-sm text-slate-300 transition-colors hover:bg-slate-700/50">Dashboard</a>
                        <a href="manage_jobs.php" class="block px-4 py-2.5 text-sm text-slate-300 transition-colors hover:bg-slate-700/50">Manage Jobs</a>
                        <a href="logout.php" class="block px-4 py-2.5 text-sm text-rose-400 transition-colors hover:bg-rose-500/10">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 min-w-0 overflow-x-hidden px-3 py-6 sm:px-4 sm:py-8 lg:px-8">
    <section class="fade-up overflow-hidden rounded-[28px] border border-indigo-500/20 bg-slate-900/70 shadow-soft backdrop-blur">
        <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.2fr,0.8fr] lg:px-8">
            <div>
                <span class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Average-score eligibility</span>
                <h2 class="font-display mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Post roles with a clear performance threshold.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                    Students qualify only when their overall test performance meets the minimum average score you set here.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold <?php echo e($statusClass); ?>">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                        <?php echo e($statusLabel); ?>
                    </span>
                    <?php if ($recruiterEmail !== ''): ?>
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-medium text-slate-300">
                            <i data-lucide="mail" class="h-3.5 w-3.5"></i>
                            <?php echo e($recruiterEmail); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid gap-3 rounded-[24px] border border-slate-700 bg-slate-950/60 p-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Posting guidance</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-300">
                        <li>Use a concise title recruiters would recognize instantly.</li>
                        <li>Set a realistic average-score threshold between 0 and 100.</li>
                        <li>Add specific tests only when the job needs targeted assessment cutoffs.</li>
                        <li>Expiry must be after today so students can still apply.</li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Flow after publish</p>
                    <p class="mt-3 text-sm text-slate-300">The job appears on the student jobs page, eligibility badges are computed from average test scores, and any specific test rules add extra pass conditions for each selected assessment.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$schemaReady): ?>
        <section class="rounded-[24px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-500/10">
            <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Database setup required</h3>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">Run <code class="rounded bg-white px-1.5 py-0.5 dark:bg-slate-900/60">sql/recruiter_hiring_panel.sql</code>. Missing tables: <?php echo e(implode(', ', $missingTables)); ?>.</p>
        </section>
    <?php endif; ?>

    <?php if ($postingLocked): ?>
        <section class="rounded-[24px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-white text-amber-700 dark:bg-slate-950/60 dark:text-amber-200">
                    <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Publishing is temporarily locked</h3>
                    <p class="mt-2 text-sm text-amber-800 dark:text-amber-200"><?php echo e($postingLockMessage); ?></p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="grid gap-6 lg:grid-cols-[1.35fr,0.65fr]">
        <div class="glass-card fade-up rounded-[28px] border border-slate-700 p-6 shadow-soft">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Job Form</p>
                    <h3 class="font-display mt-2 text-xl font-semibold text-white">Create a new hiring opportunity</h3>
                </div>
                <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-semibold text-slate-300">Secure POST + CSRF</span>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="mt-6 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <?php echo e($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6" id="createJobForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div>
                    <label for="title" class="mb-2 block text-sm font-semibold text-slate-200">Job title</label>
                    <select
                        id="title"
                        name="title"
                        required
                        class="w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($errors['title']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>"
                    >
                        <option value="">Select a job title</option>
                        <?php foreach ($jobTitleOptions as $jobTitleOption): ?>
                            <option value="<?php echo e($jobTitleOption); ?>" <?php echo $selectedTitleOption === $jobTitleOption ? 'selected' : ''; ?>>
                                <?php echo e($jobTitleOption); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__custom__" <?php echo $selectedTitleOption === '__custom__' ? 'selected' : ''; ?>>Other job title</option>
                    </select>
                    <input
                        id="title_custom"
                        name="title_custom"
                        type="text"
                        maxlength="180"
                        value="<?php echo e($values['title_custom']); ?>"
                        class="mt-3 w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($errors['title']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?> <?php echo $selectedTitleOption === '__custom__' ? '' : 'hidden'; ?>"
                        placeholder="Enter custom job title"
                    >
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">Choose from multiple job titles or add a custom one.</p>
                        <span class="text-xs text-slate-500"><span id="titleCount"><?php echo strlen($values['title']); ?></span>/180</span>
                    </div>
                    <?php if (isset($errors['title'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($errors['title']); ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="description" class="mb-2 block text-sm font-semibold text-slate-200">Job description</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="8"
                        minlength="30"
                        required
                        class="w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($errors['description']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>"
                        placeholder="Describe responsibilities, the hiring bar, and what success looks like in this role."
                    ><?php echo e($values['description']); ?></textarea>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">This will be visible to students before they apply.</p>
                        <span class="text-xs text-slate-500"><span id="descriptionCount"><?php echo strlen($values['description']); ?></span> chars</span>
                    </div>
                    <?php if (isset($errors['description'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($errors['description']); ?></p><?php endif; ?>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="min_average_score" class="mb-2 block text-sm font-semibold text-slate-200">Minimum average score</label>
                        <input
                            id="min_average_score"
                            name="min_average_score"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            value="<?php echo e($values['min_average_score']); ?>"
                            class="w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($errors['min_average_score']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>"
                            placeholder="75.00"
                        >
                        <p class="mt-2 text-xs text-slate-500">Eligibility uses AVG score across test attempts.</p>
                        <?php if (isset($errors['min_average_score'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($errors['min_average_score']); ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="expiry_date" class="mb-2 block text-sm font-semibold text-slate-200">Expiry date</label>
                        <input
                            id="expiry_date"
                            name="expiry_date"
                            type="date"
                            required
                            min="<?php echo e(date('Y-m-d', strtotime('+1 day'))); ?>"
                            value="<?php echo e($values['expiry_date']); ?>"
                            class="w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($errors['expiry_date']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>"
                        >
                        <p class="mt-2 text-xs text-slate-500">Jobs become expired automatically after this date.</p>
                        <?php if (isset($errors['expiry_date'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($errors['expiry_date']); ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="rounded-[26px] border border-indigo-500/20 bg-indigo-500/5 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-white">Specific test requirements</p>
                            <p class="mt-1 text-xs text-slate-400">Add one or more tests when this role needs targeted proof beyond the overall average score. Candidates must clear every test rule you add here.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-[11px] font-semibold text-slate-300"><?php echo e((string) count($tests)); ?> tests loaded</span>
                            <button type="button" id="addRequiredTestBtn" class="inline-flex items-center justify-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-[11px] font-semibold text-indigo-200 transition hover:bg-indigo-500/20" <?php echo $tests === [] ? 'disabled' : ''; ?>>
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                Add test
                            </button>
                        </div>
                    </div>

                    <?php if (isset($errors['required_tests_general'])): ?>
                        <div class="mt-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                            <?php echo e($errors['required_tests_general']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tests === []): ?>
                        <div class="mt-5 rounded-2xl border border-dashed border-slate-700 bg-slate-950/40 px-4 py-5 text-sm text-slate-400">
                            No tests are available yet. Add tests to the platform first, then recruiters can attach them to a job.
                        </div>
                    <?php else: ?>
                        <div class="mt-5 space-y-4" id="requiredTestsContainer">
                            <?php foreach ($requiredTestRowsForUi as $index => $rule): ?>
                                <?php
                                    $rowErrors = $errors['required_tests'][$index] ?? [];
                                    $selectedRuleTestId = (string) ($rule['test_id'] ?? '');
                                    $selectedRuleMinScore = (string) ($rule['min_score'] ?? '');
                                ?>
                                <div class="required-test-row rounded-[22px] border border-slate-700/70 bg-slate-950/60 p-4" data-row-index="<?php echo e((string) $index); ?>">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-white">Requirement <span class="required-test-number"><?php echo e((string) ($index + 1)); ?></span></p>
                                            <p class="mt-1 text-xs text-slate-500">Choose a test and set the minimum score a student must reach in that assessment.</p>
                                        </div>
                                        <button type="button" class="remove-required-test inline-flex items-center gap-2 self-start rounded-full border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-slate-800">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                            Remove
                                        </button>
                                    </div>
                                    <div class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-200">Select test</label>
                                            <select name="required_tests[<?php echo e((string) $index); ?>][test_id]" class="required-test-select w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($rowErrors['test_id']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>">
                                                <option value="">Choose a test</option>
                                                <?php foreach ($tests as $test): ?>
                                                    <?php $testId = (int) ($test['id'] ?? 0); ?>
                                                    <option value="<?php echo $testId; ?>" <?php echo $selectedRuleTestId === (string) $testId ? 'selected' : ''; ?>>
                                                        <?php echo e((string) ($test['title'] ?? 'Untitled Test')); ?><?php echo trim((string) ($test['category'] ?? '')) !== '' ? ' (' . e((string) $test['category']) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($rowErrors['test_id'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($rowErrors['test_id']); ?></p><?php endif; ?>
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-200">Minimum score</label>
                                            <input name="required_tests[<?php echo e((string) $index); ?>][min_score]" type="number" min="0" max="100" step="0.01" value="<?php echo e($selectedRuleMinScore); ?>" class="required-test-score w-full rounded-2xl border px-4 py-3 text-white outline-none transition <?php echo isset($rowErrors['min_score']) ? 'border-rose-500/30 bg-rose-500/10' : 'border-slate-700 bg-slate-950/70'; ?>" placeholder="82.00">
                                            <?php if (isset($rowErrors['min_score'])): ?><p class="mt-2 text-xs font-medium text-rose-300"><?php echo e($rowErrors['min_score']); ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Tip: leave this section empty if the role only depends on the overall average score.</p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 dark:border-white/10 sm:flex-row">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-60" <?php echo (!$schemaReady || $postingLocked) ? 'disabled' : ''; ?>>
                        <i data-lucide="briefcase-business" class="h-4 w-4"></i>
                        <?php echo e($submitButtonLabel); ?>
                    </button>
                    <a href="<?php echo e($cancelUrl); ?>" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-5 py-3 text-sm font-semibold text-slate-200">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <aside class="space-y-6">
            <div class="glass-card fade-up rounded-[28px] p-6 shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Eligibility preview</p>
                <h3 class="font-display mt-2 text-xl font-semibold text-white">How students are filtered</h3>
                <div class="mt-5 rounded-3xl border border-slate-700 bg-slate-950/60 p-5">
                    <p class="text-sm text-slate-300">Candidates qualify only when they clear the performance bar you set for this role.</p>
                    <div class="mt-4 rounded-2xl border border-emerald-500/10 bg-slate-950 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-300">Primary requirement</p>
                        <p class="mt-2 text-sm leading-6 text-slate-200">Overall average score must meet or exceed the minimum average score for this job.</p>
                    </div>
                    <div id="testRulePreview" class="mt-3 rounded-2xl border border-sky-500/10 bg-slate-950 px-4 py-4 text-sm text-sky-300">
                        No specific tests required
                    </div>
                </div>
            </div>

            <div class="glass-card fade-up rounded-[28px] p-6 shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Hiring snapshot</p>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-2xl border border-slate-700 bg-slate-950/60 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Total jobs</p>
                        <p class="mt-2 text-2xl font-semibold text-white"><?php echo e((string) $jobStats['total_jobs']); ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-950/60 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Active roles</p>
                        <p class="mt-2 text-2xl font-semibold text-white"><?php echo e((string) $jobStats['active_jobs']); ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-950/60 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Average threshold</p>
                        <p class="mt-2 text-2xl font-semibold text-white"><?php echo e(number_format((float) $jobStats['avg_threshold'], 2)); ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-950/60 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Tests available</p>
                        <p class="mt-2 text-2xl font-semibold text-white"><?php echo e((string) count($tests)); ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-card fade-up rounded-[28px] p-6 shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Recent jobs</p>
                <div class="mt-4 space-y-3">
                    <?php if ($recentJobs === []): ?>
                        <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/60 px-4 py-6 text-center text-sm text-slate-500">
                            Your latest jobs will appear here after publishing.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentJobs as $recentJob): ?>
                            <article class="rounded-2xl border border-slate-700 bg-slate-950/60 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-white"><?php echo e((string) $recentJob['title']); ?></h4>
                                        <p class="mt-2 text-xs text-slate-400">
                                            Min avg <?php echo e(number_format((float) ($recentJob['min_average_score'] ?? 0), 2)); ?> Â· Expires <?php echo e(format_date_label((string) ($recentJob['expiry_date'] ?? ''))); ?>
                                        </p>
                                        <?php if (!empty($recentJob['required_tests_summary'])): ?>
                                            <p class="mt-2 text-xs leading-5 text-sky-300"><?php echo e((string) $recentJob['required_tests_summary']); ?></p>
                                        <?php elseif (!empty($recentJob['required_test_title']) && isset($recentJob['min_test_score'])): ?>
                                            <p class="mt-2 text-xs text-sky-300">Requires <?php echo e((string) $recentJob['required_test_title']); ?> &gt;= <?php echo e(number_format((float) $recentJob['min_test_score'], 2)); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-300"><?php echo e(time_ago_label((string) $recentJob['created_at'])); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass-card fade-up rounded-[28px] p-6 shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">What gets stored</p>
                <ul class="mt-4 space-y-3 text-sm text-slate-300">
                    <li>Job title and description</li>
                    <li>Required minimum average score</li>
                    <li>Optional list of required tests and minimum scores</li>
                    <li>Expiry date for active versus expired status</li>
                    <li>Recruiter ownership from the secure session</li>
                    <li>Metadata ready for applicant filtering downstream</li>
                </ul>
            </div>
        </aside>
    </section>
</main>
    </div>
</div>

<?php if ($tests !== []): ?>
    <template id="requiredTestRowTemplate">
        <div class="required-test-row rounded-[22px] border border-slate-700/70 bg-slate-950/60 p-4" data-row-index="__INDEX__">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-white">Requirement <span class="required-test-number">__NUMBER__</span></p>
                    <p class="mt-1 text-xs text-slate-500">Choose a test and set the minimum score a student must reach in that assessment.</p>
                </div>
                <button type="button" class="remove-required-test inline-flex items-center gap-2 self-start rounded-full border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-slate-800">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                    Remove
                </button>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-200">Select test</label>
                    <select name="required_tests[__INDEX__][test_id]" class="required-test-select w-full rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-white outline-none transition">
                        <option value="">Choose a test</option>
                        <?php foreach ($tests as $test): ?>
                            <?php $testId = (int) ($test['id'] ?? 0); ?>
                            <option value="<?php echo $testId; ?>">
                                <?php echo e((string) ($test['title'] ?? 'Untitled Test')); ?><?php echo trim((string) ($test['category'] ?? '')) !== '' ? ' (' . e((string) $test['category']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-200">Minimum score</label>
                    <input name="required_tests[__INDEX__][min_score]" type="number" min="0" max="100" step="0.01" class="required-test-score w-full rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-white outline-none transition" placeholder="82.00">
                </div>
            </div>
        </div>
    </template>
<?php endif; ?>

<script type="application/json" id="toastData"><?php echo json_encode($pageToast ?? $toast, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<script>
    (function () {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        window.toggleSidebar = function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (!sidebar || !overlay) {
                return;
            }
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('active');
        };

        const recruiterMenuBtn = document.getElementById('recruiterMenuBtn');
        const recruiterMenu = document.getElementById('recruiterMenu');
        if (recruiterMenuBtn && recruiterMenu) {
            recruiterMenuBtn.addEventListener('click', function () {
                recruiterMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function (event) {
                if (!recruiterMenu.contains(event.target) && !recruiterMenuBtn.contains(event.target)) {
                    recruiterMenu.classList.add('hidden');
                }
            });
        }

        const root = document.documentElement;
        const toggleButton = document.getElementById('themeToggle');
        const toggleLabel = document.getElementById('themeToggleLabel');
        const syncTheme = function () {
            toggleLabel.textContent = root.classList.contains('dark') ? 'Light mode' : 'Dark mode';
        };
        syncTheme();
        toggleButton.addEventListener('click', function () {
            const dark = !root.classList.contains('dark');
            root.classList.toggle('dark', dark);
            localStorage.setItem('skilltrust-theme', dark ? 'dark' : 'light');
            syncTheme();
        });

        const title = document.getElementById('title');
        const titleCustom = document.getElementById('title_custom');
        const description = document.getElementById('description');
        const titleCount = document.getElementById('titleCount');
        const descriptionCount = document.getElementById('descriptionCount');
        const testRulePreview = document.getElementById('testRulePreview');
        const requiredTestsContainer = document.getElementById('requiredTestsContainer');
        const requiredTestRowTemplate = document.getElementById('requiredTestRowTemplate');
        const addRequiredTestBtn = document.getElementById('addRequiredTestBtn');
        const syncTitleField = function () {
            const usingCustomTitle = title && title.value === '__custom__';
            if (titleCustom) {
                titleCustom.classList.toggle('hidden', !usingCustomTitle);
                titleCustom.required = usingCustomTitle;
            }
        };
        const getResolvedTitle = function () {
            if (!title) {
                return '';
            }
            return title.value === '__custom__'
                ? (titleCustom ? titleCustom.value.trim() : '')
                : title.value.trim();
        };
        const updateCounters = function () {
            titleCount.textContent = String(getResolvedTitle().length);
            descriptionCount.textContent = String(description.value.length);
        };

        const refreshRequiredTestNumbers = function () {
            if (!requiredTestsContainer) {
                return;
            }

            requiredTestsContainer.querySelectorAll('.required-test-row').forEach(function (row, index) {
                const label = row.querySelector('.required-test-number');
                if (label) {
                    label.textContent = String(index + 1);
                }
            });
        };

        const getRequirementRows = function () {
            if (!requiredTestsContainer) {
                return [];
            }

            return Array.from(requiredTestsContainer.querySelectorAll('.required-test-row'));
        };

        const syncTestRequirement = function () {
            if (!testRulePreview) {
                return;
            }

            const summaries = getRequirementRows().map(function (row) {
                const select = row.querySelector('.required-test-select');
                const scoreInput = row.querySelector('.required-test-score');
                const option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
                const testLabel = option && select.value !== '' ? option.text.trim() : '';
                const minScore = scoreInput ? scoreInput.value.trim() : '';

                if (!testLabel && !minScore) {
                    return '';
                }

                return testLabel !== ''
                    ? testLabel + ' >= ' + (minScore !== '' ? minScore : '...')
                    : 'Choose test >= ' + (minScore !== '' ? minScore : '...');
            }).filter(Boolean);

            if (summaries.length === 0) {
                testRulePreview.innerHTML = [
                    '<p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-300">Specific tests</p>',
                    '<p class="mt-2 leading-6 text-slate-300">No additional test filters are active. Students only need to satisfy the overall average score requirement.</p>'
                ].join('');
                return;
            }

            testRulePreview.innerHTML = [
                '<p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-300">Specific tests</p>',
                '<div class="mt-3 space-y-2">' + summaries.map(function (summary) {
                    return '<div class="rounded-xl border border-sky-500/10 bg-slate-900/80 px-3 py-2 leading-6 text-slate-200">' + summary + '</div>';
                }).join('') + '</div>'
            ].join('');
        };

        const bindRequirementRow = function (row) {
            const select = row.querySelector('.required-test-select');
            const scoreInput = row.querySelector('.required-test-score');
            const removeBtn = row.querySelector('.remove-required-test');

            if (select) {
                select.addEventListener('change', syncTestRequirement);
            }
            if (scoreInput) {
                scoreInput.addEventListener('input', syncTestRequirement);
            }
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    row.remove();
                    refreshRequiredTestNumbers();
                    syncTestRequirement();
                });
            }
        };

        const addRequirementRow = function () {
            if (!requiredTestsContainer || !requiredTestRowTemplate) {
                return;
            }

            const nextIndex = getRequirementRows().reduce(function (maxIndex, row) {
                const rowIndex = Number(row.dataset.rowIndex || 0);
                return rowIndex > maxIndex ? rowIndex : maxIndex;
            }, -1) + 1;

            const fragment = requiredTestRowTemplate.content.cloneNode(true);
            const shell = document.createElement('div');
            shell.appendChild(fragment);
            shell.innerHTML = shell.innerHTML
                .replaceAll('__INDEX__', String(nextIndex))
                .replaceAll('__NUMBER__', String(getRequirementRows().length + 1));

            const row = shell.firstElementChild;
            if (!row) {
                return;
            }

            requiredTestsContainer.appendChild(row);
            bindRequirementRow(row);
            refreshRequiredTestNumbers();
            syncTestRequirement();
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        };

        title.addEventListener('change', function () {
            syncTitleField();
            updateCounters();
        });
        if (titleCustom) {
            titleCustom.addEventListener('input', updateCounters);
        }
        description.addEventListener('input', updateCounters);
        getRequirementRows().forEach(bindRequirementRow);
        if (addRequiredTestBtn) {
            addRequiredTestBtn.addEventListener('click', addRequirementRow);
        }
        syncTitleField();
        updateCounters();
        refreshRequiredTestNumbers();
        syncTestRequirement();

        const form = document.getElementById('createJobForm');
        const minAverageScoreInput = document.getElementById('min_average_score');
        const expiryDateInput = document.getElementById('expiry_date');
        const focusField = function (field) {
            if (!field || typeof field.focus !== 'function') {
                return;
            }
            field.focus();
            if (typeof field.scrollIntoView === 'function') {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        form.addEventListener('submit', function (event) {
            const titleValue = getResolvedTitle();
            const descriptionValue = description.value.trim();
            const scoreValue = minAverageScoreInput ? minAverageScoreInput.value.trim() : '';
            const expiryValue = expiryDateInput ? expiryDateInput.value.trim() : '';
            const score = scoreValue !== '' ? Number(scoreValue) : NaN;
            const minimumExpiry = expiryDateInput ? (expiryDateInput.getAttribute('min') || '') : '';

            if (titleValue.length < 3) {
                event.preventDefault();
                showToast('error', 'Job title must be at least 3 characters.');
                focusField(title);
                return;
            }

            if (descriptionValue.length < 30) {
                event.preventDefault();
                showToast('error', 'Job description must be at least 30 characters.');
                focusField(description);
                return;
            }

            if (scoreValue === '' || Number.isNaN(score) || score < 0 || score > 100) {
                event.preventDefault();
                showToast('error', 'Minimum average score must be a number between 0 and 100.');
                focusField(minAverageScoreInput);
                return;
            }

            if (expiryValue === '') {
                event.preventDefault();
                showToast('error', 'Please choose an expiry date.');
                focusField(expiryDateInput);
                return;
            }

            if (minimumExpiry !== '' && expiryValue < minimumExpiry) {
                event.preventDefault();
                showToast('error', 'Expiry date must be after today.');
                focusField(expiryDateInput);
                return;
            }

            const selectedTests = new Set();
            const rows = getRequirementRows();
            for (let index = 0; index < rows.length; index += 1) {
                const row = rows[index];
                const select = row.querySelector('.required-test-select');
                const scoreInput = row.querySelector('.required-test-score');
                const selectedTestId = select ? select.value.trim() : '';
                const selectedScore = scoreInput ? scoreInput.value.trim() : '';

                if (selectedTestId === '' && selectedScore === '') {
                    continue;
                }

                if (selectedTestId === '' || selectedScore === '') {
                    event.preventDefault();
                    showToast('error', 'Each specific test rule needs both a test and a minimum score.');
                    focusField(select && selectedTestId === '' ? select : scoreInput);
                    return;
                }

                const numericScore = Number(selectedScore);
                if (selectedTests.has(selectedTestId)) {
                    event.preventDefault();
                    showToast('error', 'The same test can only be added once per job.');
                    focusField(select);
                    return;
                }
                if (Number.isNaN(numericScore) || numericScore < 0 || numericScore > 100) {
                    event.preventDefault();
                    showToast('error', 'Every required test score must stay between 0 and 100.');
                    focusField(scoreInput);
                    return;
                }

                selectedTests.add(selectedTestId);
            }
        });

        function showToast(type, message) {
            const toastNode = document.getElementById('toast');
            const palette = {
                success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
                error: 'bg-rose-500/15 border-rose-500/30 text-rose-300',
                info: 'bg-indigo-500/15 border-indigo-500/30 text-indigo-200'
            };
            toastNode.className = 'fixed bottom-6 right-6 z-[100] rounded-xl border px-4 py-2.5 text-sm font-semibold ' + (palette[type] || palette.info);
            toastNode.textContent = message;
            toastNode.classList.remove('hidden');
            window.setTimeout(function () {
                toastNode.classList.add('hidden');
            }, 3200);
        }

        let pageToast = null;
        try {
            pageToast = JSON.parse(document.getElementById('toastData').textContent || 'null');
        } catch (error) {
            pageToast = null;
        }
        if (pageToast && pageToast.message) {
            showToast(pageToast.type || 'info', pageToast.message);
        }
    }());
</script>
</body>
</html>
