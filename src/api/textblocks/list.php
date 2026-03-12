<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$docType = isset($_POST['doc_type']) ? htmlspecialchars(strip_tags(trim($_POST['doc_type'])), ENT_QUOTES, 'UTF-8') : null;

$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('deleted', 0);
if ($docType) {
    $DBLIB->where("FIND_IN_SET(?, doc_types) > 0", [$docType]);
}
$DBLIB->orderBy('sort_order', 'ASC');
$DBLIB->orderBy('title', 'ASC');
$blocks = $DBLIB->get('text_blocks') ?: [];

finish(true, null, ['text_blocks' => $blocks]);
