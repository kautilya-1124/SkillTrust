<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!function_exists('skilltrust_coding_supported_languages')) {
    function skilltrust_coding_supported_languages(): array
    {
        return [
            'c' => [
                'label' => 'C (GCC 9.2.0)',
                'judge0_language_id' => 50,
                'starter_code' => "#include <stdio.h>\n\nint main(void) {\n    // Write your solution here\n    return 0;\n}\n",
            ],
            'cpp' => [
                'label' => 'C++ (G++ 9.2.0)',
                'judge0_language_id' => 54,
                'starter_code' => "#include <bits/stdc++.h>\nusing namespace std;\n\nint main() {\n    // Write your solution here\n    return 0;\n}\n",
            ],
            'java' => [
                'label' => 'Java (OpenJDK 13)',
                'judge0_language_id' => 62,
                'starter_code' => "import java.util.*;\n\npublic class Main {\n    public static void main(String[] args) {\n        // Write your solution here\n    }\n}\n",
            ],
            'python' => [
                'label' => 'Python (3.8.1)',
                'judge0_language_id' => 71,
                'starter_code' => "def solve():\n    pass\n\nif __name__ == '__main__':\n    solve()\n",
            ],
        ];
    }
}

if (!function_exists('skilltrust_judge0_config')) {
    function skilltrust_judge0_config(): array
    {
        $baseUrl = rtrim((string) skilltrust_env_get('JUDGE0_API_BASE_URL', 'https://judge0-ce.p.rapidapi.com'), '/');
        $apiKey = (string) skilltrust_env_get('JUDGE0_API_KEY', '');
        $rapidHost = (string) skilltrust_env_get('JUDGE0_RAPIDAPI_HOST', parse_url($baseUrl, PHP_URL_HOST) ?: 'judge0-ce.p.rapidapi.com');

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'rapidapi_host' => $rapidHost,
        ];
    }
}

if (!function_exists('skilltrust_judge0_is_configured')) {
    function skilltrust_judge0_is_configured(): bool
    {
        $config = skilltrust_judge0_config();
        return $config['api_key'] !== '' && $config['base_url'] !== '';
    }
}

if (!function_exists('skilltrust_judge0_request')) {
    function skilltrust_judge0_request(string $method, string $path, ?array $payload = null): array
    {
        $config = skilltrust_judge0_config();
        if (!skilltrust_judge0_is_configured()) {
            throw new RuntimeException('Judge0 API is not configured. Set JUDGE0_API_KEY in your .env file.');
        }

        $url = $config['base_url'] . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize Judge0 request.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-RapidAPI-Key: ' . $config['api_key'],
            'X-RapidAPI-Host: ' . $config['rapidapi_host'],
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        // Fix: Windows/WAMP needs the CA bundle path for HTTPS requests.
        // Read from CURL_CAINFO in .env (e.g. C:/wamp64/bin/php/cacert.pem).
        $caCert = (string) skilltrust_env_get('CURL_CAINFO', '');
        if ($caCert !== '' && file_exists($caCert)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caCert);
        }

        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                curl_close($ch);
                throw new RuntimeException('Failed to encode Judge0 payload.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Judge0 request failed: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) && $response !== 'null') {
            throw new RuntimeException('Judge0 returned an invalid response.');
        }

        if ($statusCode >= 400) {
            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error'] ?? 'Judge0 request failed.')
                : 'Judge0 request failed.';
            throw new RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('skilltrust_coding_normalize_output')) {
    function skilltrust_coding_normalize_output(?string $output): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", (string) ($output ?? ''));
        return rtrim($normalized);
    }
}

if (!function_exists('skilltrust_coding_allowed_languages')) {
    function skilltrust_coding_allowed_languages(?string $allowedJson): array
    {
        $all = skilltrust_coding_supported_languages();
        if ($allowedJson === null || trim($allowedJson) === '') {
            return $all;
        }

        $decoded = json_decode($allowedJson, true);
        if (!is_array($decoded) || $decoded === []) {
            return $all;
        }

        $filtered = [];
        foreach ($decoded as $key) {
            $langKey = strtolower(trim((string) $key));
            if (isset($all[$langKey])) {
                $filtered[$langKey] = $all[$langKey];
            }
        }

        return $filtered !== [] ? $filtered : $all;
    }
}

if (!function_exists('skilltrust_coding_run_against_cases')) {
    function skilltrust_coding_run_against_cases(string $sourceCode, string $languageKey, array $testCases, ?float $timeLimitSeconds = null, ?int $memoryLimitKb = null): array
    {
        $languages = skilltrust_coding_supported_languages();
        if (!isset($languages[$languageKey])) {
            throw new RuntimeException('Unsupported programming language selected.');
        }
        if ($testCases === []) {
            throw new RuntimeException('No coding test cases are configured for this question.');
        }

        $submissions = [];
        foreach ($testCases as $testCase) {
            $submissions[] = [
                'language_id' => $languages[$languageKey]['judge0_language_id'],
                'source_code' => base64_encode($sourceCode),
                'stdin' => base64_encode((string) ($testCase['input_data'] ?? '')),
                'expected_output' => base64_encode((string) ($testCase['expected_output'] ?? '')),
                'base64_encoded' => true,
                'cpu_time_limit' => $timeLimitSeconds ?? 2.0,
                'memory_limit' => $memoryLimitKb ?? 131072,
            ];
        }

        $createResponse = skilltrust_judge0_request('POST', '/submissions/batch?base64_encoded=true', ['submissions' => $submissions]);
        $tokens = [];
        foreach ($createResponse as $row) {
            if (is_array($row) && !empty($row['token'])) {
                $tokens[] = (string) $row['token'];
            }
        }
        if (count($tokens) !== count($testCases)) {
            throw new RuntimeException('Judge0 did not accept all test-case submissions.');
        }

        $attempts = 0;
        $results = [];
        while ($attempts < 12) {
            usleep(700000);
            $attempts++;
            $fetchResponse = skilltrust_judge0_request('GET', '/submissions/batch?tokens=' . urlencode(implode(',', $tokens)) . '&base64_encoded=true&fields=token,stdout,stderr,compile_output,message,status,time,memory');
            $results = is_array($fetchResponse['submissions'] ?? null) ? $fetchResponse['submissions'] : [];
            if (count($results) !== count($tokens)) {
                continue;
            }

            $pending = false;
            foreach ($results as $result) {
                $statusId = (int) ($result['status']['id'] ?? 0);
                if ($statusId <= 2) {
                    $pending = true;
                    break;
                }
            }
            if (!$pending) {
                break;
            }
        }

        if (count($results) !== count($tokens)) {
            throw new RuntimeException('Judge0 did not return all execution results in time.');
        }

        $evaluated = [];
        $passed = 0;
        $maxTime = 0.0;
        $maxMemory = 0;

        foreach ($results as $index => $result) {
            $actualOutput = isset($result['stdout']) ? base64_decode((string) $result['stdout'], true) : '';
            $stderr = isset($result['stderr']) ? base64_decode((string) $result['stderr'], true) : '';
            $compileOutput = isset($result['compile_output']) ? base64_decode((string) $result['compile_output'], true) : '';
            $message = isset($result['message']) ? base64_decode((string) $result['message'], true) : '';
            $actualOutput = $actualOutput === false ? '' : $actualOutput;
            $stderr = $stderr === false ? '' : $stderr;
            $compileOutput = $compileOutput === false ? '' : $compileOutput;
            $message = $message === false ? '' : $message;

            $expectedOutput = (string) ($testCases[$index]['expected_output'] ?? '');
            $statusLabel = (string) ($result['status']['description'] ?? 'Unknown');
            $statusId = (int) ($result['status']['id'] ?? 0);
            $isPassed = $statusId === 3
                && skilltrust_coding_normalize_output($actualOutput) === skilltrust_coding_normalize_output($expectedOutput);

            if ($isPassed) {
                $passed++;
            }

            $timeValue = isset($result['time']) ? (float) $result['time'] : 0.0;
            $memoryValue = isset($result['memory']) ? (int) $result['memory'] : 0;
            $maxTime = max($maxTime, $timeValue);
            $maxMemory = max($maxMemory, $memoryValue);

            $evaluated[] = [
                'token' => (string) ($result['token'] ?? ''),
                'status_id' => $statusId,
                'status_label' => $statusLabel,
                'stdin' => (string) ($testCases[$index]['input_data'] ?? ''),
                'expected_output' => $expectedOutput,
                'actual_output' => (string) $actualOutput,
                'stderr' => (string) $stderr,
                'compile_output' => (string) $compileOutput,
                'message' => (string) $message,
                'time' => $timeValue,
                'memory' => $memoryValue,
                'passed' => $isPassed,
            ];
        }

        $total = count($evaluated);
        $score = $total > 0 ? round(($passed / $total) * 100, 2) : 0.0;

        return [
            'passed_test_cases' => $passed,
            'total_test_cases' => $total,
            'score' => $score,
            'result_status' => $passed === $total ? 'passed' : 'failed',
            'judge0_status' => $evaluated !== [] ? (string) ($evaluated[0]['status_label'] ?? '') : '',
            'execution_time' => $maxTime,
            'memory_usage_kb' => $maxMemory,
            'case_results' => $evaluated,
        ];
    }
}
