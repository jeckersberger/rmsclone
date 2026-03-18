<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/CaseContentsService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$assetId = (int)($_POST['asset_id'] ?? 0);
$projectId = (int)($_POST['project_id'] ?? 0);
$condition = trim($_POST['condition'] ?? 'good');
$notes = trim($_POST['notes'] ?? '');
$scannedContents = isset($_POST['scanned_contents']) ? json_decode($_POST['scanned_contents'], true) : [];
$acknowledgeDiscrepancies = isset($_POST['acknowledge_discrepancies']) && $_POST['acknowledge_discrepancies'] === 'true';

if ($assetId <= 0 || $projectId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new CheckInOutService($DBLIB);

// Inject CaseContentsService if available
try {
    $caseContentsSvc = new CaseContentsService($DBLIB, $instanceId);
    $svc->setCaseContentsService($caseContentsSvc);
} catch (Exception $e) {
    // CaseContentsService not available, continue without case verification
}

// Check if we should use case verification
$usesCaseVerification = !empty($scannedContents) || (isset($_POST['check_case_contents']) && $_POST['check_case_contents'] === 'true');

if ($usesCaseVerification && $svc instanceof CheckInOutService) {
    // Use checkInWithCaseVerification
    $result = $svc->checkInWithCaseVerification(
        $instanceId,
        $assetId,
        $projectId,
        $AUTH->data['users_userid'],
        $scannedContents,
        [
            'condition' => $condition,
            'notes' => $notes ?: null,
        ],
        $acknowledgeDiscrepancies
    );

    $response = [
        'id' => $result['checkinId'],
        'caseVerificationResult' => $result['caseVerificationResult'],
        'warnings' => $result['warnings']
    ];

    finish(true, null, $response);
} else {
    // Use normal checkin
    $id = $svc->checkIn($instanceId, $assetId, $projectId, $AUTH->data['users_userid'], [
        'condition' => $condition,
        'notes' => $notes ?: null,
    ]);

    finish(true, null, ["id" => $id]);
}
