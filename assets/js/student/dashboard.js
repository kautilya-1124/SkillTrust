    const dashboardData = JSON.parse(document.getElementById('studentDashboardData').textContent);
    const data = dashboardData.chartData || [];
    // ─── Sidebar toggle ───
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sidebarOverlay');
        sb.classList.toggle('-translate-x-full');
        ov.classList.toggle('active');
    }

    // ─── Profile dropdown ───
    function toggleDropdown() {
        const menu = document.getElementById('dropdownMenu');
        menu.classList.toggle('open');
    }
    document.addEventListener('click', (e) => {
        const pd = document.getElementById('profileDropdown');
        if (!pd.contains(e.target)) {
            document.getElementById('dropdownMenu').classList.remove('open');
        }
    });

    // ─── Animate score bars on load ───
    function animateBars() {
        document.querySelectorAll('.score-bar-fill').forEach((bar, i) => {
            const target = bar.dataset.width;
            setTimeout(() => {
                bar.style.width = target;
            }, 300 + i * 100);
        });
    }

    // ─── Animate chart bars ───
    function animateChartBars() {
        document.querySelectorAll('.chart-bar').forEach((bar, i) => {
            const targetH = bar.dataset.height;
            setTimeout(() => {
                bar.style.height = targetH;
            }, 400 + i * 80);
        });
    }

    // ─── Trust Score ring animation ───
    function animateTrustRing() {
        const ring = document.getElementById('trustRing');
        const numEl = document.getElementById('trustNum');
        const score = Number(dashboardData.trustScore || 0);
        const circumference = 251.2;
        const offset = circumference - (score / 100) * circumference;

        setTimeout(() => {
            ring.style.strokeDashoffset = offset;
        }, 500);

        // Count-up number
        let current = 0;
        const step = score / 60;
        const interval = setInterval(() => {
            current = Math.min(current + step, score);
            numEl.textContent = Math.round(current);
            if (current >= score) clearInterval(interval);
        }, 16);
    }

    // ─── Intersection Observer for staggered reveals ───
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.opacity-0-start').forEach(el => {
        el.style.animationPlayState = 'paused';
        observer.observe(el);
    });

    // ─── Initialize on DOM ready ───
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(animateBars, 200);
        setTimeout(animateChartBars, 300);
        setTimeout(animateTrustRing, 400);
    });

    // ─── Active nav highlight (auto) ───
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href === currentPage) {
            item.classList.add('active');
        }
    });
