<?php
/**
 * In-App Notifications API
 * Actions: list, unread_count, mark_read, mark_all_read
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/NotificationService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    $service = new NotificationService($DBLIB);
    $userId = $AUTH->data['users_userid'];
    $instanceId = $AUTH->data['instance']['instances_id'] ?? null;
    $action = $_POST['action'] ?? 'list';

    switch ($action) {
        case 'unread_count':
            $count = $service->getUnreadCount($userId, $instanceId);
            finish(true, null, ['count' => $count]);
            break;

        case 'unread':
            $notifications = $service->getUnread($userId, $instanceId);
            finish(true, null, ['notifications' => $notifications]);
            break;

        case 'list':
            $limit = min(100, max(1, intval($_POST['limit'] ?? 50)));
            $offset = max(0, intval($_POST['offset'] ?? 0));
            $notifications = $service->getAll($userId, $instanceId, $limit, $offset);
            $unreadCount = $service->getUnreadCount($userId, $instanceId);
            finish(true, null, ['notifications' => $notifications, 'unread_count' => $unreadCount]);
            break;

        case 'mark_read':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) finish(false, ['message' => 'id required']);
            $service->markRead($id, $userId);
            finish(true);
            break;

        case 'mark_all_read':
            $service->markAllRead($userId, $instanceId);
            finish(true);
            break;

        default:
            finish(false, ['message' => 'Unknown action']);
    }
}, 'Notifications');
