const profilePageData = JSON.parse(document.getElementById('studentProfileData').textContent);
let skills = Array.isArray(profilePageData.skills) ? profilePageData.skills : [];
let isEditing = false;
const trustScore = Number(profilePageData.trustScore || 0);
const originalProfile = Object.assign({
    name: '',
    username: '',
    email: '',
    phone: '',
    bio: ''
}, profilePageData.profile || {});

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function toggleDropdown() {
    document.getElementById('dropdownMenu').classList.toggle('open');
}
document.addEventListener('click', function (e) {
    var pd = document.getElementById('profileDropdown');
    if (pd && !pd.contains(e.target)) document.getElementById('dropdownMenu').classList.remove('open');
});

document.getElementById('avatarUploadArea').addEventListener('click', function () {
    document.getElementById('avatarInput').click();
});
document.getElementById('avatarInput').addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
        var img = document.getElementById('avatarImg');
        img.src = ev.target.result;
        img.classList.remove('hidden');
        document.getElementById('avatarInitials').classList.add('hidden');
        localStorage.setItem('skilltrust_avatar', ev.target.result);
        showToast('Photo updated (preview only)', 'success');
        updateCompletion();
    };
    reader.readAsDataURL(file);
});

function renderSkills() {
    var c = document.getElementById('skillsContainer');
    c.innerHTML = '';
    skills.forEach(function (sk, i) {
        var t = document.createElement('div');
        t.className = 'skill-tag';
        t.innerHTML = '<span>' + escapeHtml(sk) + '</span><button type="button" class="ml-1" onclick="removeSkill(' + i + ')">&times;</button>';
        c.appendChild(t);
    });
    var hint = document.getElementById('skillsEmptyHint');
    if (hint) hint.classList.toggle('hidden', skills.length > 0);
}

function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function addSkill() {
    var inp = document.getElementById('skillInput');
    var val = inp.value.trim();
    if (!val) return;
    if (skills.includes(val)) { showToast('Skill already added', 'error'); return; }
    skills.push(val);
    inp.value = '';
    renderSkills();
    showToast('Skill added', 'success');
    updateCompletion();
}

function removeSkill(i) {
    var name = skills[i];
    skills.splice(i, 1);
    renderSkills();
    showToast('Removed: ' + name, 'success');
    updateCompletion();
}

function toggleEdit() {
    isEditing = !isEditing;
    var fields = ['inputName','inputUsername','inputEmail','inputPhone','inputBio'];
    fields.forEach(function (id) {
        document.getElementById(id).disabled = !isEditing;
    });
    var btn = document.getElementById('editToggleBtn');
    var row = document.getElementById('saveRow');
    if (isEditing) {
        btn.textContent = 'Cancel';
        btn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/25 hover:bg-red-500/20 transition-all duration-300 w-full sm:w-auto';
        btn.onclick = cancelEdit;
        row.classList.remove('hidden');
        row.classList.add('flex');
    } else {
        cancelEdit();
    }
}

function cancelEdit() {
    isEditing = false;
    document.getElementById('inputName').value = originalProfile.name;
    document.getElementById('inputUsername').value = originalProfile.username;
    document.getElementById('inputEmail').value = originalProfile.email;
    document.getElementById('inputPhone').value = originalProfile.phone;
    document.getElementById('inputBio').value = originalProfile.bio;
    ['inputName','inputUsername','inputEmail','inputPhone','inputBio'].forEach(function (id) {
        document.getElementById(id).disabled = true;
    });
    var btn = document.getElementById('editToggleBtn');
    btn.textContent = 'Edit profile';
    btn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold bg-brand-500/15 text-brand-300 border border-brand-500/25 hover:bg-brand-500/25 transition-all duration-300 w-full sm:w-auto';
    btn.onclick = toggleEdit;
    var row = document.getElementById('saveRow');
    row.classList.add('hidden');
    row.classList.remove('flex');
    updateCompletion();
}

function saveProfile() {
    var name = document.getElementById('inputName').value.trim();
    if (!name) { showToast('Name required', 'error'); return; }
    var username = document.getElementById('inputUsername').value.trim();
    if (!username) { showToast('Username required', 'error'); return; }
    var email = document.getElementById('inputEmail').value.trim();
    if (!email) { showToast('Email required', 'error'); return; }
    var fd = new FormData();
    fd.append('name', name);
    fd.append('username', username);
    fd.append('email', email);
    fd.append('phone', document.getElementById('inputPhone').value.trim());
    fd.append('bio', document.getElementById('inputBio').value);
    fd.append('skills', skills.join(', '));
    fetch('actions/update_profile.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok) {
                showToast((data && data.message) ? data.message : 'Could not save', 'error');
                return;
            }
            originalProfile.name = name;
            originalProfile.username = username;
            originalProfile.email = email;
            originalProfile.phone = document.getElementById('inputPhone').value.trim();
            originalProfile.bio = document.getElementById('inputBio').value;
            document.getElementById('displayName').textContent = name;
            document.getElementById('displayUsername').textContent = username;
            document.getElementById('displayEmail').textContent = email;
            document.getElementById('displayBio').textContent = originalProfile.bio;
            document.getElementById('dropdownDisplayName').textContent = name;
            document.getElementById('dropdownDisplayEmail').textContent = email;
            cancelEdit();
            showToast(data.message || 'Profile updated', 'success');
            updateCompletion();
        })
        .catch(function () { showToast('Network error', 'error'); });
}

function togglePass(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function changePassword() {
    var cur = document.getElementById('currentPass').value;
    var nw = document.getElementById('newPass').value;
    var conf = document.getElementById('confirmPass').value;
    if (!cur || !nw || !conf) { showToast('Fill all fields', 'error'); return; }
    if (nw !== conf) { showToast('Passwords do not match', 'error'); return; }
    if (nw.length < 6) { showToast('Min 6 characters', 'error'); return; }
    var fd = new FormData();
    fd.append('current_password', cur);
    fd.append('new_password', nw);
    fd.append('confirm_password', conf);
    fetch('actions/update_password.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok) {
                showToast((data && data.message) ? data.message : 'Could not update password', 'error');
                return;
            }
            ['currentPass','newPass','confirmPass'].forEach(function (id) { document.getElementById(id).value = ''; });
            showToast(data.message || 'Password updated', 'success');
        })
        .catch(function () { showToast('Network error', 'error'); });
}

function confirmDelete() {
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

var toastTimer;
function showToast(msg, type) {
    clearTimeout(toastTimer);
    var t = document.getElementById('toast');
    var dot = document.getElementById('toastDot');
    document.getElementById('toastMsg').textContent = msg;
    dot.className = 'w-2 h-2 rounded-full flex-shrink-0 ' + (type === 'error' ? 'bg-red-500' : 'bg-emerald-500');
    t.classList.remove('translate-y-20', 'opacity-0');
    toastTimer = setTimeout(function () {
        t.classList.add('translate-y-20', 'opacity-0');
    }, 3200);
}

function animateTrustRing() {
    var ring = document.getElementById('trustRingSvg');
    if (!ring) return;
    var c = 2 * Math.PI * 40;
    ring.style.strokeDasharray = String(c);
    ring.style.strokeDashoffset = String(c);
    requestAnimationFrame(function () {
        ring.style.strokeDashoffset = String(c - (trustScore / 100) * c);
    });
}

function updateCompletion() {
    var pts = 0, max = 6;
    if (document.getElementById('inputName').value.trim()) pts++;
    if (document.getElementById('inputEmail').value.trim()) pts++;
    if (document.getElementById('inputBio').value.trim().length > 20) pts++;
    if (document.getElementById('inputPhone').value.trim()) pts++;
    if (skills.length >= 3) pts++;
    if (!document.getElementById('avatarImg').classList.contains('hidden') || localStorage.getItem('skilltrust_avatar')) pts++;
    var pct = Math.round((pts / max) * 100);
    var ring = document.getElementById('completionRing');
    var circum = 264;
    var off = circum - (pct / 100) * circum;
    ring.style.strokeDashoffset = String(off);
    document.getElementById('completionPct').textContent = pct + '%';
    var hints = ['Add a longer bio (+20 chars).', 'Add phone number.', 'Add at least 3 skills.', 'Upload a profile photo.'];
    document.getElementById('completionHint').textContent = pct >= 100 ? 'Profile looks great.' : hints[pts % hints.length];
}

function exportProfileJson() {
    var data = {
        exportedAt: new Date().toISOString(),
        name: document.getElementById('inputName').value,
        username: document.getElementById('inputUsername').value,
        email: document.getElementById('inputEmail').value,
        phone: document.getElementById('inputPhone').value,
        bio: document.getElementById('inputBio').value,
        skills: skills,
        trustScore: trustScore
    };
    var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'skilltrust-profile.json';
    a.click();
    URL.revokeObjectURL(a.href);
    showToast('Download started', 'success');
}

function loadPrefs() {
    try {
        var p = JSON.parse(localStorage.getItem('skilltrust_prefs') || '{}');
        if (p.digest) document.getElementById('prefDigest').checked = true;
        if (p.reminders) document.getElementById('prefReminders').checked = true;
        if (p.public) document.getElementById('prefPublic').checked = true;
    } catch (e) {}
}

function savePrefs() {
    var data = {
        digest: document.getElementById('prefDigest').checked,
        reminders: document.getElementById('prefReminders').checked,
        public: document.getElementById('prefPublic').checked
    };
    localStorage.setItem('skilltrust_prefs', JSON.stringify(data));
    showToast('Preferences saved', 'success');
}

document.querySelectorAll('.pref-toggle').forEach(function (el) {
    el.addEventListener('change', savePrefs);
});

document.querySelectorAll('.score-bar-fill').forEach(function (bar, i) {
    setTimeout(function () {
        bar.style.width = bar.getAttribute('data-width');
    }, 200 + i * 80);
});

var obs = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
        if (e.isIntersecting) {
            e.target.style.animationPlayState = 'running';
            obs.unobserve(e.target);
        }
    });
}, { threshold: 0.08 });
document.querySelectorAll('.opacity-0-start').forEach(function (el) {
    el.style.animationPlayState = 'paused';
    obs.observe(el);
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.resume-upload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-target');
            if (id) {
                var inp = document.getElementById(id);
                if (inp) inp.click();
            }
        });
    });
    document.querySelectorAll('input[type=file][data-resume-type]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var f = inp.files[0];
            if (!f) return;
            var type = inp.getAttribute('data-resume-type');
            var fd = new FormData();
            fd.append('resume', f);
            fd.append('resume_type', type);
            fetch('upload_resume.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        showToast((data && data.message) ? data.message : 'Upload failed', 'error');
                        return;
                    }
                    showToast(data.message || 'Uploaded', 'success');
                    window.location.reload();
                })
                .catch(function () { showToast('Network error', 'error'); });
            inp.value = '';
        });
    });
    document.querySelectorAll('.resume-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-resume-type');
            if (!type || !window.confirm('Remove this resume?')) return;
            var fd = new FormData();
            fd.append('resume_type', type);
            fetch('delete_resume.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        showToast((data && data.message) ? data.message : 'Could not delete', 'error');
                        return;
                    }
                    showToast(data.message || 'Removed', 'success');
                    window.location.reload();
                })
                .catch(function () { showToast('Network error', 'error'); });
        });
    });

    renderSkills();
    loadPrefs();
    animateTrustRing();
    updateCompletion();
    var saved = localStorage.getItem('skilltrust_avatar');
    if (saved) {
        var img = document.getElementById('avatarImg');
        img.src = saved;
        img.classList.remove('hidden');
        document.getElementById('avatarInitials').classList.add('hidden');
    }
    var path = window.location.pathname.split('/').pop() || 'profile.php';
    document.querySelectorAll('.nav-item').forEach(function (item) {
        if (item.getAttribute('href') === path) item.classList.add('active');
    });
});
