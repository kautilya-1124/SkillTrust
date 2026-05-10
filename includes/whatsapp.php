<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

if (!function_exists('skilltrust_format_whatsapp_number')) {
    function skilltrust_format_whatsapp_number(string $phone, string $defaultCountryCode = '91'): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        $defaultCountryCode = preg_replace('/\D+/', '', $defaultCountryCode) ?: '91';
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        if (!str_starts_with($digits, $defaultCountryCode)) {
            $digits = $defaultCountryCode . $digits;
        }

        return '+' . $digits;
    }
}

if (!function_exists('skilltrust_whatsapp_config')) {
    function skilltrust_whatsapp_config(): array
    {
        skilltrust_env_load(dirname(__DIR__));

        return [
            'token' => (string) (skilltrust_env_get('ULTRAMSG_TOKEN', '') ?? ''),
            'instance' => (string) (skilltrust_env_get('ULTRAMSG_INSTANCE', '') ?? ''),
            'base_url' => rtrim((string) (skilltrust_env_get('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com') ?? 'https://api.ultramsg.com'), '/'),
            'default_country_code' => (string) (skilltrust_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE', '91') ?? '91'),
            'enabled' => strtolower((string) (skilltrust_env_get('WHATSAPP_NOTIFICATIONS_ENABLED', '1') ?? '1')) !== '0',
            'timeout' => max(5, (int) (skilltrust_env_get('ULTRAMSG_TIMEOUT', '30') ?? '30')),
        ];
    }
}

if (!function_exists('skilltrust_log_whatsapp_result')) {
    function skilltrust_log_whatsapp_result(?mysqli $conn, array $result, array $options = []): void
    {
        $summary = sprintf(
            '[SkillTrust WhatsApp] %s | to=%s | context=%s | related=%s | status=%s | error=%s',
            $result['success'] ? 'sent' : 'failed',
            (string) ($result['to'] ?? ''),
            (string) ($options['context_type'] ?? 'general'),
            (string) ($options['related_id'] ?? ''),
            (string) ($result['status_code'] ?? 0),
            (string) ($result['error'] ?? '')
        );
        error_log($summary);

        if (!$conn || !function_exists('db_table_exists') || !db_table_exists($conn, 'whatsapp_notification_logs')) {
            return;
        }

        $responseJson = null;
        if (isset($result['response'])) {
            $responseJson = json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $status = $result['success'] ? 'sent' : 'failed';
        $providerMessageId = (string) (
            $result['response']['id']
            ?? $result['response']['message_id']
            ?? $result['response']['data']['id']
            ?? ''
        );
        $errorMessage = (string) ($result['error'] ?? '');
        $contextType = substr((string) ($options['context_type'] ?? 'general'), 0, 80);
        $relatedId = (string) ($options['related_id'] ?? '');
        $messageBody = (string) ($result['body'] ?? '');
        $recipientPhone = (string) ($result['to'] ?? '');
        $provider = 'ultramsg';
        $sentAt = $result['success'] ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare(
            'INSERT INTO whatsapp_notification_logs
                (recipient_phone, message_body, status, error_message, provider, provider_message_id, context_type, related_id, response_json, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'ssssssssss',
            $recipientPhone,
            $messageBody,
            $status,
            $errorMessage,
            $provider,
            $providerMessageId,
            $contextType,
            $relatedId,
            $responseJson,
            $sentAt
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('sendWhatsApp')) {
    function sendWhatsApp(string $phone, string $message, array $options = []): array
    {
        $config = skilltrust_whatsapp_config();
        $formattedPhone = skilltrust_format_whatsapp_number($phone, (string) $config['default_country_code']);
        $debug = !empty($options['debug']);

        if (!$config['enabled']) {
            $result = [
                'success' => false,
                'status_code' => 503,
                'to' => $formattedPhone ?? '',
                'body' => $message,
                'response' => null,
                'error' => 'WhatsApp notifications are disabled.',
            ];
            skilltrust_log_whatsapp_result($options['db'] ?? null, $result, $options);
            return $result;
        }

        if ($formattedPhone === null) {
            $result = [
                'success' => false,
                'status_code' => 422,
                'to' => '',
                'body' => $message,
                'response' => null,
                'error' => 'Invalid phone number.',
            ];
            skilltrust_log_whatsapp_result($options['db'] ?? null, $result, $options);
            return $result;
        }

        if ($config['token'] === '' || $config['instance'] === '') {
            $result = [
                'success' => false,
                'status_code' => 500,
                'to' => $formattedPhone,
                'body' => $message,
                'response' => null,
                'error' => 'UltraMsg credentials are not configured.',
            ];
            skilltrust_log_whatsapp_result($options['db'] ?? null, $result, $options);
            return $result;
        }

        $params = [
            'token' => $config['token'],
            'to' => $formattedPhone,
            'body' => $message,
            'priority' => (string) ($options['priority'] ?? '1'),
        ];

        if (!empty($options['reference'])) {
            $params['referenceId'] = (string) $options['reference'];
        }

        $ch = curl_init();
        // SSL: use cacert.pem bundle if configured, otherwise fall back to
        // the env flag CURL_SSL_VERIFY (set to 0 for local dev ONLY).
        $caBundle  = (string) (skilltrust_env_get('CURL_CAINFO', '') ?? '');
        $sslVerify = strtolower((string) (skilltrust_env_get('CURL_SSL_VERIFY', '1') ?? '1')) !== '0';

        $curlOpts = [
            CURLOPT_URL              => $config['base_url'] . '/' . $config['instance'] . '/messages/chat',
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => http_build_query($params),
            CURLOPT_CONNECTTIMEOUT   => 10,
            CURLOPT_TIMEOUT          => (int) $config['timeout'],
            CURLOPT_HTTPHEADER       => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER   => $sslVerify,
            CURLOPT_SSL_VERIFYHOST   => $sslVerify ? 2 : 0,
        ];

        if ($caBundle !== '' && file_exists($caBundle)) {
            $curlOpts[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $curlOpts);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = is_string($rawResponse) && $rawResponse !== ''
            ? json_decode($rawResponse, true)
            : null;
        if (!is_array($decodedResponse)) {
            $decodedResponse = ['raw' => $rawResponse];
        }

       // ✅ FIX — handles both string "true" and boolean true from UltraMsg
$sentValue = $decodedResponse['sent'] ?? null;
$isSent    = ($sentValue === true || $sentValue === 'true');

$apiError = '';
if ($curlError !== '') {
    $apiError = $curlError;
} elseif ($statusCode >= 400) {
    $apiError = (string) ($decodedResponse['error'] ?? $decodedResponse['message'] ?? 'UltraMsg request failed.');
} elseif (!$isSent && isset($decodedResponse['sent'])) {
    $apiError = (string) ($decodedResponse['message'] ?? 'UltraMsg did not confirm delivery.');
}

$result = [
    'success' => $curlError === '' && $statusCode < 400 && $isSent,
            'status_code' => $statusCode,
            'to' => $formattedPhone,
            'body' => $message,
            'response' => $decodedResponse,
            'error' => $apiError !== '' ? $apiError : null,
        ];

        if ($debug) {
            $result['request_url'] = $config['base_url'] . '/' . $config['instance'] . '/messages/chat';
            $result['raw_response'] = is_string($rawResponse) ? $rawResponse : '';
        }

        skilltrust_log_whatsapp_result($options['db'] ?? null, $result, $options);

        return $result;
    }
}

if (!function_exists('sendWhatsAppJsonResponse')) {
    function sendWhatsAppJsonResponse(array $result): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($result['success'] ? 200 : max(400, (int) ($result['status_code'] ?? 500)));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}