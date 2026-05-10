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

if (!function_exists('interview_bind_params')) {
    function interview_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
    {
        $bind = [$types];
        foreach ($params as $idx => &$value) {
            $bind[] = &$params[$idx];
        }

        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('interview_status_badge')) {
    function interview_status_badge(string $status): array
    {
        $map = [
            'scheduled' => ['Scheduled', 'bg-blue-500/10 text-blue-700 dark:text-blue-300'],
            'completed' => ['Completed', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'],
            'cancelled' => ['Cancelled', 'bg-rose-500/10 text-rose-700 dark:text-rose-300'],
        ];

        return $map[strtolower($status)] ?? ['Unknown', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
    }
}

if (!function_exists('application_status_badge')) {
    function application_status_badge(string $status): array
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

$statusMeta = [
    'approved' => ['Approved', 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'],
    'pending' => ['Pending approval', 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'],
    'blocked' => ['Blocked', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
    'rejected' => ['Rejected', 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'],
];
[$statusLabel, $statusClass] = $statusMeta[$recruiterStatus] ?? ['Unknown', 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200'];

$requiredTables = ['recruiters', 'jobs', 'applications', 'users', 'interviews', 'results'];
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!db_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}
$schemaReady = $missingTables === [];
$hasAverageSnapshotColumn = db_column_exists($conn, 'applications', 'average_score_snapshot');
$interviewDateColumn = db_column_exists($conn, 'interviews', 'interview_datetime')
    ? 'interview_datetime'
    : (db_column_exists($conn, 'interviews', 'scheduled_at') ? 'scheduled_at' : '');
$hasInterviewNotesColumn = db_column_exists($conn, 'interviews', 'notes');
if ($schemaReady && $interviewDateColumn === '') {
    $errors['general'] = 'The interviews table is missing the interview_datetime column.';
    $schemaReady = false;
}

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
$selectedJobId = max(0, (int) ($_GET['job_id'] ?? 0));
$selectedStatus = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($selectedStatus, ['all', 'scheduled', 'completed', 'cancelled'], true)) {
    $selectedStatus = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$jobs = [];
$scheduleApplications = [];
$interviews = [];
$summary = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'upcoming' => 0,
];
$totalInterviews = 0;
$errors = [];

$scheduleForm = [
    'job_id' => $selectedJobId > 0 ? (string) $selectedJobId : '',
    'application_id' => '',
    'interview_date' => '',
    'interview_time' => '',
    'notes' => '',
];

if ($schemaReady) {
    $jobsStmt = $conn->prepare(
        'SELECT j.id, j.title, COUNT(a.id) AS applicant_count
         FROM jobs j
         LEFT JOIN applications a ON a.job_id = j.id
         WHERE j.recruiter_id = ?
         GROUP BY j.id, j.title
         ORDER BY j.created_at DESC'
    );
    if ($jobsStmt) {
        $jobsStmt->bind_param('i', $recruiterId);
        $jobsStmt->execute();
        $jobs = db_fetch_all($jobsStmt);
        $jobsStmt->close();
    }

    $scheduleApplicationsStmt = $conn->prepare(
        'SELECT
            a.id,
            a.job_id,
            a.status,
            u.name AS candidate_name,
            u.email AS candidate_email,
            j.title AS job_title,
            ' . (
                $hasAverageSnapshotColumn
                    ? 'COALESCE((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id), a.average_score_snapshot)'
                    : '(SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id)'
            ) . ' AS average_score
         FROM applications a
         INNER JOIN jobs j ON j.id = a.job_id
         INNER JOIN users u ON u.id = a.user_id
         WHERE j.recruiter_id = ?
           AND a.status IN ("shortlisted", "selected", "applied")
         ORDER BY a.applied_at DESC'
    );
    if ($scheduleApplicationsStmt) {
        $scheduleApplicationsStmt->bind_param('i', $recruiterId);
        $scheduleApplicationsStmt->execute();
        $scheduleApplications = db_fetch_all($scheduleApplicationsStmt);
        $scheduleApplicationsStmt->close();
    }

    $summaryStmt = $interviewDateColumn !== '' ? $conn->prepare(
        'SELECT
            COUNT(i.id) AS total,
            SUM(CASE WHEN i.status = "scheduled" THEN 1 ELSE 0 END) AS scheduled,
            SUM(CASE WHEN i.status = "completed" THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN i.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN i.status = "scheduled" AND i.' . $interviewDateColumn . ' >= NOW() THEN 1 ELSE 0 END) AS upcoming
         FROM interviews i
         INNER JOIN applications a ON a.id = i.application_id
         INNER JOIN jobs j ON j.id = a.job_id
         WHERE j.recruiter_id = ?'
    ) : false;
    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $recruiterId);
        $summaryStmt->execute();
        $summaryRow = db_fetch_one($summaryStmt);
        $summaryStmt->close();
        if ($summaryRow) {
            $summary['total'] = (int) ($summaryRow['total'] ?? 0);
            $summary['scheduled'] = (int) ($summaryRow['scheduled'] ?? 0);
            $summary['completed'] = (int) ($summaryRow['completed'] ?? 0);
            $summary['cancelled'] = (int) ($summaryRow['cancelled'] ?? 0);
            $summary['upcoming'] = (int) ($summaryRow['upcoming'] ?? 0);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_interview_status') {
        $interviewId = (int) ($_POST['interview_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (!in_array($newStatus, ['scheduled', 'completed', 'cancelled'], true)) {
            set_flash_toast('error', 'Please choose a valid interview status.');
        } else {
            $checkStmt = $conn->prepare(
                'SELECT i.id
                 FROM interviews i
                 INNER JOIN applications a ON a.id = i.application_id
                 INNER JOIN jobs j ON j.id = a.job_id
                 WHERE i.id = ? AND j.recruiter_id = ?
                 LIMIT 1'
            );
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $interviewId, $recruiterId);
                $checkStmt->execute();
                $checkRow = db_fetch_one($checkStmt);
                $checkStmt->close();

                if (!$checkRow) {
                    set_flash_toast('error', 'Interview not found or access denied.');
                } else {
                    $updateStmt = db_column_exists($conn, 'interviews', 'updated_at')
                        ? $conn->prepare('UPDATE interviews SET status = ?, updated_at = NOW() WHERE id = ?')
                        : $conn->prepare('UPDATE interviews SET status = ? WHERE id = ?');
                    if ($updateStmt) {
                        $updateStmt->bind_param('si', $newStatus, $interviewId);
                        if ($updateStmt->execute()) {
                            $updateStmt->close();
                            set_flash_toast('success', 'Interview status updated.');
                            header('Location: interview.php?' . http_build_query([
                                'job_id' => $selectedJobId,
                                'status' => $selectedStatus,
                                'q' => $search,
                                'page' => $page,
                            ]));
                            exit;
                        }
                        $updateStmt->close();
                        set_flash_toast('error', 'Unable to update interview status.');
                    }
                }
            }
        }
    }
}

$jobFilterSql = $selectedJobId > 0 ? ' AND j.id = ?' : '';
$statusFilterSql = $selectedStatus !== 'all' ? ' AND i.status = ?' : '';
$searchFilterSql = $search !== '' ? ' AND (u.name LIKE ? OR u.email LIKE ? OR j.title LIKE ?)' : '';

$countSql = '
    SELECT COUNT(i.id) AS total
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN users u ON a.user_id = u.id
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
    if (interview_bind_params($countStmt, $countTypes, $countParams)) {
        $countStmt->execute();
        $countRow = db_fetch_one($countStmt);
        $totalInterviews = (int) ($countRow['total'] ?? 0);
    }
    $countStmt->close();
}

if ($totalInterviews > 0 && $page > (int) ceil($totalInterviews / $perPage)) {
    $page = max(1, (int) ceil($totalInterviews / $perPage));
    $offset = ($page - 1) * $perPage;
}

$listSql = '
    SELECT
        i.id,
        i.application_id,
        i.interview_id,
        i.meeting_link,
        i.' . $interviewDateColumn . ' AS scheduled_at,
        ' . ($hasInterviewNotesColumn ? 'i.notes' : 'NULL') . ' AS notes,
        i.status,
        i.created_at,
        a.status AS application_status,
        j.id AS job_id,
        j.title AS job_title,
        COALESCE(NULLIF(u.name, ""), CONCAT("Candidate #", a.user_id)) AS candidate_name,
        COALESCE(NULLIF(u.email, ""), "No email available") AS candidate_email
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE j.recruiter_id = ?' . $jobFilterSql . $statusFilterSql . $searchFilterSql . '
    ORDER BY
        CASE WHEN i.status = "scheduled" AND i.' . $interviewDateColumn . ' >= NOW() THEN 0 ELSE 1 END,
        i.' . $interviewDateColumn . ' ASC
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
    if (interview_bind_params($listStmt, $listTypes, $listParams)) {
        $listStmt->execute();
        $interviews = db_fetch_all($listStmt);
    } else {
        $errors['general'] = 'Unable to load interviews right now.';
    }
    $listStmt->close();
}

$jobOptions = [];
foreach ($jobs as $job) {
    $jobOptions[] = [
        'id' => (int) ($job['id'] ?? 0),
        'title' => (string) ($job['title'] ?? ''),
        'count' => (int) ($job['applicant_count'] ?? 0),
    ];
}

$applicationOptions = [];
foreach ($scheduleApplications as $application) {
    $jobId = (int) ($application['job_id'] ?? 0);
    if (!isset($applicationOptions[$jobId])) {
        $applicationOptions[$jobId] = [];
    }
    $applicationOptions[$jobId][] = [
        'id' => (int) ($application['id'] ?? 0),
        'name' => (string) ($application['candidate_name'] ?? ''),
        'email' => (string) ($application['candidate_email'] ?? ''),
        'job_title' => (string) ($application['job_title'] ?? ''),
        'average_score' => normalize_score($application['average_score'] ?? 0),
        'status' => (string) ($application['status'] ?? 'applied'),
    ];
}

$cards = [
    ['Total interviews', $summary['total']],
    ['Upcoming', $summary['upcoming']],
    ['Completed', $summary['completed']],
    ['Cancelled', $summary['cancelled']],
];
$currentPage = 'interview.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interviews | SkillTrust</title>
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
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
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
                    <h2 class="font-display font-bold text-white text-lg">Interviews</h2>
                    <p class="text-xs text-slate-500">Schedule and track candidate conversations</p>
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
                        <a href="manage_jobs.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Manage Jobs</a>
                        <a href="applicants.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Applicants</a>
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

                <section class="fade-up relative overflow-hidden rounded-3xl border border-indigo-500/25 bg-gradient-to-br from-indigo-900/35 via-slate-900/80 to-violet-900/30 p-5 sm:p-8">
                    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 bottom-0 w-44 h-44 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
                    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,420px)] xl:items-end">
                        <div class="max-w-3xl min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                Interview pipeline
                            </div>
                            <h2 class="mt-4 font-display font-extrabold text-2xl sm:text-3xl text-white">Schedule future calls, keep the pipeline organized, and close interviews with confidence.</h2>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">This panel brings together candidate scheduling and interview tracking. Start with a job, pick an eligible candidate, then manage scheduled, completed, and cancelled interviews from one place.</p>
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

                <?php if (!$schemaReady): ?>
                    <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                        Missing recruiter interview tables. Run `sql/recruiter_hiring_panel.sql` first.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"><?php echo e($errors['general']); ?></div>
                <?php endif; ?>

                <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="flex flex-col gap-4 border-b border-slate-700/50 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="font-display font-bold text-white text-base">Interview List</h3>
                                <p class="mt-1 text-sm text-slate-500">Filter by job, status, or candidate.</p>
                            </div>
                            <form method="get" class="grid w-full gap-3 lg:grid-cols-[1.2fr_0.85fr_0.85fr_auto]">
                                <input type="hidden" name="page" value="1">
                                <input name="q" value="<?php echo e($search); ?>" placeholder="Search candidate or job..." class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:border-indigo-500">
                                <select name="job_id" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                    <option value="0">All jobs</option>
                                    <?php foreach ($jobOptions as $job): ?>
                                        <option value="<?php echo (int) $job['id']; ?>" <?php echo $selectedJobId === (int) $job['id'] ? 'selected' : ''; ?>>
                                            <?php echo e((string) $job['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="status" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                    <?php foreach (['all' => 'All status', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                        <option value="<?php echo e($value); ?>" <?php echo $selectedStatus === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="rounded-2xl bg-slate-800 border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-700/80">Apply</button>
                            </form>
                        </div>

                        <div class="p-4 sm:p-6">
                            <?php if ($interviews === []): ?>
                                <div class="rounded-3xl border border-dashed border-slate-700/60 bg-slate-900/30 px-5 py-14 text-center sm:px-6 sm:py-16">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500/10 text-sm font-bold text-indigo-300">ST</div>
                                    <h4 class="mt-4 font-display text-lg font-bold text-white">No interviews yet</h4>
                                    <p class="mt-2 max-w-md mx-auto text-sm leading-6 text-slate-400">Use the scheduler on this page to book the first conversation with an eligible candidate.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto rounded-2xl border border-slate-700/50">
                                    <table class="min-w-full divide-y divide-slate-700/50 text-sm">
                                        <thead class="bg-slate-900/70 text-left text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                            <tr>
                                                <th class="px-4 py-4 font-semibold">Candidate</th>
                                                <th class="px-4 py-4 font-semibold">Job</th>
                                                <th class="px-4 py-4 font-semibold">Scheduled At</th>
                                                <th class="px-4 py-4 font-semibold">Status</th>
                                                <th class="px-4 py-4 font-semibold">Notes</th>
                                                <th class="px-4 py-4 font-semibold text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-700/40 bg-slate-950/20">
                                            <?php foreach ($interviews as $interview): ?>
                                                <?php
                                                    [$interviewLabel, $interviewClass] = interview_status_badge((string) $interview['status']);
                                                    [$applicationLabel, $applicationClass] = application_status_badge((string) $interview['application_status']);
                                                ?>
                                                <tr class="align-top transition-colors hover:bg-slate-900/30">
                                                    <td class="px-4 py-4">
                                                        <div class="min-w-[180px]">
                                                            <div class="font-semibold text-white"><?php echo e((string) $interview['candidate_name']); ?></div>
                                                            <div class="mt-1 text-xs text-slate-400"><?php echo e((string) $interview['candidate_email']); ?></div>
                                                            <div class="mt-2 inline-flex flex-wrap gap-2">
                                                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?php echo e($interviewClass); ?>"><?php echo e($interviewLabel); ?></span>
                                                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?php echo e($applicationClass); ?>"><?php echo e($applicationLabel); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="min-w-[140px] font-medium text-slate-200"><?php echo e((string) $interview['job_title']); ?></div>
                                                        <div class="mt-1 text-xs text-slate-500">Interview #<?php echo e((string) ((int) $interview['id'])); ?></div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="min-w-[170px] font-medium text-white"><?php echo e(format_datetime_label((string) $interview['scheduled_at'])); ?></div>
                                                        <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500"><?php echo e(date('D', strtotime((string) $interview['scheduled_at']))); ?></div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="min-w-[120px] text-slate-300"><?php echo e($interviewLabel); ?></div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="min-w-[220px] text-slate-300"><?php echo e(trim((string) ($interview['notes'] ?? '')) !== '' ? (string) $interview['notes'] : 'No notes added'); ?></div>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="flex min-w-[220px] flex-col items-stretch gap-2 sm:items-end">
                                                            <?php if (!empty($interview['interview_id']) || (int) ($interview['id'] ?? 0) > 0): ?>
                                                                <a href="interviewer_panel.php?<?php echo http_build_query([
                                                                    'id' => (int) ($interview['id'] ?? 0),
                                                                    'interview_id' => (string) ($interview['interview_id'] ?? ''),
                                                                ]); ?>" class="w-full rounded-xl border border-indigo-500/30 px-4 py-2.5 text-center text-sm font-semibold text-indigo-200 transition-all duration-300 hover:bg-indigo-500/10">Open panel</a>
                                                            <?php endif; ?>
                                                            <?php if ((string) $interview['status'] !== 'completed'): ?>
                                                                <form method="post" class="w-full">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                                    <input type="hidden" name="action" value="update_interview_status">
                                                                    <input type="hidden" name="interview_id" value="<?php echo (int) $interview['id']; ?>">
                                                                    <input type="hidden" name="status" value="completed">
                                                                    <button type="submit" class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80">Mark completed</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ((string) $interview['status'] !== 'cancelled'): ?>
                                                                <form method="post" class="w-full">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                                    <input type="hidden" name="action" value="update_interview_status">
                                                                    <input type="hidden" name="interview_id" value="<?php echo (int) $interview['id']; ?>">
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" class="w-full rounded-xl border border-rose-500/30 px-4 py-2.5 text-sm font-semibold text-rose-300 transition-all duration-300 hover:bg-rose-500/10">Cancel interview</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-6 flex flex-col gap-3 border-t border-slate-700/50 px-1 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm text-slate-400">
                                        Showing <?php echo e((string) min($totalInterviews, $offset + 1)); ?>-<?php echo e((string) min($totalInterviews, $offset + count($interviews))); ?> of <?php echo e((string) $totalInterviews); ?> interviews
                                    </p>
                                    <div class="flex flex-wrap gap-2 sm:justify-end">
                                        <?php
                                            $prevDisabled = $page <= 1;
                                            $nextDisabled = $page >= max(1, (int) ceil(max($totalInterviews, 1) / $perPage));
                                            $baseQuery = ['q' => $search, 'job_id' => $selectedJobId, 'status' => $selectedStatus];
                                            $prevUrl = '?' . http_build_query(array_merge($baseQuery, ['page' => max(1, $page - 1)]));
                                            $nextUrl = '?' . http_build_query(array_merge($baseQuery, ['page' => $page + 1]));
                                        ?>
                                        <a href="<?php echo e($prevUrl); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 <?php echo $prevDisabled ? 'pointer-events-none opacity-40' : 'hover:bg-slate-800/80'; ?>">Previous</a>
                                        <span class="rounded-xl border border-slate-700/50 bg-slate-900/40 px-4 py-2 text-sm font-semibold text-slate-300">Page <?php echo e((string) $page); ?></span>
                                        <a href="<?php echo e($nextUrl); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 <?php echo $nextDisabled ? 'pointer-events-none opacity-40' : 'hover:bg-slate-800/80'; ?>">Next</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="border-b border-slate-700/50 p-4 sm:p-5">
                            <h3 class="font-display font-bold text-white text-base">Schedule Interview</h3>
                            <p class="mt-1 text-sm text-slate-500">Pick a job first, then choose one of its eligible applicants.</p>
                        </div>
                        <div class="p-4 sm:p-5">
                            <form method="post" action="schedule_interview.php" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Job</label>
                                    <select name="job_id" id="scheduleJobId" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                        <option value="">Select a job</option>
                                        <?php foreach ($jobOptions as $job): ?>
                                            <option value="<?php echo (int) $job['id']; ?>" <?php echo $scheduleForm['job_id'] === (string) $job['id'] ? 'selected' : ''; ?>>
                                                <?php echo e((string) $job['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['job_id'])): ?><p class="mt-1 text-xs text-rose-300"><?php echo e($errors['job_id']); ?></p><?php endif; ?>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Candidate</label>
                                    <select name="application_id" id="scheduleApplicationId" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                        <option value="">Select a candidate</option>
                                    </select>
                                    <?php if (isset($errors['application_id'])): ?><p class="mt-1 text-xs text-rose-300"><?php echo e($errors['application_id']); ?></p><?php endif; ?>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Date</label>
                                        <input type="date" name="interview_date" value="<?php echo e($scheduleForm['interview_date']); ?>" required class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Time</label>
                                        <input type="time" name="interview_time" value="<?php echo e($scheduleForm['interview_time']); ?>" required class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none focus:border-indigo-500">
                                    </div>
                                </div>
                                <?php if (isset($errors['scheduled_at'])): ?><p class="text-xs text-rose-300"><?php echo e($errors['scheduled_at']); ?></p><?php endif; ?>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Notes</label>
                                    <textarea name="notes" rows="4" class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:border-indigo-500" placeholder="Optional interview context"><?php echo e($scheduleForm['notes']); ?></textarea>
                                </div>

                                <button type="submit" class="w-full rounded-2xl bg-slate-800 border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80">Schedule Interview</button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script id="scheduleApplicationsData" type="application/json"><?php echo json_encode($applicationOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
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
    const scheduleJobId = document.getElementById('scheduleJobId');
    const scheduleApplicationId = document.getElementById('scheduleApplicationId');
    const applicationsByJob = JSON.parse(document.getElementById('scheduleApplicationsData').textContent || '{}');
    const selectedApplicationValue = <?php echo json_encode($scheduleForm['application_id'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

    function refreshCandidateOptions() {
        if (!scheduleJobId || !scheduleApplicationId) {
            return;
        }
        const jobId = scheduleJobId.value || '';
        const list = applicationsByJob[jobId] || [];
        scheduleApplicationId.innerHTML = '<option value="">Select a candidate</option>';
        list.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name + ' - ' + item.job_title + ' (' + Number(item.average_score || 0).toFixed(2) + ')';
            if (selectedApplicationValue && String(item.id) === String(selectedApplicationValue)) {
                option.selected = true;
            }
            scheduleApplicationId.appendChild(option);
        });
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

    if (scheduleJobId) {
        refreshCandidateOptions();
        scheduleJobId.addEventListener('change', function () {
            while (scheduleApplicationId.firstChild) {
                scheduleApplicationId.removeChild(scheduleApplicationId.firstChild);
            }
            refreshCandidateOptions();
        });
    }

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
