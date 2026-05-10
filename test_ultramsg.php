<?php

echo "<h2>UltraMsg Test 🚀</h2>";

$params = [
    'token' => 'bzuprwsrk94662zv',
    'to' => '+918957038424', // ✅ MUST include +91
    'body' => 'Test message from SkillTrust ✅',
    'priority' => '1'
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.ultramsg.com/instance171555/messages/chat",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),

    // SSL fix (local only)
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<pre>";

if ($error) {
    echo "❌ cURL ERROR:\n" . $error;
} else {
    echo "✅ HTTP STATUS: $httpCode\n\n";

    echo "📦 RAW RESPONSE:\n";
    echo $response . "\n\n";

    echo "🧠 DECODED RESPONSE:\n";
    print_r(json_decode($response, true));
}