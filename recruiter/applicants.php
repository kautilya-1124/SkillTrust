<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/interview_helpers.php';

require_recruiter_login();

$recruiterId = current_recruiter_id();
$toast = consume_flash_toast();
$recruiterName = (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name());
$companyName = (string) ($_SESSION['recruiter_company'] ?? $recruiterName);
$recruiterEmail = (string) ($_SESSION['recruiter_email'] ?? '');
$recruiterStatus = strtolower(trim((string) ($_SESSION['recruiter_status'] ?? 'approved')));
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');

if (!function_exists('applicants_bind_params')) {
    function applicants_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
    {
        $bind = [$types];
        foreach ($params as $idx => &$value) {
            $bind[] = &$params[$idx];
        }

        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('applicant_badge')) {
    function applicant_badge(string $status): array
    {
        $map = [
            'applied' => ['Applied', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'],
            'shortlisted' => ['Shortlisted', 'bg-amber-500/10 text-amber-700 dark:text-amber-300'],
            'rejected' => ['Rejected', 'bg-rose-500/10 text-rose-700 dark:text-rose-300'],
            'selected' => ['Selected', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'],
        ];

        return $map[strtolower($status)] ?? ['Unknown', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
    }
}

if (!function_exists('job_state_badge')) {
    function job_state_badge(string $expiryDate): array
    {
        return strtotime($expiryDate) >= strtotime(date('Y-m-d'))
            ? ['Active', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300']
            : ['Expired', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'];
    }
}

$statusMeta = [
    'approved' => ['Approved', 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'],
    'pending' => ['Pending approval', 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'],
    'blocked' => ['Blocked', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
    'rejected' => ['Rejected', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
];
[$statusLabel, $statusClass] = $statusMeta[$recruiterStatus] ?? ['Unknown', 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200'];

$requiredTables = ['recruiters', 'jobs', 'applications', 'users', 'results'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}
$schemaReady = $missingTables === [];
$hasAverageSnapshotColumn = db_column_exists($conn, 'applications', 'average_score_snapshot');
$hasInterviewsTable = db_table_exists($conn, 'interviews');
$hasApplicationUpdatedAtColumn = db_column_exists($conn, 'applications', 'updated_at');

$profileQuery = $conn->prepare('SELECT company_name, email, status FROM recruiters WHERE id = ? LIMIT 1');
if ($profileQuery) {
    $profileQuery->bind_param('i', $recruiterId);
    $profileQuery->execute();
    $profileRow = db_fetch_one($profileQuery);
    $profileQuery->close();
    if ($profileRow) {
        $companyName = (string) ($profileRow['company_name'] ?? $companyName);
        $recruiterEmail = (string) ($profileRow['email'] ?? $recruiterEmail);
        $recruiterStatus = strtolower(trim((string) ($profileRow['status'] ?? $recruiterStatus)));
        $_SESSION['recruiter_company'] = $companyName;
        $_SESSION['recruiter_email'] = $recruiterEmail;
        $_SESSION['recruiter_status'] = $recruiterStatus;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$selectedJobId = (int) ($_GET['job_id'] ?? 0);
$selectedJobMeta = null;
$selectedStatus = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($selectedStatus, ['all', 'applied', 'shortlisted', 'rejected', 'selected'], true)) {
    $selectedStatus = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$summary = [
    'total_applications' => 0,
    'applied' => 0,
    'shortlisted' => 0,
    'rejected' => 0,
    'selected' => 0,
    'avg_score' => 0.0,
];
$jobs = [];
$applications = [];
$totalApplications = 0;
$filteredAvgScore = 0.0;
$interview = [
    'application_id' => 0,
    'interview_date' => '',
    'interview_time' => '',
];
$errors = [];

if ($schemaReady && $selectedJobId > 0) {
    $selectedJobStmt = $conn->prepare(
        'SELECT id, title, expiry_date
         FROM jobs
         WHERE id = ? AND recruiter_id = ?
         LIMIT 1'
    );
    if ($selectedJobStmt) {
        $selectedJobStmt->bind_param('ii', $selectedJobId, $recruiterId);
        $selectedJobStmt->execute();
        $selectedJobMeta = db_fetch_one($selectedJobStmt);
        $selectedJobStmt->close();
    }

    if (!$selectedJobMeta) {
        $errors['general'] = 'The selected job was not found for this recruiter account.';
        $selectedJobId = 0;
    }
}

if ($schemaReady) {
    $statsStmt = $conn->prepare(
        'SELECT
            COUNT(a.id) AS total_applications,
            SUM(CASE WHEN a.status = "applied" THEN 1 ELSE 0 END) AS applied,
            SUM(CASE WHEN a.status = "shortlisted" THEN 1 ELSE 0 END) AS shortlisted,
            SUM(CASE WHEN a.status = "rejected" THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN a.status = "selected" THEN 1 ELSE 0 END) AS selected,
            AVG(' . ($hasAverageSnapshotColumn
                ? 'COALESCE((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id), a.average_score_snapshot)'
                : '(SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id)') . ') AS avg_score
         FROM applications a
         INNER JOIN jobs j ON j.id = a.job_id
         WHERE j.recruiter_id = ?'
    );
    if ($statsStmt) {
        $statsStmt->bind_param('i', $recruiterId);
        $statsStmt->execute();
        $statsRow = db_fetch_one($statsStmt);
        $statsStmt->close();
        if ($statsRow) {
            $summary['total_applications'] = (int) ($statsRow['total_applications'] ?? 0);
            $summary['applied'] = (int) ($statsRow['applied'] ?? 0);
            $summary['shortlisted'] = (int) ($statsRow['shortlisted'] ?? 0);
            $summary['rejected'] = (int) ($statsRow['rejected'] ?? 0);
            $summary['selected'] = (int) ($statsRow['selected'] ?? 0);
            $summary['avg_score'] = normalize_score($statsRow['avg_score'] ?? 0);
        }
    }

    $jobsStmt = $conn->prepare(
        'SELECT j.id, j.title, j.expiry_date, COUNT(a.id) AS applicant_count
         FROM jobs j
         LEFT JOIN applications a ON a.job_id = j.id
         WHERE j.recruiter_id = ?
         GROUP BY j.id, j.title, j.expiry_date
         ORDER BY j.created_at DESC'
    );
    if ($jobsStmt) {
        $jobsStmt->bind_param('i', $recruiterId);
        $jobsStmt->execute();
        $jobs = db_fetch_all($jobsStmt);
        $jobsStmt->close();
    }
}

$jobFilterSql = $selectedJobId > 0 ? ' AND j.id = ?' : '';
$statusFilterSql = $selectedStatus !== 'all' ? ' AND a.status = ?' : '';
$searchFilterSql = $search !== '' ? ' AND (u.name LIKE ? OR u.email LIKE ? OR j.title LIKE ?)' : '';

$countSql = '
    SELECT COUNT(a.id) AS total_applications
    FROM applications a
    INNER JOIN jobs j ON j.id = a.job_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE j.recruiter_id = ?' . $jobFilterSql . $statusFilterSql . $searchFilterSql;

$countStmt = $conn->prepare($countSql);
if (!$countStmt && $schemaReady) {
    die('SQL Error: ' . $conn->error);
}
if ($countStmt && $schemaReady) {
    $countTypes = 'i';
    $countParams = [$recruiterId];
    if ($selectedJobId > 0) {
        $countTypes .= 'i';
        $countParams[] = $selectedJobId;
    }
    if ($selectedStatus !== 'all') {
        $countTypes .= 's';
        $countParams[] = $selectedStatus;
    }
    if ($search !== '') {
        $countTypes .= 'sss';
        $like = '%' . $search . '%';
        $countParams[] = $like;
        $countParams[] = $like;
        $countParams[] = $like;
    }
    if (applicants_bind_params($countStmt, $countTypes, $countParams)) {
        $countStmt->execute();
        $countRow = db_fetch_one($countStmt);
        $totalApplications = (int) ($countRow['total_applications'] ?? 0);
    }
    $countStmt->close();
}

if ($totalApplications > 0 && $page > (int) ceil($totalApplications / $perPage)) {
    $page = max(1, (int) ceil($totalApplications / $perPage));
    $offset = ($page - 1) * $perPage;
}

$listSql = '
    SELECT
        a.id,
        a.job_id,
        a.user_id,
        a.status,
        a.applied_at,
        ' . ($hasApplicationUpdatedAtColumn ? 'a.updated_at' : 'a.applied_at AS updated_at') . ',
        ' . ($hasAverageSnapshotColumn ? 'a.average_score_snapshot,' : '0 AS average_score_snapshot,') . '
        j.title AS job_title,
        j.expiry_date,
        COALESCE(NULLIF(u.name, ""), CONCAT("Candidate #", a.user_id)) AS candidate_name,
        COALESCE(NULLIF(u.email, ""), "No email available") AS candidate_email,
        ' . ($hasAverageSnapshotColumn
            ? 'COALESCE((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id), a.average_score_snapshot)'
            : '(SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id)') . ' AS average_score,
        ' . ($hasInterviewsTable
            ? '(SELECT COUNT(*) FROM interviews i WHERE i.application_id = a.id)'
            : '0') . ' AS interview_count,
        ' . ($hasInterviewsTable
            ? '(SELECT MIN(i.interview_datetime) FROM interviews i WHERE i.application_id = a.id AND i.status = "scheduled")'
            : 'NULL') . ' AS next_interview_at
    FROM applications a
    INNER JOIN jobs j ON j.id = a.job_id
    LEFT JOIN users u ON u.id = a.user_id
    WHERE j.recruiter_id = ?' . $jobFilterSql . $statusFilterSql . $searchFilterSql . '
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?';

$listStmt = $conn->prepare($listSql);
if (!$listStmt && $schemaReady) {
    die('SQL Error: ' . $conn->error);
}
if ($listStmt && $schemaReady) {
    $listTypes = 'i';
    $listParams = [$recruiterId];
    if ($selectedJobId > 0) {
        $listTypes .= 'i';
        $listParams[] = $selectedJobId;
    }
    if ($selectedStatus !== 'all') {
        $listTypes .= 's';
        $listParams[] = $selectedStatus;
    }
    if ($search !== '') {
        $listTypes .= 'sss';
        $like = '%' . $search . '%';
        $listParams[] = $like;
        $listParams[] = $like;
        $listParams[] = $like;
    }
    $listTypes .= 'ii';
    $listParams[] = $perPage;
    $listParams[] = $offset;
    if (applicants_bind_params($listStmt, $listTypes, $listParams)) {
        $listStmt->execute();
        $applications = db_fetch_all($listStmt);
    } else {
        $errors['general'] = 'Unable to load applicants right now.';
    }
    $listStmt->close();
} elseif ($schemaReady) {
    $errors['general'] = 'SQL Error: ' . $conn->error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $action = trim((string) ($_POST['action'] ?? ''));
    $applicationId = (int) ($_POST['application_id'] ?? 0);

    if ($action === 'update_status' && $applicationId > 0) {
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (!in_array($newStatus, ['applied', 'shortlisted', 'rejected', 'selected'], true)) {
            set_flash_toast('error', 'Please choose a valid application status.');
        } else {
            $check = $conn->prepare(
                'SELECT a.id
                 FROM applications a
                 INNER JOIN jobs j ON j.id = a.job_id
                 WHERE a.id = ? AND j.recruiter_id = ? LIMIT 1'
            );
            if ($check) {
                $check->bind_param('ii', $applicationId, $recruiterId);
                $check->execute();
                $checkRow = db_fetch_one($check);
                $check->close();

                if (!$checkRow) {
                    set_flash_toast('error', 'Application not found or access denied.');
                } else {
                    $update = $conn->prepare('UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?');
                    if ($update) {
                        $update->bind_param('si', $newStatus, $applicationId);
                        if ($update->execute()) {
                            set_flash_toast('success', 'Application status updated.');
                            $update->close();
                            header('Location: applicants.php?' . http_build_query([
                                'job_id' => $selectedJobId,
                                'status' => $selectedStatus,
                                'q' => $search,
                                'page' => $page,
                            ]));
                            exit;
                        }
                        $update->close();
                        set_flash_toast('error', 'Unable to update application status.');
                    } else {
                        set_flash_toast('error', 'Unable to prepare status update.');
                    }
                }
            }
        }
    }

    if ($action === 'schedule_interview' && $applicationId > 0) {
        $dateValue = trim((string) ($_POST['interview_date'] ?? ''));
        $timeValue = trim((string) ($_POST['interview_time'] ?? ''));

        if ($dateValue === '' || $timeValue === '') {
            set_flash_toast('error', 'Interview date and time are required.');
        } else {
            $dateTime = date_create_from_format('Y-m-d H:i', $dateValue . ' ' . $timeValue);
            if (!$dateTime) {
                set_flash_toast('error', 'Please provide a valid interview date and time.');
            } else {
                $check = $conn->prepare(
                    'SELECT a.id
                     FROM applications a
                     INNER JOIN jobs j ON j.id = a.job_id
                     WHERE a.id = ? AND j.recruiter_id = ? LIMIT 1'
                );
                if ($check) {
                    $check->bind_param('ii', $applicationId, $recruiterId);
                    $check->execute();
                    $checkRow = db_fetch_one($check);
                    $check->close();

                    if (!$checkRow) {
                        set_flash_toast('error', 'Application not found or access denied.');
                    } else {
                        $scheduleResult = skilltrust_schedule_interview($conn, [
                            'application_id' => $applicationId,
                            'recruiter_id' => $recruiterId,
                            'interview_date' => $dateValue,
                            'interview_time' => $timeValue,
                            'notes' => 'Scheduled from SkillTrust applicants board for application #' . $applicationId,
                        ]);

                        if ($scheduleResult['success']) {
                            set_flash_toast('success', 'Interview scheduled successfully.');
                            header('Location: applicants.php?' . http_build_query([
                                'job_id' => $selectedJobId,
                                'status' => $selectedStatus,
                                'q' => $search,
                                'page' => $page,
                            ]));
                            exit;
                        }

                        set_flash_toast('error', (string) ($scheduleResult['message'] ?? 'Unable to schedule interview.'));
                    }
                }
            }
        }
    }
}

$jobFilterOptions = [];
foreach ($jobs as $job) {
    $jobFilterOptions[] = [
        'id' => (int) ($job['id'] ?? 0),
        'title' => (string) ($job['title'] ?? ''),
        'count' => (int) ($job['applicant_count'] ?? 0),
        'state' => job_state_badge((string) ($job['expiry_date'] ?? '')),
    ];
}

$totalPages = max(1, (int) ceil(max($totalApplications, 1) / $perPage));
$selectedJobTitle = 'All Jobs';
foreach ($jobFilterOptions as $opt) {
    if ((int) $opt['id'] === $selectedJobId) {
        $selectedJobTitle = (string) $opt['title'];
        break;
    }
}
if ($selectedJobMeta) {
    $selectedJobTitle = (string) ($selectedJobMeta['title'] ?? $selectedJobTitle);
}

$jobFocusedStats = [
    'shortlisted' => 0,
    'selected' => 0,
    'scheduled_interviews' => 0,
    'avg_score' => 0.0,
];
if ($selectedJobId > 0 && $applications !== []) {
    $scoreTotal = 0.0;
    foreach ($applications as $application) {
        $score = (float) ($application['average_score'] ?? 0);
        $scoreTotal += $score;
        if ((string) ($application['status'] ?? '') === 'shortlisted') {
            $jobFocusedStats['shortlisted']++;
        }
        if ((string) ($application['status'] ?? '') === 'selected') {
            $jobFocusedStats['selected']++;
        }
        if (!empty($application['next_interview_at'])) {
            $jobFocusedStats['scheduled_interviews']++;
        }
    }
    $jobFocusedStats['avg_score'] = $scoreTotal / max(count($applications), 1);
}

$cards = [
    ['Total applications', $summary['total_applications']],
    ['Average score', number_format($summary['avg_score'], 2)],
    ['Shortlisted', $summary['shortlisted']],
    ['Selected', $summary['selected']],
];
$currentPage = 'applicants.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants | SkillTrust</title>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('skilltrust-theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        }());
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    boxShadow: {
                        glow: '0 30px 90px -40px rgba(15, 23, 42, 0.4)',
                        soft: '0 18px 60px -35px rgba(15, 23, 42, 0.2)'
                    },
                    keyframes: {
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.5s ease forwards'
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border"></div>
<div class="flex min-h-screen">
    <?php require_once __DIR__ . '/../includes/recruiter_sidebar.php'; ?>

    <div class="recruiter-main flex min-w-0 flex-1 flex-col">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-all duration-300">Menu</button>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-300">Recruiter workspace</p>
                    <h1 class="font-display font-bold text-white text-lg">Applicants</h1>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <button id="themeToggle" type="button" class="hidden md:inline-flex rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-all duration-300">
                    <span id="themeToggleLabel">Dark mode</span>
                </button>
                <div class="relative" id="recruiterDropdown">
                    <button type="button" id="recruiterMenuBtn" class="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-slate-800 transition-all duration-300">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-xs"><?php echo e($initials); ?></div>
                        <span class="hidden md:inline text-sm text-slate-300"><?php echo e($companyName); ?></span>
                    </button>
                    <div id="recruiterMenu" class="hidden absolute right-0 mt-2 w-56 bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-[60]">
                        <div class="px-4 py-3 border-b border-slate-700/60">
                            <p class="text-sm font-semibold text-white"><?php echo e($companyName); ?></p>
                            <p class="text-xs text-slate-400"><?php echo e($recruiterEmail); ?></p>
                        </div>
                        <a href="dashboard.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Dashboard</a>
                        <a href="manage_jobs.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Manage Jobs</a>
                        <a href="logout.php" class="block px-4 py-2.5 text-sm text-rose-400 hover:bg-rose-500/10 transition-colors">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <?php if ($toast !== null): ?>
                    <div data-toast-type="<?php echo e((string) ($toast['type'] ?? '')); ?>" data-toast-message="<?php echo e((string) ($toast['message'] ?? '')); ?>"></div>
                <?php endif; ?>
                <?php if (!empty($errors['general'])): ?>
                    <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-200">
                        <?php echo e((string) $errors['general']); ?>
                    </div>
                <?php endif; ?>

                <section class="fade-up relative overflow-hidden rounded-3xl border border-indigo-500/25 bg-gradient-to-br from-indigo-900/35 via-slate-900/80 to-violet-900/30 p-5 sm:p-8">
                    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 bottom-0 w-44 h-44 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
                    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,420px)] xl:items-end">
                        <div class="max-w-3xl min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                <?php echo e($selectedJobId > 0 ? 'Job-specific applicant board' : 'Average-score hiring board'); ?>
                            </div>
                            <h2 class="mt-4 font-display font-extrabold text-2xl sm:text-3xl text-white">Review candidates, shortlist fast, and schedule interviews from one place.</h2>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">
                                Applicant ordering uses the live average score from <code class="rounded bg-white/10 px-1.5 py-0.5 text-xs text-slate-200">results</code>.
                                <?php if ($selectedJobId > 0): ?>
                                    You are reviewing candidates for <span class="font-semibold text-white"><?php echo e($selectedJobTitle); ?></span>.
                                <?php else: ?>
                                    You can filter by job, status, or search by candidate name and email.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="grid w-full grid-cols-2 gap-3 self-start xl:self-auto">
                            <?php foreach ($cards as $card): ?>
                                <div class="glass-card metric-card min-w-0 rounded-2xl p-4 sm:p-5">
                                    <div class="text-[11px] uppercase leading-5 tracking-[0.18em] text-indigo-300"><?php echo e((string) $card[0]); ?></div>
                                    <div class="mt-3 text-3xl font-extrabold leading-none text-white"><?php echo e((string) $card[1]); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="glass-card rounded-2xl overflow-hidden">
                    <div class="flex flex-col gap-4 border-b border-slate-700/50 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="font-display font-bold text-white text-base">Filters</h3>
                            <p class="mt-1 text-sm text-slate-500">Use filters to narrow the applicant pool.</p>
                        </div>
                        <form method="get" class="grid w-full gap-3 lg:grid-cols-[1.2fr_0.8fr_0.8fr_auto]">
                            <input type="hidden" name="page" value="1">
                            <div class="relative">
                                <input name="q" value="<?php echo e($search); ?>" placeholder="Search candidate or email..." class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:border-indigo-500">
                            </div>
                            <select name="job_id" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                <option value="0">All jobs</option>
                                <?php foreach ($jobFilterOptions as $jobOption): ?>
                                    <option value="<?php echo (int) $jobOption['id']; ?>" <?php echo $selectedJobId === (int) $jobOption['id'] ? 'selected' : ''; ?>>
                                        <?php echo e((string) $jobOption['title']); ?> (<?php echo e((string) $jobOption['count']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                <?php foreach (['all' => 'All status', 'applied' => 'Applied', 'shortlisted' => 'Shortlisted', 'rejected' => 'Rejected', 'selected' => 'Selected'] as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>" <?php echo $selectedStatus === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="rounded-2xl bg-slate-800 border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-700/80">Apply</button>
                        </form>
                    </div>

                    <div class="p-4 sm:p-6">
                        <?php if ($selectedJobId > 0 && $selectedJobMeta): ?>
                            <div class="mb-6 rounded-2xl border border-indigo-500/20 bg-gradient-to-r from-indigo-500/10 via-slate-900/40 to-sky-500/10 p-4 sm:p-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-indigo-300">Selected Job View</div>
                                        <h3 class="mt-2 font-display text-xl font-bold text-white"><?php echo e($selectedJobTitle); ?></h3>
                                        <p class="mt-2 text-sm text-slate-400">Showing applicants specifically matched to this role, with quick hiring signals for shortlisting and interviews.</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[460px]">
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Applicants</div>
                                            <div class="mt-2 text-2xl font-extrabold text-white"><?php echo e((string) $totalApplications); ?></div>
                                        </div>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Avg Score</div>
                                            <div class="mt-2 text-2xl font-extrabold text-white"><?php echo e(number_format($jobFocusedStats['avg_score'], 2)); ?></div>
                                        </div>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Shortlisted</div>
                                            <div class="mt-2 text-2xl font-extrabold text-white"><?php echo e((string) $jobFocusedStats['shortlisted']); ?></div>
                                        </div>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Scheduled</div>
                                            <div class="mt-2 text-2xl font-extrabold text-white"><?php echo e((string) $jobFocusedStats['scheduled_interviews']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($applications === []): ?>
                            <div class="rounded-3xl border border-dashed border-slate-700/60 bg-slate-900/30 px-5 py-14 text-center sm:px-6 sm:py-16">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500/10 text-sm font-bold text-indigo-300">ST</div>
                                <h4 class="mt-4 font-display text-lg font-bold text-white">No applicants found</h4>
                                <p class="mt-2 max-w-md mx-auto text-sm leading-6 text-slate-400">
                                    <?php echo e($selectedJobId > 0 ? 'No applicants are available for this selected job under the current filters.' : 'Try another filter or wait for candidates to apply to your jobs.'); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto rounded-2xl border border-slate-700/50">
                                <table class="min-w-full divide-y divide-slate-700/50 text-sm">
                                    <thead class="bg-slate-900/70 text-left text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                        <tr>
                                            <th class="px-4 py-4 font-semibold">Candidate</th>
                                            <th class="px-4 py-4 font-semibold">Job</th>
                                            <th class="px-4 py-4 font-semibold">Applied</th>
                                            <th class="px-4 py-4 font-semibold">Average Score</th>
                                            <th class="px-4 py-4 font-semibold">Interviews</th>
                                            <th class="px-4 py-4 font-semibold">Selected</th>
                                            <th class="px-4 py-4 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/40 bg-slate-950/20">
                                        <?php foreach ($applications as $application): ?>
                                            <?php [$appLabel, $appClass] = applicant_badge((string) $application['status']); [$jobLabel, $jobClass] = job_state_badge((string) $application['expiry_date']); ?>
                                            <tr class="align-top transition-colors hover:bg-slate-900/30">
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[190px]">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <div class="font-semibold text-white"><?php echo e((string) $application['candidate_name']); ?></div>
                                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?php echo e($appClass); ?>"><?php echo e($appLabel); ?></span>
                                                        </div>
                                                        <div class="mt-1 text-xs text-slate-400"><?php echo e((string) $application['candidate_email']); ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[150px]">
                                                        <div class="font-medium text-slate-200"><?php echo e((string) $application['job_title']); ?></div>
                                                        <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500"><?php echo e($jobLabel); ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[170px] font-medium text-white"><?php echo e(format_datetime_label((string) $application['applied_at'])); ?></div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[110px] font-extrabold text-white"><?php echo e(number_format((float) ($application['average_score'] ?? 0), 2)); ?></div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[90px] font-medium text-slate-300"><?php echo e((string) ((int) ($application['interview_count'] ?? 0))); ?></div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="min-w-[90px] font-medium text-slate-300"><?php echo e((string) ($application['status'] === 'selected' ? 'Yes' : 'No')); ?></div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex min-w-[280px] flex-wrap justify-end gap-2">
                                                        <?php foreach (['shortlisted' => 'Shortlist', 'rejected' => 'Reject', 'selected' => 'Select'] as $value => $label): ?>
                                                            <form method="post" class="inline-flex">
                                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo e($value); ?>">
                                                                <button type="submit" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80"><?php echo e($label); ?></button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                        <button type="button" class="schedule-trigger rounded-xl bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80" data-application-id="<?php echo (int) $application['id']; ?>" data-candidate="<?php echo e((string) $application['candidate_name']); ?>">Schedule interview</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-6 flex flex-col gap-3 border-t border-slate-700/50 px-1 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-slate-400">
                                    Showing <?php echo e((string) min($totalApplications, $offset + 1)); ?>-<?php echo e((string) min($totalApplications, $offset + count($applications))); ?> of <?php echo e((string) $totalApplications); ?> applicants
                                </p>
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <?php
                                        $prevDisabled = $page <= 1;
                                        $nextDisabled = $page >= $totalPages;
                                        $baseQuery = [
                                            'q' => $search,
                                            'job_id' => $selectedJobId,
                                            'status' => $selectedStatus,
                                        ];
                                        $prevUrl = '?' . http_build_query(array_merge($baseQuery, ['page' => max(1, $page - 1)]));
                                        $nextUrl = '?' . http_build_query(array_merge($baseQuery, ['page' => min($totalPages, $page + 1)]));
                                    ?>
                                    <a href="<?php echo e($prevUrl); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 <?php echo $prevDisabled ? 'pointer-events-none opacity-40' : 'hover:bg-slate-800/80'; ?>">Previous</a>
                                    <span class="rounded-xl border border-slate-700/50 bg-slate-900/40 px-4 py-2 text-sm font-semibold text-slate-300">Page <?php echo e((string) $page); ?> of <?php echo e((string) $totalPages); ?></span>
                                    <a href="<?php echo e($nextUrl); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 <?php echo $nextDisabled ? 'pointer-events-none opacity-40' : 'hover:bg-slate-800/80'; ?>">Next</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<div id="interviewModal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 px-4 py-8 backdrop-blur-sm">
    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto overflow-hidden rounded-2xl glass-card p-5 sm:p-6">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Schedule interview</p>
                <h3 id="interviewModalTitle" class="mt-1 font-display text-xl font-extrabold text-white">Candidate</h3>
            </div>
            <button type="button" id="closeInterviewModal" class="w-full rounded-xl border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80 sm:w-auto">Close</button>
        </div>
        <form method="post" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="schedule_interview">
            <input type="hidden" name="application_id" id="interviewApplicationId" value="">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Date</label>
                    <input type="date" name="interview_date" required class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Time</label>
                    <input type="time" name="interview_time" required class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                </div>
            </div>
            <p class="text-sm text-slate-400">A Jitsi meeting link will be stored with the interview record when you schedule it.</p>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" id="cancelInterviewModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80 sm:flex-1">Cancel</button>
                <button type="submit" class="rounded-xl bg-slate-800 border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80 sm:flex-1">Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const toastDataEl = document.querySelector('[data-toast-type][data-toast-message]');
    const toast = document.getElementById('toast');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const recruiterMenuBtn = document.getElementById('recruiterMenuBtn');
    const recruiterMenu = document.getElementById('recruiterMenu');
    const themeToggle = document.getElementById('themeToggle');
    const themeToggleLabel = document.getElementById('themeToggleLabel');
    const interviewModal = document.getElementById('interviewModal');
    const interviewModalTitle = document.getElementById('interviewModalTitle');
    const interviewApplicationId = document.getElementById('interviewApplicationId');
    const closeInterviewModal = document.getElementById('closeInterviewModal');
    const cancelInterviewModal = document.getElementById('cancelInterviewModal');

    function openInterviewModal(applicationId, candidateName) {
        if (interviewApplicationId) {
            interviewApplicationId.value = applicationId;
        }
        if (interviewModalTitle) {
            interviewModalTitle.textContent = candidateName || 'Candidate';
        }
        if (interviewModal) {
            interviewModal.classList.remove('hidden');
            interviewModal.classList.add('flex');
        }
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    window.toggleSidebar = function () {
        if (!sidebar) {
            return;
        }
        sidebar.classList.toggle('-translate-x-full');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
    };

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

    if (themeToggle) {
        const syncThemeLabel = function () {
            themeToggleLabel.textContent = document.documentElement.classList.contains('dark') ? 'Light mode' : 'Dark mode';
        };
        syncThemeLabel();
        themeToggle.addEventListener('click', function () {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('skilltrust-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            syncThemeLabel();
        });
    }

    document.querySelectorAll('.schedule-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            openInterviewModal(button.dataset.applicationId || '', button.dataset.candidate || 'Candidate');
        });
    });

    if (closeInterviewModal) {
        closeInterviewModal.addEventListener('click', function () {
            closeModal(interviewModal);
        });
    }
    if (cancelInterviewModal) {
        cancelInterviewModal.addEventListener('click', function () {
            closeModal(interviewModal);
        });
    }
    if (interviewModal) {
        interviewModal.addEventListener('click', function (event) {
            if (event.target === interviewModal) {
                closeModal(interviewModal);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal(interviewModal);
            if (recruiterMenu) {
                recruiterMenu.classList.add('hidden');
            }
        }
    });

    if (toastDataEl && toast && toastDataEl.dataset.toastType && toastDataEl.dataset.toastMessage) {
        toast.textContent = toastDataEl.dataset.toastMessage;
        toast.classList.remove('hidden');
        if (toastDataEl.dataset.toastType === 'success') {
            toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border bg-emerald-500/15 border-emerald-500/30 text-emerald-300';
        } else {
            toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border bg-rose-500/15 border-rose-500/30 text-rose-300';
        }
        setTimeout(function () {
            toast.classList.add('hidden');
        }, 3200);
    }
}());
</script>
</body>
</html>
