<?php
/**
 * Listet alle Freigabelinks fuer ein Projekt auf.
 * POST: id (project_id)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW") || !isset($_POST['id'])) {
    finish(false, ["code" => null, "message" => "Keine Berechtigung."]);
}

$projectId = (int)$bCMS->sanitizeString($_POST['id']);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->orderBy('created_at', 'DESC');
$links = $DBLIB->get('quote_approval_tokens');

$result = [];
foreach (($links ?: []) as $link) {
    $expired = $link['expires_at'] && strtotime($link['expires_at']) < time();
    $result[] = [
        'id' => $link['id'],
        'doc_number' => $link['doc_number'],
        'client_name' => $link['client_name'],
        'status' => $expired && $link['status'] === 'pending' ? 'expired' : $link['status'],
        'created_at' => $link['created_at'],
        'expires_at' => $link['expires_at'],
        'accepted_at' => $link['accepted_at'],
        'rejected_at' => $link['rejected_at'],
        'client_signature_name' => $link['client_signature_name'],
        'client_comment' => $link['client_comment'],
        'token' => $link['token'],
    ];
}

finish(true, null, ['links' => $result]);
