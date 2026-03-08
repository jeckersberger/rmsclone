<?php
/**
 * Project Photos API
 * Actions: list, upload, delete
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH, $bCMS) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

    $projectId = intval($_POST['project_id'] ?? 0);
    $instanceId = $AUTH->data['instance']['instances_id'];
    $userId = $AUTH->data['users_userid'];
    $action = $_POST['action'] ?? 'list';

    if (!$projectId) finish(false, ['message' => 'project_id required']);

    switch ($action) {
        case 'list':
            $DBLIB->where('projects_id', $projectId);
            $DBLIB->where('instances_id', $instanceId);
            $DBLIB->join('users', 'project_photos.uploaded_by = users.users_userid', 'LEFT');
            $DBLIB->orderBy('created_at', 'DESC');
            $photos = $DBLIB->get('project_photos', null, [
                'project_photos.*',
                'users.users_name1', 'users.users_name2',
            ]) ?: [];
            finish(true, null, ['photos' => $photos]);
            break;

        case 'upload':
            if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);
            if (!isset($_FILES['photo'])) finish(false, ['message' => 'No photo uploaded']);

            require_once __DIR__ . '/../../../services/UploadValidationService.php';
            $validator = new UploadValidationService();
            $result = $validator->validateUpload($_FILES['photo'], 'image', 10 * 1024 * 1024);
            if (!$result['valid']) finish(false, ['message' => $result['error']]);

            require_once __DIR__ . '/../../../services/LocalFileStorage.php';
            $stored = LocalFileStorage::store($instanceId, 'project_photos', $_FILES['photo']);

            $caption = trim($_POST['caption'] ?? '');
            $photoType = in_array($_POST['photo_type'] ?? '', ['before', 'during', 'after', 'other'])
                ? $_POST['photo_type'] : 'other';

            $DBLIB->insert('project_photos', [
                'projects_id' => $projectId,
                'instances_id' => $instanceId,
                'file_path' => $stored['path'],
                'caption' => $caption ?: null,
                'photo_type' => $photoType,
                'uploaded_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            finish(true, null, ['id' => $DBLIB->getInsertId()]);
            break;

        case 'delete':
            if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);
            $photoId = intval($_POST['photo_id'] ?? 0);
            if (!$photoId) finish(false, ['message' => 'photo_id required']);

            $DBLIB->where('id', $photoId);
            $DBLIB->where('projects_id', $projectId);
            $DBLIB->where('instances_id', $instanceId);
            $photo = $DBLIB->getOne('project_photos');
            if ($photo && $photo['file_path']) {
                LocalFileStorage::delete($photo['file_path']);
            }
            $DBLIB->where('id', $photoId);
            $DBLIB->where('projects_id', $projectId);
            $DBLIB->delete('project_photos');
            finish(true);
            break;

        default:
            finish(false, ['message' => 'Unknown action']);
    }
}, 'Project Photos');
