<?php
/**
 * Oeffentlicher Endpoint fuer Angebotsantwort (kein Login noetig).
 * POST: token, action (accept/reject), signature_name, comment
 */
require_once __DIR__ . '/../common/head.php';
header('Content-Type: application/json; charset=utf-8');

function respond($result, $error = null, $response = null) {
    echo json_encode(['result' => $result, 'error' => $error, 'response' => $response]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['message' => 'Nur POST erlaubt.']);
}

$token = trim($_POST['token'] ?? '');
$action = trim($_POST['action'] ?? '');
$signatureName = trim($_POST['signature_name'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($token) || !in_array($action, ['accept', 'reject'])) {
    respond(false, ['message' => 'Ungueltiger Request.']);
}
if (empty($signatureName)) {
    respond(false, ['message' => 'Bitte geben Sie Ihren Namen als Unterschrift ein.']);
}

// Token laden
$DBLIB->where('token', $token);
$approval = $DBLIB->getOne('quote_approval_tokens');

if (!$approval) {
    respond(false, ['message' => 'Angebot nicht gefunden.']);
}

if ($approval['status'] !== 'pending') {
    respond(false, ['message' => 'Dieses Angebot wurde bereits beantwortet.']);
}

if ($approval['expires_at'] && strtotime($approval['expires_at']) < time()) {
    respond(false, ['message' => 'Dieser Freigabelink ist abgelaufen.']);
}

// Status aktualisieren
$newStatus = ($action === 'accept') ? 'accepted' : 'rejected';
$updateData = [
    'status' => $newStatus,
    'client_comment' => $comment ?: null,
    'client_signature_name' => $signatureName,
    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
];

if ($action === 'accept') {
    $updateData['accepted_at'] = date('Y-m-d H:i:s');
} else {
    $updateData['rejected_at'] = date('Y-m-d H:i:s');
}

$DBLIB->where('id', $approval['id']);
$DBLIB->update('quote_approval_tokens', $updateData);

// Document lifecycle aktualisieren
if ($approval['document_lifecycle_id']) {
    require_once __DIR__ . '/../services/DocumentLifecycleService.php';
    $lifecycle = new DocumentLifecycleService($DBLIB);
    $docStatus = ($action === 'accept') ? 'accepted' : 'rejected';
    $lifecycle->changeStatus(
        (int)$approval['document_lifecycle_id'],
        $docStatus,
        (int)$approval['created_by'],
        "Kunde hat " . ($action === 'accept' ? 'angenommen' : 'abgelehnt') . ": {$signatureName}"
        . ($comment ? " - {$comment}" : '')
    );
}

// Bei Annahme: E-Mail-Benachrichtigung an Projektleiter
if ($action === 'accept' && $approval['instances_id']) {
    // Lade Projektleiter
    $DBLIB->join('users', 'projects.projects_manager=users.users_userid', 'LEFT');
    $DBLIB->where('projects.projects_id', $approval['projects_id']);
    $manager = $DBLIB->getOne('projects', ['users.users_email', 'users.users_name1', 'users.users_userid']);

    if ($manager && !empty($manager['users_email'])) {
        require_once __DIR__ . '/../api/notifications/email/email.php';
        $emailHtml = "<p>Das Angebot <strong>{$approval['doc_number']}</strong> wurde von <strong>{$signatureName}</strong> ({$approval['client_name']}) angenommen.</p>";
        if ($comment) {
            $emailHtml .= "<p>Kundenkommentar: <em>{$comment}</em></p>";
        }
        @sendEmail(
            ['userData' => ['users_email' => $manager['users_email'], 'users_name1' => $manager['users_name1'], 'users_name2' => '']],
            $approval['instances_id'],
            "Angebot {$approval['doc_number']} angenommen!",
            $emailHtml
        );
    }
}

respond(true, null, ['status' => $newStatus]);
