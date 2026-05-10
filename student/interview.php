<?php
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$interviewId = (int) ($_GET['id'] ?? 0);
$requestedMeetingLink = trim((string) ($_GET['link'] ?? ''));

if ($interviewId <= 0 && $requestedMeetingLink === '') {
    http_response_code(400);
    die('Invalid interview request.');
}

$scheduledColumn = db_column_exists($conn, 'interviews', 'interview_datetime') ? 'interview_datetime' : 'scheduled_at';
$meetingLinkColumn = db_column_exists($conn, 'interviews', 'meeting_link') ? 'meeting_link' : '';
$publicIdColumn = db_column_exists($conn, 'interviews', 'interview_id') ? 'interview_id' : '';

$selectColumns = [
    'i.id',
    'i.status',
    'i.' . $scheduledColumn . ' AS scheduled_at',
    'j.title AS job_title',
    'u.name AS candidate_name',
];
if ($meetingLinkColumn !== '') {
    $selectColumns[] = 'i.meeting_link';
}
if ($publicIdColumn !== '') {
    $selectColumns[] = 'i.interview_id';
}

$interviewSql = sprintf(
    'SELECT %s
     FROM interviews i
     INNER JOIN applications a ON a.id = i.application_id
     INNER JOIN jobs j ON j.id = a.job_id
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.user_id = ? %s
     LIMIT 1',
    implode(', ', $selectColumns),
    $interviewId > 0 ? 'AND i.id = ?' : ($requestedMeetingLink !== '' && $meetingLinkColumn !== '' ? 'AND i.meeting_link = ?' : '')
);

$interviewStmt = $conn->prepare($interviewSql);
if (!$interviewStmt) {
    die('SQL Error: ' . $conn->error);
}

if ($interviewId > 0) {
    $interviewStmt->bind_param('ii', $userId, $interviewId);
} else {
    $interviewStmt->bind_param('is', $userId, $requestedMeetingLink);
}
$interviewStmt->execute();
$interview = db_fetch_one($interviewStmt);
$interviewStmt->close();

if (!$interview) {
    http_response_code(403);
    die('Unauthorized access to this interview.');
}

$meetingLink = trim((string) ($interview['meeting_link'] ?? ''));
$publicInterviewId = trim((string) ($interview['interview_id'] ?? ''));

if ($meetingLink === '' && $publicInterviewId !== '') {
    $meetingLink = 'https://meet.jit.si/' . rawurlencode($publicInterviewId);
}

if ($meetingLink === '') {
    http_response_code(500);
    die('Meeting link is unavailable for this interview.');
}

$scheduledAt = (string) ($interview['scheduled_at'] ?? '');
$countdownTarget = $scheduledAt !== '' ? date('c', strtotime($scheduledAt)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Room | SkillTrust</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="../assets/js/student/tailwind-theme.js"></script>
    <link rel="stylesheet" href="../assets/css/student/dashboard.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300">
<script>
    const storedTheme = localStorage.getItem('skilltrust-theme');
    document.documentElement.classList.toggle('dark', storedTheme ? storedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
</script>

<div class="min-h-screen bg-[radial-gradient(ellipse_70%_48%_at_12%_-10%,rgba(99,102,241,0.14)_0%,transparent_58%),radial-gradient(ellipse_55%_38%_at_88%_110%,rgba(139,92,246,0.11)_0%,transparent_62%)] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="glass-card rounded-[30px] p-6 lg:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-400">Interview Room</p>
                    <h1 class="mt-2 font-display text-3xl font-bold text-white"><?php echo e((string) ($interview['job_title'] ?? 'Interview')); ?></h1>
                    <p class="mt-2 text-sm text-slate-400">Candidate: <?php echo e((string) ($interview['candidate_name'] ?? 'Student')); ?></p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700/70 bg-slate-900/75 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-brand-500/30 hover:text-white" data-theme-toggle>
                    <i data-lucide="moon-star" class="w-4 h-4" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <a href="dashboard.php" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700/70 bg-slate-900/75 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-brand-500/30 hover:bg-slate-800 hover:text-white">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        <span>Back to Dashboard</span>
                    </a>
                    <div class="rounded-2xl border border-brand-500/20 bg-brand-500/10 px-4 py-3 text-right">
                        <div class="text-xs uppercase tracking-[0.18em] text-brand-300">Starts In</div>
                        <div id="countdown" class="mt-1 font-display text-2xl font-bold text-white">--:--:--</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass-card rounded-[30px] p-4 lg:p-5">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-xl font-bold text-white">Live Interview</h2>
                    <p class="mt-1 text-sm text-slate-400"><?php echo e(format_datetime_label($scheduledAt)); ?></p>
                </div>
                <a href="<?php echo e($meetingLink); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand-500 to-violet-600 px-4 py-3 text-sm font-semibold text-white">
                    <i data-lucide="external-link" class="h-4 w-4"></i>
                    <span>Open in New Tab</span>
                </a>
            </div>

            <div class="overflow-hidden rounded-[24px] border border-slate-700/60 bg-slate-950/80">
                <iframe
                    src="<?php echo e($meetingLink); ?>"
                    width="100%"
                    height="680"
                    allow="camera; microphone; fullscreen; display-capture"
                    class="min-h-[520px] w-full border-0"
                ></iframe>
            </div>
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

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        countdownNode.textContent = String(hours).padStart(2, '0') + ':'
            + String(minutes).padStart(2, '0') + ':'
            + String(seconds).padStart(2, '0');
    }

    renderCountdown();
    setInterval(renderCountdown, 1000);
</script>
</body>
</html>
