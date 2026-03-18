<?php
/**
 * Damage Management (K3) - Schadenmanagement-Workflow
 *
 * Main controller for damage/claim management workflow
 */
require_once __DIR__ . '/../common/headSecure.php';
require_once __DIR__ . '/../services/DamageWorkflowService.php';
require_once __DIR__ . '/../services/DamageReportService.php';

// Permission checks
if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    die($TWIG->render('404.twig', $PAGEDATA));
}

$PAGEDATA['pageConfig'] = [
    "TITLE" => "Schadenmanagement (K3)",
    "BREADCRUMB" => false
];

$workflowService = new DamageWorkflowService($DBLIB);
$reportService = new DamageReportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

// Determine view
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

if ($view === 'detail' && isset($_GET['damage_report_id'])) {
    // Detail view for single workflow
    $damageReportId = intval($_GET['damage_report_id']);

    // Verify report belongs to instance
    $DBLIB->where('id', $damageReportId);
    $DBLIB->where('instances_id', $instanceId);
    $report = $DBLIB->getOne('damage_reports', ['*']);

    if (!$report) {
        die($TWIG->render('404.twig', $PAGEDATA));
    }

    // Get or create workflow
    $DBLIB->where('damage_report_id', $damageReportId);
    $workflow = $DBLIB->getOne('damage_workflows', ['id']);

    if (!$workflow) {
        // Create workflow if it doesn't exist
        $workflowId = $workflowService->createWorkflow(
            $damageReportId,
            $report['severity'] ?? 'minor',
            $report['repair_estimate'] ?? null,
            null,
            $instanceId
        );

        if ($workflowId) {
            $DBLIB->where('id', $workflowId);
            $workflow = $DBLIB->getOne('damage_workflows', ['*']);
        }
    } else {
        // Load full workflow with related data
        $workflow = $workflowService->getWorkflow($damageReportId);
    }

    // Load asset info
    if ($report['assets_id']) {
        $DBLIB->where('a.assets_id', $report['assets_id']);
        $DBLIB->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $asset = $DBLIB->getOne('assets a', null, ['a.*', 'at.assetTypes_name']);
        $PAGEDATA['asset'] = $asset;
    }

    // Load project info
    if ($report['projects_id']) {
        $DBLIB->where('projects_id', $report['projects_id']);
        $project = $DBLIB->getOne('projects', ['*']);
        $PAGEDATA['project'] = $project;
    }

    // Load status log
    $PAGEDATA['status_log'] = $workflowService->getStatusLog($workflow['id']);

    // Get valid transitions
    $PAGEDATA['valid_transitions'] = $workflowService->getValidTransitions($workflow['status']);

    // Get all users for assignment dropdown
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->orderBy('users_name1', 'ASC');
    $PAGEDATA['users'] = $DBLIB->get('users', null, ['users_userid', 'users_name1', 'users_name2']) ?: [];

    $PAGEDATA['report'] = $report;
    $PAGEDATA['workflow'] = $workflow;

    // Check edit permission
    $PAGEDATA['can_edit'] = $AUTH->instancePermissionCheck("DAMAGE:EDIT");
    $PAGEDATA['can_charge'] = $AUTH->instancePermissionCheck("DAMAGE:CHARGE");

    echo $TWIG->render('damage/damage_detail.twig', $PAGEDATA);

} else {
    // Dashboard view (default)
    echo $TWIG->render('damage/damage_dashboard.twig', $PAGEDATA);
}
?>
