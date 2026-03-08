<?php
/**
 * Customer Portal API - Self-Service Zugang
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/CustomerPortalService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

$portalService = new CustomerPortalService($DBLIB);

// Token-basierter Zugriff (Kunden)
if (isset($_POST['portal_token'])) {
    ErrorHandlerService::wrap(function () use ($DBLIB, $portalService) {
        $token = $_POST['portal_token'];
        $clientId = $portalService->validateToken($token);
        if (!$clientId) {
            finish(false, ["code" => "INVALID_TOKEN", "message" => "Ungueltiger oder abgelaufener Zugang"]);
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'projects':
                finish(true, null, ['projects' => $portalService->getClientProjects($clientId)]);
                break;
            case 'invoices':
                finish(true, null, ['invoices' => $portalService->getClientInvoices($clientId)]);
                break;
            case 'quotes':
                finish(true, null, ['quotes' => $portalService->getClientQuotes($clientId)]);
                break;
            case 'accept_quote':
                $docId = intval($_POST['document_id'] ?? 0);
                $result = $portalService->acceptQuote($docId, $clientId);
                finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']]);
                break;
            case 'feedback':
                $projectId = intval($_POST['project_id'] ?? 0);
                $rating = intval($_POST['rating'] ?? 0);
                $comment = $_POST['comment'] ?? '';
                $result = $portalService->submitFeedback($projectId, $clientId, $rating, $comment);
                finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
                break;
            case 'update_contact':
                $result = $portalService->updateContactInfo($clientId, $_POST);
                finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error'] ?? '']);
                break;
            default:
                finish(false, ["code" => "INVALID_ACTION"]);
        }
    }, 'Customer Portal');
    exit;
}

// Admin-Zugriff: Token generieren
require_once __DIR__ . '/../apiHeadSecure.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH, $portalService) {
    if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) {
        finish(false, ["code" => "FORBIDDEN"]);
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'generate_token') {
        $clientId = intval($_POST['client_id'] ?? 0);
        $result = $portalService->generateAccessToken($clientId);
        finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
    }

    finish(false, ["code" => "INVALID_ACTION"]);
}, 'Customer Portal Admin');
