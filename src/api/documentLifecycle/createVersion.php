<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:CREATE") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$documentExportsId = (int)($_POST['document_exports_id'] ?? 0);
$versionNotes = trim($_POST['version_notes'] ?? '');

if ($documentExportsId <= 0) finish(false, ["code" => "INVALID", "message" => "Fehlende Parameter: document_exports_id"]);

$svc = new DocumentLifecycleService($DBLIB);
$newId = $svc->createNewVersion(
    $documentExportsId,
    $AUTH->data['users_userid'],
    $versionNotes ?: null
);

if (!$newId) finish(false, ["code" => "ERROR", "message" => "Neue Version konnte nicht erstellt werden. Nur Angebote koennen versioniert werden."]);

// Neue Version laden fuer die Antwort
$DBLIB->where('id', $newId);
$newExport = $DBLIB->getOne('document_exports');

finish(true, null, [
    "new_version_id" => $newId,
    "version" => (int)($newExport['document_exports_version'] ?? 1),
    "doc_number" => $newExport['doc_number'] ?? '',
    "message" => "Neue Version v" . (int)($newExport['document_exports_version'] ?? 1) . " erfolgreich erstellt.",
]);
