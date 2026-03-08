<?php
/**
 * User Favorites/Bookmarks API
 * Actions: list, add, remove, reorder
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FavoritesService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    $service = new FavoritesService($DBLIB);
    $userId = $AUTH->data['users_userid'];
    $instanceId = $AUTH->data['instance']['instances_id'];
    $action = $_POST['action'] ?? 'list';

    switch ($action) {
        case 'list':
            $favorites = $service->getAll($userId, $instanceId);
            finish(true, null, ['favorites' => $favorites]);
            break;

        case 'add':
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-star');
            if (empty($title) || empty($url)) {
                finish(false, ['message' => 'title and url required']);
            }
            $id = $service->add($userId, $instanceId, $title, $url, $icon);
            finish(true, null, ['id' => $id]);
            break;

        case 'remove':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) finish(false, ['message' => 'id required']);
            $service->remove($id, $userId);
            finish(true);
            break;

        case 'reorder':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) $ids = json_decode($ids, true) ?? [];
            $service->reorder($userId, $ids);
            finish(true);
            break;

        case 'check':
            $url = trim($_POST['url'] ?? '');
            $isFav = $service->isFavorite($userId, $instanceId, $url);
            finish(true, null, ['is_favorite' => $isFav]);
            break;

        default:
            finish(false, ['message' => 'Unknown action']);
    }
}, 'Favorites');
