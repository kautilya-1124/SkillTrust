<?php
declare(strict_types=1);

if (!function_exists('has_column')) {
    function has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $colSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($tableSafe === '' || $colSafe === '') {
            return false;
        }
        $sql = "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'";
        $res = $conn->query($sql);
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('normalize_answer')) {
    function normalize_answer(string $answer): ?string
    {
        $v = strtolower(trim($answer));
        if (in_array($v, ['a', 'b', 'c', 'd'], true)) {
            return $v;
        }
        if ($v === 'option_a') { return 'a'; }
        if ($v === 'option_b') { return 'b'; }
        if ($v === 'option_c') { return 'c'; }
        if ($v === 'option_d') { return 'd'; }
        return null;
    }
}
