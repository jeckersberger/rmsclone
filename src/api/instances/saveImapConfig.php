<?php
/**
 * IMAP-Konfiguration speichern (pro Instance)
 *
 * POST-Parameter: key-value Paare der IMAP-Konfiguration
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:SETTINGS") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$allowedKeys = [
    'IMAP_ENABLED', 'IMAP_SERVER', 'IMAP_PORT', 'IMAP_ENCRYPTION',
    'IMAP_USERNAME', 'IMAP_PASSWORD', 'IMAP_FOLDER', 'IMAP_PROCESS_ATTACHMENTS',
];

$saved = 0;
$errors = [];

foreach ($allowedKeys as $key) {
    if (!isset($_POST[$key])) continue;

    $value = trim($_POST[$key]);

    // Validierung
    if ($key === 'IMAP_ENABLED' && !in_array($value, ['Enabled', 'Disabled'])) {
        $errors[] = $key . ': Ungueltig';
        continue;
    }
    if ($key === 'IMAP_ENCRYPTION' && !in_array($value, ['None', 'SSL', 'TLS'])) {
        $errors[] = $key . ': Ungueltig';
        continue;
    }
    if ($key === 'IMAP_PORT') {
        $value = max(1, min(65535, (int)$value));
        $value = (string)$value;
    }
    if ($key === 'IMAP_PROCESS_ATTACHMENTS' && !in_array($value, ['Enabled', 'Disabled'])) {
        $errors[] = $key . ': Ungueltig';
        continue;
    }

    // Upsert in config Tabelle
    $DBLIB->where('config_key', $key);
    $existing = $DBLIB->getOne('config', ['config_key']);

    if ($existing) {
        $DBLIB->where('config_key', $key);
        $result = $DBLIB->update('config', ['config_value' => $value]);
    } else {
        $result = $DBLIB->insert('config', [
            'config_key' => $key,
            'config_value' => $value,
        ]);
    }

    if ($result) {
        $saved++;
    } else {
        $errors[] = $key . ': Speichern fehlgeschlagen';
    }
}

$bCMS->auditLog("UPDATE", "config", "IMAP settings updated ({$saved} fields)", $AUTH->data['users_userid']);

if (count($errors) > 0) {
    finish(false, ["message" => implode(', ', $errors), "saved" => $saved]);
} else {
    finish(true, null, ["saved" => $saved]);
}
