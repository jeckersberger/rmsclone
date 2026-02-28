<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT"))
    finish(false, ["code" => "PERMISSIONS", "message" => "No permission"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$id = (int)($_POST['id'] ?? 0);

$type = trim($_POST['type'] ?? '');
$key  = trim($_POST['key'] ?? '');
$name = trim($_POST['name'] ?? '');

if (!in_array($type, ['invoice', 'quote', 'delivery_note']))
    finish(false, ["code" => "INVALID", "message" => "Invalid type"]);
if (strlen($key) < 1)
    finish(false, ["code" => "INVALID", "message" => "Key is required"]);
if (strlen($name) < 1)
    finish(false, ["code" => "INVALID", "message" => "Name is required"]);

$data = [
    'instances_id' => $instanceId,
    'type'         => $type,
    'key'          => preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)),
    'name'         => $name,
    'language'     => trim($_POST['language'] ?? 'de-DE'),
    'css'          => $_POST['css'] ?? '',
    'twig_html'    => $_POST['twig_html'] ?? '',
    'is_default'   => (int)(($_POST['is_default'] ?? '0') == '1'),
];

if ($id > 0) {
    // Update
    $DBLIB->where("id", $id);
    $DBLIB->where("instances_id", $instanceId);
    $existing = $DBLIB->getOne("document_templates", ["id"]);
    if (!$existing) finish(false, ["code" => "NOT-FOUND", "message" => "Template not found"]);

    $DBLIB->where("id", $id);
    $DBLIB->update("document_templates", $data);
} else {
    // Check for duplicate key
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("type", $data['type']);
    $DBLIB->where("`key`", $data['key']);
    $dup = $DBLIB->getOne("document_templates", ["id"]);
    if ($dup) finish(false, ["code" => "DUPLICATE", "message" => "A template with this key already exists for this type"]);

    $DBLIB->insert("document_templates", $data);
    $id = $DBLIB->getInsertId();
}

// If set as default, unset other defaults for same type
if ($data['is_default']) {
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("type", $data['type']);
    $DBLIB->where("id", $id, "!=");
    $DBLIB->update("document_templates", ["is_default" => 0]);
}

finish(true, null, ["id" => $id]);
