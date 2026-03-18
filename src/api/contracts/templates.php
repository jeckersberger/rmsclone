<?php
/**
 * GET/POST /api/contracts/templates.php
 * List and create contract templates
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ContractService.php';

header('Content-Type: application/json');

try {
    $service = new ContractService($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // List templates
        if (!hasPermission('CONTRACTS:VIEW')) {
            throw new \Exception('Zugriff verweigert', 403);
        }

        $templates = $service->getTemplates($CurrentInstance['instances_id']);

        echo json_encode([
            'success' => true,
            'data' => $templates,
            'count' => count($templates),
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create template
        if (!hasPermission('CONTRACTS:EDIT')) {
            throw new \Exception('Zugriff verweigert', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!isset($data['name'], $data['content_html'])) {
            throw new \Exception('Missing required fields: name, content_html');
        }

        $templateId = $service->createTemplate([
            'instances_id' => $CurrentInstance['instances_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'content_html' => $data['content_html'],
            'agb_set_id' => isset($data['agb_set_id']) ? (int)$data['agb_set_id'] : null,
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        ]);

        echo json_encode([
            'success' => true,
            'id' => $templateId,
            'message' => 'Vorlage erstellt',
        ]);
    } else {
        throw new \Exception('Method not allowed', 405);
    }
} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
