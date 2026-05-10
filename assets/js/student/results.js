    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    function toggleDropdown() {
        document.getElementById('dropdownMenu').classList.toggle('open');
    }
    document.addEventListener('click', function (e) {
        var pd = document.getElementById('profileDropdown');
        if (pd && !pd.contains(e.target)) {
            document.getElementById('dropdownMenu').classList.remove('open');
        }
    });

    var resultsPageData = JSON.parse(document.getElementById('studentResultsData').textContent);
    var avgScore = Number(resultsPageData.avgScore || 0);

    function animateRing() {
        var ring = document.getElementById('resultsRing');
        if (!ring) return;
        var c = 2 * Math.PI * 45;
        ring.style.strokeDasharray = String(c);
        ring.style.strokeDashoffset = String(c);
        requestAnimationFrame(function () {
            ring.style.strokeDashoffset = String(c - (avgScore / 100) * c);
        });
    }

    function countUpScore() {
        var el = document.getElementById('avgScoreNum');
        if (!el) return;
        var cur = 0;
        var steps = 48;
        var step = avgScore / steps;
        var t = setInterval(function () {
            cur = Math.min(cur + step, avgScore);
            el.textContent = Math.round(cur);
            if (cur >= avgScore) clearInterval(t);
        }, 20);
    }

    function revealRows() {
        document.querySelectorAll('.results-row').forEach(function (row, i) {
            setTimeout(function () {
                row.classList.add('visible');
            }, 80 + i * 70);
        });
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.opacity-0-start').forEach(function (el) {
        el.style.animationPlayState = 'paused';
        observer.observe(el);
    });

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(animateRing, 400);
        setTimeout(countUpScore, 500);
        setTimeout(revealRows, 600);

        var search = document.getElementById('resultsSearch');
        var table = document.getElementById('resultsTable');
        var empty = document.getElementById('emptyResults');
        if (search && table) {
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                var rows = table.querySelectorAll('.results-row');
                var n = 0;
                rows.forEach(function (row) {
                    var title = row.getAttribute('data-title') || '';
                    var show = !q || title.indexOf(q) !== -1;
                    row.style.display = show ? '' : 'none';
                    if (show) n++;
                });
                if (empty) empty.classList.toggle('hidden', n > 0);
            });
        }
    });

    var currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    document.querySelectorAll('.nav-item').forEach(function (item) {
        var href = item.getAttribute('href');
        if (href === currentPage) item.classList.add('active');
    });
