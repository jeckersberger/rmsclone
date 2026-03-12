<?php
/**
 * AI Action Queue API
 *
 * GET-Parameter (via POST):
 *   action = 'list' | 'pending' | 'stats' | 'approve' | 'reject' | 'activity'
 *
 * Fuer approve/reject:
 *   id = Queue-ID
 *   modifications = JSON (optional, nur fuer approve - z.B. geaenderter E-Mail-Text)
 *   reason = String (optional, nur fuer reject)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/AiActionQueueService.php';

$instanceId = $AUTH->data['instance']['instances_id'];
$queue = new AiActionQueueService($DBLIB, $instanceId);

$action = $_POST['action'] ?? 'pending';

switch ($action) {
    case 'pending':
        $items = $queue->getPending((int)($_POST['limit'] ?? 20));
        foreach ($items as &$item) {
            $item['payload'] = json_decode($item['payload_json'] ?? '{}', true);
            unset($item['payload_json']);
        }
        finish(true, null, ['items' => $items, 'count' => $queue->getPendingCount()]);
        break;

    case 'stats':
        finish(true, null, $queue->getStats());
        break;

    case 'activity':
        $items = $queue->getRecentActivity((int)($_POST['limit'] ?? 50));
        foreach ($items as &$item) {
            $item['payload'] = json_decode($item['payload_json'] ?? '{}', true);
            $item['result'] = json_decode($item['result_json'] ?? '{}', true);
            unset($item['payload_json'], $item['result_json']);
        }
        finish(true, null, ['items' => $items]);
        break;

    case 'approve':
        $queueId = (int)($_POST['id'] ?? 0);
        if ($queueId <= 0) finish(false, ["code" => "INVALID_ID"]);

        $modifications = null;
        if (!empty($_POST['modifications'])) {
            $modifications = json_decode($_POST['modifications'], true);
        }

        $userId = $AUTH->data['users_userid'] ?? 0;
        $result = $queue->approve($queueId, $userId, $modifications);
        finish($result, $result ? null : ["code" => "APPROVE_FAILED", "message" => "Genehmigung fehlgeschlagen"]);
        break;

    case 'reject':
        $queueId = (int)($_POST['id'] ?? 0);
        if ($queueId <= 0) finish(false, ["code" => "INVALID_ID"]);

        $userId = $AUTH->data['users_userid'] ?? 0;
        $reason = $_POST['reason'] ?? null;
        $result = $queue->reject($queueId, $userId, $reason);
        finish($result, $result ? null : ["code" => "REJECT_FAILED"]);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
