// ═══════════════════════════════════════════════════════
//  QUESTION DATA
// ═══════════════════════════════════════════════════════
// const QUESTIONS = [
//     {
//         id: 1,
//         text: "What will the following code output?",
//         code: `console.log(typeof null);`,
//         options: ["null", "undefined", "object", "string"],
//         correct: 2,
//         explanation: "In JavaScript, typeof null returns 'object'. This is a well-known bug in JavaScript that has been kept for backwards compatibility since the language's early days.",
//         category: "JavaScript"
//     },
//     {
//         id: 2,
//         text: "Which of the following correctly declares a constant in ES6?",
//         code: null,
//         options: ["let x = 5;", "constant x = 5;", "const x = 5;", "var x = 5;"],
//         correct: 2,
//         explanation: "The const keyword was introduced in ES6 to declare block-scoped constants. Unlike var and let, const bindings cannot be reassigned after initialization.",
//         category: "JavaScript"
//     },
//     {
//         id: 3,
//         text: "What is the correct way to create an arrow function that returns the square of a number?",
//         code: null,
//         options: [
//             "const sq = (n) => { return n * n }",
//             "const sq = (n) => n * n",
//             "const sq = function(n) => n * n",
//             "const sq = n -> n * n"
//         ],
//         correct: 1,
//         explanation: "Arrow functions with a single expression body can omit the curly braces and the return keyword. Both options A and B are valid, but B is the more concise, idiomatic form.",
//         category: "JavaScript"
//     },
//     {
//         id: 4,
//         text: "Which method is used to add an element to the END of an array in JavaScript?",
//         code: null,
//         options: ["array.unshift()", "array.shift()", "array.push()", "array.pop()"],
//         correct: 2,
//         explanation: "push() adds one or more elements to the end of an array. unshift() adds to the beginning, pop() removes from the end, and shift() removes from the beginning.",
//         category: "JavaScript"
//     },
//     {
//         id: 5,
//         text: "What does the '===' operator check in JavaScript?",
//         code: null,
//         options: [
//             "Only value equality",
//             "Only type equality",
//             "Both value and type equality",
//             "Reference equality only"
//         ],
//         correct: 2,
//         explanation: "The strict equality operator (===) checks both the value and the type without type coercion. The loose equality operator (==) performs type coercion before comparison.",
//         category: "JavaScript"
//     },
//     {
//         id: 6,
//         text: "What is the output of this code?",
//         code: `let a = [1, 2, 3];
// let b = a;
// b.push(4);
// console.log(a.length);`,
//         options: ["3", "4", "undefined", "Error"],
//         correct: 1,
//         explanation: "Arrays in JavaScript are reference types. When you assign b = a, both variables point to the same array in memory. Modifying b also modifies a, so a.length is 4.",
//         category: "JavaScript"
//     },
//     {
//         id: 7,
//         text: "Which of the following is NOT a valid way to declare a variable in JavaScript?",
//         code: null,
//         options: ["var x = 1;", "let x = 1;", "const x = 1;", "int x = 1;"],
//         correct: 3,
//         explanation: "JavaScript uses var, let, and const for variable declarations. There is no int keyword in JavaScript — that belongs to statically-typed languages like Java or C.",
//         category: "JavaScript"
//     },
//     {
//         id: 8,
//         text: "What does 'this' refer to inside an arrow function?",
//         code: null,
//         options: [
//             "The function itself",
//             "The global object",
//             "The enclosing lexical context",
//             "undefined"
//         ],
//         correct: 2,
//         explanation: "Arrow functions do not have their own 'this' binding. They inherit 'this' from the enclosing lexical scope at the time they are defined, unlike regular functions.",
//         category: "JavaScript"
//     },
//     {
//         id: 9,
//         text: "Which array method creates a new array with the results of calling a function on every element?",
//         code: null,
//         options: ["forEach()", "filter()", "reduce()", "map()"],
//         correct: 3,
//         explanation: "The map() method creates a new array populated with the results of calling a provided function on every element. forEach() executes but doesn't return a new array.",
//         category: "JavaScript"
//     },
//     {
//         id: 10,
//         text: "What is the result of the following expression?",
//         code: `console.log(0.1 + 0.2 === 0.3);`,
//         options: ["true", "false", "NaN", "undefined"],
//         correct: 1,
//         explanation: "Due to floating-point precision issues in JavaScript (and most languages), 0.1 + 0.2 equals 0.30000000000000004, not exactly 0.3. So the strict comparison returns false.",
//         category: "JavaScript"
//     }
// ];

const testPageData = JSON.parse(document.getElementById('studentTestStartData').textContent);
const QUESTIONS = testPageData.questions || [];

// ═══════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════
const TOTAL       = QUESTIONS.length;
const DURATION_S  = Number(testPageData.durationSeconds || 0);
const PASSING     = Number(testPageData.passingScore || 0);
const TEST_CONFIG = testPageData.testConfig || { warningLimit: 3, fullscreenRequired: true, testId: 0 };
const STORAGE_KEY = testPageData.storageKey || 'skilltrust_test';

let state = {
    current:   0,
    answers:   new Array(TOTAL).fill(null),   // null = unanswered
    flagged:   new Array(TOTAL).fill(false),
    submitted: false,
    reviewMode: false,
    timeLeft:  DURATION_S,
    startTime: Date.now(),
    timerInterval: null,
    warningCount: 0,
    autoSubmitReason: '',
    lastViolationAt: 0,
};

// Load from localStorage if exists
(function restoreState() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const s = JSON.parse(saved);
            if (!s.submitted) {
                state.answers = s.answers || state.answers;
                state.flagged = s.flagged || state.flagged;
                state.timeLeft= s.timeLeft || DURATION_S;
                state.warningCount = Number.isInteger(s.warningCount) ? s.warningCount : state.warningCount;
            }
        } catch(e) {}
    }
})();

function saveState() {
    if (!state.submitted) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            answers: state.answers,
            flagged: state.flagged,
            timeLeft: state.timeLeft,
            warningCount: state.warningCount,
        }));
    }
}

function syncAnswerHiddenInputs() {
    const container = document.getElementById('answersContainer');
    if (!container) return;
    container.innerHTML = '';
    state.answers.forEach((ans, i) => {
        if (ans !== null && QUESTIONS[i]) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `answers[${QUESTIONS[i].id}]`;
            input.value = String(ans + 1);
            container.appendChild(input);
        }
    });
}

// ═══════════════════════════════════════════════════════
//  TIMER
// ═══════════════════════════════════════════════════════
function startTimer() {
    if (window.SkillTrustTimer && typeof window.SkillTrustTimer.start === 'function') {
        window.SkillTrustTimer.start({
            state,
            durationSeconds: DURATION_S,
            onTick: updateTimerUI,
            onExpire: () => finalSubmit(true, 'timer_expired'),
            onPersist: saveState,
        });
        return;
    }

    updateTimerUI(state.timeLeft);
    state.timerInterval = setInterval(() => {
        state.timeLeft--;
        updateTimerUI(state.timeLeft);
        saveState();
        if (state.timeLeft <= 0) {
            clearInterval(state.timerInterval);
            finalSubmit(true, 'timer_expired');
        }
    }, 1000);
}

function updateTimerUI(secs) {
    const m = String(Math.floor(secs / 60)).padStart(2,'0');
    const s = String(secs % 60).padStart(2,'0');
    document.getElementById('timerDisplay').textContent = `${m}:${s}`;

    // Ring
    const circ   = 138.2;
    const frac   = secs / DURATION_S;
    const offset = circ * (1 - frac);
    document.getElementById('timerRing').style.strokeDashoffset = offset;

    // Color states
    const wrap = document.getElementById('timerWrapper');
    wrap.className = wrap.className.replace(/timer-(normal|warn|danger)/g,'');
    if (secs <= 60)       wrap.classList.add('timer-danger','timer-pulse');
    else if (secs <= 300) wrap.classList.add('timer-warn');
    else                  wrap.classList.add('timer-normal');
}

// ═══════════════════════════════════════════════════════
//  RENDER
// ═══════════════════════════════════════════════════════
let renderSeq = 0;
let renderAnimationTimeout = null;

function renderQuestion(idx, direction = 'none') {
    renderSeq++;
    const seq = renderSeq;

    if (renderAnimationTimeout) {
        clearTimeout(renderAnimationTimeout);
        renderAnimationTimeout = null;
    }

    const q   = QUESTIONS[idx];
    const card = document.getElementById('questionCard');

    // Animate out / in (cancel stale timeouts so card/map never disagree after fast Prev/Next)
    if (direction !== 'none') {
        card.style.opacity = '0';
        card.style.transform = direction === 'next' ? 'translateX(-20px)' : 'translateX(20px)';
        renderAnimationTimeout = setTimeout(() => {
            renderAnimationTimeout = null;
            if (seq !== renderSeq) return;
            doRenderQuestion(idx, q, direction);
            card.style.transition = 'opacity .28s ease, transform .28s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
        }, 160);
    } else {
        card.style.transition = '';
        card.style.opacity = '1';
        card.style.transform = 'translateX(0)';
        doRenderQuestion(idx, q, direction);
    }

    updateNav(idx);
    updateProgress(idx);
    updateQuestionMap(idx);
    updateSideStats();
}

function doRenderQuestion(idx, q) {
    // Label & category
    document.getElementById('qLabel').textContent = `Question ${String(idx+1).padStart(2,'0')}`;
    document.getElementById('qCategory').textContent = q.category;
    document.getElementById('currentQNum').textContent = idx + 1;

    // Difficulty dots
    const dotsEl = document.getElementById('qDots');
    dotsEl.innerHTML = '';
    ['★','★','★'].forEach((star, i) => {
        const s = document.createElement('span');
        s.textContent = star;
        const diff = QUESTIONS[idx].difficulty || 'easy';
        const lvl = {'easy':1,'medium':2,'hard':3}[diff] || 2;
        s.className = `text-xs ${i < 2 ? 'text-amber-400' : 'text-slate-700'}`;
        dotsEl.appendChild(s);
    });

    // Question text
    document.getElementById('questionText').textContent = q.text;

    // Code block
    const codeEl = document.getElementById('questionCode');
    if (q.code) {
        codeEl.innerHTML = `<div class="code-block">${escapeHtml(q.code)}</div>`;
        codeEl.classList.remove('hidden');
    } else {
        codeEl.classList.add('hidden');
    }

    // Options
    const optArea = document.getElementById('optionsArea');
    optArea.innerHTML = '';
    q.options.forEach((opt, i) => {
        const letter = ['A','B','C','D'][i];
        const wrapper = document.createElement('div');
        wrapper.className = 'relative';

        const isAnswered  = state.answers[idx] === i;
        const isReview    = state.reviewMode;
        const isCorrect   = i === q.correct;
        const isWrongPick = isReview && isAnswered && !isCorrect;
        const isRightPick = isReview && isCorrect;

        let wrapClass = '';
        if (isReview) {
            if (isRightPick) wrapClass = 'option-correct';
            else if (isWrongPick) wrapClass = 'option-wrong';
        }

        wrapper.innerHTML = `
            <input type="radio" name="q${idx}" id="opt_${idx}_${i}" value="${i}"
                   class="sr-only"
                   ${isAnswered ? 'checked' : ''}
                   ${isReview ? 'disabled' : ''}>
            <label for="opt_${idx}_${i}"
                   class="option-label ${wrapClass} flex items-center gap-4 px-5 py-4 rounded-2xl select-none">
                <div class="option-marker w-7 h-7 rounded-xl border-2 border-slate-600/60
                            flex items-center justify-center flex-shrink-0 transition-all duration-200
                            ${isAnswered ? 'bg-gradient-to-br from-indigo-500 to-violet-600 border-transparent' : ''}">
                    ${isAnswered
                        ? `<svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                           </svg>`
                        : `<span class="text-xs font-display font-700 text-slate-500">${letter}</span>`
                    }
                </div>
                <span class="text-sm font-medium ${isAnswered ? 'text-white' : 'text-slate-300'}">${opt}</span>
                ${isReview && isCorrect ? `<svg class="w-4 h-4 text-emerald-400 ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>` : ''}
                ${isReview && isWrongPick ? `<svg class="w-4 h-4 text-red-400 ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>` : ''}
            </label>`;

        // Click handler
        if (!isReview) {
            wrapper.querySelector('label').addEventListener('click', () => selectAnswer(idx, i));
        }

        // Apply correct/wrong wrapper class for review
        if (isReview) {
            wrapper.classList.add(wrapClass);
        }

        optArea.appendChild(wrapper);
    });

    // Flag button
    const fb   = document.getElementById('flagBtn');
    const fbtx = document.getElementById('flagBtnText');
    if (state.flagged[idx]) {
        fb.classList.add('flagged');
        fbtx.textContent = 'Flagged';
    } else {
        fb.classList.remove('flagged');
        fbtx.textContent = 'Flag';
    }

    // Explanation panel
    const expPanel = document.getElementById('explanationPanel');
    if (state.reviewMode) {
        document.getElementById('explanationText').textContent = q.explanation;
        expPanel.classList.remove('hidden');
    } else {
        expPanel.classList.add('hidden');
    }
}

function selectAnswer(qIdx, optIdx) {
    state.answers[qIdx] = optIdx;
    saveState();
    syncAnswerHiddenInputs();
    renderQuestion(qIdx, 'none');
    // Small pulse on progress bar
    const pb = document.getElementById('progressBar');
    pb.style.transform = 'scaleY(1.5)';
    setTimeout(() => pb.style.transform = '', 200);
}

// ═══════════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════════
function navigate(dir) {
    const next = state.current + dir;
    if (next < 0 || next >= TOTAL) return;
    state.current = next;
    renderQuestion(next, dir > 0 ? 'next' : 'prev');
}

function skipQuestion() {
    // Find next unanswered
    for (let i = state.current + 1; i < TOTAL; i++) {
        if (state.answers[i] === null) { goTo(i); return; }
    }
    for (let i = 0; i < state.current; i++) {
        if (state.answers[i] === null) { goTo(i); return; }
    }
}

function goTo(idx) {
    const dir = idx > state.current ? 'next' : 'prev';
    state.current = idx;
    renderQuestion(idx, dir);
}

function updateNav(idx) {
    document.getElementById('prevBtn').disabled = idx === 0;
    const isLast = idx === TOTAL - 1;
    document.getElementById('nextBtn').classList.toggle('hidden', isLast);
    document.getElementById('submitBtn').classList.toggle('hidden', !isLast);
    document.getElementById('submitBtn').style.display = isLast ? 'flex' : 'none';
    document.getElementById('skipBtn').style.display = (state.answers[idx] !== null || isLast) ? 'none' : 'flex';
}

// ═══════════════════════════════════════════════════════
//  PROGRESS
// ═══════════════════════════════════════════════════════
function updateProgress(currentIdx = state.current) {
    const answered = state.answers.filter(a => a !== null).length;
    const pct      = ((currentIdx + 1) / TOTAL) * 100;
    document.getElementById('progressBar').style.width    = pct + '%';
    document.getElementById('answeredCount').textContent  = answered;
}

function updateSideStats() {
    const answered = state.answers.filter(a => a !== null).length;
    const flagged  = state.flagged.filter(Boolean).length;
    document.getElementById('sideAnswered').textContent    = answered;
    document.getElementById('sideTotal').textContent       = TOTAL;
    document.getElementById('sideFlagged').textContent     = flagged;
    document.getElementById('sideRemaining').textContent   = `${TOTAL - answered} remaining`;
    document.getElementById('sideProgressBar').style.width = (answered / TOTAL * 100) + '%';
    document.getElementById('totalQNum').textContent       = TOTAL;
}

// ═══════════════════════════════════════════════════════
//  QUESTION MAP
// ═══════════════════════════════════════════════════════
function updateQuestionMap(currentIdx = state.current) {
    const map = document.getElementById('questionMap');
    map.innerHTML = '';
    QUESTIONS.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'q-dot';
        dot.textContent = i + 1;
        dot.onclick = () => goTo(i);

        if (i === currentIdx)                 dot.classList.add('current');
        else if (state.flagged[i])           dot.classList.add('flagged');
        else if (state.answers[i] !== null)  dot.classList.add('answered');

        // Review mode: show correct/wrong
        if (state.reviewMode) {
            if (state.answers[i] === QUESTIONS[i].correct) dot.classList.add('correct');
            else if (state.answers[i] !== null)            dot.classList.add('wrong');
        }
        map.appendChild(dot);
    });
}

// ═══════════════════════════════════════════════════════
//  FLAG
// ═══════════════════════════════════════════════════════
function toggleFlag() {
    state.flagged[state.current] = !state.flagged[state.current];
    saveState();
    renderQuestion(state.current, 'none');
    updateSideStats();
}

// ═══════════════════════════════════════════════════════
//  SUBMIT
// ═══════════════════════════════════════════════════════
function openSubmitModal() {
    const answered = state.answers.filter(a => a !== null).length;
    if (answered === 0) {
        alert('Please answer at least one question before submitting.');
        return;
    }
    const unanswered = TOTAL - answered;
    document.getElementById('submitAnsweredCount').textContent = answered;
    document.getElementById('submitTotalCount').textContent    = TOTAL;
    document.getElementById('submitUnansweredNote').textContent =
        unanswered > 0
            ? `⚠️ ${unanswered} question${unanswered > 1 ? 's' : ''} left unanswered.`
            : '✅ All questions answered!';
    openModal('submitModal');
}

function setAntiCheatBanner(message) {
    const banner = document.getElementById('antiCheatBanner');
    const text = document.getElementById('antiCheatBannerText');
    if (!banner || !text) {
        return;
    }
    text.textContent = message;
    banner.classList.remove('hidden');
}

function registerViolation(message, reason) {
    const now = Date.now();
    if (now - state.lastViolationAt < 1200) {
        return;
    }
    state.lastViolationAt = now;
    state.warningCount += 1;
    saveState();

    const violationField = document.getElementById('violationCountField');
    if (violationField) {
        violationField.value = String(state.warningCount);
    }

    setAntiCheatBanner(`${message} Warning ${state.warningCount} of ${TEST_CONFIG.warningLimit}.`);
    alert(`${message}\nWarning ${state.warningCount} of ${TEST_CONFIG.warningLimit}.`);

    if (state.warningCount >= TEST_CONFIG.warningLimit) {
        finalSubmit(true, reason || 'anti_cheat_limit');
    }
}

function finalSubmit(timeUp = false, reason = '') {
    const answered = state.answers.filter(a => a !== null).length;
    if (answered === 0) {
        clearInterval(state.timerInterval);
        closeModal('submitModal');
        if (timeUp) {
            alert('Time is up. No answers were submitted.');
            window.location.href = 'tests.php';
        }
        return;
    }

    syncAnswerHiddenInputs();
    const elapsed = Math.max(0, Math.round((Date.now() - state.startTime) / 1000));
    const ef = document.getElementById('elapsedSecondsField');
    if (ef) ef.value = String(elapsed);
    const violationField = document.getElementById('violationCountField');
    if (violationField) {
        violationField.value = String(state.warningCount);
    }
    const autoSubmittedField = document.getElementById('autoSubmittedField');
    if (autoSubmittedField) {
        autoSubmittedField.value = timeUp ? '1' : '0';
    }
    const reasonField = document.getElementById('submitReasonField');
    if (reasonField) {
        reasonField.value = reason;
    }
    state.autoSubmitReason = reason;

    clearInterval(state.timerInterval);
    closeModal('submitModal');
    state.submitted = true;
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}

    document.getElementById('submitForm').submit();
}

function showResults(score, correct, wrong, passed, mins, secs, timeUp) {
    const overlay = document.getElementById('resultOverlay');

    // Set content
    document.getElementById('resultEmoji').textContent = passed ? '🏆' : '📚';
    document.getElementById('resultTitle').textContent = passed
        ? (score >= 90 ? 'Outstanding!' : score >= 75 ? 'Great Work!' : 'You Passed!')
        : (timeUp ? "Time's Up!" : 'Keep Practicing');
    document.getElementById('resultSubtitle').textContent = passed
        ? `You scored ${score}% — above the ${PASSING}% passing mark.`
        : `You scored ${score}% — the passing mark is ${PASSING}%.`;

    const badge = document.getElementById('resultBadge');
    badge.className = `inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold mb-4 border ${passed ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' : 'bg-red-500/15 border-red-500/30 text-red-400'}`;
    document.getElementById('resultBadgeText').textContent = passed ? '✓ Passed' : '✗ Failed';

    document.getElementById('resCorrect').textContent = correct;
    document.getElementById('resWrong').textContent   = wrong;
    document.getElementById('resTime').textContent    = `${mins}m${secs < 10 ? '0' : ''}${secs}s`;

    // Ring animation
    const ring = document.getElementById('resultRingFill');
    ring.style.stroke = passed ? 'url(#resultGradPass)' : 'url(#resultGradFail)';
    const circ   = 364.4;
    const target = circ * (1 - score / 100);

    overlay.classList.add('open');

    setTimeout(() => {
        ring.style.strokeDashoffset = target;
        // Count up
        let n = 0;
        const el = document.getElementById('resultScoreNum');
        const step = score / 80;
        const iv = setInterval(() => {
            n = Math.min(n + step, score);
            el.textContent = Math.round(n);
            if (n >= score) clearInterval(iv);
        }, 16);
    }, 350);

    // Confetti for pass
    if (passed) spawnConfetti();
}

function reviewAnswers() {
    document.getElementById('resultOverlay').classList.remove('open');
    state.reviewMode = true;
    state.current = 0;
    renderQuestion(0, 'none');
}

// ═══════════════════════════════════════════════════════
//  MODALS
// ═══════════════════════════════════════════════════════
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('exitModal');
        closeModal('submitModal');
    }
    if (!state.submitted && !state.reviewMode) {
        if (e.key === 'ArrowRight' || e.key === 'l') navigate(1);
        if (e.key === 'ArrowLeft'  || e.key === 'h') navigate(-1);
        if (['1','2','3','4'].includes(e.key)) selectAnswer(state.current, parseInt(e.key)-1);
        if (e.key === 'f') toggleFlag();
    }
});

// ═══════════════════════════════════════════════════════
//  CONFETTI
// ═══════════════════════════════════════════════════════
function spawnConfetti() {
    const colors = ['#6366f1','#8b5cf6','#10b981','#f59e0b','#ef4444','#3b82f6'];
    for (let i = 0; i < 60; i++) {
        const dot = document.createElement('div');
        dot.className = 'confetti-dot';
        dot.style.cssText = `
            left:${Math.random()*100}vw;
            top:-10px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            animation-delay:${Math.random()*1.5}s;
            animation-duration:${2 + Math.random()*1.5}s;
            width:${6 + Math.random()*8}px;
            height:${6 + Math.random()*8}px;
        `;
        document.body.appendChild(dot);
        setTimeout(() => dot.remove(), 4500);
    }
}

// ═══════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════
function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════
window.addEventListener('beforeunload', (e) => {
    if (!state.submitted && state.answers.some(a => a !== null)) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.addEventListener('DOMContentLoaded', () => {
    renderQuestion(state.current, 'none');
    syncAnswerHiddenInputs();
    const violationField = document.getElementById('violationCountField');
    if (violationField) {
        violationField.value = String(state.warningCount);
    }
    startTimer();
    if (window.SkillTrustFullscreen && typeof window.SkillTrustFullscreen.init === 'function') {
        window.SkillTrustFullscreen.init({
            state,
            onViolation: (message, reason) => registerViolation(message, reason),
            onBanner: setAntiCheatBanner,
            requireFullscreen: TEST_CONFIG.fullscreenRequired,
        });
    }
    if (window.SkillTrustAntiCheat && typeof window.SkillTrustAntiCheat.init === 'function') {
        window.SkillTrustAntiCheat.init({
            state,
            warningLimit: TEST_CONFIG.warningLimit,
            onViolation: (message, reason) => registerViolation(message, reason),
            onAutoSubmit: (reason) => finalSubmit(true, reason),
            onBanner: setAntiCheatBanner,
        });
    }
});
