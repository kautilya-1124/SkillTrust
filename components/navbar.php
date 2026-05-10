<?php $pageTitle = $pageTitle ?? 'Dashboard'; ?>
<header class="h-16 bg-white/80 backdrop-blur border-b border-slate-200 px-4 lg:px-6 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <button type="button" id="menuBtn" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-all duration-300">
            &#9776;
        </button>
        <div>
            <h1 class="text-base font-semibold text-slate-900"><?php echo e($pageTitle); ?></h1>
            <p class="text-xs text-slate-500"><?php echo e(date('l, d M Y')); ?></p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="button" id="themeToggle" data-theme-toggle="true" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition-all duration-300">
            <span class="skilltrust-theme-icon" data-theme-icon="true"></span>
            <span class="skilltrust-theme-text" data-theme-label="true">Dark mode</span>
        </button>
        <div class="relative">
            <button type="button" id="adminMenuBtn" class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition-all duration-300">
                <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white text-xs font-bold flex items-center justify-center">
                    <?php echo e(strtoupper(substr((string) $adminName, 0, 1))); ?>
                </span>
                <span class="text-sm text-slate-700"><?php echo e($adminName); ?></span>
            </button>
            <div id="adminMenu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-50">
                <a href="admin-profile.php" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100">Profile</a>
                <a href="logout.php" class="block px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">Logout</a>
            </div>
        </div>
    </div>
</header>
