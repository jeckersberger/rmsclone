<?php
/**
 * Gesendete E-Mails auflisten
 *
 * GET-Parameter:
 *   page    - Seite (default 1)
 *   limit   - Eintraege pro Seite (default 25, max 100)
 *   search  - Suchbegriff (Betreff, Empfaenger)
 *   from    - Datum ab (Y-m-d)
 *   to      - Datum bis (Y-m-d)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_OUTBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

// Benutzer dieser Instanz ermitteln
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('userInstances_deleted', 0);
$instanceUsers = $DBLIB->get('userInstances', null, ['users_userid']);
$userIds = array_column($instanceUsers ?: [], 'users_userid');

if (empty($userIds)) {
    finish(true, null, [
        'emails' => [],
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'pages' => 0],
    ]);
}

$DBLIB->where('users_userid', $userIds, 'IN');

// Filter: Suche
if (!empty($_GET['search'])) {
    $search = '%' . $DBLIB->escape(trim($_GET['search'])) . '%';
    $DBLIB->where("(emailSent_subject LIKE ? OR emailSent_toEmail LIKE ? OR emailSent_toName LIKE ?)", [$search, $search, $search]);
}

// Filter: Datum
if (!empty($_GET['from'])) {
    $DBLIB->where('emailSent_sent', $_GET['from'], '>=');
}
if (!empty($_GET['to'])) {
    $DBLIB->where('emailSent_sent', $_GET['to'] . ' 23:59:59', '<=');
}

// Gesamtanzahl
$DBLIB->where('users_userid', $userIds, 'IN');
if (!empty($_GET['search'])) {
    $search = '%' . $DBLIB->escape(trim($_GET['search'])) . '%';
    $DBLIB->where("(emailSent_subject LIKE ? OR emailSent_toEmail LIKE ? OR emailSent_toName LIKE ?)", [$search, $search, $search]);
}
if (!empty($_GET['from'])) {
    $DBLIB->where('emailSent_sent', $_GET['from'], '>=');
}
if (!empty($_GET['to'])) {
    $DBLIB->where('emailSent_sent', $_GET['to'] . ' 23:59:59', '<=');
}
$totalCount = $DBLIB->getValue('emailSent', 'count(*)');

// Ergebnisse
$DBLIB->where('users_userid', $userIds, 'IN');
if (!empty($_GET['search'])) {
    $search = '%' . $DBLIB->escape(trim($_GET['search'])) . '%';
    $DBLIB->where("(emailSent_subject LIKE ? OR emailSent_toEmail LIKE ? OR emailSent_toName LIKE ?)", [$search, $search, $search]);
}
if (!empty($_GET['from'])) {
    $DBLIB->where('emailSent_sent', $_GET['from'], '>=');
}
if (!empty($_GET['to'])) {
    $DBLIB->where('emailSent_sent', $_GET['to'] . ' 23:59:59', '<=');
}
$DBLIB->orderBy('emailSent_sent', 'DESC');
$emails = $DBLIB->get('emailSent', [$offset, $limit], [
    'emailSent_id',
    'emailSent_subject',
    'emailSent_sent',
    'emailSent_fromEmail',
    'emailSent_fromName',
    'emailSent_toEmail',
    'emailSent_toName',
    'users_userid',
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
