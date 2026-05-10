<?php
declare(strict_types=1);

$studentSidebarCurrentPage = $currentPage ?? basename((string) ($_SERVER['PHP_SELF'] ?? 'dashboard.php'));
$studentSidebarName = trim((string) ($studentSidebarName ?? ($_SESSION['name'] ?? 'Student')));
$studentSidebarEmail = trim((string) ($studentSidebarEmail ?? ($_SESSION['email'] ?? '')));
$studentSidebarInitials = trim((string) ($studentSidebarInitials ?? ''));
$studentSidebarTestCount = isset($studentSidebarTestCount) ? max(0, (int) $studentSidebarTestCount) : null;

if ($studentSidebarInitials === '') {
    $studentSidebarWords = preg_split('/\s+/', $studentSidebarName, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($studentSidebarWords) && $studentSidebarWords !== []) {
        $studentSidebarInitials = count($studentSidebarWords) >= 2
            ? strtoupper(substr((string) $studentSidebarWords[0], 0, 1) . substr((string) $studentSidebarWords[1], 0, 1))
            : strtoupper(substr((string) $studentSidebarWords[0], 0, 2));
    } else {
        $studentSidebarInitials = 'ST';
    }
}

$studentSidebarItems = [
    [
        'href' => 'dashboard.php',
        'label' => 'Dashboard',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 5.75h5.5v5.5h-5.5zm9 0h5.5v5.5h-5.5zm-9 9h5.5v5.5h-5.5zm9 0h5.5v5.5h-5.5z"/></svg>',
    ],
    [
        'href' => 'tests.php',
        'label' => 'Tests',
        'badge' => $studentSidebarTestCount,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.75H7.75A1.75 1.75 0 0 0 6 7.5v8.75A1.75 1.75 0 0 0 7.75 18h8.5A1.75 1.75 0 0 0 18 16.25V7.5a1.75 1.75 0 0 0-1.75-1.75H15m-6-1.5h6a1 1 0 0 1 1 1v1H8v-1a1 1 0 0 1 1-1Zm0 7h6m-6 3h3"/></svg>',
    ],
    [
        'href' => 'jobs.php',
        'label' => 'Jobs',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5.75A1.75 1.75 0 0 1 9.75 4h4.5A1.75 1.75 0 0 1 16 5.75V7m-11 2h14v8.25A1.75 1.75 0 0 1 17.25 19h-10.5A1.75 1.75 0 0 1 5 17.25z"/></svg>',
    ],
    [
        'href' => 'applied_jobs.php',
        'label' => 'Applied Jobs',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.75H7.75A1.75 1.75 0 0 0 6 7.5v8.75A1.75 1.75 0 0 0 7.75 18h8.5A1.75 1.75 0 0 0 18 16.25V7.5a1.75 1.75 0 0 0-1.75-1.75H15m-6-1.5h6a1 1 0 0 1 1 1v1H8v-1a1 1 0 0 1 1-1Zm0 7h6m-6 3h6"/></svg>',
    ],
    [
        'href' => 'results.php',
        'label' => 'Results',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18.25V13.5m7 4.75V9.5m7 8.75V6.75"/></svg>',
    ],
    [
        'href' => 'profile.php',
        'label' => 'Profile',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0-4-4a4 4 0 0 0 4 4Zm-7 7a7 7 0 0 1 14 0"/></svg>',
    ],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="student-sidebar sidebar fixed left-0 top-0 z-50 flex h-full w-64 flex-col transform -translate-x-full transition-transform duration-300 lg:translate-x-0" id="sidebar">
    <div class="student-sidebar__brand px-6 py-5 border-b">
        <div class="flex items-center gap-3">
            <div class="student-sidebar__logo flex h-10 w-10 items-center justify-center rounded-2xl text-white">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m-7.165-5.303a3.42 3.42 0 0 0 1.946-.806a3.42 3.42 0 0 1 4.438 0a3.42 3.42 0 0 0 1.946.806a3.42 3.42 0 0 1 3.138 3.138a3.42 3.42 0 0 0 .806 1.946a3.42 3.42 0 0 1 0 4.438a3.42 3.42 0 0 0-.806 1.946a3.42 3.42 0 0 1-3.138 3.138a3.42 3.42 0 0 0-1.946.806a3.42 3.42 0 0 1-4.438 0a3.42 3.42 0 0 0-1.946-.806a3.42 3.42 0 0 1-3.138-3.138a3.42 3.42 0 0 0-.806-1.946a3.42 3.42 0 0 1 0-4.438a3.42 3.42 0 0 0 .806-1.946a3.42 3.42 0 0 1 3.138-3.138Z"/>
                </svg>
            </div>
            <div>
                <div class="font-display text-lg font-extrabold tracking-tight text-white">SkillTrust</div>
                <div class="text-xs font-medium text-indigo-300/90">Student Panel</div>
            </div>
        </div>
    </div>

    <nav class="student-sidebar__nav flex-1 overflow-y-auto px-4 py-6">
        <div class="mb-4 px-2">
            <span class="student-sidebar__caption text-xs font-semibold uppercase tracking-[0.28em]">Main Menu</span>
        </div>
        <div class="space-y-2">
            <?php foreach ($studentSidebarItems as $item): ?>
                <?php $isActive = $studentSidebarCurrentPage === $item['href']; ?>
                <a
                    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="student-sidebar__item <?php echo $isActive ? 'active' : ''; ?> flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium"
                >
                    <span class="student-sidebar__icon flex items-center justify-center" aria-hidden="true"><?php echo $item['icon']; ?></span>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (array_key_exists('badge', $item) && $item['badge'] !== null): ?>
                        <span class="student-sidebar__badge ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold"><?php echo (int) $item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mb-4 mt-7 px-2">
            <span class="student-sidebar__caption text-xs font-semibold uppercase tracking-[0.28em]">Settings</span>
        </div>
        <div class="space-y-2">
            <a href="profile.php" class="student-sidebar__item flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium">
                <span class="student-sidebar__icon flex items-center justify-center" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 0 0 2.572-1.065Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"/></svg>
                </span>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <div class="student-sidebar__footer px-4 py-4 border-t">
        <div class="student-sidebar__user flex items-center gap-3 rounded-2xl px-4 py-3">
            <div class="student-sidebar__avatar flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white">
                <?php echo htmlspecialchars($studentSidebarInitials, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-white"><?php echo htmlspecialchars($studentSidebarName, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="truncate text-xs text-slate-400"><?php echo htmlspecialchars($studentSidebarEmail, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <div class="px-4 pb-4">
            <a href="../auth/logout.php"
               class="student-sidebar__item flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-red-400 hover:bg-red-500/10 transition-colors">
                <span class="student-sidebar__icon flex items-center justify-center" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
                </span>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</aside>
