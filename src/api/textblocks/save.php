<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$blockId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$title = htmlspecialchars(strip_tags(trim($_POST['title'] ?? '')), ENT_QUOTES, 'UTF-8');
$content = trim($_POST['content'] ?? '');
$category = htmlspecialchars(strip_tags(trim($_POST['category'] ?? 'custom')), ENT_QUOTES, 'UTF-8');
$docTypes = htmlspecialchars(strip_tags(trim($_POST['doc_types'] ?? 'quote,invoice')), ENT_QUOTES, 'UTF-8');
$sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$isDefault = (int)($_POST['is_default'] ?? 0);

if (!$title || strlen($title) < 2) finish(false, ["code" => "INVALID", "message" => "Title required (min 2 chars)"]);
if (!$content) finish(false, ["code" => "INVALID", "message" => "Content required"]);

$validCategories = ['greeting', 'scope', 'terms', 'closing', 'note', 'custom'];
if (!in_array($category, $validCategories)) $category = 'custom';

$data = [
    'instances_id' => $instanceId,
    'category' => $category,
    'title' => $title,
    'content' => $content,
    'doc_types' => $docTypes,
    'sort_order' => $sortOrder,
    'is_default' => $isDefault ? 1 : 0,
];

if ($blockId) {
    // Update existing
    $DBLIB->where('id', $blockId);
    $DBLIB->where('instances_id', $instanceId);
    $result = $DBLIB->update('text_blocks', $data);
} else {
    // Create new
    $data['created_by'] = $AUTH->data['users_userid'];
    $result = $DBLIB->insert('text_blocks', $data);
    $blockId = $DBLIB->getInsertId();
}

if ($result) {
    $bCMS->auditLog("TEXTBLOCK-SAVE", "text_blocks", json_encode(['id' => $blockId, 'title' => $title]), $AUTH->data['users_userid'], null, $instanceId);
    finish(true, null, ['id' => $blockId]);
} else {
    finish(false, ["code" => "SAVE_FAILED"]);
}
