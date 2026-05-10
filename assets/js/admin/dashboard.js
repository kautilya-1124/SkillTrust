        window.toggleSidebar = window.AdminCommonPage.toggleSidebar;

        const adminMenuBtn = document.getElementById('adminMenuBtn');
        const adminMenu = document.getElementById('adminMenu');
        if (adminMenuBtn && adminMenu) {
            adminMenuBtn.addEventListener('click', function () {
                adminMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function (e) {
                if (!adminMenu.contains(e.target) && !adminMenuBtn.contains(e.target)) {
                    adminMenu.classList.add('hidden');
                }
            });
        }

        const dashboardData = JSON.parse(document.getElementById('adminDashboardData').textContent || '{}');
        const labels = dashboardData.weeklyLabels || [];
        const counts = dashboardData.weeklyCounts || [];
        const testsChartCtx = document.getElementById('testsChart');
        if (testsChartCtx) {
            new Chart(testsChartCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Tests',
                        data: counts,
                        borderColor: '#818cf8',
                        backgroundColor: 'rgba(129, 140, 248, 0.18)',
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.08)' } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.08)' } }
                    }
                }
            });
        }

        const statusChartCtx = document.getElementById('statusChart');
        if (statusChartCtx) {
            new Chart(statusChartCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active Tests', 'Expired Tests', 'Approved Recruiters', 'Pending Recruiters'],
                    datasets: [{
                        data: dashboardData.statusCounts || [],
                        backgroundColor: ['#34d399', '#fb7185', '#60a5fa', '#fbbf24'],
                        borderColor: ['#0f172a', '#0f172a', '#0f172a', '#0f172a'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#94a3b8' } },
                        tooltip: { backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#cbd5e1' }
                    },
                    animation: { duration: 1300, easing: 'easeOutQuart' }
                }
            });
        }
        window.AdminCommonPage.initPage('adminDashboardToastData');
    
