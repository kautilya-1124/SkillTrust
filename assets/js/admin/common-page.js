(function () {
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay) {
            return;
        }
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('active');
    }

    function initPage(dataId) {
        window.toggleSidebar = toggleSidebar;

        document.addEventListener('DOMContentLoaded', function () {
            const dataEl = document.getElementById(dataId);
            if (!dataEl) {
                return;
            }

            const pageData = JSON.parse(dataEl.textContent || '{}');
            const toastType = pageData.toastType || '';
            const toastMsg = pageData.toastMsg || '';
            if (!toastType || !toastMsg) {
                return;
            }

            const toast = document.getElementById('toast');
            if (!toast) {
                return;
            }

            toast.textContent = toastMsg;
            toast.classList.remove('hidden');
            if (toastType === 'success') {
                toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border bg-emerald-500/15 border-emerald-500/30 text-emerald-300';
            } else {
                toast.className = 'fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border bg-rose-500/15 border-rose-500/30 text-rose-300';
            }

            setTimeout(function () {
                toast.classList.add('hidden');
            }, 3000);
        });
    }

    window.AdminCommonPage = {
        initPage: initPage,
        toggleSidebar: toggleSidebar
    };
})();
