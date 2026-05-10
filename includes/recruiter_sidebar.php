<?php
declare(strict_types=1);

$recruiterSidebarCurrentPage = $currentPage ?? basename((string) ($_SERVER['PHP_SELF'] ?? 'dashboard.php'));
$recruiterSidebarCompany = trim((string) ($_SESSION['recruiter_company'] ?? 'SkillTrust Recruiter'));
$recruiterSidebarName = trim((string) ($_SESSION['recruiter_name'] ?? $recruiterSidebarCompany));
$recruiterSidebarEmail = trim((string) ($_SESSION['recruiter_email'] ?? ''));
$recruiterSidebarLetters = preg_replace('/[^A-Za-z]/', '', $recruiterSidebarCompany);
$recruiterSidebarInitials = strtoupper(substr($recruiterSidebarLetters !== '' ? $recruiterSidebarLetters : 'ST', 0, 2));

$recruiterSidebarItems = [
    [
        'href' => 'dashboard.php',
        'label' => 'Dashboard',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 5.75h5.5v5.5h-5.5zm9 0h5.5v5.5h-5.5zm-9 9h5.5v5.5h-5.5zm9 0h5.5v5.5h-5.5z"/></svg>',
    ],
    [
        'href' => 'create_job.php',
        'label' => 'Post Job',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>',
    ],
    [
        'href' => 'manage_jobs.php',
        'label' => 'My Jobs',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5.75A1.75 1.75 0 0 1 9.75 4h4.5A1.75 1.75 0 0 1 16 5.75V7m-11 2h14v8.25A1.75 1.75 0 0 1 17.25 19h-10.5A1.75 1.75 0 0 1 5 17.25z"/></svg>',
    ],
    [
        'href' => 'applicants.php',
        'label' => 'Applications',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7.5a4 4 0 1 1 8 0a4 4 0 0 1-8 0Zm-3 11a7 7 0 0 1 14 0"/></svg>',
    ],
    [
        'href' => 'interview.php',
        'label' => 'Interviews',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.75 6.75h6.5A2.75 2.75 0 0 1 18 9.5v5A2.75 2.75 0 0 1 15.25 17.25h-1.6l-2.8 2.25v-2.25H8.75A2.75 2.75 0 0 1 6 14.5v-5a2.75 2.75 0 0 1 2.75-2.75Z"/></svg>',
    ],
    [
        'href' => 'profile.php',
        'label' => 'Profile',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0-4-4a4 4 0 0 0 4 4Zm-7 7a7 7 0 0 1 14 0"/></svg>',
    ],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="recruiter-sidebar sidebar fixed left-0 top-0 z-50 flex h-full w-60 flex-col transform -translate-x-full transition-transform duration-300 lg:translate-x-0" id="sidebar">
    <div class="recruiter-sidebar__brand px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="recruiter-sidebar__logo flex h-10 w-10 items-center justify-center rounded-2xl text-white font-display font-extrabold">S</div>
            <div>
                <div class="font-display text-lg font-extrabold tracking-tight text-white">SkillTrust</div>
                <div class="text-xs font-medium text-indigo-300/90">Recruiter Panel</div>
            </div>
        </div>
    </div>

    <nav class="recruiter-sidebar__nav flex-1 overflow-y-auto px-4 py-6">
        <div class="mb-4 px-2">
            <span class="recruiter-sidebar__caption text-xs font-semibold uppercase tracking-[0.28em]">Main Menu</span>
        </div>
        <div class="space-y-2">
            <?php foreach ($recruiterSidebarItems as $item): ?>
                <?php $isActive = $recruiterSidebarCurrentPage === $item['href']; ?>
                <a
                    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="recruiter-sidebar__item <?php echo $isActive ? 'active' : ''; ?> flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium"
                >
                    <span class="recruiter-sidebar__icon flex items-center justify-center" aria-hidden="true"><?php echo $item['icon']; ?></span>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="recruiter-sidebar__footer px-4 py-4">
        <div class="recruiter-sidebar__profile mb-3 flex items-center gap-3 rounded-2xl px-4 py-3">
            <div class="recruiter-sidebar__avatar flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-bold text-white">
                <?php echo htmlspecialchars($recruiterSidebarInitials, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-white"><?php echo htmlspecialchars($recruiterSidebarName, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="truncate text-xs text-slate-400"><?php echo htmlspecialchars($recruiterSidebarEmail !== '' ? $recruiterSidebarEmail : $recruiterSidebarCompany, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>

        <a href="logout.php" class="recruiter-sidebar__logout flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-center text-sm font-semibold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H7.75A1.75 1.75 0 0 0 6 7.75v8.5A1.75 1.75 0 0 0 7.75 18H10m4-9 4 3m0 0-4 3m4-3H9"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>
