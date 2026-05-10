<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Admin';
$pageSubtitle = $pageSubtitle ?? '';
?>
<header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:py-0 lg:h-16 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all duration-300">☰</button>
        <div>
            <h2 class="font-display font-bold text-white text-lg"><?php echo e($pageTitle); ?></h2>
            <?php if ($pageSubtitle !== ''): ?>
                <p class="text-xs text-slate-500"><?php echo e($pageSubtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>
