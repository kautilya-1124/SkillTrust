<?php
declare(strict_types=1);

$currentPage = $currentPage ?? '';
$links = [
    ['href' => 'dashboard.php', 'label' => 'Dashboard'],
    ['href' => 'manage-tests.php', 'label' => 'Manage Tests'],
    ['href' => 'create-test.php', 'label' => 'Create Test'],
    ['href' => 'tests-list.php', 'label' => 'Tests List'],
    ['href' => 'manage-users.php', 'label' => 'Manage Users'],
    ['href' => 'manage-recruiters.php', 'label' => 'Manage Recruiters'],
    ['href' => 'admin-profile.php', 'label' => 'Admin Profile'],
];
?>
<aside class="sidebar fixed left-0 top-0 h-full w-64 z-50 flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300" id="sidebar">
    <div class="px-6 py-5 border-b border-indigo-900/30">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-display font-extrabold">S</div>
            <div>
                <span class="font-display font-extrabold text-white text-lg tracking-tight">SkillTrust</span>
                <div class="text-xs text-indigo-400 font-medium -mt-0.5">Admin Panel</div>
            </div>
        </div>
    </div>
    <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
        <?php foreach ($links as $link): ?>
            <?php $active = $currentPage === $link['href']; ?>
            <a href="<?php echo e($link['href']); ?>" class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $active ? 'active text-white' : 'text-slate-400'; ?>">
                <?php echo e($link['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
