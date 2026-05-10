<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/whatsapp.php';

if (!function_exists('skilltrust_schedule_interview')) {
    function skilltrust_schedule_interview(mysqli $conn, array $payload): array
    {
        $applicationId = (int) ($payload['application_id'] ?? 0);
        $recruiterId = (int) ($payload['recruiter_id'] ?? 0);
        $interviewDate = trim((string) ($payload['interview_date'] ?? ''));
        $interviewTime = trim((string) ($payload['interview_time'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($applicationId <= 0 || $recruiterId <= 0) {
            return ['success' => false, 'message' => 'Invalid interview request.'];
        }

        if ($interviewDate === '' || $interviewTime === '') {
            return ['success' => false, 'message' => 'Interview date and time are required.'];
        }

        $dateTime = date_create_from_format('Y-m-d H:i', $interviewDate . ' ' . $interviewTime);
        if (!$dateTime) {
            return ['success' => false, 'message' => 'Please enter a valid date and time.'];
        }

        if ($dateTime->getTimestamp() < (time() - 300)) {
            return ['success' => false, 'message' => 'Interview time must be in the future.'];
        }

        $hasUserPhoneColumn = db_column_exists($conn, 'users', 'phone');
        $candidateSql = '
            SELECT
                a.id AS application_id,
                a.user_id,
                a.status AS application_status,
                j.id AS job_id,
                j.title AS job_title,
                u.name AS candidate_name,
                u.email AS candidate_email'
            . ($hasUserPhoneColumn ? ', u.phone AS candidate_phone' : '') . '
            FROM applications a
            INNER JOIN jobs j ON j.id = a.job_id
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.id = ? AND j.recruiter_id = ?
            LIMIT 1';

        $candidateStmt = $conn->prepare($candidateSql);
        if (!$candidateStmt) {
            return ['success' => false, 'message' => 'SQL Error: ' . $conn->error];
        }

        $candidateStmt->bind_param('ii', $applicationId, $recruiterId);
        $candidateStmt->execute();
        $candidate = db_fetch_one($candidateStmt);
        $candidateStmt->close();

        if (!$candidate) {
            return ['success' => false, 'message' => 'Application not found or access denied.'];
        }

        // The Jitsi room is created here so callers do not need to pass a meeting link.
        $interviewPublicId = 'INT_' . strtoupper(str_replace('.', '', uniqid('', true)));
        $meetingLink = 'https://meet.jit.si/' . rawurlencode($interviewPublicId);
        $scheduledAt = $dateTime->format('Y-m-d H:i:s');

        $columns = ['application_id'];
        $placeholders = ['?'];
        $types = 'i';
        $values = [$applicationId];

        if (db_column_exists($conn, 'interviews', 'recruiter_id')) {
            $columns[] = 'recruiter_id';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $recruiterId;
        }
        if (db_column_exists($conn, 'interviews', 'interview_id')) {
            $columns[] = 'interview_id';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $interviewPublicId;
        }
        if (db_column_exists($conn, 'interviews', 'meeting_link')) {
            $columns[] = 'meeting_link';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $meetingLink;
        }
        if (db_column_exists($conn, 'interviews', 'interview_datetime')) {
            $columns[] = 'interview_datetime';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $scheduledAt;
        }
        if (db_column_exists($conn, 'interviews', 'scheduled_at')) {
            $columns[] = 'scheduled_at';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $scheduledAt;
        }
        if (db_column_exists($conn, 'interviews', 'notes')) {
            $columns[] = 'notes';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $notes;
        }
        if (db_column_exists($conn, 'interviews', 'status')) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = 'scheduled';
        }

        $insertSql = sprintf(
            'INSERT INTO interviews (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            return ['success' => false, 'message' => 'SQL Error: ' . $conn->error];
        }

        $insertStmt->bind_param($types, ...$values);

        try {
            $insertStmt->execute();
            $interviewRowId = (int) $conn->insert_id;
            $insertStmt->close();
        } catch (Throwable $exception) {
            $insertStmt->close();
            return ['success' => false, 'message' => 'Unable to schedule interview right now.'];
        }

        $notification = null;
        $candidatePhone = trim((string) ($candidate['candidate_phone'] ?? ''));
        if ($candidatePhone !== '') {
            $candidateName = trim((string) ($candidate['candidate_name'] ?? 'Candidate'));
            $jobTitle = trim((string) ($candidate['job_title'] ?? 'your application'));
            // Edit this block to customize the WhatsApp message sent after scheduling.
            $notificationMessage = "Hello {$candidateName},\n\n"
                . "Your interview has been scheduled for {$jobTitle}.\n"
                . 'Date: ' . $dateTime->format('d M Y') . "\n"
                . 'Time: ' . $dateTime->format('h:i A') . "\n"
                . "Meeting Link: {$meetingLink}\n\n"
                . "- SkillTrust";

            $notification = sendWhatsApp($candidatePhone, $notificationMessage, [
                'db' => $conn,
                'context_type' => 'interview_scheduled',
                'related_id' => (string) $interviewRowId,
                'reference' => $interviewPublicId,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Interview scheduled successfully.',
            'data' => [
                'id' => $interviewRowId,
                'application_id' => $applicationId,
                'interview_id' => $interviewPublicId,
                'meeting_link' => $meetingLink,
                'interview_datetime' => $scheduledAt,
                'candidate_name' => (string) ($candidate['candidate_name'] ?? ''),
                'candidate_email' => (string) ($candidate['candidate_email'] ?? ''),
                'candidate_phone' => (string) ($candidate['candidate_phone'] ?? ''),
                'job_title' => (string) ($candidate['job_title'] ?? ''),
                'whatsapp_notification' => $notification,
            ],
        ];
    }
}
