<?php
declare(strict_types=1);

if (!function_exists('skilltrust_test_attempts_ready')) {
    function skilltrust_test_attempts_ready(mysqli $conn): bool
    {
        if (!db_table_exists($conn, 'test_attempts')) {
            return false;
        }

        $requiredColumns = [
            'id',
            'user_id',
            'test_id',
            'attempts',
            'max_attempts',
            'is_blocked',
            'admin_unlocked',
            'last_attempt_at',
        ];

        foreach ($requiredColumns as $column) {
            if (!db_column_exists($conn, 'test_attempts', $column)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('skilltrust_test_attempts_get')) {
    function skilltrust_test_attempts_get(mysqli $conn, int $userId, int $testId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT id, user_id, test_id, attempts, max_attempts, is_blocked, admin_unlocked, last_attempt_at
             FROM test_attempts
             WHERE user_id = ? AND test_id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to load test attempt status.');
        }

        $stmt->bind_param('ii', $userId, $testId);
        $stmt->execute();
        $row = db_fetch_one($stmt);
        $stmt->close();

        return $row ? skilltrust_test_attempts_normalize_row($row) : null;
    }
}

if (!function_exists('skilltrust_test_attempts_get_or_create')) {
    function skilltrust_test_attempts_get_or_create(mysqli $conn, int $userId, int $testId, int $maxAttempts = 3): array
    {
        $insert = $conn->prepare(
            'INSERT INTO test_attempts (user_id, test_id, attempts, max_attempts, is_blocked, admin_unlocked, last_attempt_at)
             VALUES (?, ?, 0, ?, 0, 0, NULL)
             ON DUPLICATE KEY UPDATE id = id'
        );
        if (!$insert) {
            throw new RuntimeException('Unable to initialize test attempt record.');
        }

        $insert->bind_param('iii', $userId, $testId, $maxAttempts);
        if (!$insert->execute()) {
            $insert->close();
            throw new RuntimeException('Unable to initialize test attempt record.');
        }
        $insert->close();

        $record = skilltrust_test_attempts_get($conn, $userId, $testId);
        if ($record === null) {
            throw new RuntimeException('Test attempt record could not be loaded.');
        }

        return $record;
    }
}

if (!function_exists('skilltrust_test_attempts_can_start')) {
    function skilltrust_test_attempts_can_start(array $record): array
    {
        $attempts = (int) ($record['attempts'] ?? 0);
        $maxAttempts = (int) ($record['max_attempts'] ?? 3);
        $isBlocked = (int) ($record['is_blocked'] ?? 0) === 1;
        $adminUnlocked = (int) ($record['admin_unlocked'] ?? 0) === 1;

        if (($attempts >= $maxAttempts || $isBlocked) && !$adminUnlocked) {
            return [
                'allowed' => false,
                'message' => 'Test locked. You have reached the maximum number of attempts. Please contact admin for unlock access.',
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
        ];
    }
}

if (!function_exists('skilltrust_test_attempts_normalize_row')) {
    function skilltrust_test_attempts_normalize_row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'test_id' => (int) ($row['test_id'] ?? 0),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'max_attempts' => (int) ($row['max_attempts'] ?? 3),
            'is_blocked' => (int) ($row['is_blocked'] ?? 0),
            'admin_unlocked' => (int) ($row['admin_unlocked'] ?? 0),
            'last_attempt_at' => (string) ($row['last_attempt_at'] ?? ''),
        ];
    }
}
