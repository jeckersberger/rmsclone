<?php
/**
 * Cron-Job: KI-Hintergrund-Automatisierung
 *
 * Aufruf: php src/cron/ai-background.php
 * Empfohlener Crontab-Eintrag: every 10 min - php /path/to/src/cron/ai-background.php >> /var/log/myrms-cron.log 2>&1
 *
 * REGELN:
 *   - Eingehend (Mails einsortieren, Kategorisieren) = automatisch, ohne Bestaetigung
 *   - Ausgehend (E-Mails an Kunden, Mahnungen senden) = in Queue, IMMER Bestaetigung noetig
 *   - Wartungsintervalle = automatisch pruefen, Warnung intern anzeigen
 *
 * Tasks:
 *   1. Neue E-Mails analysieren und automatisch zuordnen (Kunde + Projekt)
 *   2. Wartungsintervalle pruefen und Warnungen erzeugen
 *   3. Ueberfaellige Rechnungen: Mahnung VORSCHLAGEN (in Queue, nicht senden)
 *   4. Projekt-Zusammenfassungen bei Status-Aenderungen generieren
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/ClaudeService.php';
require_once __DIR__ . '/../services/AiActionQueueService.php';
require_once __DIR__ . '/../services/MaintenanceScheduleService.php';

echo "[" . date('Y-m-d H:i:s') . "] AI background processing started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalActions = 0;

foreach ($instances as $inst) {
    $instanceId = (int)$inst['instances_id'];

    $claude = new ClaudeService($DBLIB, $instanceId);
    if (!$claude->isAvailable()) {
        continue;
    }

    $queue = new AiActionQueueService($DBLIB, $instanceId);
    $startTime = microtime(true);
    $actions = 0;

    echo "  [*] {$inst['instances_name']}: Verarbeitung gestartet\n";

    // ═══════════════════════════════════════════════════════
    // 1. NEUE E-MAILS ANALYSIEREN UND ZUORDNEN (AUTOMATISCH)
    // ═══════════════════════════════════════════════════════
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('ai_processed', 0);
    $DBLIB->where('emailReceived_deleted', 0);
    $DBLIB->orderBy('emailReceived_date', 'ASC');
    $unprocessedEmails = $DBLIB->get('emailReceived', 20, [
        'emailReceived_id', 'emailReceived_from', 'emailReceived_fromName',
        'emailReceived_subject', 'emailReceived_body', 'emailReceived_date',
        'clients_id', 'projects_id'
    ]) ?: [];

    if (count($unprocessedEmails) > 0) {
        // Kunden und Projekte laden fuer Kontext
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('clients_deleted', 0);
        $clients = $DBLIB->get('clients', null, ['clients_id', 'clients_name', 'clients_email']) ?: [];
        $clientMap = [];
        foreach ($clients as $c) {
            $clientMap[strtolower($c['clients_email'] ?? '')] = $c;
        }

        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('projects_deleted', 0);
        $DBLIB->where('projects_archived', 0);
        $projects = $DBLIB->get('projects', null, ['projects_id', 'projects_name', 'clients_id']) ?: [];

        foreach ($unprocessedEmails as $email) {
            // a) Kunde anhand E-Mail-Adresse zuordnen (ohne KI - schnell)
            $fromEmail = strtolower(trim($email['emailReceived_from'] ?? ''));
            $matchedClient = null;
            $matchedProject = null;

            if ($fromEmail && isset($clientMap[$fromEmail])) {
                $matchedClient = $clientMap[$fromEmail];
            } else {
                // Domain-Match versuchen
                $fromDomain = substr($fromEmail, strpos($fromEmail, '@') + 1);
                foreach ($clientMap as $ce => $c) {
                    if ($ce && substr($ce, strpos($ce, '@') + 1) === $fromDomain) {
                        $matchedClient = $c;
                        break;
                    }
                }
            }

            // b) Projekt zuordnen (anhand Kunde oder Betreff)
            if ($matchedClient) {
                foreach ($projects as $p) {
                    if ((int)$p['clients_id'] === (int)$matchedClient['clients_id']) {
                        $matchedProject = $p;
                        break;
                    }
                }
            }

            // c) KI-Analyse fuer Kategorisierung und Zusammenfassung
            $emailBody = strip_tags(substr($email['emailReceived_body'] ?? '', 0, 2000));
            $subject = $email['emailReceived_subject'] ?? '(Kein Betreff)';

            $aiResult = $claude->ask(
                'email_draft', // Re-use email feature flag
                "Du bist ein E-Mail-Analyse-Assistent fuer ein Verleih-Unternehmen. "
                . "Analysiere die eingehende E-Mail und gib ein JSON-Objekt zurueck mit:\n"
                . "- category: 'anfrage' | 'buchung' | 'reklamation' | 'rechnung' | 'allgemein' | 'spam'\n"
                . "- priority: 'hoch' | 'mittel' | 'niedrig'\n"
                . "- summary: Kurze Zusammenfassung in 1-2 Saetzen (Deutsch)\n"
                . "- needs_response: true/false\n"
                . "Antworte NUR mit dem JSON, kein anderer Text.",
                "Von: {$email['emailReceived_fromName']} <{$email['emailReceived_from']}>\n"
                . "Betreff: {$subject}\n\n{$emailBody}"
            );

            $aiText = ClaudeService::extractText($aiResult);
            $parsed = json_decode($aiText, true);

            // d) Updates zusammenstellen
            $updates = [
                'ai_processed' => 1,
                'ai_processed_at' => date('Y-m-d H:i:s'),
            ];

            if ($parsed) {
                $updates['ai_category'] = $parsed['category'] ?? null;
                $updates['ai_priority'] = $parsed['priority'] ?? null;
                $updates['ai_summary'] = $parsed['summary'] ?? null;
            }

            if ($matchedClient && empty($email['clients_id'])) {
                $updates['clients_id'] = (int)$matchedClient['clients_id'];
            }
            if ($matchedProject && empty($email['projects_id'])) {
                $updates['projects_id'] = (int)$matchedProject['projects_id'];
            }

            $DBLIB->where('emailReceived_id', $email['emailReceived_id']);
            $DBLIB->update('emailReceived', $updates);

            // e) In Queue loggen (automatisch, kein Approval noetig)
            $queue->enqueue(
                'categorize_email',
                "E-Mail kategorisiert: {$subject}",
                ($parsed['summary'] ?? 'Verarbeitet') . ($matchedClient ? " | Kunde: {$matchedClient['clients_name']}" : ''),
                [
                    'email_id' => $email['emailReceived_id'],
                    'category' => $parsed['category'] ?? null,
                    'priority' => $parsed['priority'] ?? null,
                    'summary' => $parsed['summary'] ?? null,
                    'client_id' => $matchedClient ? (int)$matchedClient['clients_id'] : null,
                    'project_id' => $matchedProject ? (int)$matchedProject['projects_id'] : null,
                ],
                'email',
                $email['emailReceived_id']
            );

            $actions++;
            echo "    [+] E-Mail kategorisiert: {$subject} -> " . ($parsed['category'] ?? '?') . "\n";
        }
    }

    // ═══════════════════════════════════════════════════════
    // 2. WARTUNGSINTERVALLE PRUEFEN (AUTOMATISCH WARNEN)
    // ═══════════════════════════════════════════════════════
    $maintenance = new MaintenanceScheduleService($DBLIB);
    $dueItems = $maintenance->getDueMaintenance($instanceId);

    foreach ($dueItems as $item) {
        // Pruefen ob schon eine aktive Warnung existiert
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('action_type', 'flag_maintenance');
        $DBLIB->where('related_type', 'asset');
        $DBLIB->where('related_id', $item['assets_id']);
        $DBLIB->where('status', ['auto_executed', 'pending'], 'IN');
        $DBLIB->where('created_at', date('Y-m-d', strtotime('-7 days')) . ' 00:00:00', '>=');
        $existing = $DBLIB->getValue('ai_action_queue', 'COUNT(*)');

        if ((int)$existing > 0) continue;

        $queue->enqueue(
            'flag_maintenance',
            "Wartung faellig: {$item['assetTypes_name']} ({$item['assets_tag']})",
            "Letzte Wartung vor {$item['days_since_maintenance']} Tagen. Intervall: {$item['assetTypes_maintenanceInterval']} Tage.",
            [
                'asset_id' => $item['assets_id'],
                'asset_tag' => $item['assets_tag'],
                'type_name' => $item['assetTypes_name'],
                'days_since' => $item['days_since_maintenance'],
                'interval' => $item['assetTypes_maintenanceInterval'],
            ],
            'asset',
            $item['assets_id']
        );
        $actions++;
        echo "    [!] Wartung faellig: {$item['assetTypes_name']} ({$item['assets_tag']}) - {$item['days_since_maintenance']} Tage\n";
    }

    // ═══════════════════════════════════════════════════════
    // 3. UEBERFAELLIGE RECHNUNGEN -> MAHNUNG VORSCHLAGEN (IN QUEUE)
    // ═══════════════════════════════════════════════════════
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('doc_type', 'invoice');
    $DBLIB->where('status', 'overdue');
    $DBLIB->where('due_date IS NOT NULL');
    $DBLIB->join('projects', 'document_lifecycle.projects_id=projects.projects_id', 'LEFT');
    $DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
    $overdueInvoices = $DBLIB->get('document_lifecycle', null, [
        'document_lifecycle.id', 'document_lifecycle.doc_number', 'document_lifecycle.due_date',
        'document_lifecycle.total_gross',
        'clients.clients_name', 'clients.clients_email', 'clients.clients_id'
    ]) ?: [];

    foreach ($overdueInvoices as $inv) {
        $daysOverdue = (int)((time() - strtotime($inv['due_date'])) / 86400);

        // Pruefen ob schon eine Mahnung vorgeschlagen wurde
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('action_type', ['send_reminder', 'send_dunning'], 'IN');
        $DBLIB->where('related_type', 'invoice');
        $DBLIB->where('related_id', $inv['id']);
        $DBLIB->where('status', ['pending', 'approved', 'executed'], 'IN');
        $existing = $DBLIB->getValue('ai_action_queue', 'COUNT(*)');

        if ((int)$existing > 0) continue;

        // KI-generierte Mahnung erstellen
        $dueDate = date('d.m.Y', strtotime($inv['due_date']));
        $amount = number_format((float)($inv['total_gross'] ?? 0), 2, ',', '.');

        if ($daysOverdue >= 7 && !empty($inv['clients_email'])) {
            $aiResult = $claude->ask(
                'email_draft',
                "Du schreibst hoefliche, professionelle Zahlungserinnerungen fuer ein deutsches Verleih-Unternehmen. "
                . "Schreibe eine freundliche Zahlungserinnerung. Gib NUR den E-Mail-Body als HTML zurueck (kein Betreff, keine Anrede-Formel am Anfang).",
                "Rechnung: {$inv['doc_number']}\nKunde: {$inv['clients_name']}\nBetrag: {$amount} EUR\n"
                . "Faellig seit: {$dueDate} ({$daysOverdue} Tage ueberfaellig)\n\n"
                . "Schreibe eine freundliche Zahlungserinnerung."
            );

            $emailBody = ClaudeService::extractText($aiResult);

            // Ab in die Queue - User muss bestaetigen!
            $queue->enqueue(
                'send_reminder',
                "Zahlungserinnerung: {$inv['doc_number']} ({$inv['clients_name']})",
                "{$amount} EUR, faellig seit {$dueDate} ({$daysOverdue} Tage). Entwurf zur Pruefung bereit.",
                [
                    'invoice_id' => $inv['id'],
                    'doc_number' => $inv['doc_number'],
                    'to' => $inv['clients_email'],
                    'to_name' => $inv['clients_name'],
                    'subject' => "Zahlungserinnerung - Rechnung {$inv['doc_number']}",
                    'body' => $emailBody,
                    'amount' => $amount,
                    'days_overdue' => $daysOverdue,
                    'dunning_level' => $daysOverdue >= 30 ? 1 : 0,
                ],
                'invoice',
                $inv['id']
            );

            $actions++;
            echo "    [>] Zahlungserinnerung vorgeschlagen: {$inv['doc_number']} -> {$inv['clients_email']} ({$daysOverdue} Tage)\n";
        }
    }

    // ═══════════════════════════════════════════════════════
    // 4. CRON-RUN PROTOKOLLIEREN
    // ═══════════════════════════════════════════════════════
    $elapsed = round(microtime(true) - $startTime, 3);
    $totalActions += $actions;

    if ($actions > 0) {
        $DBLIB->insert('cron_runs', [
            'instances_id' => $instanceId,
            'job_type' => 'ai_background',
            'status' => 'success',
            'items_processed' => $actions,
            'details_json' => json_encode([
                'emails_processed' => count($unprocessedEmails),
                'maintenance_flags' => count($dueItems),
                'dunning_suggestions' => count($overdueInvoices),
                'elapsed_seconds' => $elapsed,
            ]),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    echo "  [=] {$inst['instances_name']}: {$actions} Aktionen ({$elapsed}s)\n";
}

echo "[" . date('Y-m-d H:i:s') . "] AI background done. {$totalActions} total actions.\n";
