# AdamRMS - TODO Tracker
# Basierend auf ANALYSE_UND_EMPFEHLUNGEN.md + zusätzliche Feature-Wünsche
# Stand: 08.03.2026

---

## Phase 1: Grundlagen (KRITISCH - Legaler Betrieb in DE)

### 6.1.1 Kleinunternehmerregelung (KUR)
- [x] KUR-Flag in Datenbank (instances_kurEnabled) — `db/migrations/20260228100000_german_business_fields.php`
- [x] KUR-Logik in DocumentRenderer — `src/services/DocumentRenderer.php`
- [x] Pflichthinweis "Gemäß § 19 UStG..." auf Rechnungen — `DocumentRenderer.php`
- [x] Umsatzgrenze-Tracking/Warnung — `src/business/euer.php` + `euer.twig`
- [x] KUR-Einstellung in Business-Settings-UI (Toggle) — `src/instances/instances_germanSettings.twig`
- [x] Automatische E-Mail-Warnung bei Annäherung an Umsatzgrenze (80%, 90%, 100%) — `src/cron/kur-threshold-check.php`
- [x] KUR-Übergangslogik: Automatischer Wechsel zur Regelbesteuerung bei Überschreitung — `kur-threshold-check.php` + `DocumentRenderer.php` (instances_kurTransitionYear)

### 6.1.2 GoBD-Konforme Rechnungen
- [x] Steuernummer/USt-IdNr. in DB — `instances_taxNumber`, `instances_vatId`
- [x] Fortlaufende Rechnungsnummer — `src/services/SequenceService.php`
- [x] Nummernkreise (RE-2026-0001, AN-2026-0001, LS-2026-0001) — `SequenceService`
- [x] Steuersatz und Steuerbetrag getrennt — `DocumentRenderer.php`
- [x] Netto-/MwSt-/Bruttobetrag getrennt — `DocumentRenderer.php`
- [x] KUR-Hinweis auf Steuerbefreiung — `DocumentRenderer.php`
- [x] Leistungszeitraum/Lieferdatum auf Rechnung — `DocumentRenderer.php` + `document_de.twig`
- [x] Aufbewahrungspflicht (10 Jahre) - automatische Archivierung — `src/cron/gobd-archive-check.php` + `document_exports.retention_expires_at`
- [x] Unveränderbarkeit der Rechnung nach Erstellung (Dokument-Locking) — `DocumentRenderer.php`: GoBD-Sperre bei Duplikat
- [x] Lückenlose Nummernkreise sicherstellen (keine gelöschten Nummern) — `SequenceService.php`: Row-Level Locking + `document_sequence_log` Tabelle
- [x] Verfahrensdokumentation (GoBD-Pflicht) als generiertes PDF — `src/services/GobdDocumentationService.php` + `src/api/gobd/verfahrensdokumentation.php`

### 6.1.3 XRechnung / ZUGFeRD
- [x] DB-Tabellen für ZUGFeRD-Struktur — `db/migrations/20260228130000_phase3_zugferd_reports_logistics.php`
- [x] ~~horstoeko/zugferd Composer-Package installieren~~ — Eigene Implementierung statt Library: `src/services/ZugferdService.php` + `src/services/PdfA3Converter.php`
- [x] ZUGFeRD PDF/A-3 + XML Generierung — `src/services/ZugferdService.php` + `src/services/PdfA3Converter.php` + Integration in `DocumentRenderer.php`
- [x] XRechnung Unterstützung — `src/services/XRechnungService.php` (UBL 2.1) + Integration in `DocumentRenderer.php`
- [x] Leitweg-ID Feld für öffentliche Auftraggeber — `clients_leitwegId` + `clients_buyerReference` in DB

### 6.1.4 DSGVO (Datenschutz)
- [x] Löschkonzept für Kundendaten — `src/services/DsgvoService.php` (Art. 17, Aufbewahrungsfristen § 147 AO)
- [x] Datenexport-Funktion (Art. 20 DSGVO - Datenportabilität) — `src/api/dsgvo/export.php` + `DsgvoService.php`
- [x] Cookie-Consent-Verwaltung — `src/services/CookieConsentService.php` + Banner in `template.twig` + `cookie_consents` Tabelle
- [x] DSGVO-konforme Dokumentation des Audit-Logs — `dsgvo_log` Tabelle + `src/api/dsgvo/log.php`
- [x] DSGVO-Verwaltungsoberfläche — `src/business/dsgvo.php` + `dsgvo.twig`
- [x] Aufbewahrungsfristen-Prüfung — `src/api/dsgvo/retentionCheck.php`
- [x] Löschvorschläge (Clients > 10 Jahre inaktiv) — `src/api/dsgvo/deletionSuggestions.php`
- [x] Auftragsverarbeitungsvertrag (AVV) Vorlage generieren — `src/services/AvvService.php`
- [x] Datenschutzerklärung / Impressum-Seite — `src/business/legal.php` + `legal.twig` (Auto-Impressum aus Geschäftsdaten)
- [x] Automatischer DSGVO-Report (jährlich) als PDF — `src/services/DsgvoReportService.php` + `src/api/dsgvo/annualReport.php`

### Datenbank & Lokalisierung
- [x] DB auf utf8mb4 umstellen — Migration vorhanden
- [x] i18n/Lokalisierung-System — `src/common/libs/i18n/Translator.php`
- [x] Deutsche Übersetzungsdatei — `src/common/libs/i18n/locales/de_DE.php` (~200 Keys)
- [x] Englische Übersetzungsdatei — `src/common/libs/i18n/locales/en_GB.php` (~200 Keys)
- [x] Deutsche Datumsformate (DD.MM.YYYY) — `dateDe` Twig-Filter
- [x] Deutsche Zahlenformate (1.234,56) — `numberDe` Twig-Filter
- [x] Deutsche Geldformate — `moneyDe` Twig-Filter
- [x] Steuernummer in Business-Settings-DB — `instances_taxNumber`
- [x] USt-IdNr. in Business-Settings-DB — `instances_vatId`
- [x] IBAN/BIC/Bankname in DB — `instances_bankIban`, `instances_bankBic`, `instances_bankName`

---

## Phase 2: Kerngeschäft (Produktiver Einsatz)

### 6.2.1 Angebotswesen
- [x] Angebot → Auftrag → Rechnung Workflow — `src/services/DocumentLifecycleService.php`
- [x] Dokumentenstatus-Tracking (erstellt, gesendet, angenommen, abgelehnt) — `DocumentLifecycleService`
- [x] Quick-Convert Buttons (Angebot → AB → Rechnung) — `src/project/project_documents.twig`
- [x] Angebots-Vorlagen mit Textbausteinen — `text_blocks` Tabelle + API + `src/business/textblocks.php`
- [x] Angebots-Gültigkeit (Ablaufdatum) — `DocumentLifecycleService` (valid_until, +30 Tage Default) + `document_de.twig` + `src/cron/quote-expiry-check.php`
- [x] Angebots-Versionen — `DocumentLifecycleService::createNewVersion()` + `db/migrations/20260308100000_quote_versions.php`
- [x] PDF-Vorschau vor dem Versand — `src/api/documentLifecycle/preview.php` + `DocumentRenderer::renderPreview()` + Modal in `project_documents.twig`
- [x] Skonto-Bedingungen auf Angeboten/Rechnungen — `DocumentRenderer.php` (Skonto-Rate + Tage) + `ZugferdService.php` (XML Payment Terms)

### 6.2.2 Rechnungswesen
- [x] Mahnwesen (Vorschlagssystem) — `src/business/dunning.php` + `dunning.twig`
- [x] Mahnstufen-Eskalation (Erinnerung → 1./2./3. Mahnung) — `DunningService`
- [x] Stornorechnung/Gutschrift — `DocumentLifecycleService` (credit_note, cancellation)
- [x] Zahlungsbedingungen in DB — `instances_paymentTermDays`, `clients_paymentTermDays`
- [x] Teilrechnungen / Abschlagsrechnungen — `db/migrations/20260307300000_partial_invoices.php` + API-Endpoints (`partialInvoice.php`, `finalInvoice.php`)
- [x] SEPA-Lastschrift-Mandatsverwaltung — `src/services/SepaService.php` + `src/business/sepa.php` + `sepa.twig` + API-Endpoints
- [x] Automatischer Rechnungsversand per E-Mail (Cronjob-basiert) — `src/services/InvoiceEmailService.php` + `src/cron/auto-invoice-email.php` + Migration
- [x] Wiederkehrende Projekte (Vorlage) — `src/services/RecurringProjectService.php`
- [x] Mahngebühren automatisch berechnen und auf Mahnung ausweisen — `DunningService` (Zinsen + Mahngebühren pro Stufe)
- [x] Mahnbriefe als PDF generieren und per E-Mail versenden — `src/services/DunningLetterService.php` + `src/templates/dunning_letter_de.twig` + `src/api/dunning/generateLetter.php`
- [x] Mahnsperre bei Teilzahlung (automatisch pausieren) — `PaymentTrackingService::pauseDunningForDocument()` + automatisch bei Teilzahlung
- [x] Zahlungseingänge mit Bankdaten abgleichen (MT940/CAMT Import) — `src/services/BankImportService.php` + `src/api/bank/` (import, transactions, match, ignore)
- [x] Sammelrechnung (mehrere Projekte → eine Rechnung) — `src/services/CollectiveInvoiceService.php` + `src/business/collective-invoice.php` + Template + API
- [x] Reverse-Charge-Verfahren für EU-Auslandsgeschäfte — `clients_isEU`, `clients_reverseCharge` + `DocumentRenderer.php` + `document_de.twig` (Art. 196 MwSt-Richtlinie)
- [x] Teilzahlungs-Tracking auf Rechnungsebene — `src/services/PaymentTrackingService.php` + `src/api/payments/` (record, list, delete, toggleDunningPause)
- [x] Cron-Automatisierung für wiederkehrende Projekte — `src/cron/recurring-projects.php`
- [x] Wiederkehrende Projekte: Verfügbarkeits-Check vor Auto-Erstellung — `RecurringProjectService::checkTemplateAvailability()` + `AvailabilityService`

### 6.2.3 Buchhaltungsanbindung
- [x] DATEV-Export — `src/services/DatevExportService.php` + `src/business/datev.php`
- [x] EÜR-Unterstützung — `src/business/euer.php` + `euer.twig`
- [x] EÜR-Kategorien (Einnahme-/Ausgabearten für EÜR-Formular) — `euer_categories` Tabelle mit 7 vordefinierten Kategorien (Einnahmen KUR, Fremdpersonal, Personal, Fahrtkosten, Raumkosten, Betriebsausgaben, Abschreibungen)
- [x] SKR03/SKR04 Kontenzuordnung — `instances_datevKontenrahmen` (Standard SKR03) + Kontenmappings in `DatevExportService.php`
- [x] BWA-Auswertung (Betriebswirtschaftliche Auswertung) — `src/services/BwaService.php` + `src/business/bwa.php` + `bwa.twig` + `src/api/bwa/generate.php`
- [x] Export für lexoffice, sevDesk — `src/services/CloudAccountingExportService.php` + `src/business/cloud-accounting.php` + `src/api/cloudAccounting/export.php`
- [x] Bankanbindung (FinTS/HBCI) für automatischen Zahlungsabgleich — `src/services/FinTSService.php` + `src/services/BankImportService.php`
- [x] Umsatzsteuervoranmeldung (UStVA) vorbereiten (ELSTER-kompatibel) — `src/services/UstvaService.php` + `src/business/ustva.php` + `ustva.twig`
- [x] Kassenbuch (für Bareinnahmen/Barausgaben) — `src/services/KassenbuchService.php` + `src/business/kassenbuch.php` + `kassenbuch.twig` + API-Endpoints

### 6.2.4 Kundenverwaltung erweitern
- [x] Kundennummern — `clients_customerNumber` in DB
- [x] USt-IdNr. des Kunden — `clients_vatId` in DB
- [x] Zahlungsbedingungen pro Kunde — `clients_paymentTermDays` in DB
- [x] Kundenspezifische Preise — `src/services/CustomerPricingService.php`
- [x] Ansprechpartner (mehrere pro Kunde) — `src/services/ClientContactService.php` + `src/api/clients/contacts.php`
- [x] Kundenkategorien / Tags — `src/services/ClientCategoryService.php` + `src/api/clients/categories.php` + `assignCategory.php`
- [x] Kundenhistorie (alle Projekte, Angebote, Rechnungen) — `src/business/client-history.php` + `client-history.twig`
- [x] Kommunikationsprotokoll — `src/services/ClientCommunicationService.php` + `src/api/clients/communications.php`
- [x] Kreditlimit — `src/services/ClientCreditService.php` + `src/api/clients/creditCheck.php`
- [x] Kunden-Duplikate erkennen und zusammenführen (Merge) — `src/services/ClientMergeService.php` + `db/migrations/20260308500200_vies_duplicates_export.php`
- [x] Kunden-Import aus CSV/Excel — `src/services/ClientImportService.php` + `src/business/client-import.php` + `client-import.twig` + API-Endpoints
- [x] Lieferadresse pro Kunde — `clients_deliveryAddress`, `clients_deliveryContact`, `clients_deliveryPhone`, `clients_deliveryNotes` in DB
- [x] USt-IdNr. Validierung über VIES (EU-Dienst) — `src/services/ViesValidationService.php` + `vies_validation_cache` Tabelle

---

## Phase 3: Professionalisierung

### Reporting/Auswertungen
- [x] Reports-Dashboard — `src/business/reports.php` + `reports.twig`
- [x] Gewinn-Dashboard (Projekt-Profit, Marge) — `src/business/profit.php` + `profit.twig`
- [x] Umsatzprognose/Forecast — `src/services/ProfitCalculationService.php`
- [x] Steuer-Export (CSV) — `src/services/SteuerExportService.php`
- [x] Auslastungsberichte für Equipment (Auslastungsquote pro Asset-Typ) — `src/services/UtilizationReportService.php` + `src/api/reports/utilization.php` + Tab in `reports.twig`
- [x] Export nach Excel/PDF für Reports — `ReportExportService::exportExcel()` + `exportPdf()` + PhpSpreadsheet + dompdf
- [x] Top-Kunden Ranking nach Umsatz — `ProfitCalculationService::getTopClients()` + `src/api/reports/topClients.php` + Tab in `reports.twig`
- [x] Saisonalitäts-Analyse (welche Monate sind am stärksten) — `ProfitCalculationService::getSeasonality()` + `src/api/reports/seasonality.php` + Tab in `reports.twig`
- [x] Equipment-ROI Berechnung (Anschaffung vs. Mieteinnahmen) — `UtilizationReportService::calculateRoi()` + `src/api/reports/roi.php` + Tab in `reports.twig`
- [x] Vergleichsreports (Monat-zu-Monat, Jahr-zu-Jahr) — `ProfitCalculationService::comparePeriods()` + `src/api/reports/compare.php` + Tab in `reports.twig`
- [x] Dashboard-Widgets konfigurierbar machen (Drag & Drop Anordnung) — `src/services/DashboardWidgetService.php` + `src/api/dashboard/widgetConfig.php` + `db/migrations/20260308600000_dashboard_widgets.php`

### Logistik
- [x] Packlisten-Generierung — `src/services/PackingListService.php`
- [x] Lieferschein-Service — `src/services/DeliveryNoteService.php` + `src/api/deliveryNote/generate.php`
- [x] Check-in/Check-out mit Zustandsprotokoll — `src/services/DamageReportService.php`
- [x] Transportplanung (Fahrzeuge, Routen, Fahrer) — `src/services/TransportService.php` + `src/api/transport/` (7 Endpoints) + `src/business/transport.php` + `transport.twig`
- [x] Multi-Lager/Standortverwaltung — `src/services/WarehouseService.php` + `src/api/warehouse/` (6 Endpoints) + `src/business/warehouses.php` + `warehouses.twig` + Navigation in `template.twig`
- [x] Lieferschein-Nummer über SequenceService — `DeliveryNoteService.php`: `SequenceService::next()` statt `rand()`
- [x] Rückgabe-Erinnerungen automatisch versenden (1 Tag vorher) — `src/cron/return-reminders.php` (Vorab, Heute, Ueberfaellig + Kunden-Benachrichtigung)

### Code-Qualität
- [x] Unit Tests (PHPUnit) für Services — `phpunit.xml` + `tests/bootstrap.php` + 6 Test-Klassen in `tests/Unit/` (SequenceService, ReportExport, Dunning, Profit, Utilization, DocumentLifecycle)
- [x] SQL-Injection Audit (LIKE-Suche in clients.php etc.) — `manufacturer/search.php`, `categories/search.php`, `maintenance/searchUser.php`, `ProfitCalculationService.php` gefixt: Prepared Statements statt String-Concatenation
- [x] Code-Refactoring (Service-Layer konsequent nutzen) — Alle neuen Features als Services implementiert, Composer classmap-Autoloading fuer `src/services/`
- [x] API-Eingabevalidierung: intval/filter_input in allen Endpoints (besonders Partner-APIs) — `src/services/InputValidationService.php` (int, string, email, date, enum, positiveInt, float, json)
- [x] Error-Handling: Einheitliche try/catch-Blöcke in allen API-Endpunkten — `src/services/ErrorHandlerService.php` mit `wrap()` Methode + Sentry-Integration
- [x] PHP-CS-Fixer oder PHP_CodeSniffer für einheitlichen Code-Stil — `.php-cs-fixer.php` (PSR-12 + short arrays + import ordering)
- [x] Composer autoloading für Service-Klassen (statt manuelles require) — `composer.json`: classmap autoloading fuer `src/services/` und `src/common/libs/`
- [x] PHPStan / Psalm Static Analysis (Level 5+) — `phpstan.neon` (Level 5, src/services, globale Variablen-Ignores)

---

## Sicherheit & Robustheit

### KRITISCH - Sofort beheben!
- [x] **CORS Wildcard entfernen** — `src/api/apiHead.php`: Jetzt dynamische Origin-Prüfung via `CORS_ALLOWED_ORIGIN` env var
- [x] **CSRF-Token-Schutz** — `CsrfService.php` + Integration in `apiHeadSecure.php` + Meta-Tag + ajaxcall Header
- [x] **Partner-Code sicher** — `PartnerService.php`: `bin2hex(random_bytes(4))` + Kollisionsprüfung
- [x] **DSGVO IP-Logging gefixt** — `DsgvoService.php`: `getClientIp()` mit Cloudflare/Proxy-Support

### API-Sicherheit
- [x] Rate-Limiting für Login und API-Endpunkte — `RateLimitService.php` + DB-Migration + Login-Integration
- [x] Input-Sanitization: htmlspecialchars/strip_tags für alle User-Inputs — Globale Sanitization in `apiHead.php` + `InputSanitizer.php` + `InputValidationService.php`
- [x] Content-Security-Policy (CSP) Header setzen — `src/common/head.php` (bereits vorhanden)
- [x] X-Frame-Options Header (Clickjacking-Schutz) — `src/common/head.php` + `src/api/apiHead.php` (SAMEORIGIN/DENY + X-Content-Type-Options + Referrer-Policy + Permissions-Policy + HSTS)
- [x] Prepared Statements in allen rawQuery()-Aufrufen prüfen — Alle rawQuery() nutzen Parameterized Queries, `availability/overview.php` + `emailViewer.php` gefixt
- [x] API-Antworten: keine internen Fehler-Details an Client leaken — `apiHeadSecure.php`: Debug-Info nur ins Log, nicht an Client + `ErrorHandlerService.php`
- [x] **Partner-API: Input-Validierung** — `accept.php`, `invite.php`, `equipment.php`, `request.php`: filter_var, preg_match, json_last_error
- [x] **JSON-Decoding sicher** — `partner/request.php`: `json_last_error()` Prüfung hinzugefügt
- [x] **Datumsformate validiert** — `partner/equipment.php`, `partner/request.php`: YYYY-MM-DD Regex + strtotime-Prüfung
- [x] **Equipment-IDs geprüft** — `PartnerService.php`: `assetTypes_id` wird gegen `toInstanceId` validiert + intval + LIKE-Sanitization via `SqlSanitizer`
- [x] **Rate-Limiting für Partner-Code-Generierung** — `partner/generateCode.php`: Max 5 pro Stunde
- [x] **Account-Enumeration gefixt** — `partner/invite.php`: Einheitliche Fehlermeldung

### Session & Auth
- [x] Session-Cookie: HttpOnly + Secure + SameSite=Strict — `head.php`: session_set_cookie_params mit Array-Syntax
- [x] Session-Regeneration nach Login — `login.php`: `session_regenerate_id(true)` nach Erfolg
- [x] Passwort-Policy erzwingen (Mindestlänge, Komplexität) — `PasswordPolicyService.php` (10 Zeichen, Gross/Klein/Ziffern/Sonderzeichen, Common-Password-Check) + Integration in `changePass.php` + `forcePasswordChange.php`
- [x] Account-Lockout nach 15 Fehlversuchen (30 Min) — `login.php`: Temporäre Sperre + Meldung
- [x] Zwei-Faktor-Authentifizierung (2FA/TOTP) — `TotpService.php` (RFC 6238, Backup-Codes, bcrypt-gehashed) + `src/api/account/totpSetup.php` + Integration in `login.php`
- [x] Login-Protokoll (IP, Zeitpunkt, Erfolg/Fehler) — `LoginLogService.php` + `login_log` Tabelle + Integration in `login.php` + `src/api/account/loginLog.php`

### Datenbank-Sicherheit
- [x] Foreign Keys für alle neuen Tabellen — `20260304110000_add_foreign_keys.php` (16 Tabellen)
- [x] Verschlüsselung sensibler Daten at-rest (IBAN, Steuernummer) — `EncryptionService.php` (AES-256-GCM, ENCRYPTION_KEY env var, encrypt/decrypt/encryptFields/decryptFields)
- [x] Automatische Datenbank-Backups (mysqldump Cronjob) — `src/cron/database-backup.php` (gzip, Retention-Policy, konfigurierbar)
- [x] DB-Benutzer mit minimalen Rechten (kein DROP/ALTER in Produktion) — `docs/DB_SECURITY.md` (App/Migrate/Backup Benutzer-Konzept)
- [x] **SQL-Sanitization vereinheitlicht** — `SqlSanitizer.php` fuer LIKE-Escaping, `sanitizeStringMYSQL()` durch `SqlSanitizer::sanitizeSearch()` ersetzt in `searchType.php`

### Datei-Sicherheit
- [x] Upload-Validierung: Dateityp, Dateigröße, MIME-Type prüfen — `UploadValidationService.php` + finfo MIME-Check + PHP-Code-Detection in `localUpload.php`
- [x] Uploaded Files außerhalb des Webroot speichern — `LocalFileStorage.php`: `/data/uploads/` + `localUpload.php`: Default geaendert zu `/data/uploads`
- [x] Virus-Scan für hochgeladene Dateien (ClamAV) — `VirusScanService.php` (clamdscan/clamscan, fail-open mit Logging) + Integration in `localUpload.php`

### DSGVO-Sicherheit
- [x] **Datenexport filtert interne Felder** — `DsgvoService.php`: Whitelist-Ansatz, nur personenbezogene Felder exportieren
- [x] Verschlüsselter Datenexport (ZIP mit Passwort) für E-Mail-Versand — `src/api/dsgvo/export.php`: format=encrypted, AES-256 ZIP + Passwort
- [x] Automatische Löschung temporärer Export-Dateien — `src/cron/cleanup-temp-exports.php` (stündlich, 1h max Alter)

---

## Zusätzliche Features (User-Wünsche)

### Dashboard & Navigation
- [x] Enhanced Dashboard mit Tagesübersicht — `src/dashboard_enhanced.twig`
- [x] Dashboard-API (Projekte heute, überfällige Rückgaben, offene Posten) — `src/api/dashboard/overview.php`
- [x] Globale Live-Schnellsuche — `src/api/search/quick.php` + Template-Integration
- [x] Verfügbarkeitskalender — `src/business/availability.php` + `availability.twig`
- [x] Navigation-Audit: Defekte Links behoben, verwaiste Seiten verlinkt — `template.twig`, `instances_navigation.twig`
- [x] Label-Drucker-Integration (HTML + ZPL/Zebra) — `src/mobile/labels.php` + `labels.twig` + Buttons in `barcodeGenerator.twig`
- [ ] Benachrichtigungs-Center (In-App Benachrichtigungen)
- [ ] Favoriten/Lesezeichen für häufig genutzte Seiten

### Projekt-Features
- [x] Projekt-Klonen — `src/api/projects/clone.php` + UI in `project_index.twig`
- [x] Wiederkehrende Projekte — `src/business/recurring.php` + `recurring.twig`
- [ ] Drag & Drop Kalender-Ansicht für Projekte
- [ ] Projekt-Timeline/Gantt-Diagramm
- [ ] Checklisten pro Projekt (Aufgaben abhaken)
- [ ] Projekt-Kommentare/Notizen-Thread (intern)
- [ ] Projekt-Fotos (Vorher/Nachher für Events)
- [ ] Automatische Konflikt-Warnung bei Doppelbuchungen (UI-Integration)

### Multi-Business Kooperation
- [x] Partner-Code-System — `src/services/PartnerService.php`
- [x] Partner einladen/annehmen — `src/api/partner/invite.php`, `accept.php`
- [x] Partner-Equipment durchsuchen — `src/api/partner/equipment.php`
- [x] Equipment-Anfragen an Partner — `src/api/partner/request.php`
- [x] Partner-Management UI — `src/business/partners.php` + `partners.twig`
- [ ] Partner-Preisabstimmung automatisch synchronisieren
- [ ] Partner-Auftragsverwaltung (cross-business Projekte)
- [ ] Partner-Abrechnung: Mieteinnahmen automatisch aufteilen
- [ ] Partner-Verfügbarkeitskalender synchronisieren

### Equipment-Verwaltung
- [x] Inventur/Barcode-Scanner — `src/business/inventory.php` + `inventory.twig`
- [x] Wartungsintervall-Tracking — `src/services/MaintenanceScheduleService.php`
- [x] Wartungsplan-UI — `src/business/maintenance-schedule.php` + `maintenance-schedule.twig`
- [x] Schadensmeldungen — `src/business/damage-reports.php` + `damage-reports.twig`
- [x] Kautionsverwaltung — `src/services/DepositService.php` + API
- [x] Versicherungsnachweis-Verwaltung — `src/services/InsuranceService.php`
- [ ] Equipment-Fotos (mehrere Bilder pro Asset)
- [x] QR-Code auf Equipment-Label mit Link zur Asset-Seite — `src/mobile/labels.php` + `labels.twig` (HTML + ZPL/Zebra Label-Drucker)
- [ ] Seriennummern-Verwaltung
- [ ] Handbücher/Datenblätter pro Asset-Typ hinterlegen
- [ ] Abschreibungsrechner (AfA nach deutschem Steuerrecht)
- [ ] Equipment-Lebenszyklus: Anschaffung → Betrieb → Ausmusterung
- [ ] Mindestbestand-Warnung (z.B. "nur noch 2 von 10 verfügbar")

### Finanzen & Kunden
- [x] Kundenspezifische Preislisten — `src/services/CustomerPricingService.php` + API
- [x] Projekt-Gewinnberechnung — `src/services/ProfitCalculationService.php` + UI
- [x] Umsatzprognose — `ProfitCalculationService.php` Forecast
- [ ] Staffelpreise (ab X Tage günstiger)
- [ ] Wochenend-/Feiertags-Zuschläge automatisch berechnen
- [ ] Mindestmietdauer pro Asset-Typ
- [ ] Rabatt-Codes / Aktionspreise

### Kommunikation
- [ ] WhatsApp/SMS Benachrichtigungen
- [ ] Digitale Unterschrift (auf Lieferschein / Angebot)
- [ ] Kunden-Portal (Self-Service: Projekte einsehen, Rechnungen downloaden)
- [ ] E-Mail-Vorlagen konfigurierbar machen (Twig-basiert)
- [ ] Automatische Projekt-Bestätigungs-E-Mail an Kunden
- [ ] Termin-Erinnerungen per E-Mail (X Tage vor Projekt-Start)
- [ ] Feedback-Anfrage nach Projekt-Ende

### RFID-Integration (Konzept fertig)
- [x] RFID-Konzept dokumentiert — `docs/RFID_KONZEPT.md`
- [ ] RfidService.php implementieren
- [ ] RFID Gateway-Anbindung
- [ ] RFID Label-Druck
- [ ] Automatische Inventur beim Durchfahren eines RFID-Gates

### KI-Integration
- [ ] **Asset-Daten automatisch per KI/Web-Suche ausfüllen** — Bei Asset-Anlage: Name/Hersteller eingeben → KI sucht online nach Produktdaten (Neupreis, Marktwert, Spezifikationen, Gewicht, Abmessungen) und füllt Felder automatisch aus. Daten sind editierbar falls fehlerhaft.
  - [ ] API-Endpoint: `src/api/assets/aiLookup.php` — Nimmt Name+Hersteller, gibt Produktdaten zurück
  - [ ] Service: `src/services/AiAssetLookupService.php` — Web-Suche + KI-Auswertung (Claude API oder OpenAI)
  - [ ] UI: Auto-Complete/Vorschläge beim Tippen in Asset-Erstellungsformular
  - [ ] Felder: Neupreis, aktueller Marktwert, Gewicht, Abmessungen, Kategorie-Vorschlag, Bild-URL
  - [ ] Alle KI-Vorschläge als "vorgeschlagen" markiert und vom Benutzer bestätigbar/änderbar

### Mobile & UX
- [ ] Progressive Web App (PWA) für mobile Nutzung
- [ ] Barcode-Scanner über Handy-Kamera (ohne extra App)
- [ ] Offline-Modus für Inventur (Sync bei Internetverbindung)
- [ ] Dark Mode (DB-Feld existiert bereits)
- [ ] Touch-optimierte UI für Tablets (Lager-Nutzung)
- [ ] Schnellerfassung: Projekt anlegen in unter 30 Sekunden
- [ ] **QR-Code auf Lieferschein** — Scanbar per Handy, öffnet zugehörigen Packauftrag in mobiler Ansicht
  - [ ] QR-Code-Generierung im Lieferschein-PDF (enthält URL zum Packauftrag)
  - [ ] Mobile Packauftrag-Ansicht optimiert für Smartphone-Scan
  - [ ] DeliveryNoteService: QR-Code mit PackingList-Link generieren

### Android Scanner-App
- [ ] **Native Android-App für Equipment-Scanner** — Optimiert für Lager-/Eventbetrieb
  - [ ] Barcode/QR-Code Scanner (Kamera + externe Scanner via Bluetooth)
  - [ ] Equipment Check-in/Check-out mit Zustandserfassung (Foto + Notiz)
  - [ ] Lieferschein-QR scannen → Packauftrag öffnen und Positionen abhaken
  - [ ] Inventur-Modus: Assets scannen und mit Soll-Bestand abgleichen
  - [ ] Offline-Fähigkeit: Scans zwischenspeichern und bei Verbindung synchronisieren
  - [ ] Push-Benachrichtigungen bei anstehenden Projekten/Rückgaben
  - [ ] API-Authentifizierung über Token (kein Session-basierter Login)
  - [ ] Technologie: Kotlin/Jetpack Compose oder React Native

### Integrationen
- [ ] Google Calendar / Outlook Sync (bidirektional, nicht nur ICS-Export)
- [ ] Stripe/PayPal Zahlungslinks auf Rechnungen optional
- [ ] **Überweisungs-QR-Code auf Rechnungen** — EPC-QR-Code (GiroCode) mit IBAN, Betrag, Verwendungszweck für direktes Scannen mit Banking-App
  - [ ] QR-Code-Generierung im Rechnungs-PDF (EPC/GiroCode Standard)
  - [ ] JS-Unterstützung in Custom-Rechnungslayouts für dynamische QR-Code-Erzeugung
  - [ ] DocumentRenderer: QR-Code als Data-URI oder SVG einbetten
- [ ] Versand-Integration (DHL, DPD) für Equipment-Lieferung
- [ ] Buchhaltungs-API (lexoffice, sevDesk, FastBill)
- [ ] Webhook-System für externe Integrationen
- [ ] REST-API mit Swagger/OpenAPI Dokumentation (aktuell nicht RESTful)

### Dokumentation
- [x] System-Visualisierungen (ASCII) — `docs/VISUALISIERUNGEN.md`
- [x] RFID-Konzept — `docs/RFID_KONZEPT.md`
- [x] Analyse & Empfehlungen — `ANALYSE_UND_EMPFEHLUNGEN.md`
- [ ] Benutzerhandbuch (PDF/Wiki)
- [ ] Admin-Handbuch (Installation, Konfiguration, Backup)
- [ ] API-Dokumentation (OpenAPI/Swagger generieren)
- [ ] Video-Tutorials für Endbenutzer

### DevOps & Betrieb
- [ ] Docker-Compose Setup für Produktion (mit SSL, Reverse-Proxy)
- [ ] Automatische Datenbank-Migrationen beim Deployment
- [ ] Health-Check Endpoint (/api/health)
- [ ] Monitoring/Alerting (Uptime, Fehlerrate, Performance)
- [ ] Automatische Backups (DB + Dateien) mit Retention-Policy
- [ ] CI/CD Pipeline (GitHub Actions) für Tests + Deployment
- [ ] Staging-Umgebung für Tests vor Produktion
- [ ] Log-Rotation und zentrales Logging

---

## Zusammenfassung

| Bereich                    | Umgesetzt | Offen | Fortschritt |
|----------------------------|-----------|-------|-------------|
| Phase 1 - KUR              | 7/7       | 0     | **100%** ✅ |
| Phase 1 - GoBD             | 11/11     | 0     | **100%** ✅ |
| Phase 1 - ZUGFeRD          | 5/5       | 0     | **100%** ✅ |
| Phase 1 - DSGVO            | 10/10     | 0     | **100%** ✅ |
| Phase 1 - DB & Lokalisierung | 10/10   | 0     | **100%** ✅ |
| Phase 2 - Angebotswesen    | 8/8       | 0     | **100%** ✅ |
| Phase 2 - Rechnungswesen   | 15/15     | 0     | **100%** ✅ |
| Phase 2 - Buchhaltung      | 9/9       | 0     | **100%** ✅ |
| Phase 2 - Kunden           | 13/13     | 0     | **100%** ✅ |
| Phase 3 - Reporting        | 11/11     | 0     | **100%** ✅ |
| Phase 3 - Logistik         | 8/8       | 0     | **100%** ✅ |
| Phase 3 - Code-Qualität    | 8/8       | 0     | **100%** ✅ |
| Sicherheit - KRITISCH      | 4/4       | 0     | **100%** ✅ |
| Sicherheit - API           | 12/12     | 0     | **100%** ✅ |
| Sicherheit - Session/Auth  | 6/6       | 0     | **100%** ✅ |
| Sicherheit - Datenbank     | 5/5       | 0     | **100%** ✅ |
| Sicherheit - Dateien       | 3/3       | 0     | **100%** ✅ |
| Sicherheit - DSGVO         | 3/3       | 0     | **100%** ✅ |
| Extra - Dashboard/Nav      | 6/10      | 4     | 60%         |
| Extra - Projekte           | 2/8       | 6     | 25%         |
| Extra - Multi-Business     | 5/9       | 4     | 56%         |
| Extra - Equipment          | 7/13      | 6     | 54%         |
| Extra - Finanzen           | 3/7       | 4     | 43%         |
| Extra - KI-Integration     | 0/5       | 5     | 0%          |
| Extra - Kommunikation      | 0/7       | 7     | 0%          |
| Extra - RFID               | 1/5       | 4     | 20%         |
| Extra - Mobile/UX          | 0/9       | 9     | 0%          |
| Extra - Android-App        | 0/8       | 8     | 0%          |
| Extra - Integrationen      | 0/9       | 9     | 0%          |
| Extra - Dokumentation      | 3/7       | 4     | 43%         |
| Extra - DevOps             | 0/8       | 8     | 0%          |
| **GESAMT**                  | **177/271**| **94** | **65%**   |

---

## Priorisierte Empfehlung (nächste Schritte)

### SOFORT - Sicherheitslücken schließen (VOR Produktivbetrieb!)
1. ~~**CORS Wildcard entfernen**~~ ✅ erledigt
2. ~~**CSRF-Token-Schutz**~~ ✅ erledigt
3. ~~**Partner-Code sicher machen**~~ ✅ erledigt
4. ~~**Session-Cookie absichern**~~ ✅ erledigt
5. ~~**Input-Validierung in Partner-APIs**~~ ✅ erledigt
6. ~~**IP-Logging in DSGVO-Service fixen**~~ ✅ erledigt

### Sofort umsetzen (Blocker für legalen Betrieb)
7. ~~KUR-Toggle in Business-Settings-UI~~ ✅ erledigt
8. ~~Leistungszeitraum auf Rechnungen~~ ✅ erledigt
9. ~~Unveränderbarkeit der Rechnung nach Erstellung~~ ✅ erledigt
10. ~~Rate-Limiting für Login~~ ✅ erledigt

### Bald umsetzen (Phase 2 komplett ✅)
11. ~~Automatischer Rechnungsversand per E-Mail~~ ✅ erledigt
12. ~~Mahnbriefe als PDF generieren + versenden~~ ✅ erledigt
13. ~~Lieferschein-Nummer über SequenceService~~ ✅ erledigt
14. ~~Kundenhistorie-Übersichtsseite~~ ✅ erledigt
15. ~~Foreign Keys für neue Tabellen~~ ✅ erledigt
16. ~~Angebots-Vorlagen mit Textbausteinen~~ ✅ erledigt
17. ~~Cron-Job für wiederkehrende Projekte~~ ✅ erledigt
18. ~~EÜR-Kategorien~~ ✅ erledigt
19. ~~FinTS-Bankanbindung~~ ✅ erledigt
20. ~~BWA, UStVA, Kassenbuch~~ ✅ erledigt
21. ~~lexoffice/sevDesk Export~~ ✅ erledigt
22. ~~SEPA-Mandatsverwaltung~~ ✅ erledigt
23. ~~Kunden-Import, Kategorien, Ansprechpartner, VIES~~ ✅ erledigt

### Mittelfristig (Phase 3 + Sicherheit + Extras)
24. ~~ZUGFeRD implementieren~~ ✅ erledigt
25. ~~Teilrechnungen / Abschlagsrechnungen~~ ✅ erledigt
26. ~~XRechnung (UBL 2.1)~~ ✅ erledigt
27. ~~Lückenlose Nummernkreise~~ ✅ erledigt
28. ~~GoBD Verfahrensdokumentation~~ ✅ erledigt
29. ~~Cookie-Consent~~ ✅ erledigt
30. ~~DSGVO-Jahresbericht~~ ✅ erledigt
31. ~~AVV-Vorlage~~ ✅ erledigt
32. ~~Unit Tests für kritische Services (PHPUnit)~~ ✅ erledigt
33. KI-Asset-Lookup (Produktdaten automatisch ausfüllen)
34. QR-Code auf Rechnungen (EPC/GiroCode)
35. Kunden-Portal (Self-Service)
36. ~~Equipment-Auslastungsberichte~~ ✅ erledigt
37. Docker-Compose Produktions-Setup mit SSL
38. ~~2-Faktor-Authentifizierung (TOTP)~~ ✅ erledigt
39. Android Scanner-App für Equipment/Inventur

---

*Zuletzt aktualisiert: 08.03.2026 — Phase 1-3 + Sicherheit komplett (100%)*
