<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_recruiter_login();

$recruiterId = current_recruiter_id();
$interviewRowId = (int) ($_GET['id'] ?? 0);
$publicInterviewId = trim((string) ($_GET['interview_id'] ?? ''));

if ($interviewRowId <= 0 && $publicInterviewId === '') {
    http_response_code(400);
    die('Invalid interview request.');
}

$dateColumn = db_column_exists($conn, 'interviews', 'interview_datetime')
    ? 'interview_datetime'
    : (db_column_exists($conn, 'interviews', 'scheduled_at') ? 'scheduled_at' : '');

if ($dateColumn === '') {
    http_response_code(500);
    die('Interview datetime column is missing.');
}

$hasMeetingLinkColumn = db_column_exists($conn, 'interviews', 'meeting_link');
$hasPublicIdColumn = db_column_exists($conn, 'interviews', 'interview_id');
$hasNotesColumn = db_column_exists($conn, 'interviews', 'notes');

$selectColumns = [
    'i.id',
    'i.status',
    'i.' . $dateColumn . ' AS scheduled_at',
    'j.title AS job_title',
    'COALESCE(NULLIF(u.name, ""), CONCAT("Candidate #", a.user_id)) AS candidate_name',
    'COALESCE(NULLIF(u.email, ""), "No email available") AS candidate_email',
];
if ($hasMeetingLinkColumn) {
    $selectColumns[] = 'i.meeting_link';
}
if ($hasPublicIdColumn) {
    $selectColumns[] = 'i.interview_id';
}
if ($hasNotesColumn) {
    $selectColumns[] = 'i.notes';
}

$whereClause = $interviewRowId > 0 ? 'i.id = ?' : 'i.interview_id = ?';
$sql = sprintf(
    'SELECT %s
     FROM interviews i
     INNER JOIN applications a ON a.id = i.application_id
     INNER JOIN jobs j ON j.id = a.job_id
     LEFT JOIN users u ON u.id = a.user_id
     WHERE %s AND j.recruiter_id = ?
     LIMIT 1',
    implode(', ', $selectColumns),
    $whereClause
);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('SQL Error: ' . $conn->error);
}

if ($interviewRowId > 0) {
    $stmt->bind_param('ii', $interviewRowId, $recruiterId);
} else {
    $stmt->bind_param('si', $publicInterviewId, $recruiterId);
}
$stmt->execute();
$interview = db_fetch_one($stmt);
$stmt->close();

if (!$interview) {
    http_response_code(403);
    die('Interview not found or access denied.');
}

$meetingLink = trim((string) ($interview['meeting_link'] ?? ''));
$publicInterviewId = trim((string) ($interview['interview_id'] ?? $publicInterviewId));
if ($meetingLink === '' && $publicInterviewId !== '') {
    $meetingLink = 'https://meet.jit.si/' . rawurlencode($publicInterviewId);
}
if ($meetingLink === '') {
    http_response_code(500);
    die('Meeting link is unavailable.');
}

$scheduledAt = (string) ($interview['scheduled_at'] ?? '');
$countdownTarget = $scheduledAt !== '' ? date('c', strtotime($scheduledAt)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interviewer Panel | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<script>
    (function () {
        const stored = localStorage.getItem('skilltrust-theme');
        document.documentElement.classList.toggle('dark', stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
    }());
</script>

<div class="min-h-screen bg-[radial-gradient(ellipse_70%_48%_at_12%_-10%,rgba(99,102,241,0.14)_0%,transparent_58%),radial-gradient(ellipse_55%_38%_at_88%_110%,rgba(139,92,246,0.11)_0%,transparent_62%)] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="glass-card rounded-[30px] p-6 lg:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Recruiter Interview Panel</p>
                    <h1 class="mt-2 font-display text-3xl font-bold text-white"><?php echo e((string) ($interview['job_title'] ?? 'Interview')); ?></h1>
                    <p class="mt-2 text-sm text-slate-400">Candidate: <?php echo e((string) ($interview['candidate_name'] ?? 'Candidate')); ?></p>
                    <p class="mt-1 text-sm text-slate-500"><?php echo e((string) ($interview['candidate_email'] ?? '')); ?></p>
                </div>
                <div class="rounded-2xl border border-indigo-500/20 bg-indigo-500/10 px-4 py-3 text-right">
                    <div class="text-xs uppercase tracking-[0.18em] text-indigo-300">Starts In</div>
                    <div id="countdown" class="mt-1 font-display text-2xl font-bold text-white">--:--:--</div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_340px]">
            <div class="glass-card rounded-[30px] p-4 lg:p-5">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-xl font-bold text-white">Live Interview</h2>
                        <p class="mt-1 text-sm text-slate-400"><?php echo e(format_datetime_label($scheduledAt)); ?></p>
                    </div>
                    <a href="<?php echo e($meetingLink); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 px-4 py-3 text-sm font-semibold text-white">
                        <i data-lucide="external-link" class="h-4 w-4"></i>
                        <span>Open in New Tab</span>
                    </a>
                </div>
                <div class="overflow-hidden rounded-[24px] border border-slate-700/60 bg-slate-950/80">
                    <iframe
                        src="<?php echo e($meetingLink); ?>"
                        width="100%"
                        height="720"
                        allow="camera; microphone; fullscreen; display-capture"
                        class="min-h-[560px] w-full border-0"
                    ></iframe>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="glass-card rounded-[30px] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-300">Interview Details</p>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Interview ID</div>
                            <div class="mt-1 font-semibold text-white"><?php echo e($publicInterviewId !== '' ? $publicInterviewId : 'Private link'); ?></div>
                        </div>
                        <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Status</div>
                            <div class="mt-1 font-semibold text-white"><?php echo e(ucfirst((string) ($interview['status'] ?? 'scheduled'))); ?></div>
                        </div>
                        <div class="rounded-2xl border border-slate-700/50 bg-slate-900/40 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Notes</div>
                            <div class="mt-1 text-white"><?php echo e(trim((string) ($interview['notes'] ?? '')) !== '' ? (string) $interview['notes'] : 'No private notes added'); ?></div>
                        </div>
                    </div>
                </div>
                <a href="interview.php" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to Interviews
                </a>
            </aside>
        </section>
    </div>
</div>

<script>
    lucide.createIcons();
    const countdownNode = document.getElementById('countdown');
    const targetValue = '<?php echo e($countdownTarget); ?>';

    function renderCountdown() {
        if (!targetValue) {
            countdownNode.textContent = 'Scheduled';
            return;
        }
        const target = new Date(targetValue).getTime();
        const now = Date.now();
        const diff = target - now;
        if (diff <= 0) {
            countdownNode.textContent = 'Live now';
            return;
        }
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        countdownNode.textContent = String(hours).padStart(2, '0') + ':'
            + String(minutes).padStart(2, '0') + ':'
            + String(seconds).padStart(2, '0');
    }

    renderCountdown();
    setInterval(renderCountdown, 1000);
</script>
</body>
</html>
