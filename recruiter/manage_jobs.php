<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_recruiter_login();

if (empty($_SESSION['recruiter_manage_jobs_csrf'])) {
    $_SESSION['recruiter_manage_jobs_csrf'] = bin2hex(random_bytes(32));
}

$recruiterId = current_recruiter_id();
$toast = consume_flash_toast();
$recruiterName = (string) ($_SESSION['recruiter_name'] ?? current_recruiter_display_name());
$companyName = (string) ($_SESSION['recruiter_company'] ?? $recruiterName);
$recruiterEmail = (string) ($_SESSION['recruiter_email'] ?? '');
$recruiterStatus = strtolower(trim((string) ($_SESSION['recruiter_status'] ?? 'approved')));
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName), 0, 2) ?: 'ST');

if (!function_exists('recruiter_bind_params')) {
    function recruiter_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
    {
        $bind = [$types];
        foreach ($params as $idx => &$value) {
            $bind[] = &$params[$idx];
        }

        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('recruiter_job_state')) {
    function recruiter_job_state(string $expiryDate): array
    {
        return strtotime($expiryDate) >= strtotime(date('Y-m-d'))
            ? ['Active', 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border-emerald-500/20']
            : ['Expired', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 border-slate-200/80 dark:border-white/10'];
    }
}

if (!function_exists('recruiter_format_status')) {
    function recruiter_format_status(string $status): array
    {
        $map = [
            'applied' => ['Applied', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'],
            'shortlisted' => ['Shortlisted', 'bg-amber-500/10 text-amber-600 dark:text-amber-300'],
            'rejected' => ['Rejected', 'bg-rose-500/10 text-rose-600 dark:text-rose-300'],
            'selected' => ['Selected', 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'],
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

$pageTitle = 'Manage Jobs';
$pageToast = null;
$errors = [];
$currentPage = 'manage_jobs.php';

$search = trim((string) ($_GET['q'] ?? ''));
$tab = strtolower(trim((string) ($_GET['tab'] ?? 'active')));
if (!in_array($tab, ['active', 'expired'], true)) {
    $tab = 'active';
}

$perPage = 6;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$jobMinAverageColumn = db_column_exists($conn, 'jobs', 'min_average_score') ? 'min_average_score' : (db_column_exists($conn, 'jobs', 'min_avg_score') ? 'min_avg_score' : '');
$jobUpdatedAtColumn = db_column_exists($conn, 'jobs', 'updated_at') ? 'updated_at' : 'created_at';
$hasRequiredTestColumns = db_column_exists($conn, 'jobs', 'required_test_id') && db_column_exists($conn, 'jobs', 'min_test_score');
$hasJobRequiredTestsTable = db_table_exists($conn, 'job_required_tests');
$hasAverageSnapshotColumn = db_column_exists($conn, 'applications', 'average_score_snapshot');

if ($jobMinAverageColumn === '') {
    $errors['general'] = 'The jobs table is missing a supported minimum score column.';
}

$profileQuery = $conn->prepare('SELECT company_name, email, status FROM recruiters WHERE id = ? LIMIT 1');
if ($profileQuery) {
    $profileQuery->bind_param('i', $recruiterId);
    $profileQuery->execute();
    $profile = db_fetch_one($profileQuery);
    $profileQuery->close();
    if ($profile) {
        $companyName = (string) ($profile['company_name'] ?? $companyName);
        $recruiterEmail = (string) ($profile['email'] ?? $recruiterEmail);
        $recruiterStatus = strtolower(trim((string) ($profile['status'] ?? $recruiterStatus)));
        $_SESSION['recruiter_company'] = $companyName;
        $_SESSION['recruiter_email'] = $recruiterEmail;
        $_SESSION['recruiter_status'] = $recruiterStatus;
    }
}

$conditions = ['j.recruiter_id = ?'];
$types = 'i';
$params = [$recruiterId];

if ($tab === 'active') {
    $conditions[] = 'j.expiry_date >= CURDATE()';
} else {
    $conditions[] = 'j.expiry_date < CURDATE()';
}

if ($search !== '') {
    $conditions[] = '(j.title LIKE ? OR j.description LIKE ?)';
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $conditions);
$orderSql = $tab === 'active'
    ? 'j.expiry_date ASC, j.created_at DESC'
    : 'j.expiry_date DESC, j.created_at DESC';

$summary = [
    'total_jobs' => 0,
    'active_jobs' => 0,
    'expired_jobs' => 0,
    'total_applicants' => 0,
];
$jobs = [];
$totalJobs = 0;

$summaryStmt = $conn->prepare(
    'SELECT
        COUNT(*) AS total_jobs,
        SUM(CASE WHEN expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS active_jobs,
        SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_jobs
     FROM jobs
     WHERE recruiter_id = ?'
);
if ($summaryStmt) {
    $summaryStmt->bind_param('i', $recruiterId);
    $summaryStmt->execute();
    $summaryRow = db_fetch_one($summaryStmt);
    $summaryStmt->close();
    if ($summaryRow) {
        $summary['total_jobs'] = (int) ($summaryRow['total_jobs'] ?? 0);
        $summary['active_jobs'] = (int) ($summaryRow['active_jobs'] ?? 0);
        $summary['expired_jobs'] = (int) ($summaryRow['expired_jobs'] ?? 0);
    }
}

$applicationsStmt = $conn->prepare(
    'SELECT COUNT(a.id) AS total_applicants
     FROM applications a
     INNER JOIN jobs j ON j.id = a.job_id
     WHERE j.recruiter_id = ?'
);
if ($applicationsStmt) {
    $applicationsStmt->bind_param('i', $recruiterId);
    $applicationsStmt->execute();
    $applicationsRow = db_fetch_one($applicationsStmt);
    $applicationsStmt->close();
    if ($applicationsRow) {
        $summary['total_applicants'] = (int) ($applicationsRow['total_applicants'] ?? 0);
    }
}

$countSql = "SELECT COUNT(*) AS total_jobs FROM jobs j WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    $errors['general'] = 'SQL Error: ' . $conn->error;
} else {
    if ($search !== '') {
        $countStmt->bind_param('iss', $recruiterId, $like, $like);
    } else {
        $countStmt->bind_param('i', $recruiterId);
    }
    $countStmt->execute();
    $countRow = db_fetch_one($countStmt);
    $countStmt->close();
    $totalJobs = (int) ($countRow['total_jobs'] ?? 0);
}

if ($totalJobs > 0 && $page > (int) ceil($totalJobs / $perPage)) {
    $page = max(1, (int) ceil($totalJobs / $perPage));
    $offset = ($page - 1) * $perPage;
}

$selectColumns = [
    'j.id',
    'j.title',
    'j.description',
    sprintf('j.%s AS min_average_score', $jobMinAverageColumn !== '' ? $jobMinAverageColumn : 'id'),
    'j.expiry_date',
    'j.created_at',
    sprintf('j.%s AS updated_at', $jobUpdatedAtColumn),
    '(SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicant_count',
    '(SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = "applied") AS applied_count',
    '(SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = "shortlisted") AS shortlisted_count',
    '(SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = "rejected") AS rejected_count',
    '(SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = "selected") AS selected_count',
    $hasAverageSnapshotColumn
        ? '(SELECT ROUND(AVG(a.average_score_snapshot), 2) FROM applications a WHERE a.job_id = j.id) AS avg_applicant_score'
        : '(SELECT ROUND(AVG((SELECT AVG(r.score) FROM results r WHERE r.user_id = a.user_id)), 2) FROM applications a WHERE a.job_id = j.id) AS avg_applicant_score',
];
if ($hasRequiredTestColumns) {
    $selectColumns[] = 'j.required_test_id';
    $selectColumns[] = 'j.min_test_score';
    $selectColumns[] = '(SELECT t.title FROM tests t WHERE t.id = j.required_test_id LIMIT 1) AS required_test_title';
}
if ($hasJobRequiredTestsTable) {
    $selectColumns[] = '(SELECT COUNT(*) FROM job_required_tests jrt WHERE jrt.job_id = j.id) AS required_tests_count';
    $selectColumns[] = '(SELECT GROUP_CONCAT(CONCAT(t.title, " >= ", FORMAT(jrt.min_score, 2)) ORDER BY t.title SEPARATOR " | ") FROM job_required_tests jrt INNER JOIN tests t ON t.id = jrt.test_id WHERE jrt.job_id = j.id) AS required_tests_summary';
}

$jobsSql = sprintf(
    'SELECT %s
     FROM jobs j
     WHERE %s
     ORDER BY %s
     LIMIT ? OFFSET ?',
    implode(",\n            ", $selectColumns),
    $whereSql,
    $orderSql
);

$jobsStmt = $conn->prepare($jobsSql);
if (!$jobsStmt) {
    $errors['general'] = 'SQL Error: ' . $conn->error;
} else {
    if ($search !== '') {
        $jobsStmt->bind_param('issii', $recruiterId, $like, $like, $perPage, $offset);
    } else {
        $jobsStmt->bind_param('iii', $recruiterId, $perPage, $offset);
    }
    $jobsStmt->execute();
    $jobs = db_fetch_all($jobsStmt);
    $jobsStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $action = trim((string) ($_POST['action'] ?? ''));
    $jobId = (int) ($_POST['job_id'] ?? 0);

    if ($action === 'delete_job' && $jobId > 0) {
        $lookup = $conn->prepare('SELECT id, title FROM jobs WHERE id = ? AND recruiter_id = ? LIMIT 1');
        if ($lookup) {
            $lookup->bind_param('ii', $jobId, $recruiterId);
            $lookup->execute();
            $jobRow = db_fetch_one($lookup);
            $lookup->close();

            if (!$jobRow) {
                set_flash_toast('error', 'Job not found or you do not have permission to delete it.');
            } else {
                $delete = $conn->prepare('DELETE FROM jobs WHERE id = ? AND recruiter_id = ? LIMIT 1');
                if ($delete) {
                    $delete->bind_param('ii', $jobId, $recruiterId);
                    if ($delete->execute()) {
                        set_flash_toast('success', 'Job deleted successfully.');
                        $delete->close();
                        $redirectUrl = 'manage_jobs.php?' . http_build_query([
                            'tab' => $tab,
                            'q' => $search,
                            'page' => $page,
                        ]);
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                    $delete->close();
                    set_flash_toast('error', 'Unable to delete this job right now.');
                } else {
                    set_flash_toast('error', 'Unable to prepare delete request.');
                }
            }
        } else {
            set_flash_toast('error', 'Unable to load the selected job.');
        }
    }
}

$tabs = [
    'active' => ['Active Jobs', $summary['active_jobs']],
    'expired' => ['Expired Jobs', $summary['expired_jobs']],
];

$baseQuery = [
    'q' => $search,
    'tab' => $tab,
];
$totalPages = max(1, (int) ceil(max($totalJobs, 1) / $perPage));

$jobPayload = [];
foreach ($jobs as $job) {
    $jobId = (int) ($job['id'] ?? 0);
    $jobPayload[$jobId] = [
        'id' => $jobId,
        'title' => (string) ($job['title'] ?? ''),
        'description' => (string) ($job['description'] ?? ''),
        'min_average_score' => normalize_score($job['min_average_score'] ?? 0),
        'expiry_date' => (string) ($job['expiry_date'] ?? ''),
        'created_at' => (string) ($job['created_at'] ?? ''),
        'updated_at' => (string) ($job['updated_at'] ?? ''),
        'applicant_count' => (int) ($job['applicant_count'] ?? 0),
        'applied_count' => (int) ($job['applied_count'] ?? 0),
        'shortlisted_count' => (int) ($job['shortlisted_count'] ?? 0),
        'rejected_count' => (int) ($job['rejected_count'] ?? 0),
        'selected_count' => (int) ($job['selected_count'] ?? 0),
        'avg_applicant_score' => normalize_score($job['avg_applicant_score'] ?? 0),
        'required_tests_count' => (int) ($job['required_tests_count'] ?? 0),
        'required_tests_summary' => (string) ($job['required_tests_summary'] ?? ''),
        'required_test_id' => isset($job['required_test_id']) ? (int) $job['required_test_id'] : null,
        'min_test_score' => isset($job['min_test_score']) ? normalize_score($job['min_test_score']) : null,
        'required_test_title' => (string) ($job['required_test_title'] ?? ''),
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | SkillTrust</title>
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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 30px 90px -40px rgba(15, 23, 42, 0.4)',
                        soft: '0 18px 60px -35px rgba(15, 23, 42, 0.2)'
                    },
                    keyframes: {
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        popIn: {
                            '0%': { opacity: '0', transform: 'scale(0.96)' },
                            '100%': { opacity: '1', transform: 'scale(1)' }
                        }
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.5s ease forwards',
                        'pop-in': 'popIn 0.2s ease forwards'
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
<div id="toast" class="hidden fixed bottom-6 right-6 z-[100] max-w-sm rounded-2xl border px-4 py-3 text-sm font-semibold shadow-2xl"></div>

<div class="flex min-h-screen">
    <?php require_once __DIR__ . '/../includes/recruiter_sidebar.php'; ?>

    <div class="recruiter-main flex min-w-0 flex-1 flex-col">
        <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden rounded-xl border border-slate-700/60 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-all duration-300">Menu</button>
                <div class="min-w-0">
                    <h2 class="font-display font-bold text-white text-lg">Manage Jobs</h2>
                    <p class="text-xs text-slate-500">Review roles, applicants, and hiring progress</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
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
                            <a href="create_job.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Create Job</a>
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
                    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,420px)] xl:items-end">
                        <div class="max-w-3xl min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                Job management center
                            </div>
                            <h2 class="mt-4 font-display font-extrabold text-2xl sm:text-3xl text-white">Track every role, filter by hiring stage, and open any job in a modal.</h2>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">Use the cards below to review a job, see how many candidates applied, and jump directly to applicants or interviews. Jobs are automatically classified as active or expired from the expiry date.</p>
                        </div>
                        <div class="grid w-full gap-3 sm:grid-cols-2 self-start xl:self-auto">
                            <div class="glass-card metric-card min-w-0 rounded-2xl p-4 sm:p-5">
                                <div class="text-[11px] uppercase leading-5 tracking-[0.18em] text-indigo-300">Total jobs</div>
                                <div class="mt-3 text-3xl font-extrabold leading-none text-white"><?php echo e((string) $summary['total_jobs']); ?></div>
                            </div>
                            <div class="glass-card metric-card min-w-0 rounded-2xl p-4 sm:p-5">
                                <div class="text-[11px] uppercase leading-5 tracking-[0.18em] text-indigo-300">Applicants</div>
                                <div class="mt-3 text-3xl font-extrabold leading-none text-white"><?php echo e((string) $summary['total_applicants']); ?></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-[0.18em] text-indigo-300">Total jobs</p>
                        <p class="mt-3 text-3xl font-extrabold text-white"><?php echo e((string) $summary['total_jobs']); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-[0.18em] text-indigo-300">Active jobs</p>
                        <p class="mt-3 text-3xl font-extrabold text-emerald-300"><?php echo e((string) $summary['active_jobs']); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-[0.18em] text-indigo-300">Expired jobs</p>
                        <p class="mt-3 text-3xl font-extrabold text-rose-300"><?php echo e((string) $summary['expired_jobs']); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-[0.18em] text-indigo-300">Applications</p>
                        <p class="mt-3 text-3xl font-extrabold text-white"><?php echo e((string) $summary['total_applicants']); ?></p>
                    </div>
                </section>

                <?php if (!empty($errors['general'])): ?>
                    <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"><?php echo e($errors['general']); ?></div>
                <?php endif; ?>

                <section class="glass-card rounded-2xl overflow-hidden">
                    <div class="flex flex-col gap-4 border-b border-slate-700/50 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="font-display font-bold text-white text-base">Jobs</h3>
                            <p class="mt-1 text-sm text-slate-500">Use the tabs to switch between active and expired jobs. Search filters by title and description.</p>
                        </div>
                        <form method="get" class="flex w-full flex-col gap-3 lg:w-auto">
                            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
                            <input type="hidden" name="page" value="1">
                            <div class="relative min-w-0 lg:w-80">
                                <input name="q" value="<?php echo e($search); ?>" placeholder="Search jobs..." class="w-full rounded-2xl border border-slate-700/50 bg-slate-900/60 px-4 py-3 pr-12 text-sm text-slate-100 outline-none transition placeholder:text-slate-500 focus:border-indigo-500">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">Search</span>
                            </div>
                            <button type="submit" class="w-full rounded-2xl bg-slate-800 border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-700/80 lg:w-auto">Search</button>
                        </form>
                    </div>

                    <div class="flex gap-2 overflow-x-auto border-b border-slate-700/50 p-4">
                        <?php foreach ($tabs as $key => $data): ?>
                            <a href="?<?php echo e(http_build_query(array_merge($baseQuery, ['tab' => $key, 'page' => 1]))); ?>" class="nav-item inline-flex whitespace-nowrap items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition <?php echo $tab === $key ? 'active text-white' : 'text-slate-400'; ?>">
                                <?php echo e($data[0]); ?>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold text-slate-300"><?php echo e((string) $data[1]); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-4 sm:p-6">
                        <?php if ($jobs === []): ?>
                            <div class="rounded-3xl border border-dashed border-slate-700/60 bg-slate-900/30 px-5 py-14 text-center sm:px-6 sm:py-16">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500/10 text-sm font-bold text-indigo-300">ST</div>
                                <h4 class="mt-4 font-display text-lg font-bold text-white">No jobs found</h4>
                                <p class="mt-2 max-w-md mx-auto text-sm leading-6 text-slate-400">Try a different search, switch tabs, or create a new role to start collecting applications.</p>
                                <a href="create_job.php" class="mt-5 inline-flex w-full justify-center rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-slate-200 border border-slate-700 hover:bg-slate-700/80 transition-all duration-300 sm:w-auto">Create Job</a>
                            </div>
                        <?php else: ?>
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($jobs as $job): ?>
                                    <?php
                                        $jobId = (int) ($job['id'] ?? 0);
                                        [$jobStateLabel, $jobStateClass] = recruiter_job_state((string) ($job['expiry_date'] ?? ''));
                                        $jobPayloadItem = $jobPayload[$jobId] ?? [];
                                        $hasRequiredTest = ($hasJobRequiredTestsTable && !empty($job['required_tests_summary']))
                                            || ($hasRequiredTestColumns && (int) ($job['required_test_id'] ?? 0) > 0);
                                    ?>
                                    <article
                                        class="job-card group relative cursor-pointer overflow-hidden glass-card rounded-2xl p-4 sm:p-5 transition duration-300 hover:-translate-y-1"
                                        role="button"
                                        tabindex="0"
                                        data-job-id="<?php echo e((string) $jobId); ?>"
                                    >
                                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400 opacity-80"></div>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="truncate text-lg font-bold text-white transition group-hover:text-indigo-200"><?php echo e((string) ($job['title'] ?? 'Untitled Job')); ?></h4>
                                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?php echo e($jobStateClass); ?>"><?php echo e($jobStateLabel); ?></span>
                                                </div>
                                                <p class="mt-3 max-h-24 overflow-hidden text-sm leading-6 text-slate-300"><?php echo e((string) ($job['description'] ?? '')); ?></p>
                                            </div>
                                            <div class="rounded-2xl bg-slate-800 px-3 py-2 text-left text-white border border-slate-700/70 sm:self-start sm:text-right">
                                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-300">Avg</div>
                                                <div class="text-lg font-extrabold"><?php echo e(number_format((float) ($job['min_average_score'] ?? 0), 2)); ?></div>
                                            </div>
                                        </div>

                                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                            <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Applicants</div>
                                                <div class="mt-1 text-lg font-bold text-white"><?php echo e((string) ((int) ($job['applicant_count'] ?? 0))); ?></div>
                                            </div>
                                            <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Expiry</div>
                                                <div class="mt-1 text-lg font-bold text-white"><?php echo e(format_date_label((string) ($job['expiry_date'] ?? ''))); ?></div>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-400">
                                            <span>Posted <?php echo e(time_ago_label((string) ($job['created_at'] ?? ''))); ?></span>
                                            <span><?php echo e((string) ((int) ($job['selected_count'] ?? 0))); ?> selected</span>
                                        </div>

                                        <?php if ($hasRequiredTest): ?>
                                            <div class="mt-4 rounded-2xl border border-sky-500/10 bg-slate-950/50 px-4 py-3">
                                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-300">Specific tests</div>
                                                <div class="mt-2 text-xs leading-5 text-slate-300">
                                                    <?php if (!empty($job['required_tests_summary'])): ?>
                                                        <?php echo e((string) $job['required_tests_summary']); ?>
                                                    <?php else: ?>
                                                        <?php echo e((string) ($job['required_test_title'] ?? 'Required test')); ?> >= <?php echo e(number_format((float) ($job['min_test_score'] ?? 0), 2)); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                            <button type="button" class="open-job-modal rounded-xl bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80 sm:flex-1" data-job-id="<?php echo e((string) $jobId); ?>">View details</button>
                                            <a href="applicants.php?job_id=<?php echo $jobId; ?>" class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80 sm:flex-1 sm:text-center" onclick="event.stopPropagation();">View applicants</a>
                                            <a href="edit_job.php?id=<?php echo $jobId; ?>" class="rounded-xl border border-indigo-500/30 px-4 py-2.5 text-sm font-semibold text-indigo-200 transition-all duration-300 hover:bg-indigo-500/10 sm:flex-1 sm:text-center" onclick="event.stopPropagation();">Edit job</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-6 flex flex-col gap-3 border-t border-slate-700/50 px-1 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-slate-400">
                                    Showing <?php echo e((string) min($totalJobs, $offset + 1)); ?>-<?php echo e((string) min($totalJobs, $offset + count($jobs))); ?> of <?php echo e((string) $totalJobs); ?> jobs
                                </p>
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <?php
                                        $prevDisabled = $page <= 1;
                                        $nextDisabled = $page >= $totalPages;
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

<div id="jobModal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/70 px-4 py-8 backdrop-blur-sm">
    <div class="modal-shell glass-card relative w-full max-w-4xl max-h-[90vh] overflow-y-auto overflow-hidden rounded-2xl">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-violet-500"></div>
        <div class="flex flex-col gap-3 border-b border-slate-700/50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Job details</p>
                <h3 id="modalJobTitle" class="mt-1 font-display text-xl font-extrabold text-white">Job title</h3>
            </div>
            <button type="button" id="closeJobModal" class="w-full rounded-xl border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80 sm:w-auto">Close</button>
        </div>
        <div class="grid gap-6 p-4 sm:p-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
            <div class="space-y-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Description</p>
                    <p id="modalJobDescription" class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-300"></p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Minimum average score</p>
                        <p id="modalMinAvg" class="mt-2 text-2xl font-extrabold text-white">0.00</p>
                    </div>
                    <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Applicants</p>
                        <p id="modalApplicants" class="mt-2 text-2xl font-extrabold text-white">0</p>
                    </div>
                </div>
                <?php if ($hasRequiredTestColumns || $hasJobRequiredTestsTable): ?>
                    <div id="requiredTestBlock" class="hidden rounded-2xl border border-slate-700/50 bg-slate-900/40 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Specific test requirements</p>
                        <p id="modalRequiredTest" class="mt-2 text-sm font-semibold text-white"></p>
                        <p id="modalRequiredTestScore" class="mt-1 text-sm text-slate-400"></p>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 p-5">
                    <div class="grid gap-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Status</span>
                            <span id="modalStateBadge" class="rounded-full px-3 py-1 text-xs font-bold"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Expiry</span>
                            <span id="modalExpiry" class="font-semibold text-white"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Created</span>
                            <span id="modalCreated" class="font-semibold text-white"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Average applicant score</span>
                            <span id="modalAverageScore" class="font-semibold text-white"></span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-700/50 bg-slate-950/60 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Hiring stages</p>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-2xl bg-slate-900/40 p-3 border border-slate-700/40">
                            <div id="modalApplied" class="text-2xl font-extrabold text-white">0</div>
                            <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Applied</div>
                        </div>
                        <div class="rounded-2xl bg-slate-900/40 p-3 border border-slate-700/40">
                            <div id="modalShortlisted" class="text-2xl font-extrabold text-white">0</div>
                            <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Shortlisted</div>
                        </div>
                        <div class="rounded-2xl bg-slate-900/40 p-3 border border-slate-700/40">
                            <div id="modalSelected" class="text-2xl font-extrabold text-white">0</div>
                            <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Selected</div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <a id="modalApplicantsLink" href="#" class="rounded-xl bg-slate-800 border border-slate-700 px-4 py-3 text-center text-sm font-semibold text-slate-200 transition-all duration-300 hover:bg-slate-700/80">View applicants</a>
                    <a id="modalEditLink" href="#" class="rounded-xl border border-indigo-500/30 px-4 py-3 text-center text-sm font-semibold text-indigo-200 transition-all duration-300 hover:bg-indigo-500/10">Edit job</a>
                    <a id="modalInterviewLink" href="#" class="rounded-xl border border-slate-700 px-4 py-3 text-center text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80">Schedule interview</a>
                    <button id="openDeleteConfirm" type="button" class="rounded-xl border border-rose-500/30 px-4 py-3 text-center text-sm font-semibold text-rose-300 transition-all duration-300 hover:bg-rose-500/10">Delete job</button>
                </div>
            </aside>
        </div>
    </div>
</div>

<div id="deleteConfirmModal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 px-4 py-8 backdrop-blur-sm">
    <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto overflow-hidden rounded-2xl glass-card p-5 sm:p-6">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 to-orange-500"></div>
        <h3 class="font-display text-xl font-extrabold text-white">Delete this job?</h3>
        <p id="deleteConfirmText" class="mt-3 text-sm leading-6 text-slate-300"></p>
        <form id="deleteJobForm" method="post" class="mt-6 flex flex-col gap-3 sm:flex-row">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="delete_job">
            <input type="hidden" name="job_id" id="deleteJobId" value="">
            <button type="button" id="cancelDeleteJob" class="flex-1 rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 transition-all duration-300 hover:bg-slate-800/80">Cancel</button>
            <button type="submit" class="flex-1 rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">Delete</button>
        </form>
    </div>
</div>

<script id="jobPayloadData" type="application/json"><?php echo json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<script>
(function () {
    const payload = JSON.parse(document.getElementById('jobPayloadData').textContent || '{}');
    const toastDataEl = document.querySelector('[data-toast-type][data-toast-message]');
    const toast = document.getElementById('toast');
    const sidebar = document.getElementById('sidebar');
    const jobModal = document.getElementById('jobModal');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const openDeleteConfirm = document.getElementById('openDeleteConfirm');
    const deleteJobId = document.getElementById('deleteJobId');
    const deleteConfirmText = document.getElementById('deleteConfirmText');
    const closeJobModal = document.getElementById('closeJobModal');
    const cancelDeleteJob = document.getElementById('cancelDeleteJob');
    const themeToggle = document.getElementById('themeToggle');
    const themeToggleLabel = document.getElementById('themeToggleLabel');
    const recruiterMenuBtn = document.getElementById('recruiterMenuBtn');
    const recruiterMenu = document.getElementById('recruiterMenu');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const modalJobTitle = document.getElementById('modalJobTitle');
    const modalJobDescription = document.getElementById('modalJobDescription');
    const modalMinAvg = document.getElementById('modalMinAvg');
    const modalApplicants = document.getElementById('modalApplicants');
    const modalExpiry = document.getElementById('modalExpiry');
    const modalCreated = document.getElementById('modalCreated');
    const modalAverageScore = document.getElementById('modalAverageScore');
    const modalApplicantsLink = document.getElementById('modalApplicantsLink');
    const modalEditLink = document.getElementById('modalEditLink');
    const modalInterviewLink = document.getElementById('modalInterviewLink');
    const modalApplied = document.getElementById('modalApplied');
    const modalShortlisted = document.getElementById('modalShortlisted');
    const modalSelected = document.getElementById('modalSelected');
    const modalStateBadge = document.getElementById('modalStateBadge');
    const requiredTestBlock = document.getElementById('requiredTestBlock');
    const modalRequiredTest = document.getElementById('modalRequiredTest');
    const modalRequiredTestScore = document.getElementById('modalRequiredTestScore');

    function formatDate(value) {
        if (!value) {
            return 'N/A';
        }
        const d = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) {
            return value;
        }
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(value) {
        if (!value) {
            return 'N/A';
        }
        const d = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) {
            return value;
        }
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function openJobModal(jobId) {
        const job = payload[jobId];
        if (!job) {
            return;
        }
        const active = new Date(job.expiry_date).setHours(0, 0, 0, 0) >= new Date().setHours(0, 0, 0, 0);
        const badgeClass = active
            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';

        modalJobTitle.textContent = job.title || 'Untitled Job';
        modalJobDescription.textContent = job.description || '';
        modalMinAvg.textContent = Number(job.min_average_score || 0).toFixed(2);
        modalApplicants.textContent = String(job.applicant_count || 0);
        modalExpiry.textContent = formatDate(job.expiry_date);
        modalCreated.textContent = formatDateTime(job.created_at);
        modalAverageScore.textContent = Number(job.avg_applicant_score || 0).toFixed(2);
        modalApplicantsLink.href = 'applicants.php?job_id=' + encodeURIComponent(jobId);
        if (modalEditLink) {
            modalEditLink.href = 'edit_job.php?id=' + encodeURIComponent(jobId);
        }
        modalInterviewLink.href = 'interview.php?job_id=' + encodeURIComponent(jobId);
        modalApplied.textContent = String(job.applied_count || 0);
        modalShortlisted.textContent = String(job.shortlisted_count || 0);
        modalSelected.textContent = String(job.selected_count || 0);
        modalStateBadge.textContent = active ? 'Active' : 'Expired';
        modalStateBadge.className = 'rounded-full px-3 py-1 text-xs font-bold ' + badgeClass;

        if (requiredTestBlock) {
            if (job.required_tests_summary) {
                modalRequiredTest.textContent = job.required_tests_count > 1 ? 'Multiple tests required' : 'Specific test required';
                modalRequiredTestScore.textContent = job.required_tests_summary;
                requiredTestBlock.classList.remove('hidden');
            } else if (job.required_test_id) {
                modalRequiredTest.textContent = job.required_test_title ? job.required_test_title : 'Required test #' + job.required_test_id;
                modalRequiredTestScore.textContent = 'Minimum score: ' + Number(job.min_test_score || 0).toFixed(2);
                requiredTestBlock.classList.remove('hidden');
            } else {
                requiredTestBlock.classList.add('hidden');
            }
        }

        openDeleteConfirm.dataset.jobId = String(jobId);
        deleteConfirmText.textContent = 'Deleting "' + (job.title || 'this job') + '" will remove the job and its related applications.';
        jobModal.classList.remove('hidden');
        jobModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(el) {
        if (!el) {
            return;
        }
        el.classList.add('hidden');
        el.classList.remove('flex');
        if (el === jobModal || el === deleteConfirmModal) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    document.querySelectorAll('.job-card').forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('a, button')) {
                return;
            }
            openJobModal(card.dataset.jobId);
        });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openJobModal(card.dataset.jobId);
            }
        });
    });

    document.querySelectorAll('.open-job-modal').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            openJobModal(button.dataset.jobId);
        });
    });

    if (closeJobModal) {
        closeJobModal.addEventListener('click', function () {
            closeModal(jobModal);
        });
    }
    if (jobModal) {
        jobModal.addEventListener('click', function (event) {
            if (event.target === jobModal) {
                closeModal(jobModal);
            }
        });
    }
    if (openDeleteConfirm) {
        openDeleteConfirm.addEventListener('click', function () {
            if (deleteJobId) {
                deleteJobId.value = openDeleteConfirm.dataset.jobId || '';
            }
            closeModal(jobModal);
            deleteConfirmModal.classList.remove('hidden');
            deleteConfirmModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        });
    }
    if (cancelDeleteJob) {
        cancelDeleteJob.addEventListener('click', function () {
            closeModal(deleteConfirmModal);
        });
    }
    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('click', function (event) {
            if (event.target === deleteConfirmModal) {
                closeModal(deleteConfirmModal);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal(jobModal);
            closeModal(deleteConfirmModal);
            if (recruiterMenu) {
                recruiterMenu.classList.add('hidden');
            }
        }
    });

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

    window.toggleSidebar = function () {
        if (!sidebar) {
            return;
        }
        sidebar.classList.toggle('-translate-x-full');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
    };

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

    if (toastDataEl && toast && toastDataEl.dataset.toastType && toastDataEl.dataset.toastMessage) {
        toast.textContent = toastDataEl.dataset.toastMessage;
        toast.classList.remove('hidden');
        if (toastDataEl.dataset.toastType === 'success') {
            toast.className = 'fixed bottom-6 right-6 z-[100] max-w-sm rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-2xl dark:text-emerald-200';
        } else {
            toast.className = 'fixed bottom-6 right-6 z-[100] max-w-sm rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-700 shadow-2xl dark:text-rose-200';
        }
        setTimeout(function () {
            toast.classList.add('hidden');
        }, 3200);
    }
}());
</script>
</body>
</html>
