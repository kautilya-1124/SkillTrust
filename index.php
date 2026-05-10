<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillTrust | Prove Your Skills. Get Trusted.</title>
    <meta name="description" content="SkillTrust is a full-stack skill assessment and testing platform for developers, students, and recruiters.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="assets/js/index-tailwind-config.js"></script>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/theme-overrides.css">
</head>
<body class="antialiased">
    <div id="pageLoader" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950 transition-all duration-700">
        <div class="relative flex flex-col items-center gap-6">
            <div class="absolute h-32 w-32 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute h-28 w-28 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="relative flex h-20 w-20 items-center justify-center rounded-3xl border border-white/10 bg-white/5 shadow-glow">
                <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-500/20 via-violet-500/10 to-emerald-400/20"></div>
                <div class="h-10 w-10 rounded-2xl border border-indigo-300/20 bg-gradient-to-br from-indigo-400 via-violet-500 to-emerald-400 animate-spinSlow"></div>
                <span class="absolute text-lg font-black text-white">S</span>
            </div>
            <div class="space-y-2 text-center">
                <p class="font-display text-2xl font-bold tracking-tight text-white">SkillTrust</p>
                <p class="text-sm text-slate-400">Warming up your skill graph...</p>
            </div>
        </div>
    </div>

    <div class="custom-cursor-ring"></div>
    <div class="custom-cursor"></div>

    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
        <div class="hero-orb left-[-6rem] top-[-5rem] h-64 w-64 bg-indigo-500/20 animate-float"></div>
        <div class="hero-orb right-[8%] top-[8%] h-56 w-56 bg-violet-500/20 animate-pulseGlow"></div>
        <div class="hero-orb bottom-[12%] left-[12%] h-72 w-72 bg-emerald-400/10 animate-float" style="animation-delay: -3s;"></div>
        <div class="grid-lines absolute inset-0 opacity-40"></div>
        <div id="particles" class="absolute inset-0"></div>
    </div>

    <header class="sticky top-0 z-50 border-b border-white/5 bg-slate-950/55 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
            <a href="#home" class="interactive inline-flex items-center gap-3 text-white">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 shadow-glow">
                    <span class="bg-gradient-to-br from-indigo-400 via-violet-400 to-emerald-300 bg-clip-text font-display text-xl font-black text-transparent">S</span>
                </span>
                <span>
                    <span class="block font-display text-lg font-bold tracking-tight">SkillTrust</span>
                    <span class="block text-xs text-slate-400">Skill testing platform</span>
                </span>
            </a>
            <nav class="hidden items-center gap-8 lg:flex">
                <a href="#home" class="interactive group relative text-sm font-medium text-slate-300 transition hover:text-white">Home<span class="absolute -bottom-2 left-0 h-px w-0 bg-gradient-to-r from-indigo-400 to-emerald-300 transition-all duration-300 group-hover:w-full"></span></a>
                <a href="#features" class="interactive group relative text-sm font-medium text-slate-300 transition hover:text-white">Features<span class="absolute -bottom-2 left-0 h-px w-0 bg-gradient-to-r from-indigo-400 to-emerald-300 transition-all duration-300 group-hover:w-full"></span></a>
                <a href="auth/login.php" class="interactive group relative text-sm font-medium text-slate-300 transition hover:text-white">Dashboard<span class="absolute -bottom-2 left-0 h-px w-0 bg-gradient-to-r from-indigo-400 to-emerald-300 transition-all duration-300 group-hover:w-full"></span></a>
            </nav>
            <div class="hidden items-center gap-3 lg:flex">
                <details class="login-dropdown-wrap relative">
                    <summary class="interactive inline-flex list-none items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-emerald-400/40 hover:bg-white/5">
                        <span>Logins</span>
                        <svg class="login-dropdown-icon h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="login-dropdown absolute right-0 top-full z-20 mt-3 w-56 overflow-hidden rounded-3xl border border-white/10 bg-slate-950/90 p-2 shadow-2xl backdrop-blur-xl">
                        <a href="auth/login.php" class="interactive flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5 hover:text-white">
                            <span>User Login</span>
                            <span class="text-xs text-emerald-300">Student</span>
                        </a>
                        <a href="recruiter/login.php" class="interactive flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5 hover:text-white">
                            <span>Recruiter Login</span>
                            <span class="text-xs text-indigo-300">Hiring</span>
                        </a>
                        <a href="admin/login.php" class="interactive flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5 hover:text-white">
                            <span>Admin Login</span>
                            <span class="text-xs text-violet-300">Control</span>
                        </a>
                    </div>
                </details>
                <a href="auth/register.php" class="interactive ripple-btn relative overflow-hidden rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-400 px-5 py-2.5 text-sm font-semibold text-white shadow-glow transition hover:-translate-y-0.5">Get Started</a>
            </div>
            <button id="menuToggle" class="interactive inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 lg:hidden" type="button" aria-label="Open navigation" aria-expanded="false">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
        </div>
        <div id="mobileMenu" class="max-h-0 overflow-hidden border-t border-white/5 transition-all duration-500 lg:hidden">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-4">
                <a href="#home" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">Home</a>
                <a href="#features" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">Features</a>
                <a href="auth/login.php" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">Dashboard</a>
                <div class="rounded-[1.5rem] border border-white/10 bg-white/[0.03] p-2">
                    <p class="px-2 pb-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Logins</p>
                    <div class="flex flex-col gap-1">
                        <a href="auth/login.php" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">User Login</a>
                        <a href="recruiter/login.php" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">Recruiter Login</a>
                        <a href="admin/login.php" class="interactive rounded-2xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5">Admin Login</a>
                    </div>
                </div>
                <a href="auth/register.php" class="interactive ripple-btn relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-400 px-4 py-3 text-center text-sm font-semibold text-white">Get Started</a>
            </div>
        </div>
    </header>

    <main>
        <section id="home" class="relative overflow-hidden">
            <div class="mx-auto grid min-h-[92vh] max-w-7xl items-center gap-14 px-5 py-20 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-24">
                <div class="relative z-10">
                    <div class="reveal inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200 shadow-glow">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_16px_rgba(52,211,153,0.9)]"></span>
                        Trusted by learners, hiring teams, and training programs
                    </div>
                    <h1 class="reveal mt-8 max-w-3xl font-display text-5xl font-bold leading-[0.95] text-white sm:text-6xl lg:text-7xl">
                        Prove Your Skills.
                        <span class="text-gradient">Get Trusted.</span>
                    </h1>
                    <p class="reveal mt-6 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                        Skill-based testing platform for developers, students, and recruiters. Launch verified assessments, track performance in real time, and turn raw talent into trusted proof.
                    </p>
                    <div class="reveal mt-9 flex flex-col gap-4 sm:flex-row">
                        <a href="auth/register.php" class="interactive ripple-btn relative inline-flex items-center justify-center overflow-hidden rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-400 px-7 py-4 text-base font-semibold text-white shadow-glow transition hover:-translate-y-1">Get Started</a>
                        <a href="auth/login.php" class="interactive ripple-btn relative inline-flex items-center justify-center overflow-hidden rounded-full border border-white/10 bg-white/5 px-7 py-4 text-base font-semibold text-slate-100 transition hover:border-emerald-300/35 hover:bg-white/10 hover:shadow-emeraldGlow">Student Login</a>
                    </div>
                    <div class="reveal mt-10 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="glass-panel rounded-3xl p-4">
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Precision</p>
                            <p class="mt-3 text-2xl font-bold text-white">98.4%</p>
                            <p class="mt-1 text-sm text-slate-400">Score consistency across test sessions</p>
                        </div>
                        <div class="glass-panel rounded-3xl p-4">
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Screened</p>
                            <p class="mt-3 text-2xl font-bold text-white">120k+</p>
                            <p class="mt-1 text-sm text-slate-400">Candidates evaluated with trust signals</p>
                        </div>
                        <div class="glass-panel rounded-3xl p-4">
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Secure</p>
                            <p class="mt-3 text-2xl font-bold text-white">24/7</p>
                            <p class="mt-1 text-sm text-slate-400">Active anti-cheating and audit monitoring</p>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 reveal">
                    <div id="heroParallax" class="relative mx-auto max-w-xl">
                        <div class="absolute -left-8 top-8 h-28 w-28 rounded-full bg-indigo-500/20 blur-3xl"></div>
                        <div class="absolute -right-6 bottom-12 h-32 w-32 rounded-full bg-emerald-400/15 blur-3xl"></div>
                        <div class="glass-panel-strong relative overflow-hidden rounded-[2rem] p-5 sm:p-6">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(129,140,248,0.2),transparent_25%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.15),transparent_30%)]"></div>
                            <div class="relative space-y-5">
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-400">Live verification score</p>
                                        <p class="mt-1 font-display text-2xl font-bold text-white">94 Trust Index</p>
                                    </div>
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500 text-lg font-bold text-white shadow-glow">AI</div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-3xl border border-white/10 bg-slate-950/55 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm text-slate-400">Completion flow</p>
                                            <span class="rounded-full bg-emerald-400/10 px-2 py-1 text-xs text-emerald-300">Live</span>
                                        </div>
                                        <div class="mt-5 flex h-24 items-end gap-2">
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-indigo-500 to-indigo-300" style="height: 48%"></span>
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-indigo-500 to-violet-300" style="height: 70%"></span>
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-violet-500 to-fuchsia-300" style="height: 58%"></span>
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-emerald-500 to-emerald-300" style="height: 84%"></span>
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-indigo-500 to-cyan-300" style="height: 76%"></span>
                                            <span class="w-1/6 rounded-t-xl bg-gradient-to-t from-emerald-500 to-teal-300" style="height: 94%"></span>
                                        </div>
                                    </div>
                                    <div class="rounded-3xl border border-white/10 bg-slate-950/55 p-4">
                                        <p class="text-sm text-slate-400">Top verified skills</p>
                                        <div class="mt-4 space-y-3">
                                            <div>
                                                <div class="mb-1 flex justify-between text-xs text-slate-300"><span>JavaScript</span><span>96%</span></div>
                                                <div class="h-2 rounded-full bg-white/5"><div class="h-2 rounded-full bg-gradient-to-r from-indigo-400 to-violet-400" style="width: 96%"></div></div>
                                            </div>
                                            <div>
                                                <div class="mb-1 flex justify-between text-xs text-slate-300"><span>Problem Solving</span><span>91%</span></div>
                                                <div class="h-2 rounded-full bg-white/5"><div class="h-2 rounded-full bg-gradient-to-r from-violet-400 to-emerald-300" style="width: 91%"></div></div>
                                            </div>
                                            <div>
                                                <div class="mb-1 flex justify-between text-xs text-slate-300"><span>System Design</span><span>88%</span></div>
                                                <div class="h-2 rounded-full bg-white/5"><div class="h-2 rounded-full bg-gradient-to-r from-emerald-300 to-cyan-300" style="width: 88%"></div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-[1.2fr_0.8fr]">
                                    <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                                        <p class="text-sm text-slate-400">Recent activity</p>
                                        <div class="mt-4 space-y-3">
                                            <div class="flex items-center gap-3 rounded-2xl bg-slate-950/40 px-3 py-3">
                                                <span class="h-10 w-10 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500"></span>
                                                <div>
                                                    <p class="text-sm font-semibold text-white">Node.js Backend Assessment completed</p>
                                                    <p class="text-xs text-slate-400">Verified with proctor signals and coding score</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 rounded-2xl bg-slate-950/40 px-3 py-3">
                                                <span class="h-10 w-10 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500"></span>
                                                <div>
                                                    <p class="text-sm font-semibold text-white">Recruiter shortlist generated</p>
                                                    <p class="text-xs text-slate-400">Matched candidates by trust profile and benchmark band</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-3xl border border-white/10 bg-slate-950/55 p-4">
                                        <p class="text-sm text-slate-400">Platform health</p>
                                        <p class="mt-3 font-display text-3xl font-bold text-white">99.98%</p>
                                        <p class="mt-1 text-sm text-slate-400">Assessment uptime this quarter</p>
                                        <div class="mt-4 rounded-2xl border border-emerald-300/15 bg-emerald-400/8 p-3 text-xs text-emerald-300">Monitoring, anti-cheat checks, and candidate data sync are operating normally.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="reveal border-y border-white/5 bg-white/[0.02] py-6">
            <div class="mx-auto max-w-7xl overflow-hidden px-5 lg:px-8">
                <div class="flex min-w-max animate-marquee gap-4">
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Verified assessment engine</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Adaptive test pipelines</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Recruiter-grade candidate insights</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Cheat detection signals</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Certified skill reports</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Verified assessment engine</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Adaptive test pipelines</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Recruiter-grade candidate insights</div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm text-slate-300">Cheat detection signals</div>
                </div>
            </div>
        </section>

        <section id="features" class="py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mx-auto max-w-3xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-300">Features</p>
                    <h2 class="mt-4 font-display text-4xl font-bold text-white sm:text-5xl">Built to make skill proof feel premium, trusted, and fast.</h2>
                    <p class="mt-4 text-lg leading-8 text-slate-400">Everything on SkillTrust is designed around clear evaluation, trustworthy results, and recruiter-ready outcomes.</p>
                </div>
                <div class="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400/20 to-indigo-500/10 text-indigo-200 shadow-glow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m6-6H6"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Smart Skill Tests</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Launch role-specific tests with structured question sets, code-friendly workflows, and assessment paths tuned for real ability.</p>
                    </article>
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400/20 to-emerald-500/10 text-emerald-200 shadow-emeraldGlow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 13.5l6-6 4 4 8-8"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Real-Time Results</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Instant score breakdowns, readiness signals, and live progress updates keep learners and hiring teams in sync.</p>
                    </article>
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400/20 to-fuchsia-500/10 text-violet-200 shadow-glow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 11c0-1.657 1.343-3 3-3h1a3 3 0 110 6h-1m-3-3V9a3 3 0 10-6 0v3m0 0h12v6a2 2 0 01-2 2H8a2 2 0 01-2-2v-6z"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Anti-Cheating System</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Proctoring cues, behavior checks, and audit-friendly event tracking help maintain integrity without slowing the experience.</p>
                    </article>
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400/20 to-indigo-500/10 text-cyan-100 shadow-glow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5V4H2v16h5m10 0v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6m10 0H7"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Recruiter Dashboard</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Compare candidates with clean filters, trust badges, and shortlisting tools built for practical hiring workflows.</p>
                    </article>
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400/20 to-cyan-500/10 text-emerald-100 shadow-emeraldGlow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 19V6m0 13l-4-4m4 4l4-4M5 19h14"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Performance Analytics</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Reveal strengths, weak spots, attempt patterns, and progress trends with data that feels useful rather than noisy.</p>
                    </article>
                    <article class="interactive tilt-card animated-border glass-panel reveal rounded-[1.75rem] p-6" data-tilt>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400/20 to-emerald-400/10 text-violet-100 shadow-glow"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <h3 class="mt-6 font-display text-2xl font-bold text-white">Trusted Certifications</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">Issue polished certifications backed by score logic, verified session data, and platform trust signals.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
                    <div class="reveal">
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-emerald-300">How It Works</p>
                        <h2 class="mt-4 font-display text-4xl font-bold text-white sm:text-5xl">Three clean steps from sign-up to trusted proof.</h2>
                        <p class="mt-4 max-w-xl text-lg leading-8 text-slate-400">A simple candidate journey with strong platform structure underneath. Register, take the assessment, and graduate with a verified trust signal recruiters can actually use.</p>
                        <div class="mt-8 rounded-[1.75rem] border border-white/10 bg-gradient-to-br from-indigo-500/10 to-emerald-400/5 p-5">
                            <div class="flex items-center justify-between text-sm text-slate-300">
                                <span>Verification progress</span>
                                <span id="stepIndicator">Step 1 of 3</span>
                            </div>
                            <div class="mt-4 h-2 rounded-full bg-white/5">
                                <div id="progressLine" class="h-2 rounded-full bg-gradient-to-r from-indigo-400 via-violet-400 to-emerald-300 transition-all duration-700" style="width: 33%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="story-line relative space-y-5 pl-0 sm:pl-2">
                        <article class="interactive reveal tilt-card glass-panel rounded-[1.75rem] p-6 sm:ml-8" data-step-card="1" data-tilt>
                            <div class="mb-5 flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500 text-lg font-bold text-white shadow-glow">1</div>
                                <div>
                                    <h3 class="font-display text-2xl font-bold text-white">Register</h3>
                                    <p class="text-sm text-slate-400">Create your candidate or recruiter profile in minutes.</p>
                                </div>
                            </div>
                            <p class="text-sm leading-7 text-slate-400">Onboard quickly, personalize your profile, and unlock tests aligned with your learning goals or hiring pipeline.</p>
                        </article>

                        <article class="interactive reveal tilt-card glass-panel rounded-[1.75rem] p-6 sm:ml-16" data-step-card="2" data-tilt>
                            <div class="mb-5 flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-fuchsia-500 text-lg font-bold text-white shadow-glow">2</div>
                                <div>
                                    <h3 class="font-display text-2xl font-bold text-white">Take Test</h3>
                                    <p class="text-sm text-slate-400">Complete assessments with live scoring and integrity checks.</p>
                                </div>
                            </div>
                            <p class="text-sm leading-7 text-slate-400">Move through focused, skill-based tests while SkillTrust tracks performance, timing, and verification signals in the background.</p>
                        </article>

                        <article class="interactive reveal tilt-card glass-panel rounded-[1.75rem] p-6 sm:ml-24" data-step-card="3" data-tilt>
                            <div class="mb-5 flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-cyan-400 text-lg font-bold text-slate-950 shadow-emeraldGlow">3</div>
                                <div>
                                    <h3 class="font-display text-2xl font-bold text-white">Get Certified</h3>
                                    <p class="text-sm text-slate-400">Share a trusted result that stands out.</p>
                                </div>
                            </div>
                            <p class="text-sm leading-7 text-slate-400">Turn strong performance into polished certifications, trust-based insights, and recruiter-ready proof of capability.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-end">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-violet-300">Live Stats</p>
                        <h2 class="mt-4 font-display text-4xl font-bold text-white sm:text-5xl">A growing signal network of real skill proof.</h2>
                    </div>
                    <p class="max-w-2xl text-lg leading-8 text-slate-400">Numbers matter when they reflect trust. These live counters highlight platform usage, assessment volume, and certificate generation.</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="reveal glass-panel rounded-[1.75rem] p-7 text-center">
                        <p class="text-sm uppercase tracking-[0.35em] text-slate-400">Users</p>
                        <p class="mt-5 font-display text-5xl font-bold text-white"><span class="counter" data-target="18500">0</span>+</p>
                        <p class="mt-3 text-sm text-slate-400">Learners and hiring teams actively using SkillTrust</p>
                    </div>
                    <div class="reveal glass-panel rounded-[1.75rem] p-7 text-center">
                        <p class="text-sm uppercase tracking-[0.35em] text-slate-400">Tests Taken</p>
                        <p class="mt-5 font-display text-5xl font-bold text-white"><span class="counter" data-target="96400">0</span>+</p>
                        <p class="mt-3 text-sm text-slate-400">Assessments completed across developer and student tracks</p>
                    </div>
                    <div class="reveal glass-panel rounded-[1.75rem] p-7 text-center">
                        <p class="text-sm uppercase tracking-[0.35em] text-slate-400">Certifications</p>
                        <p class="mt-5 font-display text-5xl font-bold text-white"><span class="counter" data-target="12750">0</span>+</p>
                        <p class="mt-3 text-sm text-slate-400">Verified reports and certificates issued through the platform</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-300">Testimonials</p>
                    <h2 class="mt-4 font-display text-4xl font-bold text-white sm:text-5xl">People trust platforms that make proof feel real.</h2>
                </div>
                <div class="relative mt-14 overflow-hidden">
                    <div id="testimonialTrack" class="flex transition-transform duration-700 ease-out">
                        <article class="w-full shrink-0 px-1">
                            <div class="glass-panel mx-auto max-w-4xl rounded-[2rem] p-8 md:p-10">
                                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                                    <div class="max-w-2xl">
                                        <p class="text-lg leading-8 text-slate-200">&ldquo;SkillTrust gave us a sharper way to screen candidates. The mix of performance data and trust signals cut hours from our shortlisting flow.&rdquo;</p>
                                        <div class="mt-6">
                                            <p class="font-display text-2xl font-bold text-white">Maya Patel</p>
                                            <p class="text-sm text-slate-400">Talent Lead, VertexLoop</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-400 to-violet-500 text-2xl font-bold text-white shadow-glow">MP</div>
                                        <div class="rounded-3xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                                            <p class="font-semibold text-white">Hiring speed</p>
                                            <p class="mt-1 text-emerald-300">42% faster</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <article class="w-full shrink-0 px-1">
                            <div class="glass-panel mx-auto max-w-4xl rounded-[2rem] p-8 md:p-10">
                                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                                    <div class="max-w-2xl">
                                        <p class="text-lg leading-8 text-slate-200">&ldquo;The test experience feels modern and serious at the same time. I could finally share a result that felt like proof, not just a score screenshot.&rdquo;</p>
                                        <div class="mt-6">
                                            <p class="font-display text-2xl font-bold text-white">Arjun Mehta</p>
                                            <p class="text-sm text-slate-400">Full-Stack Developer</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-emerald-400 to-cyan-400 text-2xl font-bold text-slate-950 shadow-emeraldGlow">AM</div>
                                        <div class="rounded-3xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                                            <p class="font-semibold text-white">Trust score</p>
                                            <p class="mt-1 text-emerald-300">Top 7%</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <article class="w-full shrink-0 px-1">
                            <div class="glass-panel mx-auto max-w-4xl rounded-[2rem] p-8 md:p-10">
                                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                                    <div class="max-w-2xl">
                                        <p class="text-lg leading-8 text-slate-200">&ldquo;Our training cohort now has a clear benchmark before placement. Students love the dashboards, and recruiters trust the outcomes.&rdquo;</p>
                                        <div class="mt-6">
                                            <p class="font-display text-2xl font-bold text-white">Sana Rodrigues</p>
                                            <p class="text-sm text-slate-400">Program Director, CodeBridge Academy</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-violet-400 to-fuchsia-500 text-2xl font-bold text-white shadow-glow">SR</div>
                                        <div class="rounded-3xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                                            <p class="font-semibold text-white">Placement lift</p>
                                            <p class="mt-1 text-emerald-300">31% higher</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mt-8 flex items-center justify-center gap-3">
                        <button class="interactive testimonial-dot h-3 w-10 rounded-full bg-white/10 transition" data-slide="0" aria-label="Show testimonial 1"></button>
                        <button class="interactive testimonial-dot h-3 w-10 rounded-full bg-white/10 transition" data-slide="1" aria-label="Show testimonial 2"></button>
                        <button class="interactive testimonial-dot h-3 w-10 rounded-full bg-white/10 transition" data-slide="2" aria-label="Show testimonial 3"></button>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-24 pt-8">
            <div class="mx-auto max-w-6xl px-5 lg:px-8">
                <div class="reveal relative overflow-hidden rounded-[2rem] border border-white/10 bg-[linear-gradient(135deg,rgba(79,70,229,0.22),rgba(124,58,237,0.18),rgba(16,185,129,0.18))] p-8 shadow-glow sm:p-12">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.12),transparent_22%),radial-gradient(circle_at_bottom_right,rgba(16,185,129,0.12),transparent_24%)]"></div>
                    <div class="relative mx-auto max-w-3xl text-center">
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-slate-200">Start Now</p>
                        <h2 class="mt-5 font-display text-4xl font-bold text-white sm:text-5xl">Start Your Skill Journey Today</h2>
                        <p class="mt-5 text-lg leading-8 text-slate-200/85">Build trust with every assessment, every result, and every certificate you share.</p>
                        <div class="mt-8">
                            <a href="auth/register.php" class="interactive ripple-btn relative inline-flex items-center justify-center overflow-hidden rounded-full border border-white/20 bg-slate-950/50 px-8 py-4 text-base font-semibold text-white shadow-[0_0_40px_rgba(129,140,248,0.28)] transition hover:-translate-y-1 hover:shadow-[0_0_55px_rgba(110,231,183,0.35)]">Begin with SkillTrust</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-white/5 py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div>
                <div class="font-display text-2xl font-bold text-white">SkillTrust</div>
                <p class="mt-2 max-w-md text-sm leading-7 text-slate-400">A modern skill assessment platform for testing, verification, analytics, and recruiter-grade trust.</p>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-slate-400">
                <a href="#home" class="interactive transition hover:text-white">Home</a>
                <a href="#features" class="interactive transition hover:text-white">Features</a>
                <a href="auth/login.php" class="interactive transition hover:text-white">Dashboard</a>
                <a href="auth/login.php" class="interactive transition hover:text-white">User Login</a>
                <a href="auth/login.php" class="interactive transition hover:text-white">Login</a>
                <a href="admin/login.php" class="interactive transition hover:text-white">Admin Login</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="#" class="interactive flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:border-indigo-300/35 hover:text-white" aria-label="X"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2H21l-6.56 7.497L22 22h-6.828l-5.347-6.985L3.71 22H.95l7.016-8.018L1 2h7l4.833 6.382L18.244 2zm-2.394 18h1.885L6.976 3.895H4.954L15.85 20z"/></svg></a>
                <a href="#" class="interactive flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:border-indigo-300/35 hover:text-white" aria-label="LinkedIn"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5C4.98 4.88 3.87 6 2.49 6S0 4.88 0 3.5 1.11 1 2.49 1s2.49 1.12 2.49 2.5zM.5 8h4V24h-4V8zm7 0h3.84v2.19h.05c.53-1.01 1.84-2.19 3.79-2.19 4.05 0 4.8 2.67 4.8 6.14V24h-4v-7.79c0-1.86-.03-4.25-2.59-4.25-2.59 0-2.99 2.02-2.99 4.12V24h-4V8z"/></svg></a>
                <a href="#" class="interactive flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:border-emerald-300/35 hover:text-white" aria-label="GitHub"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .5C5.65.5.5 5.65.5 12A11.5 11.5 0 008.36 22.9c.57.11.78-.25.78-.56 0-.28-.01-1.01-.02-1.99-3.2.69-3.88-1.54-3.88-1.54-.52-1.34-1.28-1.69-1.28-1.69-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.76 2.71 1.25 3.37.96.1-.74.4-1.25.72-1.54-2.56-.29-5.26-1.28-5.26-5.72 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.48.11-3.08 0 0 .97-.31 3.19 1.18A11.12 11.12 0 0112 6.1c.98 0 1.96.13 2.88.39 2.21-1.5 3.18-1.18 3.18-1.18.64 1.6.24 2.79.12 3.08.74.81 1.19 1.83 1.19 3.09 0 4.45-2.71 5.42-5.3 5.71.41.36.78 1.09.78 2.2 0 1.59-.01 2.88-.01 3.27 0 .31.2.68.79.56A11.5 11.5 0 0023.5 12C23.5 5.65 18.35.5 12 .5z"/></svg></a>
            </div>
        </div>
    </footer>

    <script src="assets/js/index.js"></script>
</body>
</html>
