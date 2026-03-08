<?php
/**
 * Mobile Packauftrag-Ansicht
 *
 * Wird via QR-Code auf dem Lieferschein aufgerufen.
 * Token-basierter Zugriff ohne Login.
 */
require_once __DIR__ . '/../common/libs/config.php';
require_once __DIR__ . '/../services/DeliveryNoteQrService.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    die('Token fehlt');
}

$qrService = new DeliveryNoteQrService($DBLIB);
$deliveryNoteId = $qrService->validateToken($token);

if (!$deliveryNoteId) {
    http_response_code(403);
    die('Ungueltiger oder abgelaufener Link');
}

$data = $qrService->getPackingListData($deliveryNoteId);
if (!$data) {
    http_response_code(404);
    die('Lieferschein nicht gefunden');
}

$note = $data['delivery_note'];
$items = $data['items'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <title>Packauftrag - <?= htmlspecialchars($note['document_number'] ?? '') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #007bff; color: white; padding: 16px; position: sticky; top: 0; z-index: 10; }
        .header h1 { font-size: 18px; margin-bottom: 4px; }
        .header .meta { font-size: 13px; opacity: 0.9; }
        .info { background: white; padding: 16px; margin: 8px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .info h2 { font-size: 15px; color: #666; margin-bottom: 8px; }
        .info p { font-size: 14px; margin: 4px 0; }
        .items { margin: 8px; }
        .item { background: white; padding: 16px; margin-bottom: 8px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background 0.2s; }
        .item.checked { background: #e8f5e9; }
        .item .checkbox { width: 28px; height: 28px; border: 2px solid #ccc; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .item.checked .checkbox { background: #4caf50; border-color: #4caf50; color: white; }
        .item .details { flex: 1; }
        .item .name { font-size: 15px; font-weight: 600; }
        .item .tag { font-size: 13px; color: #888; margin-top: 2px; }
        .progress-bar { background: white; padding: 12px 16px; margin: 8px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .progress-bar .bar { height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
        .progress-bar .fill { height: 100%; background: #4caf50; transition: width 0.3s; border-radius: 4px; }
        .progress-bar .text { font-size: 13px; color: #666; margin-top: 6px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Packauftrag</h1>
        <div class="meta"><?= htmlspecialchars($note['document_number'] ?? 'Lieferschein') ?> &bull; <?= htmlspecialchars($note['projects_name'] ?? '') ?></div>
    </div>

    <div class="info">
        <h2>Kunde</h2>
        <p><strong><?= htmlspecialchars($note['clients_name'] ?? '-') ?></strong></p>
        <p><?= nl2br(htmlspecialchars($note['clients_address'] ?? '')) ?></p>
    </div>

    <div class="progress-bar">
        <div class="bar"><div class="fill" id="progressFill" style="width: 0%"></div></div>
        <div class="text" id="progressText">0 / <?= count($items) ?> Positionen gepackt</div>
    </div>

    <div class="items" id="itemList">
        <?php foreach ($items as $i => $item): ?>
        <div class="item" data-index="<?= $i ?>" onclick="toggleItem(this)">
            <div class="checkbox"></div>
            <div class="details">
                <div class="name"><?= htmlspecialchars($item['assetTypes_name'] ?? 'Artikel') ?></div>
                <div class="tag"><?= htmlspecialchars($item['assets_tag'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        const totalItems = <?= count($items) ?>;
        let checkedCount = 0;

        // Zustand aus LocalStorage laden
        const stateKey = 'packing_<?= $deliveryNoteId ?>';
        const saved = JSON.parse(localStorage.getItem(stateKey) || '{}');
        document.querySelectorAll('.item').forEach(item => {
            if (saved[item.dataset.index]) {
                item.classList.add('checked');
                item.querySelector('.checkbox').textContent = '✓';
                checkedCount++;
            }
        });
        updateProgress();

        function toggleItem(el) {
            el.classList.toggle('checked');
            const isChecked = el.classList.contains('checked');
            el.querySelector('.checkbox').textContent = isChecked ? '✓' : '';
            checkedCount += isChecked ? 1 : -1;

            saved[el.dataset.index] = isChecked;
            localStorage.setItem(stateKey, JSON.stringify(saved));
            updateProgress();

            // Haptic Feedback
            if (navigator.vibrate) navigator.vibrate(isChecked ? 50 : 20);
        }

        function updateProgress() {
            const pct = totalItems > 0 ? Math.round(checkedCount / totalItems * 100) : 0;
            document.getElementById('progressFill').style.width = pct + '%';
            document.getElementById('progressText').textContent = checkedCount + ' / ' + totalItems + ' Positionen gepackt';
            if (checkedCount === totalItems && totalItems > 0) {
                document.getElementById('progressFill').style.background = '#2e7d32';
                document.getElementById('progressText').textContent = 'Alle Positionen gepackt!';
            }
        }
    </script>
</body>
</html>
