<?php
/**
 * TOTP 2FA Setup Endpoint
 *
 * Actions:
 *   setup    - Generate new TOTP secret and QR code
 *   enable   - Verify code and activate TOTP
 *   disable  - Deactivate TOTP (requires current password)
 *   status   - Check if TOTP is enabled
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TotpService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH, $bCMS) {
    $totpService = new TotpService($DBLIB);
    $action = $_POST['action'] ?? 'status';
    $userId = $AUTH->data['users_userid'];

    switch ($action) {
        case 'status':
            $enabled = $totpService->isTotpEnabled($userId);
            finish(true, null, ['totp_enabled' => $enabled]);
            break;

        case 'setup':
            // Generate a new secret (don't save yet until verified)
            $secret = $totpService->generateSecret();
            $uri = $totpService->getProvisioningUri($secret, $AUTH->data['users_email']);
            $qrUrl = $totpService->generateQrCode($uri);
            finish(true, null, [
                'secret' => $secret,
                'qr_url' => $qrUrl,
                'provisioning_uri' => $uri,
            ]);
            break;

        case 'enable':
            $secret = $_POST['secret'] ?? '';
            $code = $_POST['code'] ?? '';
            if (empty($secret) || empty($code)) {
                finish(false, ['message' => 'Secret und Code erforderlich']);
            }
            // Verify the code against the provided secret
            if (!$totpService->verifyCode($secret, $code)) {
                finish(false, ['message' => 'Ungueltiger Code. Bitte erneut versuchen.']);
            }
            // Activate TOTP
            $totpService->enableTotp($userId, $secret);
            // Generate backup codes
            $backupCodes = $totpService->generateBackupCodes(8);
            $totpService->storeBackupCodes($userId, $backupCodes);
            $bCMS->auditLog("UPDATE", "users", "TOTP ENABLED", $userId, $userId);
            finish(true, null, [
                'message' => 'Zwei-Faktor-Authentifizierung aktiviert',
                'backup_codes' => $backupCodes,
            ]);
            break;

        case 'disable':
            // Require current password to disable
            $password = $_POST['password'] ?? '';
            if (empty($password)) {
                finish(false, ['message' => 'Aktuelles Passwort erforderlich']);
            }
            $hash = hash($AUTH->data['users_hash'], $AUTH->data['users_salty1'] . $password . $AUTH->data['users_salty2']);
            if ($hash !== $AUTH->data['users_password']) {
                finish(false, ['message' => 'Falsches Passwort']);
            }
            $totpService->disableTotp($userId);
            $bCMS->auditLog("UPDATE", "users", "TOTP DISABLED", $userId, $userId);
            finish(true, null, ['message' => 'Zwei-Faktor-Authentifizierung deaktiviert']);
            break;

        default:
            finish(false, ['message' => 'Unbekannte Aktion']);
    }
}, 'TOTP Setup');
