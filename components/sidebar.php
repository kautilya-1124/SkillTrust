<?php
$currentPage = $currentPage ?? '';
$sidebarLinks = [
    ['href' => 'dashboard.php', 'label' => 'Dashboard'],
    ['href' => 'manage-tests.php', 'label' => 'Manage Tests'],
    ['href' => 'create-test.php', 'label' => 'Create Test'],
    ['href' => 'tests-list.php', 'label' => 'Tests List'],
    ['href' => 'manage-users.php', 'label' => 'Manage Users'],
    ['href' => 'manage-recruiters.php', 'label' => 'Manage Recruiters'],
    ['href' => 'admin-profile.php', 'label' => 'Admin Profile'],
];
?>
<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 -translate-x-full lg:translate-x-0 transition-all duration-300 bg-white/90 backdrop-blur border-r border-slate-200 shadow-sm">
    <div class="h-16 px-5 flex items-center border-b border-slate-200">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold">S</div>
        <div class="ml-3">
            <p class="text-sm font-semibold text-slate-900">SkillTrust</p>
            <p class="text-[11px] text-slate-500">Admin Panel</p>
        </div>
    </div>
    <nav class="p-3 space-y-1">
        <?php foreach ($sidebarLinks as $link):
            $active = $currentPage === $link['href']; ?>
            <a href="<?php echo e($link['href']); ?>"
               class="flex items-center px-3 py-2.5 rounded-xl text-sm transition-all duration-300 <?php echo $active ? 'bg-indigo-50 text-indigo-600 font-semibold shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?>">
                <?php echo e($link['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
