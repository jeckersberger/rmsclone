<?php
/**
 * Insurance Management (K2) - Versicherungsmanagement
 *
 * Main controller for insurance policy, certificate, and claims management
 */
require_once __DIR__ . '/../common/headSecure.php';
require_once __DIR__ . '/../services/InsuranceService.php';

// Permission checks
if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    die($TWIG->render('404.twig', $PAGEDATA));
}

$PAGEDATA['pageConfig'] = [
    "TITLE" => "Versicherungsmanagement (K2)",
    "BREADCRUMB" => false
];

$insuranceService = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

// Determine view
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

if ($view === 'policy' && isset($_GET['policy_id'])) {
    // Policy detail view
    $policyId = intval($_GET['policy_id']);
    
    $policy = $insuranceService->getPolicy($policyId);
    if (!$policy) {
        die($TWIG->render('404.twig', $PAGEDATA));
    }
    
    $PAGEDATA['policy'] = $policy;
    $PAGEDATA['can_edit'] = $AUTH->instancePermissionCheck("INSURANCE:EDIT");
    
    // Render policy detail view
    echo $TWIG->render('insurance/policy_detail.twig', $PAGEDATA);

} elseif ($view === 'claim' && isset($_GET['claim_id'])) {
    // Claim detail view
    $claimId = intval($_GET['claim_id']);
    
    $DBLIB->where('id', $claimId);
    $claim = $DBLIB->getOne('insurance_claims', null, ['*']);
    
    if (!$claim || $claim['instances_id'] != $instanceId) {
        die($TWIG->render('404.twig', $PAGEDATA));
    }
    
    $PAGEDATA['claim'] = $claim;
    $PAGEDATA['can_edit'] = $AUTH->instancePermissionCheck("INSURANCE:EDIT");
    
    // Get related policy
    $DBLIB->where('id', $claim['policy_id']);
    $PAGEDATA['policy'] = $DBLIB->getOne('insurance_policies', null, ['*']);
    
    // Render claim detail view
    echo $TWIG->render('insurance/claim_detail.twig', $PAGEDATA);

} else {
    // Dashboard view (default)
    $stats = $insuranceService->getDashboardStats($instanceId);
    $PAGEDATA['stats'] = $stats;
    
    // Load data for dashboard
    $PAGEDATA['policies'] = $insuranceService->getPolicies($instanceId, true);
    $PAGEDATA['expiring_policies'] = $insuranceService->getExpiringPolicies($instanceId, 30);
    $PAGEDATA['uncovered_assets'] = $insuranceService->getUncoveredAssets($instanceId);
    $PAGEDATA['recent_claims'] = $insuranceService->getClaims($instanceId);
    
    $PAGEDATA['can_edit'] = $AUTH->instancePermissionCheck("INSURANCE:EDIT");
    
    echo $TWIG->render('insurance/insurance_index.twig', $PAGEDATA);
}
?>
