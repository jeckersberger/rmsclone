<?php
/**
 * Recurring Invoices API Endpoint
 *
 * POST-based API for managing recurring invoice templates and generating invoices
 * Requires: PROJECTS:PROJECT_PAYMENTS:VIEW permission
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RecurringInvoiceService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];
$action = trim($_POST['action'] ?? '');

$service = new RecurringInvoiceService($DBLIB);

switch ($action) {
    /**
     * List all active recurring invoice templates
     */
    case 'list':
        $templates = $service->getTemplates($instanceId);
        finish(true, null, ['templates' => $templates]);
        break;

    /**
     * Get a single template by ID
     */
    case 'get':
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            finish(false, ["message" => "template_id required"]);
        }
        $template = $service->getTemplate($templateId);
        if (!$template) {
            finish(false, ["message" => "Template not found"]);
        }
        finish(true, null, ['template' => $template]);
        break;

    /**
     * Create a new recurring invoice template
     * Required fields: clients_id, title, items_json (array of line items)
     * Optional fields: description, project_template_id, interval_type, interval_days,
     *                  next_due_date, payment_terms_days, auto_send_email
     */
    case 'create':
        if (empty($_POST['clients_id']) || empty($_POST['title'])) {
            finish(false, ["message" => "clients_id and title are required"]);
        }

        // Parse items from JSON or array
        $itemsData = $_POST['items_json'] ?? '[]';
        if (is_string($itemsData)) {
            $items = json_decode($itemsData, true) ?: [];
        } else {
            $items = (array)$itemsData;
        }

        if (empty($items)) {
            finish(false, ["message" => "At least one line item is required"]);
        }

        $templateData = [
            'clients_id' => (int)$_POST['clients_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'items_json' => $items,
            'project_template_id' => (int)($_POST['project_template_id'] ?? 0) ?: null,
            'interval_type' => $_POST['interval_type'] ?? 'monthly',
            'interval_days' => (int)($_POST['interval_days'] ?? 0),
            'next_due_date' => $_POST['next_due_date'] ?? null,
            'payment_terms_days' => (int)($_POST['payment_terms_days'] ?? 14),
            'auto_send_email' => (int)($_POST['auto_send_email'] ?? 0),
            'created_by' => $userId,
        ];

        $templateId = $service->createTemplate($instanceId, $templateData);
        if (!$templateId) {
            finish(false, ["message" => "Could not create template"]);
        }

        finish(true, null, ['id' => $templateId]);
        break;

    /**
     * Update an existing recurring invoice template
     * Required: template_id
     * Optional: Any field from create (except clients_id/created_by)
     */
    case 'update':
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            finish(false, ["message" => "template_id required"]);
        }

        $template = $service->getTemplate($templateId);
        if (!$template) {
            finish(false, ["message" => "Template not found"]);
        }

        $updateData = [];

        if (isset($_POST['title'])) {
            $updateData['title'] = trim($_POST['title']);
        }
        if (isset($_POST['description'])) {
            $updateData['description'] = trim($_POST['description']);
        }
        if (isset($_POST['items_json'])) {
            $itemsData = $_POST['items_json'];
            if (is_string($itemsData)) {
                $itemsData = json_decode($itemsData, true) ?: [];
            }
            if (!empty($itemsData)) {
                $updateData['items_json'] = $itemsData;
            }
        }
        if (isset($_POST['interval_type'])) {
            $updateData['interval_type'] = $_POST['interval_type'];
        }
        if (isset($_POST['interval_days'])) {
            $updateData['interval_days'] = (int)$_POST['interval_days'];
        }
        if (isset($_POST['payment_terms_days'])) {
            $updateData['payment_terms_days'] = (int)$_POST['payment_terms_days'];
        }
        if (isset($_POST['auto_send_email'])) {
            $updateData['auto_send_email'] = (int)$_POST['auto_send_email'];
        }
        if (isset($_POST['next_due_date'])) {
            $updateData['next_due_date'] = $_POST['next_due_date'];
        }

        if (empty($updateData)) {
            finish(true, null, ["message" => "No updates provided"]);
        }

        $success = $service->updateTemplate($templateId, $updateData);
        if (!$success) {
            finish(false, ["message" => "Could not update template"]);
        }

        finish(true, null, ["message" => "Template updated"]);
        break;

    /**
     * Soft delete a recurring invoice template
     * Required: template_id
     */
    case 'delete':
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            finish(false, ["message" => "template_id required"]);
        }

        $template = $service->getTemplate($templateId);
        if (!$template) {
            finish(false, ["message" => "Template not found"]);
        }

        $success = $service->deleteTemplate($templateId);
        if (!$success) {
            finish(false, ["message" => "Could not delete template"]);
        }

        finish(true, null, ["message" => "Template deleted"]);
        break;

    /**
     * Generate all due invoices for this instance
     * Checks all templates where next_due_date <= today and is_active = true
     * Creates document_exports entries for each
     */
    case 'generate':
        $generated = $service->generateDueInvoices($instanceId);
        finish(true, null, [
            'generated' => $generated,
            'count' => count($generated),
        ]);
        break;

    /**
     * Generate a single invoice from a specific template
     * Required: template_id
     */
    case 'generate_single':
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            finish(false, ["message" => "template_id required"]);
        }

        $template = $service->getTemplate($templateId);
        if (!$template) {
            finish(false, ["message" => "Template not found"]);
        }

        $invoice = $service->generateFromTemplate($templateId);
        if (!$invoice) {
            finish(false, ["message" => "Could not generate invoice"]);
        }

        finish(true, null, ['invoice' => $invoice]);
        break;

    /**
     * Preview what invoices would be generated in the next 30 days
     */
    case 'preview':
        $preview = $service->previewNextInvoices($instanceId);
        finish(true, null, [
            'preview' => $preview,
            'count' => count($preview),
        ]);
        break;

    default:
        finish(false, ["message" => "Unknown action: {$action}"]);
}
