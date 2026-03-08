<?php
/** API-Key testen - sendet eine minimale Anfrage an die Claude API */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("AI:SETTINGS") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$apiKey = trim($_POST['apiKey'] ?? '');
if (empty($apiKey)) finish(false, ["code" => "MISSING_KEY", "message" => "Kein API-Key angegeben."]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'claude-haiku-4-5-20251001',
        'max_tokens' => 16,
        'messages' => [
            ['role' => 'user', 'content' => 'Antworte nur mit: OK']
        ],
    ]),
]);

$raw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    finish(false, ["code" => "NETWORK_ERROR", "message" => "Verbindungsfehler: " . $curlError]);
}

if ($httpCode === 401) {
    finish(false, ["code" => "INVALID_KEY", "message" => "API-Key ist ungueltig oder abgelaufen."]);
}

if ($httpCode === 403) {
    finish(false, ["code" => "FORBIDDEN", "message" => "API-Key hat keine Berechtigung fuer dieses Modell."]);
}

if ($httpCode === 429) {
    finish(false, ["code" => "RATE_LIMITED", "message" => "Rate-Limit erreicht. Bitte spaeter erneut versuchen."]);
}

if ($httpCode !== 200) {
    $data = json_decode($raw, true);
    $msg = $data['error']['message'] ?? "HTTP $httpCode";
    finish(false, ["code" => "API_ERROR", "message" => $msg]);
}

$data = json_decode($raw, true);
$text = '';
foreach (($data['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
}

finish(true, null, [
    'status' => 'valid',
    'model' => $data['model'] ?? 'unknown',
    'response' => $text,
]);
