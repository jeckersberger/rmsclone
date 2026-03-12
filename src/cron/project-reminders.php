<?php
/**
 * Cron: Projekt-Erinnerungen und Feedback-Anfragen
 *
 * Ausfuehrung: taeglich um 08:00
 * crontab: 0 8 * * * php /path/to/src/cron/project-reminders.php
 */
require_once __DIR__ . '/../common/libs/config.php';

$logPrefix = '[CRON:ProjectReminders]';

try {
    require_once __DIR__ . '/../services/ProjectNotificationService.php';
    require_once __DIR__ . '/../services/EmailTemplateService.php';

    $notificationService = new ProjectNotificationService($DBLIB);

    // 1. Erinnerungen 3 Tage vor Start
    echo "$logPrefix Sende Termin-Erinnerungen (3 Tage vorher)...\n";
    $result = $notificationService->sendReminders(3);
    echo "$logPrefix Erinnerungen gesendet: {$result['sent']}\n";
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) {
            error_log("$logPrefix Erinnerungs-Fehler: $err");
        }
    }

    // 2. Erinnerungen 1 Tag vor Start
    echo "$logPrefix Sende Termin-Erinnerungen (1 Tag vorher)...\n";
    $result = $notificationService->sendReminders(1);
    echo "$logPrefix Erinnerungen gesendet: {$result['sent']}\n";

    // 3. Feedback-Anfragen 2 Tage nach Ende
    echo "$logPrefix Sende Feedback-Anfragen...\n";
    $result = $notificationService->sendFeedbackRequests(2);
    echo "$logPrefix Feedback-Anfragen gesendet: {$result['sent']}\n";
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) {
            error_log("$logPrefix Feedback-Fehler: $err");
        }
    }

    echo "$logPrefix Fertig.\n";
} catch (Exception $e) {
    error_log("$logPrefix Fehler: " . $e->getMessage());
    echo "$logPrefix FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
