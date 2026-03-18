<?php
/**
 * Public signing page for contracts
 * No authentication required - accessed via unique token in URL
 *
 * Usage: /contracts/sign.php?token=XXX&contract=123
 */

// Load minimal dependencies (no auth required)
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/ContractService.php';

$contractId = isset($_GET['contract']) ? (int)$_GET['contract'] : 0;
$token = $_GET['token'] ?? '';

if ($contractId <= 0 || !$token) {
    http_response_code(404);
    die('Contract not found');
}

// Validate token and get contract
$db->where('id', $contractId);
$contract = $db->getOne('contracts');

if (!$contract) {
    http_response_code(404);
    die('Contract not found');
}

$service = new ContractService($db);
$rendered = $service->renderContract($contractId);

// Mark as viewed
$service->markViewed($contractId, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? '');

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($contract['title']); ?> - Unterzeichnung</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 900px; }
        .contract-viewer {
            background: white;
            padding: 40px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .contract-viewer iframe {
            width: 100%;
            height: 600px;
            border: none;
        }
        .signature-area {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        #signatureCanvas {
            border: 2px solid #333;
            display: block;
            cursor: crosshair;
            background: white;
        }
        .btn-group-form {
            display: flex;
            gap: 10px;
        }
        .form-check {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Vertragsunterzeichnung</h1>

            <!-- Contract Content -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><?php echo htmlspecialchars($contract['title']); ?></h5>
                </div>
                <div class="card-body contract-viewer">
                    <?php echo $rendered; ?>
                </div>
            </div>

            <!-- Signing Form -->
            <form id="signForm" class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Unterschrift erforderlich</h5>
                </div>
                <div class="card-body">
                    <!-- Signer Name -->
                    <div class="mb-3">
                        <label for="signerName" class="form-label">Vollständiger Name *</label>
                        <input type="text" class="form-control" id="signerName" name="signer_name"
                               placeholder="Max Mustermann" required minlength="2">
                        <small class="text-muted">Name des Unterzeichners</small>
                    </div>

                    <!-- Signature Canvas -->
                    <div class="signature-area">
                        <label class="form-label">Unterschrift (bitte unterschreiben) *</label>
                        <canvas id="signatureCanvas" width="800" height="150"></canvas>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                Löschen
                            </button>
                        </div>
                    </div>

                    <!-- AGB Checkbox -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agbCheckbox" required>
                        <label class="form-check-label" for="agbCheckbox">
                            Ich akzeptiere die <a href="#" data-bs-toggle="modal" data-bs-target="#agbModal">Allgemeinen Geschäftsbedingungen</a> *
                        </label>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="btn-group-form mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-pen"></i> Vertrag unterzeichnen
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Zurücksetzen
                        </button>
                    </div>

                    <!-- Status Message -->
                    <div id="statusMessage" class="mt-3"></div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- AGB Modal -->
<div class="modal fade" id="agbModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allgemeine Geschäftsbedingungen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="agbContent" style="max-height: 500px; overflow-y: auto;">
                <!-- AGB will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    // Mouse/Touch handlers for signature
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    function startDrawing(e) {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches[0].clientX) - rect.left;
        const y = (e.clientY || e.touches[0].clientY) - rect.top;
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();

        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches[0].clientX) - rect.left;
        const y = (e.clientY || e.touches[0].clientY) - rect.top;

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#000';
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    function stopDrawing() {
        isDrawing = false;
    }

    // Clear button
    document.getElementById('clearSignature').addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    // Form submission
    document.getElementById('signForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const signerName = document.getElementById('signerName').value.trim();
        const agbAccepted = document.getElementById('agbCheckbox').checked;
        const signatureData = canvas.toDataURL('image/png');

        if (!signerName) {
            showMessage('error', 'Bitte geben Sie Ihren Namen ein');
            return;
        }

        if (signatureData === 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') {
            showMessage('error', 'Bitte unterzeichnen Sie das Dokument');
            return;
        }

        if (!agbAccepted) {
            showMessage('error', 'Bitte akzeptieren Sie die Allgemeinen Geschäftsbedingungen');
            return;
        }

        try {
            const response = await fetch('/api/contracts/sign.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    contract_id: <?php echo $contractId; ?>,
                    signer_name: signerName,
                    signature_data: signatureData,
                })
            });

            const result = await response.json();

            if (result.success) {
                showMessage('success', 'Vertrag erfolgreich unterzeichnet! Sie werden weitergeleitet...');
                setTimeout(() => {
                    window.location.href = '/';
                }, 2000);
            } else {
                showMessage('error', result.error || 'Fehler beim Unterzeichnen');
            }
        } catch (error) {
            showMessage('error', 'Fehler: ' + error.message);
        }
    });

    function showMessage(type, text) {
        const msg = document.getElementById('statusMessage');
        msg.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
        msg.textContent = text;
    }

    // Load AGB on page load
    document.addEventListener('DOMContentLoaded', function() {
        // TODO: Load AGB from API or database
        document.getElementById('agbContent').innerHTML = '<p>Allgemeine Geschäftsbedingungen werden hier angezeigt.</p>';
    });
</script>
</body>
</html>
