<?php
/**
 * Stripe/PayPal Payment Links API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/StripePaymentService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new StripePaymentService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

    switch ($action) {
        case 'create_stripe':
            $documentId = InputValidationService::positiveInt($_POST['document_id'] ?? 0);
            $amount = InputValidationService::float($_POST['amount'] ?? 0);
            $description = InputValidationService::string($_POST['description'] ?? '', 1, 200);
            $email = filter_var($_POST['customer_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
            $result = $service->createStripeCheckout($documentId, $amount, $description, $email);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'create_paypal':
            $documentId = InputValidationService::positiveInt($_POST['document_id'] ?? 0);
            $amount = InputValidationService::float($_POST['amount'] ?? 0);
            $description = InputValidationService::string($_POST['description'] ?? '', 1, 200);
            $result = $service->createPayPalLink($documentId, $amount, $description);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'status':
            $sessionId = InputValidationService::string($_POST['session_id'] ?? '', 1, 200);
            $provider = InputValidationService::enum($_POST['provider'] ?? 'stripe', ['stripe', 'paypal']);
            $result = $service->checkPaymentStatus($sessionId, $provider);
            finish(true, null, $result);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Payments');
