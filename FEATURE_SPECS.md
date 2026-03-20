# MyRMS – Feature-Spezifikationen (ausformuliert)

**Stand:** 20. März 2026
**Zweck:** Detaillierte Beschreibung aller geplanten und implementierten Module. Jeder Baustein wird ausformuliert: Was soll er tun, welche Logik steckt dahinter, welche Ein-/Ausgaben gibt es, wie verhält sich die UI.

---

## L1 – Flexible Lagerorte

### Überblick
Jedes Asset und jeder Lagerbestand soll einem frei definierbaren Lagerort zugeordnet werden können. Lagerorte sind nicht fest vorgegeben, sondern werden vom Benutzer selbst angelegt – z.B. „Auto 1", „Anhänger blau", „Büro EG", „Garage Hinten", „Keller Regal 3". Jeder Lagerort bekommt ein Emoji-Icon zur schnellen visuellen Unterscheidung. Assets bleiben weiterhin frei buchbar (Verleih, Projekt-Zuordnung etc.), der Lagerort ist eine zusätzliche Information, die überall angezeigt wird.

### Bausteine im Detail

**1. DB-Migration: emoji_icon + instances_id auf locations, bulk_move_log**
Die bestehende `locations`-Tabelle wird um ein `emoji_icon`-Feld (VARCHAR, z.B. „🚗" oder „📦") und eine `instances_id`-Spalte (Mandantentrennung) erweitert. Zusätzlich wird eine neue Tabelle `bulk_move_log` angelegt, die protokolliert, wann welche Assets oder Lagerbestände von einem Ort zum anderen verschoben wurden (Zeitstempel, User, Quell-Ort, Ziel-Ort, betroffene Asset-IDs).

**2. LocationService: getLocationSummary()**
Eine Methode, die für jeden Lagerort eine Zusammenfassung liefert: Anzahl der Assets am Ort, Anzahl der Lagerbestands-Einheiten, Gesamtwert der gelagerten Gegenstände, letzter Bewegungszeitpunkt. Diese Daten werden als Array zurückgegeben und im Dashboard sowie in der Lagerort-Verwaltung angezeigt.

**3. LocationService: bulkMoveAssets(), bulkMoveStockInstances()**
Ermöglicht das Verschieben mehrerer Assets oder Lagerbestände auf einmal von einem Lagerort zu einem anderen. Der Benutzer wählt z.B. „alle 15 Kabel von Auto 1 nach Anhänger blau verschieben". Jede Bulk-Verschiebung wird im `bulk_move_log` protokolliert. Die Methode prüft, ob alle ausgewählten Assets tatsächlich am Quell-Ort sind, und gibt Fehlermeldungen zurück, wenn ein Asset bereits woanders ist.

**4. LocationService: getAssetsAtLocation(), createLocationInline()**
`getAssetsAtLocation()` liefert eine paginierte Liste aller Assets an einem bestimmten Lagerort, inklusive Asset-Typ, Status und letzter Bewegung. `createLocationInline()` erlaubt das schnelle Anlegen eines neuen Lagerorts direkt aus einem Dropdown heraus (z.B. beim Zuweisen eines Assets tippt man einen neuen Lagerort-Namen ein und er wird sofort angelegt, ohne die Seite zu verlassen).

**5–8. API-Endpunkte (Location Summary, Bulk Move, Assets at Location, Inline Create)**
Vier REST-Endpunkte unter `/api/locations/`:
- `GET /api/locations/summary` – Gibt die Zusammenfassung aller Lagerorte zurück (Anzahl Assets, Wert, letzter Move).
- `POST /api/locations/bulk-move` – Nimmt eine Liste von Asset-IDs und einen Ziel-Lagerort entgegen, verschiebt alle auf einmal.
- `GET /api/locations/{id}/assets` – Liefert alle Assets an einem bestimmten Lagerort, paginiert, mit Suchfilter.
- `POST /api/locations/inline-create` – Erstellt einen neuen Lagerort mit Name und optionalem Emoji, gibt die neue ID zurück.

**9. UI: manage.twig mit Emoji-Icons und Asset-Counts**
Die Lagerort-Verwaltungsseite zeigt alle Lagerorte als Karten oder Liste an. Jede Karte zeigt: Emoji-Icon groß, Name des Lagerorts, Anzahl Assets, Anzahl Lagerbestände, Gesamtwert. Per Klick öffnet sich die Detail-Ansicht mit allen Assets an diesem Ort. Drag & Drop zum Verschieben von Assets zwischen Lagerorten wäre ein Nice-to-have.

**10. Lagerort-Spalte in Asset-Listen**
In allen bestehenden Asset-Tabellen (Inventar-Übersicht, Projekt-Assets, Suchergebnisse) soll eine neue Spalte „Lagerort" erscheinen, die den aktuellen Lagerort des Assets mit Emoji anzeigt. Die Spalte soll sortierbar und filterbar sein. Wenn kein Lagerort zugewiesen ist, bleibt die Zelle leer.

**11. Lagerort-Abfrage bei Check-In**
Wenn ein Asset von einem Projekt zurückkommt (Check-In), soll das System fragen: „An welchen Lagerort soll dieses Asset zurück?" Der Benutzer wählt aus einem Dropdown den Lagerort aus oder legt inline einen neuen an. Der vorherige Lagerort wird als Vorschlag angezeigt. So bleibt die Lagerort-Zuordnung immer aktuell.

**12. Dashboard-Widget: Lagerort-Zusammenfassung**
Ein Widget auf dem Haupt-Dashboard, das die wichtigsten Lagerort-Informationen auf einen Blick zeigt: Wie viele Lagerorte gibt es, wie viele Assets sind insgesamt verteilt, welcher Lagerort hat die meisten Assets, gibt es Assets ohne Lagerort-Zuordnung. Klick auf das Widget führt zur Lagerort-Verwaltung.

**13. Tests**
Unit-Tests für alle LocationService-Methoden: getLocationSummary() mit verschiedenen Datenkonstellationen, bulkMoveAssets() mit gültigen und ungültigen Szenarien (Asset nicht am Quell-Ort, Ziel-Ort existiert nicht), createLocationInline() mit Duplikat-Check, getAssetsAtLocation() mit Paginierung.

---

## L2 – Preiskalkulations-Engine

### Überblick
Eine vollständige Preiskalkulations-Engine, die verschiedene Preismodelle unterstützt: Staffelpreise (unterschiedliche Preise je nach Mietdauer – Tag/Woche/Monat), Mengenrabatte (ab X Stück günstiger), Saisonzuschläge (Hochsaison teurer), Bundle-Pricing (Paketpreise für Geräte-Kombinationen) und kundenspezifische Preislisten (VIP-Kunden bekommen andere Preise). Die Engine berechnet den finalen Preis automatisch durch eine Kaskade: Basispreis → Staffel → Menge → Saison → Kundenliste → Bundle.

### Bausteine im Detail

**1. DB-Migration: 7 Tabellen**
Sieben neue Tabellen werden angelegt:
- `pricing_tiers` – Staffelpreise: Asset-Typ-ID, Dauer-Von (Tage), Dauer-Bis (Tage), Preis pro Tag. Beispiel: 1–3 Tage = 50€/Tag, 4–7 Tage = 40€/Tag, 8–30 Tage = 30€/Tag.
- `volume_discounts` – Mengenrabatte: Asset-Typ-ID, Ab-Menge, Rabatt-Prozent. Beispiel: ab 5 Stück 10% Rabatt, ab 10 Stück 20%.
- `seasonal_surcharges` – Saisonzuschläge: Zeitraum-Von, Zeitraum-Bis, Zuschlag-Prozent, Name. Beispiel: „Sommerhochsaison" 01.06.–31.08. +15%.
- `bundles` – Bundle-Definitionen: Name, Beschreibung, Gesamtpreis, Gültig-Von, Gültig-Bis.
- `bundle_items` – Items in einem Bundle: Bundle-ID, Asset-Typ-ID, Menge.
- `customer_lists` – Kundenpreislisten: Name, Beschreibung, Priorität (höhere Priorität überschreibt).
- `list_items` – Einträge in einer Kundenpreisliste: Liste-ID, Asset-Typ-ID, Sonderpreis pro Tag.

**2. PricingEngineService: calculatePrice() mit Kaskade**
Die zentrale Methode, die den Endpreis berechnet. Eingabe: Asset-Typ, Menge, Mietdauer (Tage), Kunde, Datum. Die Berechnung läuft in einer festen Reihenfolge (Kaskade):
1. Basispreis aus dem Asset-Typ holen
2. Staffelpreis anwenden (falls Mietdauer in einen Staffelbereich fällt, wird der Tagespreis überschrieben)
3. Mengenrabatt berechnen (falls Menge über Schwellwert, prozentualen Rabatt abziehen)
4. Saisonzuschlag prüfen (falls aktuelles Datum in einen Saisonzeitraum fällt, Zuschlag aufschlagen)
5. Kundenpreisliste prüfen (falls der Kunde eine Sonderpreisliste hat, den Preis überschreiben)
6. Bundle-Preis prüfen (falls alle Items eines Bundles gebucht werden, Bundle-Gesamtpreis statt Einzelpreise)
Rückgabe: Aufgeschlüsselter Preis (Basis, Staffelrabatt, Mengenrabatt, Saisonzuschlag, Kundenrabatt, Bundle-Ersparnis, Endpreis).

**3. PricingEngineService: Staffelpreis-CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Staffelpreisen pro Asset-Typ. Validierung: Dauer-Bereiche dürfen sich nicht überlappen (z.B. nicht 1–5 Tage und 3–7 Tage gleichzeitig). Der Preis muss > 0 sein. Beim Löschen eines Staffelpreises gilt wieder der Basispreis für diesen Dauerbereich.

**4. PricingEngineService: Mengenrabatte CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Mengenrabatten. Pro Asset-Typ können mehrere Stufen definiert werden (ab 5 Stück, ab 10 Stück, ab 20 Stück). Validierung: Mengenschwellen müssen aufsteigend sein, Rabatt-Prozent muss zwischen 0 und 100 liegen.

**5. PricingEngineService: Saisonzuschläge CRUD**
Verwaltung von Saisonzuschlägen. Jeder Zuschlag hat einen Namen (z.B. „Weihnachtsmarkt-Saison"), einen Zeitraum und einen Prozentsatz. Mehrere Saisonzuschläge können sich überlappen – in dem Fall werden sie addiert. Negative Werte sind erlaubt (Nebensaison-Rabatt).

**6. PricingEngineService: Bundle-Verwaltung**
Bundles sind Pakete aus mehreren Asset-Typen mit einem Paketpreis. Beispiel: „Veranstaltungspaket" = 10× Stuhl + 2× Tisch + 1× Zelt = 500€/Tag statt 650€/Tag einzeln. Der Benutzer kann Bundles zusammenstellen, Items hinzufügen/entfernen und den Paketpreis festlegen. Die Engine erkennt automatisch, wenn alle Items eines Bundles in einer Buchung enthalten sind, und bietet den Bundle-Preis an.

**7. PricingEngineService: Kundenpreislisten**
VIP-Kunden oder Stammkunden können einer Preisliste zugeordnet werden. Eine Preisliste enthält Sonderpreise für bestimmte Asset-Typen. Beispiel: Kunde „Müller GmbH" bekommt Bagger für 80€/Tag statt 100€/Tag. Preislisten haben eine Priorität – bei Zugehörigkeit zu mehreren Listen gewinnt die höchste Priorität.

**8. API: 10 Endpunkte in /api/pricing/**
- `GET /api/pricing/calculate` – Preis berechnen (Asset-Typ, Menge, Dauer, Kunde, Datum)
- `GET/POST/PUT/DELETE /api/pricing/tiers` – Staffelpreise CRUD
- `GET/POST/PUT/DELETE /api/pricing/discounts` – Mengenrabatte CRUD
- `GET/POST/PUT/DELETE /api/pricing/seasonal` – Saisonzuschläge CRUD
- `GET/POST/PUT/DELETE /api/pricing/bundles` – Bundle CRUD
- `GET/POST/PUT/DELETE /api/pricing/customer-lists` – Kundenpreislisten CRUD

**9. UI: pricing_manage.twig mit 5-Tab Layout**
Eine Verwaltungsseite mit 5 Tabs:
- Tab 1: **Staffelpreise** – Tabelle mit Asset-Typ, Dauer-Bereichen und Preisen. Inline-Bearbeitung.
- Tab 2: **Mengenrabatte** – Tabelle mit Mengenstufen und Rabattprozenten.
- Tab 3: **Saisonzuschläge** – Kalenderansicht oder Tabelle mit Zeiträumen und Zuschlägen.
- Tab 4: **Bundles** – Bundle-Cards mit enthaltenen Items und Paketpreis.
- Tab 5: **Kundenpreislisten** – Preislisten mit zugeordneten Kunden und Sonderpreisen.

**10. JS: Echtzeit-Preisrechner**
Ein JavaScript-Widget, das live den Preis berechnet, während der Benutzer Parameter ändert. Sobald Asset-Typ, Menge, Dauer oder Kunde geändert wird, ruft das Widget die API auf und zeigt sofort den aufgeschlüsselten Preis an (Basispreis, Rabatte, Zuschläge, Endpreis). Wird in der Projekt-Anlage und in der Angebotserstellung eingebunden.

**11. Integration in Projektanlage**
Wenn ein neues Projekt angelegt wird und Assets zugewiesen werden, soll die Preisberechnung automatisch im Hintergrund laufen. Der berechnete Preis wird als Vorschlag angezeigt und kann manuell überschrieben werden. Bei Änderungen (andere Mietdauer, andere Menge) wird automatisch neu kalkuliert.

**12. Integration in Rechnungserstellung**
Wenn eine Rechnung aus einem Projekt generiert wird, sollen die Preise aus der Engine übernommen werden. Die Rechnung zeigt die Aufschlüsselung: Basispreis, angewendete Rabatte, Zuschläge, Endpreis. Der Benutzer kann den Preis vor Rechnungsstellung noch manuell anpassen.

**13. Tests**
Unit-Tests für die calculatePrice()-Kaskade mit allen Kombinationen: nur Basispreis, Staffelpreis aktiv, Mengenrabatt aktiv, Saisonzuschlag aktiv, Kundenpreisliste aktiv, Bundle aktiv, alle gleichzeitig aktiv. Edge Cases: Menge = 0, Dauer = 0, kein passender Staffelpreis, überlappende Saisonzuschläge.

---

## J2 – Wartung & Predictive Maintenance

### Überblick
Jedes Asset soll einen eigenen Wartungsplan haben können. Der Vermieter legt fest, welche Wartungsarbeiten in welchem Intervall durchgeführt werden müssen (z.B. alle 6 Monate Ölwechsel, jährlich TÜV, nach 500 Betriebsstunden Filterwechsel). Das System erstellt automatisch Wartungsaufträge (Jobs), erinnert per Dashboard an überfällige Wartungen, und dokumentiert die gesamte Service-Historie pro Asset mit Fotos, Checklisten und Kosten.

### Bausteine im Detail

**1. DB-Migration: 5 Tabellen**
- `maintenance_schedules` – Wartungspläne: Asset-ID oder Asset-Typ-ID, Intervall (Tage oder Betriebsstunden), Beschreibung, Priorität (niedrig/mittel/hoch/kritisch), letzter Ausführungstermin, nächster fälliger Termin.
- `maintenance_jobs` – Konkrete Wartungsaufträge: Schedule-ID, Asset-ID, Status (offen/in_arbeit/abgeschlossen/abgebrochen), zugewiesener Techniker, geplantes Datum, tatsächliches Datum, Dauer in Minuten, Kosten.
- `maintenance_photos` – Fotos zu einem Wartungsjob: Job-ID, Dateipfad, Typ (vorher/nachher/während), Zeitstempel, Beschreibung.
- `maintenance_checklists` – Checklisten-Vorlagen: Schedule-ID, Prüfpunkt-Text, Reihenfolge, ist_pflicht (ja/nein).
- `maintenance_checklist_results` – Ausgefüllte Checklisten: Job-ID, Checklisten-Item-ID, Status (ok/nicht_ok/übersprungen), Bemerkung.

**2. MaintenanceService: Schedule-CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Wartungsplänen. Ein Wartungsplan kann an ein einzelnes Asset gebunden sein (z.B. „Bagger #47 – Ölwechsel alle 6 Monate") oder an einen Asset-Typ (z.B. „Alle Generatoren – Luftfilter alle 3 Monate"). Bei Typ-Bindung gilt der Plan automatisch für alle Assets dieses Typs. Intervalle können in Tagen, Wochen, Monaten oder Betriebsstunden angegeben werden.

**3. MaintenanceService: Job-Lifecycle**
Ein Wartungsjob durchläuft folgende Status: `offen` → `in_arbeit` → `abgeschlossen` oder `abgebrochen`. Beim Erstellen wird ein Techniker zugewiesen und ein geplantes Datum gesetzt. Beim Starten (`in_arbeit`) wird der Zeitstempel erfasst. Beim Abschließen werden Dauer, Kosten und Checklisten-Ergebnisse gespeichert. Abgebrochene Jobs bekommen einen Grund-Text. Jeder Statuswechsel wird geloggt.

**4. MaintenanceService: getOverdueMaintenances(), getUpcoming()**
`getOverdueMaintenances()` liefert alle Wartungen, deren Fälligkeitsdatum überschritten ist, sortiert nach Dringlichkeit (Tage überfällig × Priorität). `getUpcoming()` liefert alle Wartungen, die in den nächsten X Tagen (konfigurierbar, Standard 14) fällig sind. Beide Methoden werden im Dashboard und für E-Mail-Benachrichtigungen verwendet.

**5. MaintenanceService: checkAndCreateScheduledJobs() (CRON)**
Eine Methode, die per CRON-Job (z.B. täglich um 6:00 Uhr) aufgerufen wird. Sie prüft alle aktiven Wartungspläne: Ist der nächste Fälligkeitstermin erreicht oder überschritten? Falls ja, wird automatisch ein neuer Wartungsjob im Status `offen` erstellt. So muss niemand manuell Wartungsaufträge anlegen – das System erstellt sie automatisch basierend auf den Plänen.

**6. MaintenanceService: Checklisten + Foto-Upload**
Jeder Wartungsplan kann eine Checkliste haben (z.B. „Ölstand prüfen", „Keilriemen prüfen", „Bremsbeläge messen"). Beim Durchführen der Wartung hakt der Techniker jeden Punkt als ok/nicht_ok ab und kann Bemerkungen hinzufügen. Zusätzlich können Fotos hochgeladen werden (vorher/nachher/während), die am Job gespeichert werden. Die Fotos werden auf dem Server im Upload-Verzeichnis abgelegt.

**7. MaintenanceService: Kosten-Tracking + Dashboard-Stats**
Jeder Wartungsjob kann Kosten erfassen: Materialkosten, Arbeitszeit (Stunden × Stundensatz), Fremdleistungen. Die Dashboard-Stats aggregieren: Gesamte Wartungskosten pro Monat/Quartal/Jahr, Kosten pro Asset, Kosten pro Asset-Typ, durchschnittliche Kosten pro Wartungsart. So kann der Vermieter sehen, welche Assets die höchsten Wartungskosten verursachen.

**8. API: 12 Endpunkte in /api/maintenance/**
- `GET/POST/PUT/DELETE /api/maintenance/schedules` – Wartungspläne CRUD
- `GET/POST/PUT /api/maintenance/jobs` – Wartungsjobs verwalten
- `POST /api/maintenance/jobs/{id}/start` – Job starten
- `POST /api/maintenance/jobs/{id}/complete` – Job abschließen (mit Checkliste + Kosten)
- `POST /api/maintenance/jobs/{id}/cancel` – Job abbrechen
- `POST /api/maintenance/jobs/{id}/photos` – Foto hochladen
- `GET /api/maintenance/overdue` – Überfällige Wartungen
- `GET /api/maintenance/upcoming` – Anstehende Wartungen
- `GET /api/maintenance/stats` – Dashboard-Statistiken

**9. UI: maintenance_dashboard.twig**
Das Wartungs-Dashboard zeigt oben 4 Status-Cards: Offene Jobs (Anzahl), Überfällige (Anzahl, rot), In Arbeit (Anzahl), Diesen Monat abgeschlossen (Anzahl). Darunter 4 Tabs:
- Tab 1: **Offene Jobs** – Tabelle mit allen offenen/überfälligen Wartungsaufträgen, sortierbar nach Dringlichkeit.
- Tab 2: **Wartungspläne** – Alle aktiven Pläne mit Asset, Intervall, nächster Fälligkeit.
- Tab 3: **Historie** – Abgeschlossene Wartungen mit Kosten, Dauer, Ergebnissen.
- Tab 4: **Statistiken** – Charts: Kosten pro Monat, Kosten pro Asset-Typ, Top-5 teuerste Assets.

**10. Controller: index.php**
Routet die Anfragen zum richtigen Template, lädt die Daten über den MaintenanceService und gibt sie an Twig weiter. Prüft Berechtigungen (nur Admins und Techniker sehen das Wartungs-Dashboard).

**11. Wartungsstatus-Badge auf Asset-Karte**
Auf jeder Asset-Detailseite und in Asset-Listen soll ein kleiner Badge den Wartungsstatus anzeigen: Grün „OK" (nächste Wartung > 14 Tage), Gelb „Bald fällig" (nächste Wartung ≤ 14 Tage), Rot „Überfällig" (Fälligkeitsdatum überschritten). Der Badge ist ein visueller Sofort-Indikator, ohne dass man ins Wartungs-Dashboard gehen muss.

**12. CRON-Einrichtung für automatische Job-Erstellung**
Der CRON-Job muss in der Server-Konfiguration eingetragen werden (z.B. `0 6 * * * php /path/to/scripts/maintenance_cron.php`). Das Script ruft `checkAndCreateScheduledJobs()` auf und sendet optional eine Zusammenfassungs-E-Mail an den Admin mit den neu erstellten Jobs.

**13. Tests**
Unit-Tests für: Schedule-CRUD mit Validierung (ungültige Intervalle, fehlende Pflichtfelder), Job-Lifecycle (korrekter Statuswechsel, ungültige Übergänge wie offen→abgeschlossen ohne in_arbeit), getOverdueMaintenances() mit verschiedenen Datums-Szenarien, checkAndCreateScheduledJobs() mit Plänen die fällig/nicht fällig sind, Kosten-Aggregation.

---

## K1 – Automatisierte Workflow-Engine

### Überblick
Eine No-Code Automatisierungsengine nach dem Prinzip „WENN X passiert, DANN tue Y". Der Benutzer kann ohne Programmierkenntnisse Workflows zusammenklicken. Trigger können Events sein (z.B. „Rechnung erstellt"), CRON-basiert (z.B. „jeden Montag um 8 Uhr") oder manuell ausgelöst. Jeder Workflow besteht aus Schritten, die nacheinander ausgeführt werden. Es gibt 7 Aktionstypen: E-Mail senden, Task erstellen, Status ändern, Benachrichtigung, Webhook aufrufen, Verzögerung, Bedingung (If/Else). Vorgefertigte Templates erleichtern den Einstieg.

### Bausteine im Detail

**1. DB-Migration: 5 Tabellen**
- `workflows` – Workflow-Definitionen: Name, Beschreibung, Trigger-Typ (event/cron/manual), Trigger-Config (JSON, z.B. `{"event": "invoice.created"}` oder `{"cron": "0 8 * * 1"}`), ist_aktiv, Instanz-ID, erstellt_von, erstellt_am.
- `workflow_steps` – Schritte eines Workflows: Workflow-ID, Reihenfolge, Aktionstyp (email/task/status_change/notify/webhook/delay/condition), Konfiguration (JSON, z.B. `{"to": "admin@firma.de", "subject": "Neue Rechnung", "body": "..."}`), Bedingung für Ausführung (optional).
- `workflow_executions` – Laufende/abgeschlossene Ausführungen: Workflow-ID, Status (running/completed/failed/cancelled), gestartet_am, beendet_am, Trigger-Daten (JSON), aktueller Schritt.
- `workflow_logs` – Detailliertes Log jeder Ausführung: Execution-ID, Schritt-ID, Status (success/error/skipped), Zeitstempel, Input-Daten (JSON), Output-Daten (JSON), Fehlermeldung (falls error).
- `workflow_templates` – Vorgefertigte Vorlagen: Name, Beschreibung, Kategorie, Workflow-JSON (kompletter Workflow als Template zum Importieren).

**2. WorkflowEngineService: Workflow-CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Workflows. Beim Erstellen wird der Trigger-Typ festgelegt und die Schritte definiert. Ein Workflow kann aktiviert/deaktiviert werden (deaktivierte Workflows werden nicht getriggert). Beim Löschen eines Workflows bleiben die Execution-Logs erhalten. Validierung: Mindestens ein Schritt nötig, Trigger-Config muss zum Trigger-Typ passen.

**3. WorkflowEngineService: executeWorkflow()**
Die zentrale Ausführungsmethode. Erstellt eine neue Execution, geht Schritt für Schritt durch:
1. Prüfe ob der Schritt eine Bedingung hat → falls Bedingung nicht erfüllt, überspringe den Schritt
2. Führe die Aktion aus (E-Mail senden, Task erstellen, etc.)
3. Logge das Ergebnis (Erfolg/Fehler)
4. Falls Fehler: Workflow abbrechen oder nächsten Schritt versuchen (konfigurierbar)
5. Falls Verzögerung: Execution pausieren und per CRON später fortsetzen
Die Methode ist idempotent – bei einem Neustart des Servers wird eine unterbrochene Execution fortgesetzt, nicht neu gestartet.

**4. WorkflowEngineService: 7 Aktionstypen**
- **E-Mail senden:** Empfänger, Betreff, Body (mit Platzhaltern wie `{{kunde.name}}`, `{{rechnung.nummer}}`). Nutzt den bestehenden E-Mail-Service.
- **Task erstellen:** Erstellt eine Aufgabe im Task-System mit Titel, Beschreibung, Zugewiesen-An, Fälligkeitsdatum.
- **Status ändern:** Ändert den Status eines Objekts (z.B. Rechnung auf „Gemahnt", Asset auf „In Wartung"). Objekt-Typ und neuer Status werden konfiguriert.
- **Benachrichtigung:** Sendet eine In-App-Benachrichtigung an einen oder mehrere Benutzer (Push-Notification im Dashboard).
- **Webhook:** Ruft eine externe URL per HTTP POST auf mit konfigurierbarem Payload (JSON). Für Integration mit externen Systemen (Slack, Teams, Buchhaltung etc.).
- **Verzögerung:** Pausiert die Workflow-Ausführung für X Minuten/Stunden/Tage. Beispiel: Rechnung erstellt → 14 Tage warten → prüfen ob bezahlt → Mahnung senden.
- **Bedingung (If/Else):** Prüft eine Bedingung und verzweigt: Falls wahr → nächster Schritt, falls falsch → überspringe X Schritte oder springe zu Schritt Y. Beispiel: „Wenn Rechnungsbetrag > 1000€, dann Chef benachrichtigen".

**5. WorkflowEngineService: 4 Templates**
Vorgefertigte Workflows, die der Benutzer mit einem Klick importieren und anpassen kann:
- **Mahnwesen:** Rechnung erstellt → 14 Tage warten → Prüfen ob bezahlt → Wenn nicht: 1. Mahnung per E-Mail → 14 Tage warten → 2. Mahnung → 14 Tage → 3. Mahnung + Chef benachrichtigen.
- **Wartungserinnerung:** CRON täglich → Prüfe überfällige Wartungen → E-Mail an Techniker mit Liste → Task erstellen pro überfälliger Wartung.
- **Kunden-Onboarding:** Neuer Kunde angelegt → Willkommens-E-Mail senden → Task „Vertrag vorbereiten" für Vertrieb erstellen → 3 Tage warten → Nachfass-E-Mail.
- **Rückgabe-Erinnerung:** CRON täglich → Prüfe Projekte deren Rückgabedatum in 2 Tagen ist → E-Mail an Kunde „Bitte Rückgabe vorbereiten" → Benachrichtigung an Disponent.

**6. WorkflowEngineService: Execution-Logging**
Jeder Schritt jeder Ausführung wird detailliert geloggt: Welcher Schritt wurde wann ausgeführt, was war der Input (z.B. Rechnungsdaten), was war das Ergebnis (E-Mail gesendet an X, Task #123 erstellt), gab es Fehler (SMTP-Verbindung fehlgeschlagen). Das Log ist über die UI einsehbar und hilft beim Debugging von Workflows.

**7. API: 11 Endpunkte in /api/workflows/**
- `GET/POST/PUT/DELETE /api/workflows` – Workflow CRUD
- `POST /api/workflows/{id}/execute` – Workflow manuell auslösen
- `POST /api/workflows/{id}/toggle` – Workflow aktivieren/deaktivieren
- `GET /api/workflows/{id}/executions` – Alle Ausführungen eines Workflows
- `GET /api/workflows/executions/{id}/logs` – Logs einer bestimmten Ausführung
- `GET /api/workflows/templates` – Verfügbare Templates
- `POST /api/workflows/templates/{id}/import` – Template als neuen Workflow importieren

**8. UI: workflows_index.twig (visueller Editor)**
Die Workflow-Verwaltung zeigt links eine Liste aller Workflows (Name, Status aktiv/inaktiv, letzter Lauf, Erfolgsrate). Rechts ein visueller Editor: Schritte werden als Karten dargestellt, verbunden durch Pfeile. Jede Karte zeigt den Aktionstyp mit Icon, eine Kurzbeschreibung und den Status. Neue Schritte werden per Drag & Drop hinzugefügt. Klick auf eine Karte öffnet das Konfigurationsformular für diesen Schritt. Unten eine Timeline der letzten Ausführungen.

**9. Controller: index.php**
Routet Anfragen, prüft Berechtigungen (Workflows erstellen = Admin, Workflows einsehen = alle), lädt Daten und gibt sie an Twig weiter.

**10. Event-Hooks**
Die bestehenden Services (InvoiceService, ProjectService, AssetService etc.) müssen um Event-Hooks erweitert werden. Nach jeder wichtigen Aktion (Rechnung erstellt, Asset zurückgegeben, Kunde angelegt etc.) wird ein Event gefeuert, das die Workflow-Engine prüft. Beispiel: Nach `$invoiceService->createInvoice()` wird `WorkflowEngineService::triggerEvent('invoice.created', $invoiceData)` aufgerufen.

**11. CRON-Integration**
Für CRON-basierte Trigger muss ein CRON-Script eingerichtet werden, das die Workflow-Engine periodisch aufruft. Das Script prüft alle aktiven Workflows mit Trigger-Typ „cron", vergleicht die CRON-Expression mit der aktuellen Zeit und führt fällige Workflows aus. Zusätzlich werden pausierte Executions (durch Verzögerungsschritte) fortgesetzt.

**12. Tests**
Unit-Tests für: Workflow-CRUD mit Validierung, executeWorkflow() mit allen 7 Aktionstypen einzeln und in Kombination, Bedingungen (wahr/falsch), Verzögerungen (Mock der Zeit), Fehlerbehandlung (Schritt schlägt fehl → Workflow-Status = failed), Event-Trigger (Event feuern → richtiger Workflow wird ausgeführt), Template-Import.

---

## J1 – Digitales Vertragsmanagement

### Überblick
Vollständiges Vertragswesen innerhalb von MyRMS. Mietverträge, Rahmenverträge und Einzelverträge werden aus konfigurierbaren Templates generiert. Platzhalter (`{{kunde.name}}`, `{{projekt.startdatum}}`, `{{positionen}}`) werden automatisch mit echten Daten befüllt. Verträge haben eine Versionierung (jede Änderung erzeugt eine neue Version), können digital signiert werden (Canvas-Unterschrift im Browser) und werden GoBD-konform archiviert (revisionssicher mit Audit-Trail).

### Bausteine im Detail

**1. DB-Migration: 5 Tabellen**
- `contracts` – Verträge: Projekt-ID (optional), Kunde-ID, Template-ID, Status (entwurf/gesendet/unterschrieben/aktiv/abgelaufen/gekuendigt), aktuelle Version-Nr, erstellt_von, erstellt_am, signiert_am, signiert_von_ip, signiert_von_useragent.
- `contract_versions` – Versionierung: Vertrag-ID, Versionsnummer, HTML-Inhalt (gerendert), Änderungsgrund, erstellt_von, erstellt_am. Jede Änderung am Vertrag erzeugt eine neue Version, alte Versionen bleiben erhalten.
- `contract_templates` – Vorlagen: Name, Kategorie (mietvertrag/rahmenvertrag/einzelvertrag), HTML-Template mit Platzhaltern, Standard-AGB-Set-ID, ist_standard (ja/nein).
- `agb_sets` – AGB-Sammlungen: Name, Version, HTML-Inhalt der AGB, gültig_ab, ist_aktuell. Mehrere AGB-Versionen können existieren, der Vertrag wird immer mit der zum Erstellungszeitpunkt aktuellen AGB-Version verknüpft.
- `contract_audit` – GoBD-Audit-Trail: Vertrag-ID, Aktion (erstellt/bearbeitet/gesendet/signiert/storniert), Benutzer-ID, IP-Adresse, User-Agent, Zeitstempel, Details (JSON).

**2. ContractService: Template-Engine mit Platzhaltern**
Templates werden in HTML geschrieben und enthalten Platzhalter in doppelten geschweiften Klammern. Verfügbare Platzhalter:
- Kunde: `{{kunde.name}}`, `{{kunde.adresse}}`, `{{kunde.email}}`, `{{kunde.telefon}}`, `{{kunde.steuernr}}`
- Projekt: `{{projekt.name}}`, `{{projekt.startdatum}}`, `{{projekt.enddatum}}`, `{{projekt.ort}}`
- Positionen: `{{positionen_tabelle}}` – rendert eine HTML-Tabelle mit allen gebuchten Assets (Name, Menge, Einzelpreis, Gesamtpreis)
- Preise: `{{gesamtpreis_netto}}`, `{{mwst_betrag}}`, `{{gesamtpreis_brutto}}`
- Firma: `{{firma.name}}`, `{{firma.adresse}}`, `{{firma.bankverbindung}}`
- Datum: `{{datum_heute}}`, `{{datum_rueckgabe}}`
Die Engine ersetzt alle Platzhalter mit echten Daten und rendert das fertige HTML.

**3. ContractService: Versionierung**
Jede Änderung am Vertrag (Inhalt bearbeiten, Positionen ändern, AGB aktualisieren) erzeugt eine neue Version. Die alte Version bleibt unverändert erhalten. Der Benutzer kann alle Versionen einsehen und vergleichen (Diff-View). Ein bereits signierter Vertrag kann nicht mehr bearbeitet werden – es muss ein neuer Vertrag erstellt werden.

**4. ContractService: Auto-Generierung aus Projektdaten**
Wenn ein Projekt angelegt wird, kann per Klick automatisch ein Vertrag generiert werden. Das System wählt das passende Template (basierend auf Projekt-Typ oder Standardvorlage), befüllt alle Platzhalter mit den Projektdaten und erstellt den Vertrag im Status „Entwurf". Der Benutzer muss nur noch prüfen und absenden.

**5. ContractService: PDF-Generierung (dompdf)**
Der gerenderte HTML-Vertrag wird per dompdf in ein PDF konvertiert. Das PDF enthält: Firmenlogo, Vertragstext, Positionstabelle, AGB, Unterschriftenfeld. Das PDF wird serverseitig gespeichert und kann heruntergeladen oder per E-Mail versendet werden. Jede Version hat ihr eigenes PDF.

**6. ContractService: Canvas-basierte Signatur**
Der Kunde erhält einen Link zur Signatur-Seite (kein Login nötig, Token-basiert). Dort sieht er den kompletten Vertrag als HTML und kann am Ende per Canvas (Touch/Maus) unterschreiben. Die Unterschrift wird als PNG gespeichert und in das PDF eingebettet. Beim Signieren werden IP-Adresse und User-Agent für den GoBD-Audit-Trail erfasst.

**7. ContractService: Status-Workflow**
Ein Vertrag durchläuft: Entwurf → Gesendet (an Kunde per E-Mail) → Unterschrieben (Kunde hat signiert) → Aktiv (Vermieter hat bestätigt) → Abgelaufen (Enddatum überschritten) oder Gekündigt. Jeder Statuswechsel wird im Audit-Trail dokumentiert. Nur bestimmte Übergänge sind erlaubt (z.B. nicht direkt von Entwurf zu Aktiv).

**8. ContractService: AGB-Verwaltung**
Allgemeine Geschäftsbedingungen werden zentral verwaltet und versioniert. Wenn neue AGB erstellt werden, gilt die alte Version weiterhin für bestehende Verträge. Neue Verträge bekommen automatisch die aktuellste AGB-Version angehängt. Der Vermieter kann verschiedene AGB-Sets für verschiedene Vertragstypen pflegen.

**9. API: 12 Endpunkte in /api/contracts/**
- `GET/POST/PUT/DELETE /api/contracts` – Vertrags-CRUD
- `POST /api/contracts/{id}/generate-pdf` – PDF generieren
- `POST /api/contracts/{id}/send` – Vertrag per E-Mail an Kunden senden
- `GET /api/contracts/{id}/versions` – Alle Versionen abrufen
- `POST /api/contracts/{id}/sign` – Öffentlicher Signatur-Endpunkt
- `GET/POST/PUT/DELETE /api/contracts/templates` – Template-CRUD
- `GET/POST/PUT/DELETE /api/contracts/agb` – AGB-Verwaltung

**10. Öffentliche Signatur-Seite**
Eine eigenständige Seite unter `/contracts/sign/{token}`, die ohne Login erreichbar ist. Sie zeigt den Vertrag im Volltext an, hat unten ein Canvas-Feld für die Unterschrift und einen „Verbindlich unterschreiben"-Button. Nach der Unterschrift wird eine Bestätigungsseite gezeigt und eine E-Mail an beide Parteien gesendet.

**11. UI: contracts_index.twig**
Die Vertragsübersicht zeigt alle Verträge als Tabelle mit: Vertragsnummer, Kunde, Projekt, Status (mit Farbe), Erstelldatum, Signaturdatum. Filter nach Status, Kunde, Zeitraum. Klick öffnet die Detailansicht mit Vorschau, Versionshistorie und Aktions-Buttons (Bearbeiten, PDF, Senden, Signatur-Link kopieren).

**12. Controller: index.php**
Routet Anfragen, prüft Berechtigungen (Verträge erstellen/bearbeiten = Admin + Vertrieb, Verträge einsehen = alle).

**13. GoBD-Audit-Trail**
Jede Aktion am Vertrag wird mit IP-Adresse, User-Agent, Benutzer-ID und Zeitstempel protokolliert. Der Audit-Trail ist unveränderlich (nur INSERT, kein UPDATE/DELETE). Er dient als Nachweis bei Rechtsstreitigkeiten und erfüllt die GoBD-Anforderungen für digitale Geschäftsdokumente.

**14. Tests**
Unit-Tests für: Template-Engine (alle Platzhalter korrekt ersetzt, fehlende Daten → leerer String), Versionierung (neue Version nach Änderung, alte Version unverändert), Status-Workflow (erlaubte/verbotene Übergänge), PDF-Generierung (Datei wird erstellt, ist gültiges PDF), Signatur (Token-Validierung, Audit-Trail-Eintrag).

---

## K3 – Schadenmanagement-Workflow

### Überblick
Ein strukturierter 8-Schritte-Prozess zur Abwicklung von Schäden an Mietgeräten. Von der Schadenerfassung über Foto-Dokumentation, Kostenvoranschläge, Versicherungsmeldung bis zur Abrechnung mit dem Kunden. 11 Status-Typen bilden den kompletten Lebenszyklus eines Schadenfalles ab. Das Kanban-Board gibt einen visuellen Überblick über alle laufenden Schadenfälle.

### Bausteine im Detail

**1. DB-Migration: 4 Tabellen**
- `damage_workflows` – Schadenfälle: Asset-ID, Projekt-ID, Kunde-ID, Status (11 mögliche Werte), Schadenbeschreibung, Schweregrad (kosmetisch/funktional/sicherheitsrelevant/totalschaden), entdeckt_am, entdeckt_von, geschätzte_kosten, tatsächliche_kosten, versicherungsfall (ja/nein), Kautionsabzug-Betrag.
- `damage_workflow_log` – Status-Historie: Workflow-ID, alter_Status, neuer_Status, Benutzer-ID, Zeitstempel, Kommentar. Jeder Statuswechsel wird protokolliert.
- `damage_cost_estimates` – Kostenvoranschläge: Workflow-ID, Anbieter-Name, Betrag, Beschreibung, Dokument-Pfad (PDF), Status (angefragt/erhalten/akzeptiert/abgelehnt), erstellt_am.
- `damage_photos` – Fotos: Workflow-ID, Dateipfad, Typ (vorher/während/nachher), Zeitstempel, Beschreibung, aufgenommen_von.

**2. DamageWorkflowService: 11 Status mit erzwungenen Übergängen**
Die 11 Status und ihre erlaubten Übergänge:
1. `entdeckt` – Schaden wurde festgestellt → kann zu `dokumentiert` oder `abgebrochen`
2. `dokumentiert` – Fotos und Beschreibung erfasst → kann zu `bewertet`
3. `bewertet` – Schweregrad und geschätzte Kosten festgelegt → kann zu `kv_angefragt` oder `reparatur_intern`
4. `kv_angefragt` – Kostenvoranschlag bei externem Dienstleister angefragt → kann zu `kv_erhalten`
5. `kv_erhalten` – Kostenvoranschlag liegt vor → kann zu `kv_akzeptiert` oder `kv_angefragt` (neuen KV anfordern)
6. `kv_akzeptiert` – Reparaturauftrag erteilt → kann zu `in_reparatur`
7. `reparatur_intern` – Wird intern repariert → kann zu `repariert`
8. `in_reparatur` – Extern in Reparatur → kann zu `repariert`
9. `repariert` – Reparatur abgeschlossen, Fotos nachher → kann zu `abgerechnet`
10. `abgerechnet` – Kosten dem Kunden/Versicherung in Rechnung gestellt → kann zu `abgeschlossen`
11. `abgeschlossen` – Fall erledigt (Endstatus)
Plus: `abgebrochen` – Fehlalarm, kein Schaden (Endstatus)
Ungültige Übergänge werden mit einer Fehlermeldung abgelehnt.

**3. DamageWorkflowService: Multi-Vendor Kostenvoranschläge**
Pro Schadensfall können mehrere Kostenvoranschläge von verschiedenen Anbietern eingeholt werden. Jeder KV hat: Anbieter-Name, geschätzter Betrag, Beschreibung der Reparatur, PDF-Upload des Angebots. Der Benutzer kann KVs vergleichen und den besten akzeptieren. Der akzeptierte KV wird als Grundlage für die Reparaturkosten übernommen.

**4. DamageWorkflowService: Foto-Dokumentation**
Fotos werden in drei Phasen erfasst:
- **Vorher:** Fotos des Schadens direkt nach Entdeckung (Pflicht beim Übergang zu „dokumentiert")
- **Während:** Optionale Fotos während der Reparatur
- **Nachher:** Fotos nach abgeschlossener Reparatur (Pflicht beim Übergang zu „repariert")
Fotos werden auf dem Server im Upload-Verzeichnis abgelegt, Thumbnails automatisch generiert. Die Timeline zeigt alle Fotos chronologisch.

**5. DamageWorkflowService: Versicherungsmeldung**
Wenn ein Schadensfall als Versicherungsfall markiert wird, generiert das System eine Schadenmeldung mit allen relevanten Daten: Asset-Beschreibung, Schadendatum, Schadenort (Projekt-Adresse), Schadenursache, Fotos, geschätzte/tatsächliche Kosten, Policen-Nummer (aus K2 Versicherungsmanagement). Die Meldung kann als PDF exportiert oder per E-Mail an die Versicherung geschickt werden.

**6. DamageWorkflowService: Kunden-Weiterbelastung + Kautionsabzug**
Bei Schäden, die der Kunde zu verantworten hat, berechnet das System die Weiterbelastung: Reparaturkosten abzüglich Versicherungsleistung abzüglich Kaution. Wenn eine Kaution hinterlegt wurde, wird der Kautionsbetrag (ganz oder teilweise) einbehalten. Der Restbetrag wird dem Kunden in Rechnung gestellt. Das System erstellt automatisch eine Schadenrechnung, die in die Rechnungsübersicht einfließt.

**7. API: 13 Endpunkte in /api/damage/**
- `GET/POST/PUT /api/damage/workflows` – Schadenfälle verwalten
- `POST /api/damage/workflows/{id}/transition` – Statusübergang durchführen
- `GET /api/damage/workflows/{id}/log` – Status-Historie abrufen
- `GET/POST/PUT/DELETE /api/damage/workflows/{id}/cost-estimates` – Kostenvoranschläge
- `POST /api/damage/workflows/{id}/photos` – Fotos hochladen
- `GET /api/damage/workflows/{id}/photos` – Fotos abrufen
- `POST /api/damage/workflows/{id}/insurance-report` – Versicherungsmeldung generieren
- `POST /api/damage/workflows/{id}/invoice` – Schadenrechnung erstellen
- `GET /api/damage/dashboard` – Dashboard-Daten (Zähler pro Status)

**8. UI: Kanban-Board (damage_dashboard.twig)**
Das Schadenmanagement-Dashboard zeigt ein Kanban-Board mit Spalten für jeden Status. Jede Karte zeigt: Asset-Name, Schadentyp (Icon), Schweregrad (Farbe), geschätzte Kosten, Tage seit Entdeckung. Karten können per Drag & Drop in den nächsten Status gezogen werden (unter Einhaltung der erlaubten Übergänge). Filter nach Schweregrad, Asset-Typ, Zeitraum.

**9. UI: Detail-View mit Timeline (damage_detail.twig)**
Die Detailansicht eines Schadenfalles zeigt links die Schadendetails (Asset, Kunde, Beschreibung, Kosten) und rechts eine vertikale Timeline mit allen Ereignissen: Statuswechsel, Fotos hochgeladen, KVs erstellt, Kommentare. Jeder Timeline-Eintrag hat Zeitstempel, Benutzer und Details. Unten: Fotos-Galerie (vorher/nachher nebeneinander), KV-Vergleichstabelle, Abrechnungsübersicht.

**10. Controller: index.php**
Routet Anfragen zum richtigen Template, prüft Berechtigungen.

**11. Integration mit bestehendem DamageReportService**
Der bestehende DamageReportService (einfache Schadensmeldung) wird nicht ersetzt, sondern erweitert. Wenn ein Schaden gemeldet wird, erstellt der DamageReportService weiterhin den Initialeintrag, aber zusätzlich wird automatisch ein DamageWorkflow gestartet. So bleiben bestehende Schnittstellen kompatibel.

**12. Tests**
Unit-Tests für: Alle 11 Status-Übergänge (erlaubte und verbotene), Multi-Vendor KV (erstellen, akzeptieren, ablehnen), Foto-Upload mit Typ-Validierung, Versicherungsmeldung (alle Pflichtfelder vorhanden), Kunden-Weiterbelastung (Berechnung mit/ohne Kaution, mit/ohne Versicherung).

---

## K2 – Versicherungsmanagement

### Überblick
Verwaltung aller Versicherungspolicen des Unternehmens. Jede Police deckt bestimmte Assets oder Asset-Typen ab. Das System prüft automatisch, ob alle Assets versichert sind (Deckungslücken-Analyse), warnt vor ablaufenden Policen und verwaltet Kundennachweise (wenn Kunden eigene Versicherungen mitbringen). Schadenmeldungen werden mit den passenden Policen verknüpft.

### Bausteine im Detail

**1. DB-Migration: 4 Tabellen**
- `insurance_policies` – Policen: Versicherung-Name, Policen-Nummer, Typ (Haftpflicht/Transportversicherung/Elektronikversicherung/Maschinenbruch/Allgefahren), Deckungssumme, Selbstbeteiligung, Jahresprämie, gültig_von, gültig_bis, Status (aktiv/abgelaufen/gekündigt), Dokument-Pfad (PDF der Police).
- `insurance_asset_coverage` – Welche Assets sind durch welche Police gedeckt: Police-ID, Asset-ID (einzelnes Asset) oder Asset-Typ-ID (alle eines Typs), Deckungssumme pro Asset (falls abweichend von Police-Gesamtsumme).
- `insurance_client_certificates` – Kundennachweise: Kunde-ID, Versicherungstyp, Versicherung-Name, Policen-Nummer, Deckungssumme, gültig_bis, Dokument-Pfad (PDF des Nachweises), verifiziert (ja/nein), verifiziert_von, verifiziert_am.
- `insurance_claims` – Versicherungsfälle: Police-ID, Damage-Workflow-ID (Verknüpfung zum Schadenfall), Schadensdatum, Meldedatum, Aktenzeichen der Versicherung, Status (gemeldet/in_prüfung/anerkannt/abgelehnt/ausgezahlt/teilausgezahlt/abgeschlossen/widerspruch), gemeldeter_Betrag, ausgezahlter_Betrag.

**2. InsuranceService: Policen-CRUD**
Erstellen, Lesen, Aktualisieren, Löschen von Versicherungspolicen. Beim Erstellen wird die Police mit Assets oder Asset-Typen verknüpft. Das System berechnet automatisch die Deckungsquote (wie viel Prozent des Gesamtbestands sind versichert). Ablaufende Policen (< 30 Tage) werden farblich markiert.

**3. InsuranceService: Deckungsprüfung + Deckungslücken**
Die Methode `checkCoverage($assetId)` prüft, ob ein bestimmtes Asset versichert ist und gibt die Details zurück: Welche Police, welche Deckungssumme, welche Selbstbeteiligung. `findCoverageGaps()` analysiert den gesamten Bestand und listet alle Assets auf, die durch keine aktive Police gedeckt sind. Dies wird im Dashboard als Warnung angezeigt.

**4. InsuranceService: Kundennachweise mit Verifizierung**
Manche Kunden bringen eigene Versicherungsnachweise mit (z.B. Haftpflicht für gemietete Geräte). Der Kunde lädt den Nachweis hoch (PDF), ein Mitarbeiter verifiziert ihn (prüft Gültigkeit, Deckungssumme). Verifizierte Nachweise reduzieren die Haftung des Vermieters. Bei abgelaufenem Nachweis warnt das System bei der nächsten Buchung.

**5. InsuranceService: Schadenmeldungen (8 Status)**
Versicherungsfälle durchlaufen 8 Status: gemeldet → in_prüfung → anerkannt/abgelehnt → (bei Anerkennung) ausgezahlt/teilausgezahlt → abgeschlossen, oder (bei Ablehnung) widerspruch → zurück zu in_prüfung. Pro Fall werden erfasst: Aktenzeichen der Versicherung, gemeldeter Betrag, anerkannter Betrag, ausgezahlter Betrag. Differenzen (gemeldet vs. ausgezahlt) werden in der Schadensabrechnung berücksichtigt.

**6. InsuranceService: Dashboard-Stats**
Statistiken für das Versicherungs-Dashboard: Anzahl aktive Policen, Gesamtdeckungssumme, jährliche Gesamtprämie, Deckungsquote (% versicherter Assets), laufende Schadenmeldungen, Summe offener Ansprüche, Summe ausgezahlter Leistungen (YTD), Top-5 Schadensursachen.

**7. API: 12 Endpunkte in /api/insurance/**
- `GET/POST/PUT/DELETE /api/insurance/policies` – Policen CRUD
- `GET /api/insurance/coverage-check/{assetId}` – Deckungsprüfung für ein Asset
- `GET /api/insurance/coverage-gaps` – Alle unversicherten Assets
- `GET/POST/PUT/DELETE /api/insurance/client-certificates` – Kundennachweise
- `POST /api/insurance/client-certificates/{id}/verify` – Nachweis verifizieren
- `GET/POST/PUT /api/insurance/claims` – Schadenmeldungen
- `POST /api/insurance/claims/{id}/transition` – Status-Übergang
- `GET /api/insurance/dashboard` – Dashboard-Statistiken

**8. UI: insurance_index.twig**
Versicherungsübersicht mit mehreren Bereichen: Oben: KPI-Cards (aktive Policen, Deckungsquote, offene Claims, Prämien/Jahr). Mitte: Policen-Tabelle mit Status-Ampel (grün = aktiv, gelb = läuft bald ab, rot = abgelaufen). Unten: Deckungslücken-Warnung (rote Box mit Liste unversicherter Assets). Seitenleiste: Laufende Schadenmeldungen mit Status.

**9. Controller: index.php**
Routet Anfragen, prüft Berechtigungen (Versicherungen verwalten = Admin).

**10. Integration mit K3 Schadenmanagement**
Wenn ein Schadenfall im K3-Modul als Versicherungsfall markiert wird, erstellt das System automatisch einen Eintrag in `insurance_claims` und verknüpft ihn mit der passenden Police (basierend auf dem betroffenen Asset). Der Versicherungsstatus wird im Schadenmanagement-Dashboard angezeigt.

**11. Tests**
Unit-Tests für: Policen-CRUD, Deckungsprüfung (Asset versichert/nicht versichert, multiple Policen), Deckungslücken-Analyse, Kundennachweise (Upload, Verifizierung, Ablauf), Schadenmeldungen (Status-Übergänge, Betragsberechnung).

---

## J3 – Transport & Logistik

### Überblick
Verwaltung des kompletten Fuhrparks und der Tourenplanung. Fahrzeuge (LKW, Transporter, Anhänger) und Fahrer werden verwaltet, Touren mit mehreren Stopps geplant, die Kapazität (Gewicht und Volumen) geprüft. Bei der Übergabe an den Kunden wird per Unterschrift quittiert. Alle Transportkosten werden erfasst und den Projekten zugeordnet.

### Bausteine im Detail

**1. DB-Migration: 5 Tabellen**
- `transport_vehicles` – Fahrzeuge: Kennzeichen, Typ (LKW/Transporter/Anhänger/PKW), Marke, Modell, max_gewicht_kg, max_volumen_m3, Status (verfügbar/unterwegs/in_wartung/stillgelegt), nächster_TÜV, Kilometerstand, Kraftstofftyp, Verbrauch_l_pro_100km.
- `transport_drivers` – Fahrer: Name, Führerscheinklasse, Telefon, Status (verfügbar/unterwegs/krank/urlaub), Führerschein_gültig_bis.
- `transport_tours` – Touren: Datum, Fahrer-ID, Fahrzeug-ID, Anhänger-ID (optional), Status (geplant/unterwegs/abgeschlossen/abgebrochen), geplante_km, tatsächliche_km, Startzeit, Endzeit.
- `transport_tour_stops` – Stopps einer Tour: Tour-ID, Reihenfolge, Adresse, Typ (laden/entladen/beides), Projekt-ID (optional), geplante_Ankunft, tatsächliche_Ankunft, Unterschrift_Kunde (Dateipfad), Unterschrift_Zeitstempel, Bemerkungen.
- `transport_costs` – Kosten: Tour-ID, Typ (kraftstoff/maut/parkgebühr/fähre/sonstiges), Betrag, Beschreibung, Beleg-Pfad (Foto/PDF).

**2. TransportLogisticsService: Fahrzeug- + Fahrer-Verwaltung**
CRUD für Fahrzeuge und Fahrer. Fahrzeuge haben einen Status (verfügbar/unterwegs/in_wartung) der automatisch aktualisiert wird, wenn eine Tour gestartet oder beendet wird. Fahrer ebenso. Das System warnt bei ablaufendem TÜV (< 30 Tage) oder ablaufendem Führerschein. Ein Kalender zeigt die Belegung aller Fahrzeuge und Fahrer auf einen Blick.

**3. TransportLogisticsService: Multi-Stopp Tourenplanung**
Eine Tour kann beliebig viele Stopps haben. Jeder Stopp hat eine Adresse, einen Typ (laden, entladen oder beides) und eine optionale Projekt-Zuordnung. Die Reihenfolge der Stopps kann per Drag & Drop geändert werden. Das System berechnet die geschätzte Gesamtstrecke und Fahrzeit (basierend auf Luftlinie × Faktor 1,3 als Näherung, oder per Geocoding-API falls konfiguriert).

**4. TransportLogisticsService: Kapazitätsprüfung**
Beim Planen einer Tour prüft das System, ob die zu transportierenden Güter in das gewählte Fahrzeug passen. Pro Stopp wird erfasst, was geladen und was entladen wird (Gewicht in kg, Volumen in m³). Das System berechnet die maximale Beladung an jedem Punkt der Tour und warnt, wenn das Gewicht- oder Volumenlimit des Fahrzeugs überschritten wird.

**5. TransportLogisticsService: Übergabe-Bestätigung mit Unterschrift**
An jedem Stopp kann der Kunde die Übergabe per Unterschrift bestätigen (Canvas-basiert, wie bei den Verträgen). Die Unterschrift wird als PNG gespeichert und dem Stopp zugeordnet. Erfasst werden: Unterschrift, Name des Empfängers, Zeitstempel, GPS-Koordinaten (falls verfügbar über die Android-App). Dies dient als Nachweis der Lieferung/Abholung.

**6. TransportLogisticsService: Kosten-Tracking**
Alle Transportkosten werden erfasst: Kraftstoff (getankte Liter × Preis), Mautgebühren, Parkgebühren, Fährkosten, Sonstiges. Zu jeder Kostenposition kann ein Beleg hochgeladen werden (Foto der Tankquittung etc.). Die Kosten werden der Tour und darüber den Projekten zugeordnet. Monatliche Auswertung: Gesamtkosten, Kosten pro km, Kosten pro Fahrzeug, Kosten pro Projekt.

**7. API: 11 Endpunkte in /api/transport/**
- `GET/POST/PUT/DELETE /api/transport/vehicles` – Fahrzeuge CRUD
- `GET/POST/PUT/DELETE /api/transport/drivers` – Fahrer CRUD
- `GET/POST/PUT /api/transport/tours` – Touren verwalten
- `POST /api/transport/tours/{id}/start` – Tour starten
- `POST /api/transport/tours/{id}/complete` – Tour abschließen
- `POST /api/transport/tours/{id}/stops/{stopId}/sign` – Übergabe bestätigen
- `GET/POST /api/transport/tours/{id}/costs` – Kosten erfassen/abrufen
- `GET /api/transport/capacity-check` – Kapazitätsprüfung
- `GET /api/transport/calendar` – Kalender-Daten (Belegung)

**8. UI: transport_index.twig mit Kalender**
Das Transport-Dashboard zeigt oben einen Wochenkalender mit allen geplanten Touren (farblich nach Fahrzeug). Darunter: Aktive Touren (live-Status), Fahrzeugübersicht (verfügbar/unterwegs), Fahrer-Status. Die Tourenplanung erfolgt per Formular: Datum wählen, Fahrzeug + Fahrer zuweisen, Stopps hinzufügen (Adresse, Typ, Projekt), Kapazitätsprüfung automatisch im Hintergrund.

**9. Controller: index.php**
Routet Anfragen, prüft Berechtigungen (Touren planen = Disponent + Admin, Touren einsehen = alle).

**10. Tests**
Unit-Tests für: Fahrzeug/Fahrer-CRUD, Tourenplanung mit Stopps (Reihenfolge, Hinzufügen, Entfernen), Kapazitätsprüfung (unter Limit, genau am Limit, über Limit, Zwischenstopps mit Laden/Entladen), Kosten-Tracking (Aggregation pro Tour/Projekt/Monat), Übergabe-Unterschrift.

---

## I1-I3 – Multi-KI-Provider System

### Überblick
Anstatt fest an einen KI-Anbieter (z.B. Claude) gebunden zu sein, unterstützt MyRMS 6 verschiedene KI-Provider gleichzeitig. Jeder Task-Typ (Vertragserstellung, Schadensbewertung, Kategorisierung etc.) kann einem bestimmten Provider zugeordnet werden (Task-Routing). Fällt ein Provider aus, springt automatisch der nächste in der Fallback-Kette ein. API-Keys werden AES-256 verschlüsselt in der Datenbank gespeichert. Ein Usage-Tracker protokolliert alle KI-Aufrufe mit Tokens und geschätzten Kosten.

### Bausteine im Detail

**1. DB-Migration: 4 Tabellen**
- `ai_providers` – Konfigurierte Provider: Name (claude/openai/gemini/mistral/ollama/openai_compatible), API-Key (verschlüsselt), Basis-URL (für Self-Hosted/Kompatible), Modell-Name, max_tokens, temperature, ist_aktiv, Priorität (für Fallback-Reihenfolge).
- `ai_task_routing` – Zuordnung Task-Typ → Provider: Task-Typ (contract_generation/damage_assessment/categorization/email_draft/translation/general), primärer Provider-ID, Fallback-Provider-IDs (JSON-Array).
- `ai_usage_log` – Usage-Tracking: Provider-ID, Task-Typ, Modell, Input-Tokens, Output-Tokens, geschätzte_Kosten_EUR, Antwortzeit_ms, Status (success/error), Fehler-Text, Zeitstempel, Benutzer-ID.
- `ai_fallback_chain` – Fallback-Konfiguration: Task-Typ, Provider-Reihenfolge (JSON-Array), max_Retries, Timeout_Sekunden.

**2. LlmProviderInterface + LlmResponse**
Ein PHP-Interface, das alle Provider implementieren müssen:
- `chat(string $systemPrompt, string $userMessage, array $options): LlmResponse` – Standard-Chat-Completion
- `chatWithTools(string $systemPrompt, string $userMessage, array $tools, array $options): LlmResponse` – Chat mit Tool-Use/Function-Calling
- `getName(): string` – Provider-Name
- `isAvailable(): bool` – Prüft ob der Provider erreichbar ist (API-Key gültig, Endpoint antwortet)
`LlmResponse` ist ein Value Object mit: content (Text-Antwort), toolCalls (Array), inputTokens, outputTokens, model, finishReason.

**3–8. Adapter: Claude, OpenAI, Gemini, Mistral, Ollama, OpenAI-Compatible**
Sechs konkrete Implementierungen des LlmProviderInterface:
- **ClaudeAdapter:** Anthropic Messages API, unterstützt Claude 3.5 Sonnet/Haiku/Opus, Tool-Use mit Anthropic-Format.
- **OpenAiAdapter:** OpenAI Chat Completions API, unterstützt GPT-4o/GPT-4-turbo/GPT-3.5, Function Calling.
- **GeminiAdapter:** Google Gemini API, unterstützt Gemini Pro/Ultra, Function Calling.
- **MistralAdapter:** Mistral API, unterstützt Mistral Large/Medium/Small, Function Calling.
- **OllamaAdapter:** Lokaler Ollama-Server (kein API-Key nötig), unterstützt alle Ollama-Modelle (Llama, Mixtral etc.), kein Function Calling.
- **OpenAiCompatibleAdapter:** Generischer Adapter für alle APIs, die das OpenAI-Format sprechen (z.B. vLLM, LocalAI, Together.ai, Fireworks). Basis-URL konfigurierbar.

**9. AiProviderRegistry**
Zentrale Registry, die alle konfigurierten Provider kennt. Funktionen:
- `getProviderForTask(string $taskType): LlmProviderInterface` – Gibt den primären Provider für einen Task-Typ zurück
- `executewithFallback(string $taskType, callable $fn): LlmResponse` – Führt einen KI-Aufruf aus und wechselt bei Fehler automatisch zum nächsten Provider in der Fallback-Kette
- API-Key-Management: Keys werden mit AES-256-GCM verschlüsselt und erst beim Aufruf entschlüsselt. Der Encryption-Key liegt in der `.env`-Datei, nie in der Datenbank.

**10. AiRequestHandler**
Der zentrale Einstiegspunkt für alle KI-Aufrufe in der gesamten Anwendung. Anstatt direkt einen Provider aufzurufen, nutzen alle Services den AiRequestHandler:
```php
$response = $aiRequestHandler->execute('contract_generation', $systemPrompt, $userMessage);
```
Der Handler kümmert sich um: Provider-Auswahl (via Registry), Anonymisierung (via I6), Fallback, Usage-Tracking, Fehlerbehandlung. So muss kein Service wissen, welcher KI-Provider gerade aktiv ist.

**11. AiUsageTracker**
Protokolliert jeden KI-Aufruf: Welcher Provider, welches Modell, wie viele Input/Output-Tokens, geschätzte Kosten (basierend auf Provider-spezifischen Token-Preisen), Antwortzeit, Erfolg/Fehler. Dashboard-Auswertung: Kosten pro Tag/Woche/Monat, Kosten pro Task-Typ, Kosten pro Provider, Top-10 teuerste Anfragen. So behält der Admin die KI-Kosten im Blick.

**12. AiInitializer**
Bootstrap-Helper, der beim Start der Anwendung alle Provider registriert. Liest die Provider-Konfiguration aus der Datenbank, erstellt die Adapter-Instanzen und registriert sie in der Registry. Wird einmal pro Request aufgerufen (Singleton-Pattern).

**13. API-Key Verschlüsselung**
Alle API-Keys werden mit AES-256-GCM verschlüsselt, bevor sie in die Datenbank geschrieben werden. Der Encryption-Key wird aus der Umgebungsvariable `AI_ENCRYPTION_KEY` gelesen. Jeder Eintrag hat seinen eigenen IV (Initialization Vector) und Auth-Tag. Beim Auslesen wird der Key entschlüsselt und nur im RAM gehalten, nie geloggt oder in Responses ausgegeben.

**14. API: 6 Endpunkte in /api/ai/**
- `GET/POST/PUT/DELETE /api/ai/providers` – Provider CRUD (Keys werden maskiert zurückgegeben)
- `GET/PUT /api/ai/routing` – Task-Routing Konfiguration
- `GET /api/ai/usage` – Usage-Statistiken
- `POST /api/ai/test-connection` – Provider-Verbindung testen

**15. UI: ai_settings.twig mit Provider-Cards**
KI-Einstellungsseite mit Provider-Cards: Jeder Provider wird als Karte dargestellt (Logo, Name, Status aktiv/inaktiv, Modell, geschätzte Kosten/Monat). Klick öffnet das Konfigurationsformular (API-Key, Modell, Temperatur). Darunter: Task-Routing-Tabelle (welcher Task → welcher Provider), Fallback-Konfiguration. Unten: Usage-Chart (Kosten der letzten 30 Tage).

**16. Config: ai_bootstrap.php**
PHP-Konfigurationsdatei, die den AiInitializer aufruft und die globale `$aiRequestHandler`-Instanz bereitstellt. Wird in der `index.php` eingebunden.

**17. Migration bestehender ClaudeService**
Der bestehende ClaudeService (der direkt die Claude-API aufruft) muss auf den AiRequestHandler umgestellt werden. Alle Stellen, die `$claudeService->chat()` aufrufen, werden auf `$aiRequestHandler->execute()` umgestellt. So profitieren alle bestehenden KI-Features automatisch von Multi-Provider, Fallback und Anonymisierung.

**18. Tests**
Unit-Tests für: Jeden Adapter (Mock der HTTP-Responses), Registry (Task-Routing, Fallback bei Provider-Ausfall), AiRequestHandler (Integration mit Anonymisierung und Usage-Tracking), API-Key Verschlüsselung (Encrypt/Decrypt Roundtrip, ungültiger Key).

---

## I6 – KI-Anonymisierung

### Überblick
Pflicht-Anonymisierung für alle KI-Anfragen, die an Cloud-Provider (Claude, OpenAI, Gemini, Mistral) gesendet werden. Personenbezogene Daten (Namen, E-Mails, IBANs, Telefonnummern etc.) werden vor dem Senden durch Platzhalter ersetzt und in der Antwort wieder zurückgetauscht. So verlassen keine echten Kundendaten das Firmennetzwerk. Für lokale Provider (Ollama) kann die Anonymisierung deaktiviert werden.

### Bausteine im Detail

**1. DB-Migration: 2 Tabellen**
- `ai_anonymization_config` – Konfiguration pro Instanz: Modus (strikt/standard/minimal/aus), aktive Patterns (JSON-Array), DB-Abgleich aktiv (ja/nein), maximale Mapping-Lebensdauer (Sekunden).
- `ai_anonymization_audit_log` – Audit-Log: Request-ID, Zeitstempel, Modus, Anzahl Ersetzungen pro Typ (z.B. 3× IBAN, 2× E-Mail), keine echten Daten! Nur Zähler und Typen werden geloggt.

**2. AnonymizationService: anonymize() + deAnonymize()**
`anonymize($text)` durchsucht den Text nach personenbezogenen Daten, ersetzt sie durch Platzhalter (z.B. `[IBAN_1]`, `[EMAIL_1]`, `[NAME_1]`) und speichert die Zuordnung (Mapping) im RAM. `deAnonymize($text, $mapping)` tauscht die Platzhalter in der KI-Antwort wieder gegen die echten Daten zurück. Das Mapping wird nie persistiert, nur für die Dauer eines einzelnen Requests im Speicher gehalten.

**3. 7 Regex-Patterns**
Folgende Muster werden erkannt und ersetzt:
- **IBAN:** Deutsches Format (DE + 2 Prüfziffern + 18 Ziffern) und internationale Formate
- **E-Mail:** Standard-E-Mail-Regex
- **Telefon:** Deutsche Formate (+49, 0049, 0-Vorwahl), Mobilnummern, Festnetz
- **USt-IdNr:** Deutsches Format (DE + 9 Ziffern) und EU-Formate
- **Steuernummer:** Deutsches Format (XX/XXX/XXXXX)
- **IP-Adresse:** IPv4 und IPv6
- **Datumsangaben:** Deutsche Formate (TT.MM.JJJJ, TT.MM.JJ)

**4. DB-Abgleich**
Zusätzlich zu den Regex-Patterns prüft der Service, ob im Text Namen vorkommen, die in der Datenbank als Kunden, Kontaktpersonen oder Mitarbeiter gespeichert sind. Dazu werden alle aktiven Kunden-, Kontakt- und Mitarbeiternamen geladen und per String-Matching im Text gesucht. Gefundene Namen werden durch `[PERSON_1]`, `[PERSON_2]` etc. ersetzt. Dies fängt Fälle ab, die Regex nicht erkennt (z.B. „Herr Müller hat angerufen").

**5. 4 Modi**
- **Strikt:** Alle 7 Regex-Patterns + DB-Abgleich aktiv. Maximaler Schutz, aber möglicherweise False Positives (z.B. eine Bestellnummer, die wie eine IBAN aussieht).
- **Standard:** Alle 7 Regex-Patterns aktiv, aber kein DB-Abgleich. Guter Kompromiss zwischen Schutz und Performance.
- **Minimal:** Nur IBAN, E-Mail und Telefon werden ersetzt. Für Fälle, wo andere Daten für die KI relevant sind.
- **Aus:** Keine Anonymisierung. Nur für lokale Provider (Ollama) erlaubt.

**6. Integration in AiRequestHandler**
Die Anonymisierung wird automatisch im AiRequestHandler ausgeführt – kein Service muss sich selbst darum kümmern. Ablauf: User-Prompt kommt rein → `anonymize()` → anonymisierter Prompt wird an KI gesendet → Antwort kommt zurück → `deAnonymize()` → Antwort mit echten Daten wird an den Service zurückgegeben. Für Cloud-Provider wird mindestens Modus „Standard" erzwungen.

**7. Cloud-Provider min. Standard erzwungen**
Hardcoded im AiRequestHandler: Wenn der aktive Provider ein Cloud-Provider ist (alles außer Ollama), wird die Anonymisierung mindestens auf „Standard" gesetzt, auch wenn der Admin „Aus" konfiguriert hat. Dies verhindert versehentliches Senden von Klartext-Daten an Cloud-APIs. Nur bei Ollama (lokal) kann die Anonymisierung komplett deaktiviert werden.

**8. API: 2 Endpunkte**
- `GET/PUT /api/ai/anonymization/config` – Anonymisierungs-Konfiguration lesen/schreiben
- `POST /api/ai/anonymization/test` – Test-Endpunkt: Text eingeben, anonymisierte Version sehen (zum Testen der Patterns ohne echten KI-Aufruf)

**9. Audit-Log**
Jeder anonymisierte Request wird im Audit-Log erfasst: Wie viele Ersetzungen pro Typ (3× IBAN, 2× E-Mail, 5× PERSON). Wichtig: Es werden NIEMALS die echten Daten oder die Platzhalter-Mappings geloggt! Nur die Zähler und Typen. Das Log dient zur Überprüfung, dass die Anonymisierung korrekt arbeitet.

**10. Tests**
Unit-Tests für: Alle 7 Regex-Patterns (jeweils gültige und ungültige Formate), DB-Abgleich (Name im Text gefunden, Name nicht im Text, Teilmatch), anonymize() + deAnonymize() Roundtrip (Original → anonymisiert → de-anonymisiert = Original), alle 4 Modi, Cloud-Provider-Erzwingung.
