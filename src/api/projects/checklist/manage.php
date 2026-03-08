<?php
/**
 * Project Checklists API
 * Actions: list, add, toggle, delete, update, progress
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/ProjectChecklistService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

    $service = new ProjectChecklistService($DBLIB);
    $projectId = intval($_POST['project_id'] ?? 0);
    $instanceId = $AUTH->data['instance']['instances_id'];
    $userId = $AUTH->data['users_userid'];
    $action = $_POST['action'] ?? 'list';

    if (!$projectId) finish(false, ['message' => 'project_id required']);

    switch ($action) {
        case 'list':
            $items = $service->getItems($projectId);
            $progress = $service->getProgress($projectId);
            finish(true, null, ['items' => $items, 'progress' => $progress]);
            break;

        case 'add':
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) finish(false, ['message' => 'title required']);
            $id = $service->addItem($projectId, $instanceId, $title, $userId);
            finish(true, null, ['id' => $id]);
            break;

        case 'toggle':
            $itemId = intval($_POST['item_id'] ?? 0);
            if (!$itemId) finish(false, ['message' => 'item_id required']);
            $service->toggleItem($itemId, $projectId, $userId);
            finish(true);
            break;

        case 'delete':
            $itemId = intval($_POST['item_id'] ?? 0);
            if (!$itemId) finish(false, ['message' => 'item_id required']);
            $service->deleteItem($itemId, $projectId);
            finish(true);
            break;

        case 'update':
            $itemId = intval($_POST['item_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if (!$itemId || empty($title)) finish(false, ['message' => 'item_id and title required']);
            $service->updateTitle($itemId, $projectId, $title);
            finish(true);
            break;

        case 'progress':
            $progress = $service->getProgress($projectId);
            finish(true, null, $progress);
            break;

        default:
            finish(false, ['message' => 'Unknown action']);
    }
}, 'Project Checklist');
