<?php

require_once __DIR__ . '/../apiHeadSecure.php';

use App\Services\GobdArchiveService;

// Permission check
$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW");

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];
$action = $_REQUEST['action'] ?? null;

// Initialize service
$archiveService = new GobdArchiveService($this->db);

$response = [];

try {
    switch ($action) {
        case 'archive':
            // Archive a single document
            $documentId = (int)($_REQUEST['document_id'] ?? 0);
            if (!$documentId) {
                http_response_code(400);
                $response = ['success' => false, 'error' => 'document_id required'];
                break;
            }

            $response = $archiveService->archiveDocument($instanceId, $documentId, $userId);
            break;

        case 'bulk_archive':
            // Archive all unarchived finalized documents
            $response = $archiveService->bulkArchive($instanceId, $userId);
            break;

        case 'verify':
            // Verify integrity of a single document
            $documentId = (int)($_REQUEST['document_id'] ?? 0);
            if (!$documentId) {
                http_response_code(400);
                $response = ['success' => false, 'error' => 'document_id required'];
                break;
            }

            $response = $archiveService->verifyIntegrity($instanceId, $documentId);
            break;

        case 'verify_all':
            // Verify integrity of all archived documents
            $response = $archiveService->bulkVerifyIntegrity($instanceId);
            break;

        case 'status':
            // Get retention status overview
            $response = $archiveService->getRetentionStatus($instanceId);
            break;

        case 'expiring':
            // Get documents approaching expiry
            $monthsAhead = (int)($_REQUEST['months_ahead'] ?? 6);
            $documents = $archiveService->getExpiringDocuments($instanceId, $monthsAhead);
            $response = [
                'success' => true,
                'count' => count($documents),
                'documents' => $documents,
                'months_ahead' => $monthsAhead
            ];
            break;

        case 'process_expired':
            // Mark expired documents (does not delete)
            $response = $archiveService->processExpired($instanceId, $userId);
            break;

        case 'audit_trail':
            // Get audit log with optional filters
            $documentId = (int)($_REQUEST['document_id'] ?? 0);
            $from = $_REQUEST['from'] ?? null;
            $to = $_REQUEST['to'] ?? null;

            $auditLog = $archiveService->getAuditTrail(
                $instanceId,
                $documentId ?: null,
                $from,
                $to
            );

            $response = [
                'success' => true,
                'count' => count($auditLog),
                'entries' => $auditLog
            ];
            break;

        case 'export_index':
            // Export archive index as CSV
            $csv = $archiveService->exportArchiveIndex($instanceId, 'csv');

            // Set headers for download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gobd-archive-index-' . date('Y-m-d-His') . '.csv"');
            header('Content-Length: ' . strlen($csv));

            echo $csv;
            exit;

        default:
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => 'Unknown action',
                'available_actions' => [
                    'archive',
                    'bulk_archive',
                    'verify',
                    'verify_all',
                    'status',
                    'expiring',
                    'process_expired',
                    'audit_trail',
                    'export_index'
                ]
            ];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
