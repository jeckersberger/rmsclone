# Release 1.1 — Implementierungsplan

Branch: `feature/rfid-cases-invoices-android`
Stand: 17. März 2026

---

## Status-Übersicht

### Migrations-Analyse
- **127 Migrations** total, alle PHP-Syntax korrekt
- **0 Tabellenkonflikte**, alle 167 Tabellen einzigartig
- **147 Foreign Keys** — alle valide
- **2 kleine Probleme:**
  - `20240320173000_new_project_finance_maths.php` — fehlende `down()` Methode
  - `20250302170000_fix_collation.php` — Raw SQL in `change()` ohne explizite `down()`

### Was fertig ist (geprüft & funktional)
- ✅ RFID/Scanner System (TID-Pairing, Universal Scan, Tag Format Service)
- ✅ Federation (Handshake, Tag Lookup, Cross-Instance, Foreign Loans)
- ✅ Stock Items (Two-Tier Model, Assignments, Warnings)
- ✅ External Items (Fremdmaterial-Verwaltung)
- ✅ Case/Box Management (Content Definition, Verification)
- ✅ Packlisten (Generierung, Scan-Abhaken, Multi-Entity)
- ✅ Android App Struktur (12 Screens, API Layer, Partner-Anzeige)
- ✅ Label-Druck System (Templates, ZPL)
- ✅ Company Code System (MD5, Collision Detection)

### Was geprüft werden muss (152 Services, 200+ Endpoints)

---

## Phase 1: Kritische Fixes (sofort)

### 1.1 Migration Fixes
- [ ] `20240320173000_new_project_finance_maths.php` — `down()` Methode ergänzen
- [ ] `20250302170000_fix_collation.php` — explizite `down()` Methode ergänzen

### 1.2 QR+RFID Dual-Scanning (Android App)
- [ ] BroadcastReceiver: Barcode-Wert an ScanViewModel weiterleiten (nicht nur Trigger)
- [ ] ScanTriggerManager: BarcodeEvent hinzufügen
- [ ] ScanViewModel: handleBarcodeScanned() Methode
- [ ] Input-Erkennung: TID (hex) vs RMS-Format (QR) vs Barcode automatisch unterscheiden
- [ ] Backend: Sicherstellen dass scan.php beide Input-Typen verarbeitet

---

## Phase 2: Finance & Banking (11 Migrations, 18 Services)

### 2.1 Bank-Import & FinTS
- [ ] BankImportService.php — MT940, CAMT.053, CSV Import verifizieren
- [ ] FinTSService.php — FinTS/HBCI Bankverbindung + TAN-Support prüfen
- [ ] API-Endpoints für Bank-Import testen
- [ ] Tabellen: bank_accounts, bank_import_sessions, bank_transactions, fints_tan_sessions, fints_sync_log

### 2.2 SEPA & Zahlungen
- [ ] SepaService.php — SEPA-Lastschrift-Mandate (CORE & B2B) verifizieren
- [ ] PaymentTrackingService.php — Teilzahlungen prüfen
- [ ] PaymentMatchingService.php — Automatisches Matching Bank → Rechnung
- [ ] Tabellen: sepa_mandates, invoice_payments

### 2.3 Buchhaltung
- [ ] BwaService.php — BWA (Betriebswirtschaftliche Auswertung) prüfen
- [ ] KassenbuchService.php — Kassenbuch verifizieren
- [ ] UstvaService.php — Umsatzsteuer-Voranmeldung prüfen
- [ ] EuerService.php — Einnahmen-Überschuss-Rechnung prüfen
- [ ] DatevExportService.php — DATEV-Export verifizieren
- [ ] Tabellen: bwa_reports, kassenbuch_entries, ustva_reports, euer_categories, euer_bookings, datev_exports, datev_account_mapping

### 2.4 Mahnwesen
- [ ] DunningService.php — 4-stufiges Mahnwesen prüfen
- [ ] DunningQueueService.php — Automatische Mahnläufe
- [ ] DunningLetterService.php — Mahnbrief-Generierung (PDF)
- [ ] Tabellen: dunning_levels, dunning_history

---

## Phase 3: Dokumente & Workflow (8 Migrations, 8 Services)

### 3.1 Angebots-Workflow
- [ ] DocumentLifecycleService.php — Quote → Order → Invoice Lifecycle
- [ ] Quote Approval Tokens — Kunden-Freigabe per URL
- [ ] Quote Versioning — Angebots-Versionierung
- [ ] Text Blocks — Wiederverwendbare Textbausteine
- [ ] Tabellen: document_lifecycle, document_status_history, quote_approval_tokens, text_blocks

### 3.2 Rechnungen
- [ ] InvoiceService — Abschlagsrechnungen (Partial Invoices) prüfen
- [ ] InvoiceEmailService.php — Automatischer E-Mail-Versand
- [ ] RecurringInvoiceService.php — Wiederkehrende Rechnungen
- [ ] IncomingInvoiceService.php — Eingangsrechnungen + Bewirtungsbelege
- [ ] Tabellen: recurring_invoices, incoming_invoices, incoming_invoice_files, document_email_log

### 3.3 E-Rechnungen
- [ ] ZugferdService.php — ZUGFeRD XML-Generierung prüfen
- [ ] XRechnungService.php — XRechnung für Behörden
- [ ] Reverse Charge — EU-Reverse-Charge (Art. 196 MwSt-RL)

---

## Phase 4: Compliance & Sicherheit (6 Migrations, 12 Services)

### 4.1 DSGVO
- [ ] DsgvoService.php — Datenexport, Löschanfragen, Anonymisierung
- [ ] DsgvoReportService.php — DSGVO-Berichte
- [ ] CookieConsentService.php — Cookie-Banner
- [ ] Tabellen: dsgvo_log, dsgvo_reports, cookie_consents

### 4.2 GoBD
- [ ] GobdArchiveService.php — Revisionssichere Archivierung
- [ ] GobdDocumentationService.php — Verfahrensdokumentation
- [ ] FileEncryptionService.php — AES-256-GCM Verschlüsselung
- [ ] Tabellen: gobd_retention_config, document_sequence_log

### 4.3 Sicherheit
- [ ] RateLimitService.php — Rate Limiting prüfen
- [ ] PasswordPolicyService.php — Passwort-Richtlinien
- [ ] SecurityHeadersService.php — HTTP Security Headers
- [ ] CsrfService.php — CSRF-Schutz
- [ ] TotpService.php — 2FA (TOTP)
- [ ] Tabelle: rate_limits

---

## Phase 5: Kommunikation & AI (6 Migrations, 5 Services)

### 5.1 E-Mail
- [ ] ImapMailService.php — IMAP-Inbox-Integration
- [ ] EmailTemplateService.php — E-Mail-Vorlagen
- [ ] Tabellen: emailReceived, emailAttachments

### 5.2 AI-Features
- [ ] ClaudeService.php — AI-Konfiguration
- [ ] AiActionQueueService.php — Automatisierungs-Queue mit Approval-Workflow
- [ ] AiAssetLookupService.php — Intelligente Asset-Suche
- [ ] ChatService.php — AI-Chat-Interface
- [ ] Tabellen: ai_action_queue, ai_usage_log, ai_chat_conversations

### 5.3 Webhooks & Benachrichtigungen
- [ ] WebhookService.php — Webhook-System
- [ ] NotificationService.php — Push-Benachrichtigungen
- [ ] SmsNotificationService.php — SMS-Versand

---

## Phase 6: Client & Projekt-Management (15 Services)

### 6.1 Kunden-Verwaltung
- [ ] ClientCategoryService.php — Kunden-Kategorien
- [ ] ClientContactService.php — Mehrere Ansprechpartner pro Kunde
- [ ] ClientImportService.php — CSV-Import
- [ ] ClientMergeService.php — Duplikat-Zusammenführung
- [ ] ClientCreditService.php — Kreditlimit
- [ ] CustomerPortalService.php — Kunden-Portal
- [ ] CustomerPricingService.php — Kundenspezifische Preise
- [ ] ViesValidationService.php — EU-USt-ID-Validierung
- [ ] Tabellen: client_contacts, client_tags, client_tag_assignments

### 6.2 Projekt-Features
- [ ] AvailabilityService.php — Verfügbarkeitsprüfung
- [ ] ConflictDetectionService.php — Doppelbuchungs-Erkennung
- [ ] ProjectChecklistService.php — Projekt-Checklisten
- [ ] ProjectCommentService.php — Projekt-Kommentare
- [ ] RecurringProjectService.php — Wiederkehrende Projekte
- [ ] Tabelle: asset_availability_blocks

---

## Phase 7: Asset-Erweiterungen & Reporting

### 7.1 Asset Lifecycle
- [ ] EquipmentLifecycleService.php — Garantie, Versicherung, Abschreibung
- [ ] DepreciationService.php — AfA-Berechnung
- [ ] MaintenanceScheduleService.php — Wartungsintervalle
- [ ] InsuranceService.php — Versicherungs-Tracking
- [ ] DamageReportService.php — Schadensmeldungen

### 7.2 Reporting
- [ ] ReportingService.php — Report-Framework
- [ ] UtilizationReportService.php — Auslastungsberichte
- [ ] ProfitCalculationService.php — Gewinnberechnung
- [ ] ReportExportService.php — PDF/Excel-Export
- [ ] DashboardWidgetService.php — Dashboard-Widgets
- [ ] Tabelle: saved_reports

---

## Phase 8: Integrations & Sonstiges

### 8.1 Externe Dienste
- [ ] CalendarSyncService.php — Kalender-Sync
- [ ] StripePaymentService.php — Stripe-Zahlungen
- [ ] CloudAccountingExportService.php — Cloud-Buchhaltung

### 8.2 Sonstiges
- [ ] PwaService.php — Progressive Web App
- [ ] DarkModeService.php — Dark Mode
- [ ] FavoritesService.php — Favoriten-System
- [ ] ShippingService.php — Versand-Tracking
- [ ] DigitalSignatureService.php — Digitale Signaturen
- [ ] GiroCodeService.php — GiroCode auf Rechnungen

---

## Phase 9: Finaler Test & Release

### 9.1 Migration-Test
- [ ] Alle 127 Migrations gegen saubere DB laufen lassen
- [ ] Rollback aller Migrations testen (down/rollback)
- [ ] Foreign Key Integrität verifizieren

### 9.2 Service-Tests
- [ ] PHP Syntax-Check aller Services
- [ ] Dependency-Check (fehlende Includes/Uses)
- [ ] API-Endpoint-Tests (curl/Postman)

### 9.3 Android App
- [ ] Chafon SDK Stubs → Mock-Implementation für Testing
- [ ] QR+RFID Dual-Scanning E2E-Test
- [ ] Alle 12 Screens gegen API testen

### 9.4 Release
- [ ] Git Tag: v1.1.0
- [ ] GitHub Release erstellen
- [ ] CHANGELOG.md schreiben
- [ ] FEATURES.md finalisieren

---

## Zeitschätzung

| Phase | Aufwand | Priorität |
|-------|---------|-----------|
| Phase 1: Kritische Fixes | 1 Session | KRITISCH |
| Phase 2: Finance & Banking | 2-3 Sessions | HOCH |
| Phase 3: Dokumente & Workflow | 2 Sessions | HOCH |
| Phase 4: Compliance & Sicherheit | 1-2 Sessions | HOCH |
| Phase 5: Kommunikation & AI | 1-2 Sessions | MITTEL |
| Phase 6: Client & Projekt | 1-2 Sessions | MITTEL |
| Phase 7: Asset & Reporting | 1 Session | MITTEL |
| Phase 8: Integrations | 1 Session | NIEDRIG |
| Phase 9: Finaler Test | 2-3 Sessions | KRITISCH |
| **Gesamt** | **~12-18 Sessions** | |

---

## Offene Fragen an den Auftraggeber

1. **FinTS/HBCI:** Welche Bank(en) nutzt du? Brauchen wir eine echte Testbank-Verbindung oder reicht Simulation?
2. **DATEV:** Nutzt du einen Steuerberater mit DATEV? Welches Kontenrahmen (SKR03/SKR04)?
3. **ZUGFeRD/XRechnung:** Stellst du Rechnungen an Behörden? (XRechnung ist Pflicht für öffentliche Auftraggeber)
4. **Stripe:** Soll Online-Zahlung direkt über die Software möglich sein?
5. **AI-Features (Claude API):** Soll das System KI-gestützte Funktionen haben (Rechnungsscan, intelligente Suche, E-Mail-Entwürfe)? Hast du einen Anthropic API-Key?
6. **IMAP Inbox:** Soll das System E-Mails direkt empfangen und verarbeiten können?
7. **SMS-Benachrichtigungen:** Brauchst du SMS-Versand (z.B. für Erinnerungen)?
8. **Kunden-Portal:** Sollen Kunden sich einloggen und eigene Angebote/Rechnungen sehen?
9. **KUR (Kleinunternehmerregelung):** Bist du Kleinunternehmer nach §19 UStG oder regelbesteuert?
10. **Bewirtungsbelege:** Brauchst du die Erfassung von Bewirtungsbelegen für die Steuer?
