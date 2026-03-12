<?php
/**
 * Generiert ein PDF-Dokument (Rechnung/Angebot/Lieferschein) serverseitig
 * und speichert es als Projektdatei.
 *
 * POST-Parameter:
 *   id            - Projekt-ID
 *   type          - invoice | quote | deliveryNote
 *   template_key  - Template-Key (default: "default")
 *   discount_pct  - Rabatt in % (default: 0)
 *   duration_days - Miettage (default: 0 = automatisch)
 *   send_email    - 1 = automatisch per E-Mail an Kunden senden
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW") || !isset($_POST['id'])) {
    finish(false, ["code" => null, "message" => "Keine Berechtigung oder fehlende Projekt-ID."]);
}

// Lade benoetigte Services
require_once __DIR__ . '/../../services/BusinessRepo.php';
require_once __DIR__ . '/../../services/ProjectRepo.php';
require_once __DIR__ . '/../../services/ClientsRepo.php';
require_once __DIR__ . '/../../services/S3Files.php';
require_once __DIR__ . '/../../services/SequenceService.php';
require_once __DIR__ . '/../../services/DocumentRenderer.php';
require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/InvoiceMailService.php';

$projectId = (int)$bCMS->sanitizeString($_POST['id']);
$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

// Typ-Mapping: Frontend nutzt "deliveryNote", Backend "delivery_note"
$typeMap = [
    'invoice' => 'invoice',
    'quote' => 'quote',
    'deliveryNote' => 'delivery_note',
    'delivery_note' => 'delivery_note',
];
$rawType = $bCMS->sanitizeString($_POST['type'] ?? 'invoice');
$type = $typeMap[$rawType] ?? 'invoice';

$templateKey = $bCMS->sanitizeString($_POST['template_key'] ?? 'default');
$discountPct = (float)($_POST['discount_pct'] ?? 0);
$durationDays = (int)($_POST['duration_days'] ?? 0);
$sendEmail = !empty($_POST['send_email']);

$skontoEnabled = !empty($_POST['skonto_enabled']);
$skontoRate = isset($_POST['skonto_rate']) ? (float)$_POST['skonto_rate'] : null;
$skontoDays = isset($_POST['skonto_days']) ? (int)$_POST['skonto_days'] : null;

$options = [
    'discount_pct' => $discountPct,
    'duration_days' => $durationDays,
    'show_component_prices' => true,
    'set_group_prefix' => 'Set:',
    'skonto_enabled' => $skontoEnabled,
];
if ($skontoRate !== null) $options['skonto_rate'] = $skontoRate;
if ($skontoDays !== null) $options['skonto_days'] = $skontoDays;

try {
    // PDF generieren und speichern
    $result = DocumentRenderer::renderAndStore(
        $DBLIB, $instanceId, $projectId, $type, $templateKey, $options, $userId
    );

    // Document-Lifecycle eintragen
    $lifecycle = new DocumentLifecycleService($DBLIB);
    $docId = $lifecycle->create($instanceId, $projectId, $type, [
        'doc_number' => $result['doc_number'],
        's3files_id' => $result['s3files_id'],
        'created_by' => $userId,
    ]);

    $response = [
        'doc_number' => $result['doc_number'],
        's3files_id' => $result['s3files_id'],
        'doc_id' => $docId,
    ];

    // Automatischer E-Mail-Versand
    if ($sendEmail) {
        $mailService = new InvoiceMailService($DBLIB);
        $emailResult = $mailService->autoSendIfEmailAvailable(
            $instanceId, $projectId, $result['s3files_id'],
            $result['doc_number'], $type, $userId
        );
        $response['email'] = $emailResult;

        if ($emailResult['success']) {
            $lifecycle->changeStatus($docId, 'sent', $userId, 'Automatisch per E-Mail versendet');
        }
    }

    finish(true, null, $response);

} catch (\RuntimeException $e) {
    finish(false, ["code" => "GOBD", "message" => $e->getMessage()]);
} catch (\Exception $e) {
    finish(false, ["code" => null, "message" => "Fehler bei der PDF-Erstellung: " . $e->getMessage()]);
}
