<?php
/**
 * Oeffentliche Angebotsfreigabe-Seite
 *
 * Kunden koennen ueber einen einzigartigen Link ihr Angebot einsehen,
 * annehmen oder ablehnen - ohne Login.
 *
 * URL: /public/quote.php?token=XXXXX
 */
require_once __DIR__ . '/../common/head.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token) || strlen($token) < 10) {
    http_response_code(404);
    die($TWIG->render('public/quote_error.twig', [
        'CONFIG' => $CONFIG,
        'error' => 'Ungueltiger oder fehlender Token.'
    ]));
}

// Token laden
$DBLIB->where('token', $token);
$approval = $DBLIB->getOne('quote_approval_tokens');

if (!$approval) {
    http_response_code(404);
    die($TWIG->render('public/quote_error.twig', [
        'CONFIG' => $CONFIG,
        'error' => 'Dieses Angebot wurde nicht gefunden oder der Link ist ungueltig.'
    ]));
}

// Ablaufdatum pruefen
if ($approval['expires_at'] && strtotime($approval['expires_at']) < time()) {
    die($TWIG->render('public/quote_error.twig', [
        'CONFIG' => $CONFIG,
        'error' => 'Dieser Freigabelink ist am ' . date('d.m.Y', strtotime($approval['expires_at'])) . ' abgelaufen.'
    ]));
}

// Instance laden
$DBLIB->where('instances_id', $approval['instances_id']);
$instance = $DBLIB->getOne('instances');

// Projekt laden
$DBLIB->where('projects_id', $approval['projects_id']);
$project = $DBLIB->getOne('projects');

// Document lifecycle laden
$DBLIB->where('id', $approval['document_lifecycle_id']);
$docLifecycle = $DBLIB->getOne('document_lifecycle');

// PDF-Download-URL
$pdfUrl = null;
if ($approval['s3files_id']) {
    $pdfUrl = $CONFIG['ROOTURL'] . '/api/file/?r=true&f=' . $approval['s3files_id'];
}

$PAGEDATA = [
    'CONFIG' => $CONFIG,
    'approval' => $approval,
    'instance' => $instance,
    'project' => $project,
    'docLifecycle' => $docLifecycle,
    'pdfUrl' => $pdfUrl,
];

echo $TWIG->render('public/quote_approval.twig', $PAGEDATA);
