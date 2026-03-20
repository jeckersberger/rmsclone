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
