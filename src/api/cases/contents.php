<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/CaseContentsService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? '';
$service = new CaseContentsService($DBLIB, $instanceId);

switch ($action) {
    case 'list_cases':
        try {
            $cases = $service->listCases();
            finish(true, null, $cases);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'get_contents':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            if (!$caseAssetId) {
                finish(false, ["message" => "case_asset_id is required"]);
            }
            $contents = $service->getCaseContents($caseAssetId);
            finish(true, null, $contents);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'add_content':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            $contentType = $_POST['content_type'] ?? '';
            $contentTypeId = (int)($_POST['content_type_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            $notes = $_POST['notes'] ?? '';

            if (!$caseAssetId || !$contentType || !$contentTypeId) {
                finish(false, ["message" => "case_asset_id, content_type, and content_type_id are required"]);
            }

            $contentId = $service->addContent(
                $caseAssetId,
                $contentType,
                $contentTypeId,
                $quantity,
                $notes ?: null
            );

            finish(true, null, ["content_id" => $contentId]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'remove_content':
        try {
            $contentId = (int)($_POST['content_id'] ?? 0);
            if (!$contentId) {
                finish(false, ["message" => "content_id is required"]);
            }
            $service->removeContent($contentId);
            finish(true, null, ["content_id" => $contentId]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'update_content':
        try {
            $contentId = (int)($_POST['content_id'] ?? 0);
            if (!$contentId) {
                finish(false, ["message" => "content_id is required"]);
            }

            $updateData = [];
            if (isset($_POST['quantity'])) {
                $updateData['quantity'] = (int)$_POST['quantity'];
            }
            if (isset($_POST['notes'])) {
                $updateData['notes'] = $_POST['notes'];
            }
            if (isset($_POST['sort_order'])) {
                $updateData['sort_order'] = (int)$_POST['sort_order'];
            }

            if (empty($updateData)) {
                finish(false, ["message" => "At least one field to update is required"]);
            }

            $service->updateContent($contentId, $updateData);
            finish(true, null, ["content_id" => $contentId]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'mark_as_case':
        try {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            if (!$assetId) {
                finish(false, ["message" => "asset_id is required"]);
            }
            $service->markAsCase($assetId);
            finish(true, null, ["asset_id" => $assetId, "is_case" => 1]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'unmark_case':
        try {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            if (!$assetId) {
                finish(false, ["message" => "asset_id is required"]);
            }
            $service->unmarkAsCase($assetId);
            finish(true, null, ["asset_id" => $assetId, "is_case" => 0]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'verify_scan':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            $scannedItemsJson = $_POST['scanned_items'] ?? '[]';

            if (!$caseAssetId) {
                finish(false, ["message" => "case_asset_id is required"]);
            }

            $scannedItems = json_decode($scannedItemsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                finish(false, ["message" => "Invalid scanned_items JSON"]);
            }

            $verification = $service->verifyCaseContents($caseAssetId, $scannedItems);
            finish(true, null, $verification);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'log_check':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            $projectId = (int)($_POST['project_id'] ?? 0);
            $checkType = $_POST['check_type'] ?? '';
            $resultJson = $_POST['result'] ?? '{}';

            if (!$caseAssetId || !$projectId || !$checkType) {
                finish(false, ["message" => "case_asset_id, project_id, and check_type are required"]);
            }

            $result = json_decode($resultJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                finish(false, ["message" => "Invalid result JSON"]);
            }

            $userId = (int)$AUTH->data['user']['users_id'];
            $checkId = $service->logCheck($caseAssetId, $projectId, $checkType, $userId, $result);
            finish(true, null, ["check_id" => $checkId]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'acknowledge':
        try {
            $checkId = (int)($_POST['check_id'] ?? 0);
            if (!$checkId) {
                finish(false, ["message" => "check_id is required"]);
            }
            $userId = (int)$AUTH->data['user']['users_id'];
            $service->acknowledgeDiscrepancy($checkId, $userId);
            finish(true, null, ["check_id" => $checkId, "acknowledged" => 1]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'check_history':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            if (!$caseAssetId) {
                finish(false, ["message" => "case_asset_id is required"]);
            }
            $history = $service->getCheckHistory($caseAssetId);
            finish(true, null, $history);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'copy_contents':
        try {
            $fromCaseId = (int)($_POST['from_case_id'] ?? 0);
            $toCaseId = (int)($_POST['to_case_id'] ?? 0);

            if (!$fromCaseId || !$toCaseId) {
                finish(false, ["message" => "from_case_id and to_case_id are required"]);
            }

            if ($fromCaseId === $toCaseId) {
                finish(false, ["message" => "Source and destination cases cannot be the same"]);
            }

            $copiedCount = $service->copyContents($fromCaseId, $toCaseId);
            finish(true, null, ["from_case_id" => $fromCaseId, "to_case_id" => $toCaseId, "copied_count" => $copiedCount]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'get_case_summary':
        try {
            $caseAssetId = (int)($_POST['case_asset_id'] ?? 0);
            if (!$caseAssetId) {
                finish(false, ["message" => "case_asset_id is required"]);
            }
            $summary = $service->getCaseSummary($caseAssetId);
            finish(true, null, $summary);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'available_asset_types':
        try {
            $types = $service->getAvailableAssetTypes();
            finish(true, null, $types);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'available_stock_items':
        try {
            $items = $service->getAvailableStockItems();
            finish(true, null, $items);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'search_assets':
        // Search assets for "mark as case" modal
        try {
            $search = trim($_POST['search'] ?? '');
            if (strlen($search) < 2) {
                finish(true, null, []);
            }
            $escapedSearch = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
            $DBLIB->where('a.instances_id', $instanceId);
            $DBLIB->where('a.assets_deleted', 0);
            $DBLIB->where('(at.assetTypes_name LIKE ? OR a.assets_tag LIKE ? OR a.assets_serialInternal LIKE ?)',
                ["%{$escapedSearch}%", "%{$escapedSearch}%", "%{$escapedSearch}%"]);
            $DBLIB->join('assetTypes at', 'at.assetTypes_id = a.assetTypes_id', 'LEFT');
            $DBLIB->orderBy('at.assetTypes_name', 'ASC');
            $assets = $DBLIB->get('assets a', 20, [
                'a.assets_id',
                'a.assets_tag',
                'at.assetTypes_name AS type_name',
                'a.assets_serialInternal',
                'a.is_case'
            ]);
            finish(true, null, $assets ?: []);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    case 'update_sort_order':
        // Update sort order of case contents (drag-and-drop)
        try {
            if (!$AUTH->instancePermissionCheck("ASSETS:EDIT")) {
                finish(false, ["code" => "PERMISSIONS"]);
            }
            $sortData = $_POST['sort_data'] ?? '';
            if (is_string($sortData)) {
                $sortData = json_decode($sortData, true);
            }
            if (!is_array($sortData) || empty($sortData)) {
                finish(false, ["message" => "sort_data must be a JSON array of {id, sort_order}"]);
            }
            foreach ($sortData as $item) {
                $contentId = (int)($item['id'] ?? 0);
                $sortOrder = (int)($item['sort_order'] ?? 0);
                if ($contentId > 0) {
                    $DBLIB->where('id', $contentId);
                    $DBLIB->where('instances_id', $instanceId);
                    $DBLIB->update('case_contents', ['sort_order' => $sortOrder]);
                }
            }
            finish(true, null, ["updated" => count($sortData)]);
        } catch (Exception $e) {
            finish(false, ["message" => $e->getMessage()]);
        }
        break;

    default:
        finish(false, ["message" => "Unknown action: $action"]);
        break;
}
