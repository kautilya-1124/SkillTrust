<?php
// profile.php - SkillTrust Student Panel
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, username, email, phone, bio, skills, created_at, general_cv, specialization_cv FROM users WHERE id = ?');
if (!$stmt) {
    header('Location: ../auth/login.php');
    exit;
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$user['name']     = (string) ($user['name'] ?? '');
$user['username'] = (string) ($user['username'] ?? '');
$user['email']    = (string) ($user['email'] ?? '');
$user['phone']    = (string) ($user['phone'] ?? '');
$user['bio']      = (string) ($user['bio'] ?? '');
$user['skills']   = (string) ($user['skills'] ?? '');
$generalCvPath    = isset($user['general_cv']) && $user['general_cv'] !== null && $user['general_cv'] !== ''
    ? (string) $user['general_cv'] : '';
$specCvPath       = isset($user['specialization_cv']) && $user['specialization_cv'] !== null && $user['specialization_cv'] !== ''
    ? (string) $user['specialization_cv'] : '';

$stats = ['total_tests' => 0, 'avg_score' => 0.0, 'best_score' => 0.0];
$statsStmt = $conn->prepare('SELECT COUNT(*) AS total_tests, AVG(percentage) AS avg_score, MAX(percentage) AS best_score FROM results WHERE user_id = ?');
if ($statsStmt) {
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();
    $srow = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    if ($srow) {
        $stats['total_tests'] = (int) ($srow['total_tests'] ?? 0);
        $stats['avg_score']   = $srow['avg_score'] !== null ? (float) $srow['avg_score'] : 0.0;
        $stats['best_score']  = $srow['best_score'] !== null ? (float) $srow['best_score'] : 0.0;
    }
}

$skill_performance = [];
$perfStmt = $conn->prepare(
    'SELECT t.category AS cat_name, AVG(r.percentage) AS pct FROM results r INNER JOIN tests t ON t.id = r.test_id WHERE r.user_id = ? GROUP BY t.category'
);
if ($perfStmt) {
    $perfStmt->bind_param('i', $userId);
    $perfStmt->execute();
    $perfRes = $perfStmt->get_result();
    while ($row = $perfRes->fetch_assoc()) {
        $skill_performance[] = [
            'name' => (string) ($row['cat_name'] ?? 'General'),
            'pct'  => (int) round((float) ($row['pct'] ?? 0)),
        ];
    }
    $perfStmt->close();
}

$userSkills = array_values(array_filter(array_map('trim', explode(',', $user['skills']))));

$nameForInitials = trim($user['name']);
$avatarInitials  = '?';
if ($nameForInitials !== '') {
    $parts = preg_split('/\s+/', $nameForInitials, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts !== false && count($parts) >= 2) {
        $avatarInitials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } else {
        $avatarInitials = strtoupper(strlen($parts[0]) >= 2 ? substr($parts[0], 0, 2) : substr($parts[0], 0, 1) . '?');
    }
}

$trustScore = (int) round($stats['avg_score']);
$tier       = $trustScore >= 85 ? 'Gold' : ($trustScore >= 70 ? 'Silver' : 'Bronze');

$memberSince = '-';
$created     = $user['created_at'] ?? null;
if ($created !== null && $created !== '') {
    try {
        $memberSince = (new DateTimeImmutable((string) $created))->format('F Y');
    } catch (Throwable $e) {
        $memberSince = '-';
    }
}

$avgScoreInt  = (int) round($stats['avg_score']);
$bestScoreInt = (int) round($stats['best_score']);
$testsCountBadge = 0;
$testsCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM tests');
if ($testsCountStmt) {
    $testsCountStmt->execute();
    $testsCountRow = $testsCountStmt->get_result()->fetch_assoc();
    $testsCountStmt->close();
    $testsCountBadge = (int) ($testsCountRow['total'] ?? 0);
}

$streakDisplay = 0;
$rankDisplay   = '-';

$achievements = [
    ['icon' => 'TOP', 'label' => 'Top Scorer',     'sub' => 'Scored 95%+ on a test'],
    ['icon' => 'SPD', 'label' => 'Speed Demon',   'sub' => 'Finished under time target'],
    ['icon' => '7D',  'label' => 'Week Streak',   'sub' => '7-day activity streak'],
    ['icon' => 'QL',  'label' => 'Quick Learner', 'sub' => 'Passed 3 tests first try'],
];

$currentPage = 'profile.php';
$studentSidebarName = $user['name'] !== '' ? $user['name'] : 'Student';
$studentSidebarEmail = $user['email'];
$studentSidebarInitials = $avatarInitials;
$studentSidebarTestCount = $testsCountBadge;
$profileSeed = [
    'name' => $user['name'],
    'username' => $user['username'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'bio' => $user['bio'],
];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/profile.css">
    <link rel="stylesheet" href="../assets/css/student_sidebar.css">
    <link rel="stylesheet" href="../assets/css/student/student-shell.css">
    <script src="../assets/js/student/student-shell.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">

<div class="flex min-h-screen">
    <?php require_once __DIR__ . '/../includes/student_sidebar.php'; ?>

    <div class="student-main flex-1 flex flex-col min-h-screen min-w-0 max-w-full">

        <header class="navbar sticky top-0 z-30 px-4 lg:px-8 py-3 lg:py-0 lg:h-16 flex flex-wrap sm:flex-nowrap items-center justify-between gap-2 min-w-0">
            <div class="flex items-center gap-2 min-w-0 flex-1 lg:flex-initial overflow-hidden">
                <button type="button" onclick="toggleSidebar()" aria-label="Open menu"
                        class="lg:hidden flex-shrink-0 p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="lg:hidden min-w-0">
                    <h2 class="font-display font-bold text-white text-sm truncate">Profile</h2>
                    <p class="text-[10px] text-slate-500 truncate">Account & preferences</p>
                </div>
            </div>

            <div class="hidden lg:block flex-shrink-0">
                <h2 class="font-display font-bold text-white text-lg">Your Profile</h2>
                <p class="text-xs text-slate-500">Manage identity, skills, and security</p>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0 basis-full sm:basis-auto justify-end sm:ml-auto">
                <div class="hidden md:flex items-center gap-2 bg-slate-800/60 border border-slate-700/50 rounded-xl px-3 py-2 focus-within:border-brand-500/50 transition-all duration-300 max-w-[11rem] lg:max-w-none">
                    <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" placeholder="Search..." class="bg-transparent text-sm text-slate-300 placeholder-slate-600 outline-none w-full min-w-0">
                </div>

                <button type="button" class="hidden sm:flex relative p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot absolute top-2 right-2 w-2 h-2 rounded-full bg-brand-500"></span>
                </button>

                <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-900/80 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                    <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <div class="relative" id="profileDropdown">
                    <button type="button" onclick="toggleDropdown()"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-800 transition-all duration-300">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 flex items-center justify-center text-white font-display font-bold text-xs">
                            <?php echo htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-500 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu absolute right-0 top-full mt-2 w-52 max-w-[min(13rem,calc(100vw-1.5rem))] bg-slate-800 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden z-[60]" id="dropdownMenu">
                        <div class="px-4 py-3 border-b border-slate-700/50">
                            <div id="dropdownDisplayName" class="font-semibold text-white text-sm"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div id="dropdownDisplayEmail" class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Dashboard</a>
                        <a href="results.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors">Results</a>
                        <div class="border-t border-slate-700/50 py-1">
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors">Sign Out</a>
                        </div>
                    </div>
                </div>

                <a href="../auth/logout.php"
                   class="hidden sm:inline-flex text-xs font-semibold text-slate-400 hover:text-white px-3 py-2 rounded-xl border border-slate-700/60 hover:bg-slate-800/60 transition-all duration-300">
                    Logout
                </a>
            </div>
        </header>

        <main class="flex-1 min-w-0 px-4 lg:px-8 py-8 space-y-8 overflow-x-hidden">

            <!-- Hero -->
            <section class="opacity-0-start animate-fade-up stagger-1 relative rounded-3xl overflow-hidden border border-brand-500/25
                            bg-gradient-to-br from-brand-900/40 via-slate-900/80 to-violet-900/30 p-6 sm:p-8 lg:p-10">
                <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
                     style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.35) 1px, transparent 0); background-size: 28px 28px;"></div>
                <div class="absolute -right-20 -top-20 w-72 h-72 rounded-full bg-violet-500/15 blur-3xl pointer-events-none"></div>
                <div class="absolute -left-10 bottom-0 w-48 h-48 rounded-full bg-brand-500/10 blur-3xl pointer-events-none"></div>

                <div class="relative flex flex-col xl:flex-row gap-8 xl:items-start">
                    <button type="button" id="avatarUploadArea" class="group flex-shrink-0 text-left w-full sm:w-auto">
                        <div class="avatar-ring rounded-2xl w-28 h-28 sm:w-32 sm:h-32 mx-auto sm:mx-0">
                            <div class="w-full h-full rounded-[0.85rem] bg-slate-900 flex items-center justify-center overflow-hidden relative">
                                <span id="avatarInitials" class="font-display font-extrabold text-3xl text-brand-300"><?php echo htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                                <img id="avatarImg" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden">
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center rounded-[0.85rem]">
                                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="avatarInput" accept="image/*" class="hidden">
                        <p class="text-center sm:text-left text-xs text-slate-500 mt-2">Click to update photo</p>
                    </button>

                    <div class="flex-1 min-w-0 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 sm:gap-3 mb-2">
                            <h1 id="displayName" class="font-display font-extrabold text-2xl sm:text-3xl text-white break-words">
                                <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h1>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/15 text-amber-400 border border-amber-500/25">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?> Tier
                            </span>
                        </div>
                        <p class="text-sm text-slate-400 mb-3 break-all sm:break-normal">
                            @<span id="displayUsername"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="text-slate-600"> | </span>
                            <span id="displayEmail"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </p>
                        <p id="displayBio" class="text-slate-400 text-sm leading-relaxed max-w-2xl mx-auto sm:mx-0">
                            <?php echo htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2 mt-5">
                            <a href="tests.php" class="glow-btn relative inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg">
                                <span class="relative z-10 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Take a test
                                </span>
                            </a>
                            <a href="results.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-white/10 border border-white/15 hover:bg-white/15 transition-all duration-300">
                                View results
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-row xl:flex-col justify-center gap-6 xl:gap-4 xl:pl-4 xl:border-l xl:border-slate-700/50">
                        <div class="text-center">
                            <div class="relative w-24 h-24 mx-auto">
                                <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(99,102,241,0.12)" stroke-width="8"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="url(#profileRing)" stroke-width="8" stroke-linecap="round"
                                            class="progress-trust" id="trustRingSvg"/>
                                    <defs>
                                        <linearGradient id="profileRing" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#6366f1"/>
                                            <stop offset="100%" stop-color="#a78bfa"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="font-display font-extrabold text-xl text-white" id="trustNum"><?php echo $trustScore; ?></span>
                                    <span class="text-[10px] text-slate-500 font-semibold">Trust</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-center sm:text-left">
                            <div class="glass-card rounded-xl px-3 py-2.5">
                                <div class="font-display font-bold text-lg text-emerald-400"><?php echo $streakDisplay; ?></div>
                                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Streak</div>
                            </div>
                            <div class="glass-card rounded-xl px-3 py-2.5">
                                <div class="font-display font-bold text-lg text-brand-300"><?php echo htmlspecialchars($rankDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Rank</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-8">
                    <?php
                    $mini = [
                        ['label' => 'Tests done', 'val' => (string) $stats['total_tests'], 'c' => 'text-emerald-400'],
                        ['label' => 'Avg score', 'val' => $avgScoreInt . '%', 'c' => 'text-brand-300'],
                        ['label' => 'Best score', 'val' => $bestScoreInt . '%', 'c' => 'text-amber-400'],
                        ['label' => 'Member since', 'val' => $memberSince, 'c' => 'text-slate-300', 'small' => true],
                    ];
                    foreach ($mini as $m): ?>
                        <div class="glass-card rounded-2xl px-4 py-3 text-center sm:text-left hover:border-brand-500/25 transition-all duration-300">
                            <div class="font-display font-extrabold <?php echo $m['c']; ?> <?php echo !empty($m['small']) ? 'text-sm sm:text-base break-words' : 'text-xl'; ?>"><?php echo htmlspecialchars((string) $m['val']); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($m['label']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8 items-start">
                <!-- Main column -->
                <div class="xl:col-span-2 space-y-6">

                    <section class="opacity-0-start animate-fade-up stagger-2 glass-card rounded-2xl p-5 sm:p-7">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                            <div>
                                <h2 class="font-display font-bold text-white text-lg">Personal information</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Details shown on certificates and leaderboards</p>
                            </div>
                            <button type="button" id="editToggleBtn" onclick="toggleEdit()"
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold bg-brand-500/15 text-brand-300 border border-brand-500/25 hover:bg-brand-500/25 transition-all duration-300 w-full sm:w-auto">
                                Edit profile
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Full name</label>
                                <input class="form-input" id="inputName" type="text" value="<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Username</label>
                                <input class="form-input" id="inputUsername" type="text" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                                <input class="form-input" id="inputEmail" type="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Phone</label>
                                <input class="form-input" id="inputPhone" type="tel" value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>" disabled placeholder="+1 ...">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Bio</label>
                                <textarea class="form-input resize-y min-h-[5rem]" id="inputBio" rows="3" disabled><?php echo htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <div id="saveRow" class="hidden mt-5 flex flex-col-reverse sm:flex-row gap-2 justify-end">
                            <button type="button" onclick="cancelEdit()" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-600 text-slate-300 hover:bg-slate-800 transition-all duration-300">Cancel</button>
                            <button type="button" onclick="saveProfile()" class="glow-btn relative px-5 py-2.5 rounded-xl text-sm font-semibold text-white">
                                <span class="relative z-10">Save changes</span>
                            </button>
                        </div>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-3 glass-card rounded-2xl p-5 sm:p-7">
                        <h2 class="font-display font-bold text-white text-lg mb-1">Skills</h2>
                        <p class="text-xs text-slate-500 mb-4">Shown to mentors and on your public skill card</p>
                        <p id="skillsEmptyHint" class="text-xs text-slate-500 mb-4 <?php echo $userSkills !== [] ? 'hidden' : ''; ?>">No skills added yet.</p>
                        <div id="skillsContainer" class="flex flex-wrap gap-2 mb-4"></div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input class="form-input flex-1" id="skillInput" type="text" placeholder="Add a skill..." onkeydown="if(event.key==='Enter'){event.preventDefault();addSkill();}">
                            <button type="button" onclick="addSkill()" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-brand-500/30 text-brand-300 hover:bg-brand-500/10 transition-all duration-300 whitespace-nowrap">
                                Add skill
                            </button>
                        </div>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-3 glass-card rounded-2xl p-5 sm:p-7">
                        <h2 class="font-display font-bold text-white text-lg mb-1">Resumes</h2>
                        <p class="text-xs text-slate-500 mb-5">PDF, DOC, or DOCX - max 2MB each</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php
                            $resumeBlocks = [
                                [
                                    'type'     => 'general_cv',
                                    'title'    => 'General CV',
                                    'subtitle' => 'Main resume',
                                    'path'     => $generalCvPath,
                                ],
                                [
                                    'type'     => 'specialization_cv',
                                    'title'    => 'Specialization CV',
                                    'subtitle' => 'Domain-specific',
                                    'path'     => $specCvPath,
                                ],
                            ];
                            foreach ($resumeBlocks as $rb):
                                $hasFile = $rb['path'] !== '';
                                $dlView  = 'actions/download_resume.php?type=' . urlencode($rb['type']);
                            ?>
                            <div class="rounded-xl border border-slate-700/50 bg-slate-900/40 p-4 space-y-3">
                                <div>
                                    <h3 class="font-display font-semibold text-white text-sm"><?php echo htmlspecialchars($rb['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="text-[11px] text-slate-500"><?php echo htmlspecialchars($rb['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <?php if ($hasFile): ?>
                                    <p class="text-xs text-slate-400 truncate" title="<?php echo htmlspecialchars(basename($rb['path']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(basename($rb['path']), ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                                <div class="flex flex-wrap gap-2">
                                    <input type="file" id="resumeFile_<?php echo htmlspecialchars($rb['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                           class="hidden" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                           data-resume-type="<?php echo htmlspecialchars($rb['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if (!$hasFile): ?>
                                        <button type="button" class="resume-upload-btn px-3 py-2 rounded-xl text-xs font-semibold border border-brand-500/30 text-brand-300 hover:bg-brand-500/10 transition-all duration-300"
                                                data-target="resumeFile_<?php echo htmlspecialchars($rb['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                            Upload
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="resume-upload-btn px-3 py-2 rounded-xl text-xs font-semibold border border-brand-500/30 text-brand-300 hover:bg-brand-500/10 transition-all duration-300"
                                                data-target="resumeFile_<?php echo htmlspecialchars($rb['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                            Change
                                        </button>
                                        <a href="<?php echo htmlspecialchars($dlView, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-white/10 border border-white/15 text-white hover:bg-white/15 transition-all duration-300">
                                            Download
                                        </a>
                                        <a href="<?php echo htmlspecialchars($dlView . '&inline=1', ENT_QUOTES, 'UTF-8'); ?>"
                                           target="_blank" rel="noopener"
                                           class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-slate-800 border border-slate-600 text-slate-300 hover:bg-slate-700 transition-all duration-300">
                                            View
                                        </a>
                                        <button type="button" class="resume-delete-btn px-3 py-2 rounded-xl text-xs font-semibold border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-all duration-300"
                                                data-resume-type="<?php echo htmlspecialchars($rb['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-4 glass-card rounded-2xl p-5 sm:p-7">
                        <h2 class="font-display font-bold text-white text-lg mb-1">Security</h2>
                        <p class="text-xs text-slate-500 mb-5">Update password - demo validation only</p>
                        <div class="space-y-4 max-w-md">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Current password</label>
                                <div class="relative">
                                    <input class="form-input pr-11" id="currentPass" type="password" placeholder="********">
                                    <button type="button" onclick="togglePass('currentPass')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 p-1" aria-label="Toggle visibility">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">New password</label>
                                <input class="form-input" id="newPass" type="password" placeholder="Min. 6 characters">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Confirm</label>
                                <input class="form-input" id="confirmPass" type="password" placeholder="Repeat new password">
                            </div>
                            <button type="button" onclick="changePassword()" class="glow-btn relative w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-semibold text-white">
                                <span class="relative z-10">Update password</span>
                            </button>
                        </div>
                    </section>
                </div>

                <!-- Sidebar column -->
                <div class="space-y-6">

                    <section class="opacity-0-start animate-fade-up stagger-2 glass-card rounded-2xl p-5">
                        <h2 class="font-display font-bold text-white text-base mb-3">Profile strength</h2>
                        <div class="flex items-center gap-4">
                            <div class="relative w-16 h-16 flex-shrink-0">
                                <svg class="w-16 h-16 -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(99,102,241,0.12)" stroke-width="8"/>
                                    <circle id="completionRing" cx="50" cy="50" r="42" fill="none" stroke="url(#completeGrad)" stroke-width="8" stroke-linecap="round"
                                            stroke-dasharray="264" stroke-dashoffset="264" class="transition-all duration-1000"/>
                                    <defs>
                                        <linearGradient id="completeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#6366f1"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <span id="completionPct" class="absolute inset-0 flex items-center justify-center font-display font-bold text-sm text-white">0%</span>
                            </div>
                            <div class="min-w-0">
                                <p id="completionHint" class="text-xs text-slate-400 leading-relaxed">Complete your profile to unlock badge eligibility.</p>
                                <button type="button" onclick="exportProfileJson()" class="mt-2 text-xs font-semibold text-brand-400 hover:text-brand-300 transition-colors">
                                    Download profile JSON
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-3 glass-card rounded-2xl p-5">
                        <h2 class="font-display font-bold text-white text-base mb-4">Notifications</h2>
                        <p class="text-xs text-slate-500 mb-4">Saved in this browser (localStorage)</p>
                        <div class="space-y-4">
                            <label class="flex items-center justify-between gap-3 cursor-pointer">
                                <span class="text-sm text-slate-300">Weekly digest</span>
                                <input type="checkbox" id="prefDigest" class="pref-toggle sr-only peer">
                                <div class="relative w-11 h-6 shrink-0 rounded-full bg-slate-700 transition-colors duration-300 peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 after:absolute after:top-1 after:left-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-300 peer-checked:after:translate-x-5"></div>
                            </label>
                            <label class="flex items-center justify-between gap-3 cursor-pointer">
                                <span class="text-sm text-slate-300">Test reminders</span>
                                <input type="checkbox" id="prefReminders" class="pref-toggle sr-only peer">
                                <div class="relative w-11 h-6 shrink-0 rounded-full bg-slate-700 transition-colors duration-300 peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 after:absolute after:top-1 after:left-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-300 peer-checked:after:translate-x-5"></div>
                            </label>
                            <label class="flex items-center justify-between gap-3 cursor-pointer">
                                <span class="text-sm text-slate-300">Public skill card</span>
                                <input type="checkbox" id="prefPublic" class="pref-toggle sr-only peer">
                                <div class="relative w-11 h-6 shrink-0 rounded-full bg-slate-700 transition-colors duration-300 peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 after:absolute after:top-1 after:left-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-300 peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-3 glass-card rounded-2xl p-5">
                        <h2 class="font-display font-bold text-white text-base mb-4">Skill performance</h2>
                        <?php if ($skill_performance === []): ?>
                        <p class="text-xs text-slate-500">No category data yet.</p>
                        <?php else:
                        $barColors = ['#fbbf24', '#818cf8', '#34d399', '#f472b6', '#60a5fa'];
                        $i = 0;
                        foreach ($skill_performance as $s):
                            $col = $barColors[$i % count($barColors)];
                            $i++;
                        ?>
                        <div class="mb-4 last:mb-0">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-300 font-medium"><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-slate-500"><?php echo (int) $s['pct']; ?>%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-brand-500/15 overflow-hidden">
                                <div class="score-bar-fill h-full rounded-full" style="width:0%;background:<?php echo $col; ?>" data-width="<?php echo (int) $s['pct']; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach;
                        endif; ?>
                    </section>

                    <section class="opacity-0-start animate-fade-up stagger-4 glass-card rounded-2xl p-5">
                        <h2 class="font-display font-bold text-white text-base mb-3">Achievements</h2>
                        <ul class="space-y-3">
                            <?php foreach ($achievements as $b): ?>
                                <li class="flex items-center gap-3 py-2 border-b border-slate-700/40 last:border-0">
                                    <span class="text-xl w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center flex-shrink-0"><?php echo $b['icon']; ?></span>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($b['label']); ?></div>
                                        <div class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($b['sub']); ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>

                    <section class="opacity-0-start animate-fade-up glass-card rounded-2xl p-5 border-red-500/20">
                        <h2 class="font-display font-bold text-red-400 text-base mb-1">Danger zone</h2>
                        <p class="text-xs text-slate-500 mb-4">Permanently delete your SkillTrust student account and history.</p>
                        <button type="button" onclick="confirmDelete()" class="w-full py-2.5 rounded-xl text-sm font-semibold bg-red-500/10 text-red-400 border border-red-500/25 hover:bg-red-500/20 transition-all duration-300">
                            Delete account
                        </button>
                    </section>
                </div>
            </div>
        </main>

        <footer class="px-4 sm:px-8 py-4 border-t border-slate-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
            <span class="text-xs text-slate-600">(c) 2026 SkillTrust - Student Panel</span>
            <span class="text-xs text-slate-600">Profile</span>
        </footer>
    </div>
</div>

<div id="toast" class="fixed bottom-6 right-4 sm:right-8 z-[100] px-4 py-3 rounded-xl border border-slate-700 bg-slate-800 shadow-2xl text-sm text-slate-200 translate-y-20 opacity-0 transition-all duration-300 pointer-events-none flex items-center gap-2 max-w-[calc(100vw-2rem)]">
    <span id="toastDot" class="w-2 h-2 rounded-full flex-shrink-0"></span>
    <span id="toastMsg"></span>
</div>

<div id="deleteModal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
    <div class="glass-card border-red-500/25 rounded-2xl p-6 sm:p-8 max-w-md w-full animate-fade-up">
        <h3 class="font-display font-bold text-white text-lg mb-2">Delete account?</h3>
        <p class="text-sm text-slate-400 mb-6">This removes your profile and all stored results. You will be signed out.</p>
        <div class="flex gap-3">
            <button type="button" onclick="closeDeleteModal()" class="flex-1 py-2.5 rounded-xl text-sm font-semibold border border-slate-600 text-slate-300 hover:bg-slate-800">Cancel</button>
            <button type="submit" form="deleteAccountForm" class="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-red-600 text-white hover:bg-red-500">Confirm</button>
        </div>
    </div>
</div>

<form id="deleteAccountForm" method="post" action="actions/delete_account.php" class="hidden" autocomplete="off">
    <input type="hidden" name="confirm" value="1">
</form>

<script type="application/json" id="studentProfileData"><?php echo json_encode(['skills' => $userSkills, 'trustScore' => $trustScore, 'profile' => $profileSeed], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
<script src="../assets/js/student/profile.js"></script>
</body>
</html>
