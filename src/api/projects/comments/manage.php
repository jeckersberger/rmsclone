<?php
/**
 * Project Comments/Notes API
 * Actions: list, add, delete
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/ProjectCommentService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

    $service = new ProjectCommentService($DBLIB);
    $projectId = intval($_POST['project_id'] ?? 0);
    $instanceId = $AUTH->data['instance']['instances_id'];
    $userId = $AUTH->data['users_userid'];
    $action = $_POST['action'] ?? 'list';

    if (!$projectId) finish(false, ['message' => 'project_id required']);

    switch ($action) {
        case 'list':
            $comments = $service->getComments($projectId);
            finish(true, null, ['comments' => $comments]);
            break;

        case 'add':
            $comment = trim($_POST['comment'] ?? '');
            if (empty($comment)) finish(false, ['message' => 'comment required']);
            $isInternal = intval($_POST['is_internal'] ?? 1);
            $parentId = intval($_POST['parent_id'] ?? 0) ?: null;
            $id = $service->addComment($projectId, $instanceId, $userId, $comment, (bool) $isInternal, $parentId);
            finish(true, null, ['id' => $id]);
            break;

        case 'delete':
            $commentId = intval($_POST['comment_id'] ?? 0);
            if (!$commentId) finish(false, ['message' => 'comment_id required']);
            $service->deleteComment($commentId, $projectId, $userId);
            finish(true);
            break;

        default:
            finish(false, ['message' => 'Unknown action']);
    }
}, 'Project Comments');
