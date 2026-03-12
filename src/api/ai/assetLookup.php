<?php
/**
 * AI Asset Lookup API - Produktdaten per KI suchen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/AiAssetLookupService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';
require_once __DIR__ . '/../../services/RateLimitService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:CREATE")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    // Rate Limiting: max 20 AI-Anfragen pro Stunde
    $rateLimiter = new RateLimitService($DBLIB);
    if (!$rateLimiter->check('ai_lookup_' . $AUTH->data['users_userid'], 20, 3600)) {
        finish(false, ["code" => "RATE_LIMIT", "message" => "Maximale KI-Anfragen pro Stunde erreicht"]);
    }

    $service = new AiAssetLookupService($DBLIB);
    $productName = InputValidationService::string($_POST['product_name'] ?? '', 2, 200);
    $manufacturer = InputValidationService::string($_POST['manufacturer'] ?? '', 0, 100);

    $result = $service->lookup($productName, $manufacturer);
    finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error'] ?? 'Fehler'], $result);
}, 'AI Asset Lookup');
