<?php
/**
 * Workflows Controller
 *
 * Main page for managing automated workflows
 * Renders the workflow dashboard with lists, templates, and execution history
 */

// Check authentication
if (!$AUTH->loggedIn()) {
    header('Location: /login');
    exit;
}

$user = $AUTH->data;

// Check permission
if (!$AUTH->serverPermissionCheck('WORKFLOWS:VIEW')) {
    // Render permission denied page
    $twig->display('error.twig', [
        'error_code' => 403,
        'error_title' => 'Zugriff verweigert',
        'error_message' => 'Sie haben keine Berechtigung, auf die Workflows zuzugreifen.',
    ]);
    exit;
}

// Render workflows index page
$twig->display('workflows/workflows_index.twig', [
    'page_title' => 'Automatisierte Workflows',
    'user' => $user,
    'instance' => $user['instance'] ?? [],
]);
