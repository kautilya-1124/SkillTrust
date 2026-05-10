<?php
// auth/register.php – SkillTrust Student Panel
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SkillTrust</title>
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

        html, body { height: 100%; overflow-x: hidden; }
        body {
            background: #0f172a;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(99,102,241,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(139,92,246,0.15) 0%, transparent 60%);
        }

        .glass-card {
            background: radial-gradient(circle at top left, rgba(148,163,184,0.18), transparent 55%),
                        rgba(15,23,42,0.96);
            border: 1px solid rgba(148,163,184,0.3);
            backdrop-filter: blur(26px);
            box-shadow: 0 30px 80px rgba(15,23,42,0.95);
        }

        .input-field {
            background: rgba(15,23,42,0.96);
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
        .glow-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .field-error { border-color: rgba(248,113,113,0.9) !important; }
    </style>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300 flex items-start sm:items-center justify-center min-h-screen px-4 py-8 sm:py-10 overflow-x-hidden">

<div class="absolute inset-0 pointer-events-none">
    <div class="absolute -top-24 left-10 w-64 h-64 bg-brand-500/25 blur-3xl rounded-full"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 bg-violet-500/25 blur-3xl rounded-full"></div>
</div>

<div class="relative w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 items-start sm:items-center">

    <!-- Left: Branding / benefits -->
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
                Create your SkillTrust account.
            </h1>
            <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                Build your trust score, track practice tests, and show a verified skill profile to mentors and hiring managers.
            </p>
        </div>
        <ul class="space-y-2 text-xs text-slate-400">
            <li class="flex items-start gap-2">
                <span class="mt-0.5 text-emerald-400">✓</span>
                <span>Modern dashboard with animated trust score and detailed results.</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 text-emerald-400">✓</span>
                <span>Dynamic tests, timers, and local progress saving.</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 text-emerald-400">✓</span>
                <span>Get WhatsApp alerts when interviews are scheduled.</span>
            </li>
        </ul>
    </div>

    <!-- Right: Registration form -->
    <div class="glass-card relative rounded-3xl px-5 py-6 sm:px-8 sm:py-8 w-full max-w-md lg:max-w-lg mt-0 sm:mt-6 lg:mt-10 mx-auto lg:mx-0 lg:ml-auto">
        <div class="absolute -top-8 right-8 w-24 h-24 bg-emerald-400/25 blur-3xl rounded-full pointer-events-none"></div>

        <div class="mb-6 text-center sm:text-left">
            <div class="inline-flex items-center gap-2 bg-brand-500/10 border border-brand-500/25 text-brand-200 text-[10px] font-semibold px-2.5 py-1 rounded-full mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Free student account
            </div>
            <h2 class="font-display font-bold text-xl sm:text-2xl text-white mb-1">Get started in seconds</h2>
            <p class="text-xs text-slate-500">All fields except phone are required.</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
        <div class="mb-4 px-4 py-3 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 px-4 py-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
        <?php endif; ?>

        <form id="registerForm" action="actions/register_action.php" method="POST" class="space-y-4" novalidate>

            <!-- CSRF token — required for security -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Name + Username -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Full name
                    </label>
                    <input id="name" name="name" type="text"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                           placeholder="Alex Johnson" required>
                    <p id="nameError" class="mt-1 text-[11px] text-red-400 hidden">Please enter your name.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Username
                    </label>
                    <input id="username" name="username" type="text" autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                           placeholder="alex_j" required>
                    <p id="usernameError" class="mt-1 text-[11px] text-red-400 hidden">3–18 chars, letters, digits, _ only.</p>
                </div>
            </div>

            <!-- Email -->
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                    Email
                </label>
                <input id="email" name="email" type="email" autocomplete="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                       placeholder="you@example.com" required>
                <p id="emailError" class="mt-1 text-[11px] text-red-400 hidden">Enter a valid email address.</p>
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                    WhatsApp Number
                    <span class="normal-case text-slate-500 font-normal ml-1">(optional — for interview alerts)</span>
                </label>
                <div class="flex gap-2">
                    <div class="input-field flex items-center px-3 rounded-2xl text-sm text-slate-400 whitespace-nowrap">
                        🇮🇳 +91
                    </div>
                    <input id="phone" name="phone" type="tel"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                           placeholder="98765 43210" maxlength="10" pattern="[0-9]{10}">
                </div>
                <p id="phoneError" class="mt-1 text-[11px] text-red-400 hidden">Enter a valid 10-digit mobile number.</p>
            </div>

            <!-- Password + Confirm -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                           placeholder="Min. 6 characters" required>
                    <p id="passwordError" class="mt-1 text-[11px] text-red-400 hidden">Password must be at least 6 characters.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Confirm
                    </label>
                    <input id="confirm" name="confirm" type="password" autocomplete="new-password"
                           class="input-field w-full rounded-2xl px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                           placeholder="Repeat password" required>
                    <p id="confirmError" class="mt-1 text-[11px] text-red-400 hidden">Passwords do not match.</p>
                </div>
            </div>

            <!-- Terms -->
            <div class="flex items-start gap-2 text-[11px]">
                <input id="terms" type="checkbox"
                       class="mt-0.5 h-3.5 w-3.5 rounded border-slate-600 bg-slate-900 text-brand-500 focus:ring-brand-500/60" required>
                <label for="terms" class="text-slate-400">
                    I agree to the <span class="text-brand-300 font-semibold">Terms</span> and
                    <span class="text-brand-300 font-semibold">Privacy Policy</span>.
                </label>
            </div>
            <p id="termsError" class="mt-1 text-[11px] text-red-400 hidden">You must accept the terms to continue.</p>

            <button id="submitBtn" type="submit"
                    class="glow-btn w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-semibold text-white mt-1">
                <span id="btnText">Create account</span>
                <svg id="btnSpinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
                </svg>
            </button>

        </form>

        <p class="mt-4 text-[11px] text-slate-500 text-center">
            Already have an account?
            <a href="login.php" class="text-brand-300 font-semibold hover:text-brand-200">Sign in</a>
        </p>
    </div>
</div>

<script>
// ── Field helpers ──────────────────────────────────────────────────────────────
function setError(id, show, msg) {
    var errEl  = document.getElementById(id + 'Error');
    var field  = document.getElementById(id);
    if (errEl) {
        errEl.classList.toggle('hidden', !show);
        if (msg) errEl.textContent = msg;
    }
    if (field) field.classList.toggle('field-error', show);
    return !show;
}

// ── Form submit validation ─────────────────────────────────────────────────────
document.getElementById('registerForm').addEventListener('submit', function(e) {
    var ok = true;

    // Name
    var name = document.getElementById('name').value.trim();
    if (!setError('name', name === '')) ok = false;

    // Username
    var username = document.getElementById('username').value.trim();
    if (!setError('username', !/^[a-zA-Z0-9_]{3,18}$/.test(username))) ok = false;

    // Email
    var email = document.getElementById('email').value.trim();
    if (!setError('email', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))) ok = false;

    // Phone (optional — only validate if filled)
    var phone = document.getElementById('phone').value.replace(/\D/g, '');
    var phoneEl = document.getElementById('phone');
    if (phoneEl.value.trim() !== '' && phone.length !== 10) {
        setError('phone', true, 'Enter a valid 10-digit mobile number.');
        ok = false;
    } else {
        setError('phone', false);
    }

    // Password
    var password = document.getElementById('password').value;
    if (!setError('password', password.length < 6)) ok = false;

    // Confirm
    var confirm = document.getElementById('confirm').value;
    if (!setError('confirm', confirm !== password || password.length < 6)) ok = false;

    // Terms
    var terms = document.getElementById('terms').checked;
    var termsErr = document.getElementById('termsError');
    termsErr.classList.toggle('hidden', terms);
    if (!terms) ok = false;

    if (!ok) {
        e.preventDefault();
        return;
    }

    // Show spinner while submitting
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('btnText').textContent = 'Creating account…';
    document.getElementById('btnSpinner').classList.remove('hidden');
});
</script>

</body>
</html>
