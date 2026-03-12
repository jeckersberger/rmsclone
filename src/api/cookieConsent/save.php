<?php
require_once __DIR__ . '/../apiHeadSecure.php';

$categories = isset($_POST['categories']) ? $_POST['categories'] : 'necessary';

// Validieren
$allowed = ['necessary', 'analytics', 'marketing'];
$cats = array_filter(explode(',', $categories), function($c) use ($allowed) {
    return in_array(trim($c), $allowed);
});
if (empty($cats)) $cats = ['necessary'];

require_once __DIR__ . '/../../services/CookieConsentService.php';
$svc = new CookieConsentService($DBLIB);

$instanceId = $AUTH->data['instance']['instances_id'];
$sessionId = session_id();
$userId = $AUTH->data['users_userid'] ?? null;

$svc->saveConsent($instanceId, $sessionId, $cats, $userId);

finish(true, null, ['categories' => $cats]);
