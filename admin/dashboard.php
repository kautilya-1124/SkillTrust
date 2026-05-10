<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentPage = 'dashboard.php';
$pageTitle = 'Dashboard';

$totalTests = 0;
$totalUsers = 0;
$totalRecruiters = 0;
$approvedRecruiters = 0;
$pendingRecruiters = 0;
$activeTests = 0;
$expiredTests = 0;

$weeklyLabels = [];
$weeklyCounts = [];
$recentTests = [];
$recentUsers = [];
$recentRecruiters = [];

/**
 * Keep dashboard resilient to schema drift across environments.
 */
function dashboard_has_column(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $colSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($tableSafe === '' || $colSafe === '') {
        return false;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'");
    if (!$res) {
        return false;
    }
    $exists = $res->num_rows > 0;
    $res->free();
    return $exists;
}

function dashboard_fetch_count(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return (int) ($row['c'] ?? 0);
}

$countQueries = [
    'totalTests' => 'SELECT COUNT(*) AS c FROM tests',
    'totalUsers' => 'SELECT COUNT(*) AS c FROM users',
    'totalRecruiters' => 'SELECT COUNT(*) AS c FROM recruiters',
    'approvedRecruiters' => "SELECT COUNT(*) AS c FROM recruiters WHERE LOWER(status) = 'approved'",
    'pendingRecruiters' => "SELECT COUNT(*) AS c FROM recruiters WHERE LOWER(status) = 'pending'",
    'activeTests' => 'SELECT COUNT(*) AS c FROM tests WHERE start_datetime <= NOW() AND expiry_datetime >= NOW()',
    'expiredTests' => 'SELECT COUNT(*) AS c FROM tests WHERE expiry_datetime < NOW()',
];

foreach ($countQueries as $key => $sql) {
    ${$key} = dashboard_fetch_count($conn, $sql);
}

$map = [];
$hasTestsCreatedAt = dashboard_has_column($conn, 'tests', 'created_at');
if ($hasTestsCreatedAt) {
    $weeklySql = "
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM tests
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ";
    $weeklyResult = $conn->query($weeklySql);
    if ($weeklyResult) {
        while ($row = $weeklyResult->fetch_assoc()) {
            $map[(string) $row['d']] = (int) ($row['c'] ?? 0);
        }
        $weeklyResult->free();
    }
} else {
    // Fallback: derive a simple 7-day trend from latest 7 tests by id.
    $latestTestsById = [];
    $fallbackWeeklyRes = $conn->query('SELECT id FROM tests ORDER BY id DESC LIMIT 7');
    if ($fallbackWeeklyRes) {
        while ($row = $fallbackWeeklyRes->fetch_assoc()) {
            $latestTestsById[] = (int) ($row['id'] ?? 0);
        }
        $fallbackWeeklyRes->free();
    }
    $latestTestsById = array_values(array_filter($latestTestsById, static fn($v): bool => $v > 0));
    $latestTestsById = array_reverse($latestTestsById);
    for ($i = 0; $i < 7; $i++) {
        $day = date('Y-m-d', strtotime('-' . (6 - $i) . ' day'));
        $map[$day] = isset($latestTestsById[$i]) ? 1 : 0;
    }
}
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $weeklyLabels[] = date('D', strtotime($day));
    $weeklyCounts[] = (int) ($map[$day] ?? 0);
}

$recentTestsSql = "
    SELECT t.id, t.test_name, t.start_datetime, t.expiry_datetime, c.name AS category_name
    FROM tests t
    LEFT JOIN categories c ON c.id = t.category_id
    ORDER BY t.id DESC
    LIMIT 5
";
$recentTestsRes = $conn->query($recentTestsSql);
if ($recentTestsRes) {
    while ($row = $recentTestsRes->fetch_assoc()) {
        $recentTests[] = [
            'id' => (int) ($row['id'] ?? 0),
            'test_name' => (string) ($row['test_name'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
            'start_datetime' => (string) ($row['start_datetime'] ?? ''),
            'expiry_datetime' => (string) ($row['expiry_datetime'] ?? ''),
        ];
    }
    $recentTestsRes->free();
}

$recentUsersSql = "SELECT name, email, status FROM users ORDER BY id DESC LIMIT 5";
$recentUsersRes = $conn->query($recentUsersSql);
if ($recentUsersRes) {
    while ($row = $recentUsersRes->fetch_assoc()) {
        $recentUsers[] = [
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'status' => strtolower((string) ($row['status'] ?? 'active')),
        ];
    }
    $recentUsersRes->free();
}

$recentRecruitersSql = "SELECT company_name, email, status FROM recruiters ORDER BY id DESC LIMIT 5";
$recentRecruitersRes = $conn->query($recentRecruitersSql);
if ($recentRecruitersRes) {
    while ($row = $recentRecruitersRes->fetch_assoc()) {
        $recentRecruiters[] = [
            'company_name' => (string) ($row['company_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'status' => strtolower((string) ($row['status'] ?? 'pending')),
        ];
    }
    $recentRecruitersRes->free();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <div class="flex min-h-screen">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 lg:ml-64 flex flex-col min-h-screen min-w-0 max-w-full">
            <header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <button type="button" onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">&#9776;</button>
                    <div>
                        <h2 class="font-display font-bold text-white text-lg">Admin Dashboard</h2>
                        <p class="text-xs text-slate-500"><?php echo e(date('l, d M Y')); ?></p>
                    </div>
                </div>
                <div class="relative" id="profileDropdown">
                    <button type="button" id="adminMenuBtn" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all duration-300">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-xs">
                            <?php echo e(strtoupper(substr((string) $adminName, 0, 1))); ?>
                        </div>
                        <span class="hidden md:inline text-sm text-slate-300"><?php echo e($adminName); ?></span>
                    </button>
                    <div id="adminMenu" class="hidden absolute right-0 mt-2 w-48 bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-[60]">
                        <a href="admin-profile.php" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Profile</a>
                        <a href="logout.php" class="block px-4 py-2.5 text-sm text-rose-400 hover:bg-rose-500/10 transition-colors">Logout</a>
                    </div>
                </div>
            </header>

            <main class="flex-1 min-w-0 px-3 sm:px-4 lg:px-8 py-6 sm:py-8 space-y-6 overflow-x-hidden">
                <section class="fade-up relative rounded-3xl overflow-hidden border border-indigo-500/25 bg-gradient-to-br from-indigo-900/35 via-slate-900/80 to-violet-900/30 p-6 sm:p-8">
                    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 bottom-0 w-44 h-44 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
                    <h1 class="font-display font-extrabold text-2xl sm:text-3xl text-white">Welcome back, <?php echo e($adminName); ?></h1>
                    <p class="text-sm text-slate-400 mt-2">System overview with live counts and recent platform activity.</p>
                </section>

                <section class="fade-up grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Total Tests</p>
                        <p class="stat-value mt-2 text-indigo-300"><?php echo e((string) $totalTests); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Total Students</p>
                        <p class="stat-value mt-2 text-sky-300"><?php echo e((string) $totalUsers); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Total Recruiters</p>
                        <p class="stat-value mt-2 text-fuchsia-300"><?php echo e((string) $totalRecruiters); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Active Tests</p>
                        <p class="stat-value mt-2 text-emerald-300"><?php echo e((string) $activeTests); ?></p>
                    </div>
                    <div class="glass-card metric-card rounded-2xl p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Expired Tests</p>
                        <p class="stat-value mt-2 text-rose-300"><?php echo e((string) $expiredTests); ?></p>
                    </div>
                </section>

                <section class="fade-up grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="xl:col-span-2 glass-card rounded-2xl p-5 sm:p-6">
                        <h2 class="font-display font-bold text-white text-lg mb-4">Tests Created (Last 7 Days)</h2>
                        <canvas id="testsChart" height="120"></canvas>
                    </div>
                    <div class="glass-card rounded-2xl p-5 sm:p-6">
                        <h2 class="font-display font-bold text-white text-lg mb-4">Status Snapshot</h2>
                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-2 py-1 text-emerald-300">Approved Recruiters: <?php echo (int) $approvedRecruiters; ?></div>
                            <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 px-2 py-1 text-amber-300">Pending Recruiters: <?php echo (int) $pendingRecruiters; ?></div>
                        </div>
                        <canvas id="statusChart" height="170"></canvas>
                    </div>
                </section>

                <section class="fade-up grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-700/50"><h3 class="font-display font-bold text-white text-base">Recent Tests</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-slate-500 text-xs">
                                    <tr>
                                        <th class="text-left font-medium px-5 py-3">Test</th>
                                        <th class="text-left font-medium px-5 py-3">Category</th>
                                        <th class="text-left font-medium px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/30">
                                    <?php if ($recentTests === []): ?>
                                        <tr><td colspan="3" class="px-5 py-5 text-slate-500">No tests found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentTests as $test):
                                            $nowTs = time();
                                            $startTs = strtotime($test['start_datetime']) ?: 0;
                                            $endTs = strtotime($test['expiry_datetime']) ?: 0;
                                            $isUpcoming = $startTs > $nowTs;
                                            $isActive = $startTs > 0 && $endTs > 0 && $startTs <= $nowTs && $endTs >= $nowTs;
                                            $statusLabel = $isActive ? 'Active' : ($isUpcoming ? 'Upcoming' : 'Expired');
                                            $statusClass = $isActive
                                                ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'
                                                : ($isUpcoming ? 'bg-amber-500/15 text-amber-300 border border-amber-500/25' : 'bg-slate-700/70 text-slate-300 border border-slate-600/60');
                                        ?>
                                            <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                                <td class="px-5 py-3 font-medium text-slate-200"><?php echo e($test['test_name']); ?></td>
                                                <td class="px-5 py-3 text-slate-400"><?php echo e($test['category_name']); ?></td>
                                                <td class="px-5 py-3">
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                                        <?php echo $statusLabel; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-700/50"><h3 class="font-display font-bold text-white text-base">Recent Students</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-slate-500 text-xs">
                                    <tr>
                                        <th class="text-left font-medium px-5 py-3">Name</th>
                                        <th class="text-left font-medium px-5 py-3">Email</th>
                                        <th class="text-left font-medium px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/30">
                                    <?php if ($recentUsers === []): ?>
                                        <tr><td colspan="3" class="px-5 py-5 text-slate-500">No students found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentUsers as $user): ?>
                                            <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                                <td class="px-5 py-3 font-medium text-slate-200"><?php echo e($user['name']); ?></td>
                                                <td class="px-5 py-3 text-slate-400"><?php echo e($user['email']); ?></td>
                                                <td class="px-5 py-3">
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $user['status'] === 'blocked' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25' : 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'; ?>">
                                                        <?php echo $user['status'] === 'blocked' ? 'Blocked' : 'Active'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-700/50"><h3 class="font-display font-bold text-white text-base">Recent Recruiters</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-slate-500 text-xs">
                                    <tr>
                                        <th class="text-left font-medium px-5 py-3">Company</th>
                                        <th class="text-left font-medium px-5 py-3">Email</th>
                                        <th class="text-left font-medium px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/30">
                                    <?php if ($recentRecruiters === []): ?>
                                        <tr><td colspan="3" class="px-5 py-5 text-slate-500">No recruiters found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentRecruiters as $rec): ?>
                                            <tr class="hover:bg-slate-800/40 transition-all duration-300">
                                                <td class="px-5 py-3 font-medium text-slate-200"><?php echo e($rec['company_name']); ?></td>
                                                <td class="px-5 py-3 text-slate-400"><?php echo e($rec['email']); ?></td>
                                                <td class="px-5 py-3">
                                                    <?php $rs = $rec['status']; ?>
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $rs === 'approved' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25' : ($rs === 'rejected' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/25' : 'bg-amber-500/15 text-amber-300 border border-amber-500/25'); ?>">
                                                        <?php echo ucfirst($rs); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script type="application/json" id="adminDashboardToastData"><?php echo json_encode(['toastType' => '', 'toastMsg' => ''], JSON_UNESCAPED_UNICODE); ?></script>
    <script type="application/json" id="adminDashboardData"><?php echo json_encode(['weeklyLabels' => $weeklyLabels, 'weeklyCounts' => $weeklyCounts, 'statusCounts' => [(int) $activeTests, (int) $expiredTests, (int) $approvedRecruiters, (int) $pendingRecruiters]], JSON_UNESCAPED_UNICODE); ?></script>
    <script src="../assets/js/admin/common-page.js"></script>
    <script src="../assets/js/admin/dashboard.js"></script>
</body>
</html>
