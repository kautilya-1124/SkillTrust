<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'display': ['Syne', 'sans-serif'],
                        'body': ['DM Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f4ff', 100: '#e0e9ff', 200: '#c7d5fe', 300: '#a5b4fc',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                            800: '#3730a3', 900: '#1e1b4b',
                        },
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1, h2, h3, .font-display { font-family: 'Syne', sans-serif; }

        html, body { height: 100%; }
        body {
            background: #0f172a;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(99,102,241,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(139,92,246,0.15) 0%, transparent 60%);
        }

        .glass-card {
            background: rgba(15,23,42,0.9);
            border: 1px solid rgba(148,163,184,0.35);
            backdrop-filter: blur(26px);
            box-shadow: 0 30px 80px rgba(15,23,42,0.9);
        }

        .input-field {
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(148,163,184,0.4);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .input-field:focus {
            border-color: rgba(129,140,248,0.85);
            box-shadow: 0 0 0 3px rgba(129,140,248,0.25);
            background: rgba(15,23,42,1);
        }

        .glow-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            transition: all 0.25s ease;
        }
        .glow-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #818cf8, #a78bfa);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .glow-btn:hover::before { opacity: 1; }
        .glow-btn:hover {
            box-shadow: 0 0 30px rgba(99,102,241,0.7);
            transform: translateY(-2px);
        }
        .glow-btn:active { transform: translateY(0) scale(0.98); }

        .field-error { border-color: rgba(248,113,113,0.9) !important; }
    </style>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300 min-h-screen">

<header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/65 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="../index.php" class="inline-flex items-center gap-3 text-white">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-violet-600 shadow-lg shadow-brand-500/30">S</span>
            <span>
                <span class="block font-display text-lg font-bold tracking-tight">SkillTrust</span>
                <span class="block text-[11px] text-brand-300">Student Access</span>
            </span>
        </a>
        <nav class="flex items-center gap-2 sm:gap-3">
            <a href="../index.php" class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-brand-400/40 hover:bg-white/5 sm:text-sm">Home</a>
            <a href="login.php" class="rounded-full border border-brand-400/35 bg-brand-500/10 px-4 py-2 text-xs font-semibold text-brand-200 transition hover:bg-brand-500/20 sm:text-sm">User Login</a>
            <a href="../admin/login.php" class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-brand-400/40 hover:bg-white/5 sm:text-sm">Admin Login</a>
        </nav>
    </div>
</header>

<main class="relative flex min-h-[calc(100vh-81px)] items-center justify-center px-4 py-10">

<div class="absolute inset-0 pointer-events-none">
    <div class="absolute -top-32 -left-10 w-72 h-72 bg-brand-500/20 blur-3xl rounded-full"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 bg-violet-500/20 blur-3xl rounded-full"></div>
</div>

<div class="relative w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
    <!-- Left: Branding -->
    <div class="hidden lg:flex flex-col gap-6 pr-6">
        <div class="inline-flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 flex items-center justify-center shadow-lg shadow-brand-500/40">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 01-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 01-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 01-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 01.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <div>
                <div class="font-display font-extrabold text-white text-xl tracking-tight">SkillTrust</div>
                <div class="text-xs text-brand-300 font-medium -mt-0.5">Student Panel</div>
            </div>
        </div>
        <div>
            <h1 class="font-display font-extrabold text-3xl text-white mb-3">
                Welcome back, learner.
            </h1>
            <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                Track your trust score, complete tests, and showcase your skills in a modern, AI-powered student dashboard.
            </p>
        </div>
        <div class="grid grid-cols-3 gap-3 text-xs text-slate-400">
            <div class="glass-card rounded-xl px-4 py-3">
                <div class="font-display font-bold text-lg text-emerald-400">10k+</div>
                <div class="mt-0.5">Tests completed</div>
            </div>
            <div class="glass-card rounded-xl px-4 py-3">
                <div class="font-display font-bold text-lg text-brand-300">4.8</div>
                <div class="mt-0.5">Avg. trust rating</div>
            </div>
            <div class="glass-card rounded-xl px-4 py-3">
                <div class="font-display font-bold text-lg text-amber-300">180+</div>
                <div class="mt-0.5">Skill tracks</div>
            </div>
        </div>
    </div>

    <!-- Right: Login card -->
    <div class="glass-card relative rounded-3xl px-5 py-6 sm:px-8 sm:py-8 w-full max-w-md mx-auto">
        <div class="absolute -top-10 right-10 w-24 h-24 bg-brand-500/20 blur-3xl rounded-full pointer-events-none"></div>

        <div class="mb-6">
            <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-[10px] font-semibold px-2.5 py-1 rounded-full mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Secure student login
            </div>
            <h2 class="font-display font-bold text-xl sm:text-2xl text-white mb-1">Sign in to SkillTrust</h2>
            <p class="text-xs text-slate-500">Use your registered email to access your student panel.</p>
        </div>

        <form action="actions/login_action.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                    Email
                </label>
                <input id="email" name="email" type="email" autocomplete="email"
                       class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                       placeholder="********" required>
                <p id="emailError" class="mt-1 text-[11px] text-red-400 hidden">Enter a valid email address.</p>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        Password
                    </label>
                    <button type="button" onclick="togglePassword()" class="text-[11px] text-slate-400 hover:text-slate-200">
                        Show / hide
                    </button>
                </div>
                <div class="relative">
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none pr-9"
                           placeholder="********" required>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </span>
                </div>
                <p id="passwordError" class="mt-1 text-[11px] text-red-400 hidden">Password must be at least 6 characters.</p>
            </div>

            <div class="flex items-center justify-between gap-3 text-xs">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input id="remember" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-900 text-brand-500 focus:ring-brand-500/60">
                    <span class="text-slate-400">Remember me on this device</span>
                </label>
                <a href="register.php" class="text-brand-300 hover:text-brand-200 font-semibold">Forgot?</a>
            </div>

            <button id="submitBtn" type="submit"
                    class="glow-btn w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-semibold text-white mt-1">
                <span>Continue</span>
            </button>
        </form>

<div class="mt-3 text-[11px] text-center text-red-400 min-h-[1.25rem]">
<?php
if (isset($_GET['error'])) {
    echo htmlspecialchars($_GET['error']);
}
?>
</div>

        <p class="mt-4 text-[11px] text-slate-500 text-center">
            New to SkillTrust?
            <a href="register.php" class="text-brand-300 font-semibold hover:text-brand-200">Create a student account</a>
        </p>

            </div>
</div>

</main>

<script>
function togglePassword() {
    var el = document.getElementById('password');
    el.type = el.type === 'password' ? 'text' : 'password';
}

function setError(id, show) {
    var msg = document.getElementById(id + 'Error');
    var field = document.getElementById(id);
    if (!msg || !field) return;
    msg.classList.toggle('hidden', !show);
    field.classList.toggle('field-error', show);
}

// function handleLogin(e) {
//     e.preventDefault();
//     var email = document.getElementById('email').value.trim();
//     var password = document.getElementById('password').value;
//     var remember = document.getElementById('remember').checked;
//     var ok = true;

//     var emailValid = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(email);
//     setError('email', !emailValid);
//     if (!emailValid) ok = false;

//     var passValid = password.length >= 6;
//     setError('password', !passValid);
//     if (!passValid) ok = false;

//     var msg = document.getElementById('message');
//     if (!ok) {
//         msg.textContent = 'Please fix the highlighted fields and try again.';
//         return;
//     }

//     try {
//         if (remember) {
//             localStorage.setItem('skilltrust_login_email', email);
//         } else {
//             localStorage.removeItem('skilltrust_login_email');
//         }
//     } catch (e) {}

//     msg.classList.remove('text-red-400');
//     msg.classList.add('text-emerald-400');
//     msg.textContent = 'Login successful â€” redirectingâ€¦';

//     setTimeout(function () {
//         window.location.href = 'dashboard.php';
//     }, 700);
// }

document.addEventListener('DOMContentLoaded', function () {
    try {
        var saved = localStorage.getItem('skilltrust_login_email');
        if (saved) {
            var el = document.getElementById('email');
            el.value = saved;
            document.getElementById('remember').checked = true;
        }
    } catch (e) {}
});
</script>

</body>
</html>
