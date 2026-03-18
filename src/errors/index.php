<?php

/**
 * Error Terminal Dashboard Controller
 *
 * GET /errors/ - Display error terminal dashboard
 * Permission required: SYSTEM:VIEW
 */

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/ErrorTerminalService.php';

use Rms\Services\ErrorTerminalService;

// Permission check
if (!$AUTH || !$AUTH->instancePermissionCheck('SYSTEM:VIEW')) {
    header('Location: /login');
    exit;
}

$instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);

// Render the error terminal template
$template = $twig->load('error_terminal.twig');

echo $template->render([
    'instanceId' => $instanceId,
    'user' => $AUTH->data,
]);
