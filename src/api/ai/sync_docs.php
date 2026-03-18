<?php
/**
 * Document Sync API
 *
 * Trigger bidirectional synchronization between database and markdown files
 *
 * POST /api/ai/sync_docs - Sync both directions (default)
 * POST /api/ai/sync_docs?direction=to_markdown - DB -> FEATURE_REQUESTS.md
 * POST /api/ai/sync_docs?direction=to_markdown&type=implementation - DB -> IMPLEMENTATION_CHECKLIST.md
 * POST /api/ai/sync_docs?direction=from_markdown - FEATURE_REQUESTS.md -> DB
 * POST /api/ai/sync_docs?direction=from_markdown&type=implementation - IMPLEMENTATION_CHECKLIST.md -> DB
 *
 * Request body: {} (empty)
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied. Requires AI:CONFIGURE']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

try {
    $direction = $_GET['direction'] ?? 'both';
    $type = $_GET['type'] ?? 'all';

    $results = [];

    // Sync Feature Requests
    if ($type === 'all' || $type === 'feature_requests') {
        $frService = new FeatureRequestService($db);

        if ($direction === 'to_markdown' || $direction === 'both') {
            $success = $frService->syncToMarkdown($instanceId);
            $results['feature_requests_to_markdown'] = [
                'success' => $success,
                'message' => $success ? 'Synced DB to FEATURE_REQUESTS.md' : 'Failed to sync',
            ];
        }

        if ($direction === 'from_markdown' || $direction === 'both') {
            $imported = $frService->syncFromMarkdown($instanceId);
            $results['feature_requests_from_markdown'] = [
                'success' => true,
                'imported' => $imported,
                'message' => "Imported {$imported} new feature requests",
            ];
        }
    }

    // Sync Implementation Status
    if ($type === 'all' || $type === 'implementation') {
        $implService = new ImplementationTrackerService($db);

        if ($direction === 'to_markdown' || $direction === 'both') {
            $success = $implService->syncToMarkdown($instanceId);
            $results['implementation_to_markdown'] = [
                'success' => $success,
                'message' => $success ? 'Synced DB to IMPLEMENTATION_CHECKLIST.md' : 'Failed to sync',
            ];
        }

        if ($direction === 'from_markdown' || $direction === 'both') {
            $imported = $implService->syncFromMarkdown($instanceId);
            $results['implementation_from_markdown'] = [
                'success' => true,
                'imported' => $imported,
                'message' => "Imported {$imported} updated bausteine",
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'direction' => $direction,
        'type' => $type,
        'results' => $results,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
