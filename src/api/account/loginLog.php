<?php
/**
 * Login Log API - Shows login history for the current user
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/LoginLogService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    $logService = new LoginLogService($DBLIB);
    $limit = min(100, max(1, intval($_POST['limit'] ?? 50)));

    $log = $logService->getLog($AUTH->data['users_userid'], $limit);

    finish(true, null, ['entries' => $log ?: []]);
}, 'Login Log');
