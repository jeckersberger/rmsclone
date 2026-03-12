<?php
/**
 * Eingehende E-Mails auflisten
 *
 * GET-Parameter:
 *   page    - Seite (default 1)
 *   limit   - Eintraege pro Seite (default 25, max 100)
 *   unread  - Nur ungelesene (1/0)
 *   search  - Suchbegriff (Betreff, Absender)
 *   from    - Datum ab (Y-m-d)
 *   to      - Datum bis (Y-m-d)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

$DBLIB->where('instances_id', $instanceId);

// Filter: nur ungelesene
if (isset($_GET['unread']) && $_GET['unread'] === '1') {
    $DBLIB->where('emailReceived_isRead', 0);
}

// Filter: Suche
if (!empty($_GET['search'])) {
    $search = '%' . $DBLIB->escape(trim($_GET['search'])) . '%';
    $DBLIB->where("(emailReceived_subject LIKE ? OR emailReceived_fromEmail LIKE ? OR emailReceived_fromName LIKE ?)", [$search, $search, $search]);
}

// Filter: Datum
if (!empty($_GET['from'])) {
    $DBLIB->where('emailReceived_date', $_GET['from'], '>=');
}
if (!empty($_GET['to'])) {
    $DBLIB->where('emailReceived_date', $_GET['to'] . ' 23:59:59', '<=');
}

// Gesamtanzahl fuer Paginierung
$DBLIB->where('instances_id', $instanceId);
$totalCount = $DBLIB->getValue('emailReceived', 'count(*)');

// Ergebnisse
$DBLIB->where('instances_id', $instanceId);
if (isset($_GET['unread']) && $_GET['unread'] === '1') {
    $DBLIB->where('emailReceived_isRead', 0);
}
if (!empty($_GET['search'])) {
    $search = '%' . $DBLIB->escape(trim($_GET['search'])) . '%';
    $DBLIB->where("(emailReceived_subject LIKE ? OR emailReceived_fromEmail LIKE ? OR emailReceived_fromName LIKE ?)", [$search, $search, $search]);
}
if (!empty($_GET['from'])) {
    $DBLIB->where('emailReceived_date', $_GET['from'], '>=');
}
if (!empty($_GET['to'])) {
    $DBLIB->where('emailReceived_date', $_GET['to'] . ' 23:59:59', '<=');
}

$DBLIB->orderBy('emailReceived_date', 'DESC');
$emails = $DBLIB->get('emailReceived', [$offset, $limit], [
    'emailReceived_id',
    'emailReceived_fromEmail',
    'emailReceived_fromName',
    'emailReceived_toEmail',
    'emailReceived_subject',
    'emailReceived_date',
    'emailReceived_isRead',
    'emailReceived_isProcessed',
    'emailReceived_hasAttachments',
    'emailReceived_folder',
    'clients_id',
    'projects_id',
]);

finish(true, null, [
    'emails' => $emails ?: [],
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => (int)$totalCount,
        'pages' => ceil((int)$totalCount / $limit),
    ],
]);
