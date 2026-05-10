// ── State ──────────────────────────────────────────────────────────────
let state = {
    search: '',
    difficulty: 'all',
    category: 'all',
    status: null,
    sort: 'default',
    view: 'grid'
};

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatCodingDifficulty(value) {
    const difficulty = String(value || 'Medium').toLowerCase();
    if (difficulty === 'easy' || difficulty === 'beginner') {
        return {
            label: 'Easy',
            className: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25'
        };
    }
    if (difficulty === 'hard' || difficulty === 'advanced') {
        return {
            label: 'Hard',
            className: 'bg-rose-500/15 text-rose-300 border border-rose-500/25'
        };
    }
    return {
        label: 'Medium',
        className: 'bg-amber-500/15 text-amber-300 border border-amber-500/25'
    };
}

function renderCodingQuestions(data) {
    console.log(data);

    const container = document.getElementById('codingChallenges');
    const countBadge = document.getElementById('codingQuestionCountBadge');
    if (!container) return;

    if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML = `
            <div class="rounded-2xl border border-dashed border-slate-700/60 bg-slate-900/30 px-5 py-10 text-center xl:col-span-2">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-fuchsia-500/10 text-sm font-bold text-fuchsia-300">ST</div>
                <h3 class="mt-4 font-display text-lg font-bold text-white">No coding tests available</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">New coding challenges will appear here once active questions are published.</p>
            </div>
        `;
        if (countBadge) countBadge.textContent = '0 live';
        return;
    }

    let html = '';
    data.forEach((q, index) => {
        const problemStatement = String(q.problem_statement || '').trim();
        const excerpt = problemStatement.length > 170
            ? `${problemStatement.slice(0, 167).trimEnd()}...`
            : problemStatement;
        const difficulty = formatCodingDifficulty(q.difficulty);
        const timeLimit = Number(q.time_limit_seconds || 0);
        const questionId = parseInt(q.id, 10) || 0;

        html += `
            <article class="test-card rounded-2xl p-5 card-enter visible" style="transition-delay:${(index % 4) * 80}ms">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-fuchsia-500/15 border border-fuchsia-500/20 text-fuchsia-200">
                                ${escapeHtml(q.category || 'Programming')}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full ${difficulty.className}">
                                ${escapeHtml(difficulty.label)}
                            </span>
                        </div>
                        <h3 class="font-display font-700 text-white text-base leading-snug">
                            ${escapeHtml(q.title || q.problem_statement || 'Coding Challenge')}
                        </h3>
                    </div>
                </div>

                <p class="mt-3 text-sm leading-6 text-slate-400">
                    ${escapeHtml(excerpt)}
                </p>

                <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-400 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-700/40 bg-slate-800/30 px-3 py-2">
                        <div class="text-slate-500">Category</div>
                        <div class="mt-1 font-semibold text-slate-200">${escapeHtml(q.category || 'Programming')}</div>
                    </div>
                    <div class="rounded-xl border border-slate-700/40 bg-slate-800/30 px-3 py-2">
                        <div class="text-slate-500">Time Limit</div>
                        <div class="mt-1 font-semibold text-slate-200">${escapeHtml(timeLimit)}s</div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-700/40 pt-4">
                    <span class="text-xs text-slate-500">Open the challenge and start coding.</span>
                    <a href="coding-test.php?id=${questionId}"
                       class="start-btn inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white w-full sm:w-auto transition-all duration-300">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Start Coding
                    </a>
                </div>
            </article>
        `;
    });

    container.innerHTML = html;
    if (countBadge) countBadge.textContent = `${data.length} live`;
}

function loadCodingQuestions() {
    fetch('get-coding-tests.php')
        .then(res => res.json())
        .then(data => {
            renderCodingQuestions(data);
        })
        .catch(err => {
            console.error('loadCodingQuestions error:', err);
            const container = document.getElementById('codingChallenges');
            if (container) {
                container.innerHTML = `
                    <div class="rounded-2xl border border-dashed border-red-700/60 bg-slate-900/30 px-5 py-10 text-center xl:col-span-2">
                        <h3 class="mt-4 font-display text-lg font-bold text-red-400">Failed to load coding challenges</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Check your database connection and session. Error: ${err.message}</p>
                    </div>`;
            }
        });
}

// ── Sidebar ────────────────────────────────────────────────────────────
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    sb.classList.toggle('-translate-x-full');
    ov.classList.toggle('active');
}

// ── Dropdown ───────────────────────────────────────────────────────────
function toggleDropdown() {
    document.getElementById('dropdownMenu').classList.toggle('open');
}
document.addEventListener('click', e => {
    const pd = document.getElementById('profileDropdown');
    if (pd && !pd.contains(e.target)) document.getElementById('dropdownMenu').classList.remove('open');
});

// ── View Toggle ────────────────────────────────────────────────────────
function setView(v) {
    state.view = v;
    const grid = document.getElementById('gridView');
    const list = document.getElementById('listView');
    const gBtn = document.getElementById('gridViewBtn');
    const lBtn = document.getElementById('listViewBtn');

    if (v === 'grid') {
        if (grid) {
            grid.classList.remove('hidden');
            grid.style.display = '';
        }
        if (list) {
            list.classList.add('hidden');
            list.style.display = '';
        }
        if (gBtn) gBtn.classList.add('active');
        if (lBtn) lBtn.classList.remove('active');
    } else {
        if (grid) {
            grid.classList.add('hidden');
            grid.style.display = '';
        }
        if (list) {
            list.classList.remove('hidden');
            list.style.display = '';
        }
        if (lBtn) lBtn.classList.add('active');
        if (gBtn) gBtn.classList.remove('active');
    }
    applyFilters();
}

// ── Filter Logic ───────────────────────────────────────────────────────
function setFilter(type, value, btn) {
    // Update state
    if (type === 'difficulty') {
        state.difficulty = value;
        document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
    } else if (type === 'category') {
        state.category = value;
        document.querySelectorAll('[data-cat]').forEach(b => b.classList.remove('active'));
    } else if (type === 'status') {
        // Toggle status
        if (state.status === value) {
            state.status = null;
            btn.classList.remove('active');
        } else {
            state.status = value;
            document.querySelectorAll('[data-status]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
        applyFilters();
        updateActiveFilters();
        return;
    }
    btn.classList.add('active');
    applyFilters();
    updateActiveFilters();
}

function sortTests(val) {
    state.sort = val;
    applyFilters();
}

function applyFilters() {
    const isGrid = state.view === 'grid';
    const viewCards = isGrid
        ? document.querySelectorAll('#gridView .test-card')
        : document.querySelectorAll('#listView .list-card');
    const featCards = document.querySelectorAll('#featuredSection .test-card');
    const featuredSection = document.getElementById('featuredSection');

    [...viewCards, ...featCards].forEach(card => {
        const diff = card.dataset.difficulty;
        const cat  = card.dataset.category;
        const comp = card.dataset.completed === 'true';
        const title= card.dataset.title || '';
        const tags = card.dataset.tags  || '';

        const matchDiff   = state.difficulty === 'all' || diff === state.difficulty;
        const matchCat    = state.category   === 'all' || cat  === state.category;
        const matchStatus = !state.status ||
                            (state.status === 'completed' && comp) ||
                            (state.status === 'new' && !comp);
        const q = state.search.toLowerCase();
        const matchSearch = !q || title.includes(q) || tags.includes(q) || diff.toLowerCase().includes(q) || cat.toLowerCase().includes(q);

        const show = matchDiff && matchCat && matchStatus && matchSearch;

        card.style.display = show ? '' : 'none';
        if (show) {
            setTimeout(() => card.classList.add('visible'), 30);
        } else {
            card.classList.remove('visible');
        }
    });

    let viewVisible = 0;
    viewCards.forEach(c => { if (c.style.display !== 'none') viewVisible++; });
    const countEl = document.getElementById('countNum');
    if (countEl) countEl.textContent = viewVisible;

    let featVisible = 0;
    featCards.forEach(c => { if (c.style.display !== 'none') featVisible++; });
    if (featuredSection) {
        featuredSection.classList.toggle('hidden', featVisible === 0);
    }

    const emptyEl = document.getElementById('emptyState');
    const gridEl = document.getElementById('gridView');
    const listEl = document.getElementById('listView');
    if (viewVisible === 0) {
        if (emptyEl) {
            emptyEl.classList.remove('hidden');
            emptyEl.style.display = 'block';
        }
        if (gridEl) gridEl.classList.add('hidden');
        if (listEl) listEl.classList.add('hidden');
    } else {
        if (emptyEl) {
            emptyEl.classList.add('hidden');
            emptyEl.style.display = '';
        }
        if (gridEl) gridEl.classList.toggle('hidden', state.view !== 'grid');
        if (listEl) listEl.classList.toggle('hidden', state.view !== 'list');
    }

    sortCards([...viewCards]);
}

function sortCards(cards) {
    const container = state.view === 'grid'
        ? document.getElementById('gridView')
        : document.getElementById('listView');
    if (!container) return;

    const sorted = [...cards].sort((a, b) => {
        const ida = parseInt(a.dataset.id, 10) || 0;
        const idb = parseInt(b.dataset.id, 10) || 0;
        if (state.sort === 'default') return ida - idb;
        if (state.sort === 'newest') return idb - ida;
        if (state.sort === 'rating') return parseFloat(b.dataset.rating || 0) - parseFloat(a.dataset.rating || 0);
        if (state.sort === 'popular') return parseInt(b.dataset.attempts || 0, 10) - parseInt(a.dataset.attempts || 0, 10);
        if (state.sort === 'duration-asc') return parseInt(a.dataset.duration || 0, 10) - parseInt(b.dataset.duration || 0, 10);
        if (state.sort === 'duration-desc') return parseInt(b.dataset.duration || 0, 10) - parseInt(a.dataset.duration || 0, 10);
        return ida - idb;
    });

    sorted.forEach(c => container.appendChild(c));
}

function updateActiveFilters() {
    const row = document.getElementById('activeFiltersRow');
    row.innerHTML = '';

    if (state.difficulty !== 'all') addChip(row, state.difficulty, () => { state.difficulty = 'all'; document.querySelectorAll('[data-filter]').forEach(b=>{if(b.dataset.filter==='all')b.classList.add('active');else b.classList.remove('active');}); applyFilters(); updateActiveFilters(); });
    if (state.category   !== 'all') addChip(row, state.category,   () => { state.category   = 'all'; document.querySelectorAll('[data-cat]').forEach(b=>{if(b.dataset.cat==='all')b.classList.add('active');else b.classList.remove('active');}); applyFilters(); updateActiveFilters(); });
    if (state.status)                addChip(row, state.status,     () => { state.status = null; document.querySelectorAll('[data-status]').forEach(b=>b.classList.remove('active')); applyFilters(); updateActiveFilters(); });
    if (state.search)                addChip(row, `"${state.search}"`, () => { state.search=''; const si=document.getElementById('searchInput'); const ns=document.getElementById('navSearch'); const cs=document.getElementById('clearSearch'); if(si)si.value=''; if(ns)ns.value=''; if(cs)cs.classList.add('hidden'); applyFilters(); updateActiveFilters(); });
}

function addChip(container, label, onRemove) {
    const chip = document.createElement('span');
    chip.className = 'inline-flex items-center gap-1.5 bg-indigo-500/15 border border-indigo-500/25 text-indigo-300 text-xs font-medium px-2.5 py-1 rounded-full count-badge';
    chip.innerHTML = `${label}<button class="hover:text-white transition-colors">×</button>`;
    chip.querySelector('button').addEventListener('click', onRemove);
    container.appendChild(chip);
}

function clearSearchInput() {
    state.search = '';
    const si = document.getElementById('searchInput');
    const ns = document.getElementById('navSearch');
    const cs = document.getElementById('clearSearch');
    if (si) si.value = '';
    if (ns) ns.value = '';
    if (cs) cs.classList.add('hidden');
    applyFilters();
    updateActiveFilters();
}

function resetFilters() {
    state = { search:'', difficulty:'all', category:'all', status:null, sort:'default', view:state.view };
    const si = document.getElementById('searchInput');
    const ns = document.getElementById('navSearch');
    const cs = document.getElementById('clearSearch');
    if (si) si.value = '';
    if (ns) ns.value = '';
    if (cs) cs.classList.add('hidden');
    document.getElementById('sortSelect').value = 'default';
    document.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('active', b.dataset.filter === 'all'));
    document.querySelectorAll('[data-cat]').forEach(b   => b.classList.toggle('active', b.dataset.cat === 'all'));
    document.querySelectorAll('[data-status]').forEach(b => b.classList.remove('active'));
    applyFilters();
    updateActiveFilters();
}

// ── Search ─────────────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const navSearch   = document.getElementById('navSearch');
const clearSearchBtn = document.getElementById('clearSearch');

if (searchInput) {
    searchInput.addEventListener('input', e => {
        state.search = (e.target.value || '').trim();
        if (navSearch) navSearch.value = e.target.value;
        if (clearSearchBtn) clearSearchBtn.classList.toggle('hidden', !state.search);
        applyFilters();
        updateActiveFilters();
    });
}
if (navSearch) {
    navSearch.addEventListener('input', e => {
        if (searchInput) searchInput.value = e.target.value;
        state.search = (e.target.value || '').trim();
        if (clearSearchBtn) clearSearchBtn.classList.toggle('hidden', !state.search);
        applyFilters();
        updateActiveFilters();
    });
}

// ── Intersection Observer for card entrance ────────────────────────────
const cardObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            setTimeout(() => entry.target.classList.add('visible'), 60);
            cardObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.08 });

document.querySelectorAll('.card-enter').forEach(el => cardObserver.observe(el));

// ── Init ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadCodingQuestions();
    applyFilters();
    const currentPage = window.location.pathname.split('/').pop() || 'tests.php';
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href === currentPage) item.classList.add('active');
    });
});
