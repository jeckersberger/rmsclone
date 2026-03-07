# AdamRMS - TODO Tracker
# Basierend auf ANALYSE_UND_EMPFEHLUNGEN.md + zusätzliche Feature-Wünsche
# Stand: 01.03.2026

---

## Phase 1: Grundlagen (KRITISCH - Legaler Betrieb in DE)

### 6.1.1 Kleinunternehmerregelung (KUR)
- [x] KUR-Flag in Datenbank (instances_kurEnabled) — `db/migrations/20260228100000_german_business_fields.php`
- [x] KUR-Logik in DocumentRenderer — `src/services/DocumentRenderer.php`
- [x] Pflichthinweis "Gemäß § 19 UStG..." auf Rechnungen — `DocumentRenderer.php`
- [x] Umsatzgrenze-Tracking/Warnung — `src/business/euer.php` + `euer.twig`
- [x] KUR-Einstellung in Business-Settings-UI (Toggle) — `src/instances/instances_germanSettings.twig`
- [ ] Automatische E-Mail-Warnung bei Annäherung an Umsatzgrenze (80%, 90%, 100%)
- [ ] KUR-Übergangslogik: Automatischer Wechsel zur Regelbesteuerung bei Überschreitung

### 6.1.2 GoBD-Konforme Rechnungen
- [x] Steuernummer/USt-IdNr. in DB — `instances_taxNumber`, `instances_vatId`
- [x] Fortlaufende Rechnungsnummer — `src/services/SequenceService.php`
- [x] Nummernkreise (RE-2026-0001, AN-2026-0001, LS-2026-0001) — `SequenceService`
- [x] Steuersatz und Steuerbetrag getrennt — `DocumentRenderer.php`
- [x] Netto-/MwSt-/Bruttobetrag getrennt — `DocumentRenderer.php`
- [x] KUR-Hinweis auf Steuerbefreiung — `DocumentRenderer.php`
- [x] Leistungszeitraum/Lieferdatum auf Rechnung — `DocumentRenderer.php` + `document_de.twig`
- [ ] Aufbewahrungspflicht (10 Jahre) - automatische Archivierung
- [x] Unveränderbarkeit der Rechnung nach Erstellung (Dokument-Locking) — `DocumentRenderer.php`: GoBD-Sperre bei Duplikat
- [ ] Lückenlose Nummernkreise sicherstellen (keine gelöschten Nummern)
- [ ] Verfahrensdokumentation (GoBD-Pflicht) als generiertes PDF

### 6.1.3 XRechnung / ZUGFeRD
- [x] DB-Tabellen für ZUGFeRD-Struktur — `db/migrations/20260228130000_phase3_zugferd_reports_logistics.php`
- [ ] horstoeko/zugferd Composer-Package installieren
- [ ] ZUGFeRD PDF/A-3 + XML Generierung
- [ ] XRechnung Unterstützung
- [ ] Leitweg-ID Feld für öffentliche Auftraggeber

### 6.1.4 DSGVO (Datenschutz)
- [x] Löschkonzept für Kundendaten — `src/services/DsgvoService.php` (Art. 17, Aufbewahrungsfristen § 147 AO)
- [x] Datenexport-Funktion (Art. 20 DSGVO - Datenportabilität) — `src/api/dsgvo/export.php` + `DsgvoService.php`
- [ ] Cookie-Consent-Verwaltung
- [x] DSGVO-konforme Dokumentation des Audit-Logs — `dsgvo_log` Tabelle + `src/api/dsgvo/log.php`
- [x] DSGVO-Verwaltungsoberfläche — `src/business/dsgvo.php` + `dsgvo.twig`
- [x] Aufbewahrungsfristen-Prüfung — `src/api/dsgvo/retentionCheck.php`
- [x] Löschvorschläge (Clients > 10 Jahre inaktiv) — `src/api/dsgvo/deletionSuggestions.php`
- [ ] Auftragsverarbeitungsvertrag (AVV) Vorlage generieren
- [ ] Datenschutzerklärung / Impressum-Seite
- [ ] Automatischer DSGVO-Report (jährlich) als PDF

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
- [ ] Angebots-Gültigkeit (Ablaufdatum)
- [ ] Angebots-Versionen
- [ ] PDF-Vorschau vor dem Versand
- [ ] Skonto-Bedingungen auf Angeboten/Rechnungen (z.B. "2% bei Zahlung innerhalb 10 Tagen")

### 6.2.2 Rechnungswesen
- [x] Mahnwesen (Vorschlagssystem) — `src/business/dunning.php` + `dunning.twig`
- [x] Mahnstufen-Eskalation (Erinnerung → 1./2./3. Mahnung) — `DunningService`
- [x] Stornorechnung/Gutschrift — `DocumentLifecycleService` (credit_note, cancellation)
- [x] Zahlungsbedingungen in DB — `instances_paymentTermDays`, `clients_paymentTermDays`
- [ ] Teilrechnungen / Abschlagsrechnungen
- [ ] SEPA-Lastschrift-Mandatsverwaltung
- [ ] Automatischer Rechnungsversand per E-Mail (Cronjob-basiert)
- [x] Wiederkehrende Projekte (Vorlage) — `src/services/RecurringProjectService.php`
- [ ] Mahngebühren automatisch berechnen und auf Mahnung ausweisen
- [ ] Mahnbriefe als PDF generieren und per E-Mail versenden
- [ ] Mahnsperre bei Teilzahlung (automatisch pausieren)
- [ ] Zahlungseingänge mit Bankdaten abgleichen (MT940/CAMT Import)
- [ ] Sammelrechnung (mehrere Projekte → eine Rechnung)
- [ ] Reverse-Charge-Verfahren für EU-Auslandsgeschäfte
- [ ] Teilzahlungs-Tracking auf Rechnungsebene
- [x] Cron-Automatisierung für wiederkehrende Projekte — `src/cron/recurring-projects.php`
- [ ] Wiederkehrende Projekte: Verfügbarkeits-Check vor Auto-Erstellung

### 6.2.3 Buchhaltungsanbindung
- [x] DATEV-Export — `src/services/DatevExportService.php` + `src/business/datev.php`
- [x] EÜR-Unterstützung — `src/business/euer.php` + `euer.twig`
- [ ] SKR03/SKR04 Kontenzuordnung
- [ ] BWA-Auswertung (Betriebswirtschaftliche Auswertung)
- [ ] Export für lexoffice, sevDesk
- [ ] Bankanbindung (FinTS/HBCI) für automatischen Zahlungsabgleich
- [ ] Umsatzsteuervoranmeldung (UStVA) vorbereiten (ELSTER-kompatibel)
- [ ] Kassenbuch (für Bareinnahmen/Barausgaben)

### 6.2.4 Kundenverwaltung erweitern
- [x] Kundennummern — `clients_customerNumber` in DB
- [x] USt-IdNr. des Kunden — `clients_vatId` in DB
- [x] Zahlungsbedingungen pro Kunde — `clients_paymentTermDays` in DB
- [x] Kundenspezifische Preise — `src/services/CustomerPricingService.php`
- [ ] Ansprechpartner (mehrere pro Kunde)
- [ ] Kundenkategorien / Tags
- [x] Kundenhistorie (alle Projekte, Angebote, Rechnungen) — `src/business/client-history.php` + `client-history.twig`
- [ ] Kommunikationsprotokoll
- [ ] Kreditlimit
- [ ] Kunden-Duplikate erkennen und zusammenführen (Merge)
- [ ] Kunden-Import aus CSV/Excel
- [ ] Verschiedene Lieferadressen pro Kunde
- [ ] USt-IdNr. Validierung über VIES (EU-Dienst)

---

## Phase 3: Professionalisierung

### Reporting/Auswertungen
- [x] Reports-Dashboard — `src/business/reports.php` + `reports.twig`
- [x] Gewinn-Dashboard (Projekt-Profit, Marge) — `src/business/profit.php` + `profit.twig`
- [x] Umsatzprognose/Forecast — `src/services/ProfitCalculationService.php`
- [x] Steuer-Export (CSV) — `src/services/SteuerExportService.php`
- [ ] Auslastungsberichte für Equipment (Auslastungsquote pro Asset-Typ)
- [ ] Export nach Excel/PDF für Reports
- [ ] Top-Kunden Ranking nach Umsatz
- [ ] Saisonalitäts-Analyse (welche Monate sind am stärksten)
- [ ] Equipment-ROI Berechnung (Anschaffung vs. Mieteinnahmen)
- [ ] Vergleichsreports (Monat-zu-Monat, Jahr-zu-Jahr)
- [ ] Dashboard-Widgets konfigurierbar machen (Drag & Drop Anordnung)

### Logistik
- [x] Packlisten-Generierung — `src/services/PackingListService.php`
- [x] Lieferschein-Service — `src/services/DeliveryNoteService.php` + `src/api/deliveryNote/generate.php`
- [x] Check-in/Check-out mit Zustandsprotokoll — `src/services/DamageReportService.php`
- [ ] Transportplanung (Fahrzeuge, Routen, Fahrer)
- [ ] Multi-Lager/Standortverwaltung
- [x] Lieferschein-Nummer über SequenceService — `DeliveryNoteService.php`: `SequenceService::next()` statt `rand()`
- [ ] Rückgabe-Erinnerungen automatisch versenden (1 Tag vorher)

### Code-Qualität
- [ ] Unit Tests (PHPUnit) für Services
- [ ] SQL-Injection Audit (LIKE-Suche in clients.php etc.)
- [ ] Code-Refactoring (Service-Layer konsequent nutzen)
- [ ] API-Eingabevalidierung: intval/filter_input in allen Endpoints (besonders Partner-APIs)
- [ ] Error-Handling: Einheitliche try/catch-Blöcke in allen API-Endpunkten
- [ ] PHP-CS-Fixer oder PHP_CodeSniffer für einheitlichen Code-Stil
- [ ] Composer autoloading für Service-Klassen (statt manuelles require)
- [ ] PHPStan / Psalm Static Analysis (Level 5+)

---

## Sicherheit & Robustheit

### KRITISCH - Sofort beheben!
- [x] **CORS Wildcard entfernen** — `src/api/apiHead.php`: Jetzt dynamische Origin-Prüfung via `CORS_ALLOWED_ORIGIN` env var
- [x] **CSRF-Token-Schutz** — `CsrfService.php` + Integration in `apiHeadSecure.php` + Meta-Tag + ajaxcall Header
- [x] **Partner-Code sicher** — `PartnerService.php`: `bin2hex(random_bytes(4))` + Kollisionsprüfung
- [x] **DSGVO IP-Logging gefixt** — `DsgvoService.php`: `getClientIp()` mit Cloudflare/Proxy-Support

### API-Sicherheit
- [x] Rate-Limiting für Login und API-Endpunkte — `RateLimitService.php` + DB-Migration + Login-Integration
- [ ] Input-Sanitization: htmlspecialchars/strip_tags für alle User-Inputs
- [ ] Content-Security-Policy (CSP) Header setzen
- [ ] X-Frame-Options Header (Clickjacking-Schutz)
- [ ] Prepared Statements in allen rawQuery()-Aufrufen prüfen
- [ ] API-Antworten: keine internen Fehler-Details an Client leaken
- [x] **Partner-API: Input-Validierung** — `accept.php`, `invite.php`, `equipment.php`, `request.php`: filter_var, preg_match, json_last_error
- [x] **JSON-Decoding sicher** — `partner/request.php`: `json_last_error()` Prüfung hinzugefügt
- [x] **Datumsformate validiert** — `partner/equipment.php`, `partner/request.php`: YYYY-MM-DD Regex + strtotime-Prüfung
- [ ] **Equipment-IDs nicht geprüft** — `PartnerService.php:246`: `assetTypes_id` wird nicht validiert ob es zur Partner-Instance gehört
- [x] **Rate-Limiting für Partner-Code-Generierung** — `partner/generateCode.php`: Max 5 pro Stunde
- [x] **Account-Enumeration gefixt** — `partner/invite.php`: Einheitliche Fehlermeldung

### Session & Auth
- [x] Session-Cookie: HttpOnly + Secure + SameSite=Strict — `head.php`: session_set_cookie_params mit Array-Syntax
- [x] Session-Regeneration nach Login — `login.php`: `session_regenerate_id(true)` nach Erfolg
- [ ] Passwort-Policy erzwingen (Mindestlänge, Komplexität)
- [x] Account-Lockout nach 15 Fehlversuchen (30 Min) — `login.php`: Temporäre Sperre + Meldung
- [ ] Zwei-Faktor-Authentifizierung (2FA/TOTP)
- [ ] Login-Protokoll (IP, Zeitpunkt, Erfolg/Fehler)

### Datenbank-Sicherheit
- [x] Foreign Keys für alle neuen Tabellen — `20260304110000_add_foreign_keys.php` (16 Tabellen)
- [ ] Verschlüsselung sensibler Daten at-rest (IBAN, Steuernummer)
- [ ] Automatische Datenbank-Backups (mysqldump Cronjob)
- [ ] DB-Benutzer mit minimalen Rechten (kein DROP/ALTER in Produktion)
- [ ] **Inkonsistente SQL-Sanitization** — `search/quick.php` nutzt `$DBLIB->escape()`, aber `groups/search.php` nutzt `sanitizeStringMYSQL()` → vereinheitlichen

### Datei-Sicherheit
- [ ] Upload-Validierung: Dateityp, Dateigröße, MIME-Type prüfen
- [ ] Uploaded Files außerhalb des Webroot speichern
- [ ] Virus-Scan für hochgeladene Dateien (ClamAV)

### DSGVO-Sicherheit
- [ ] **Datenexport filtert interne Felder nicht** — `DsgvoService.php:72-80`: Export enthält System-IDs und interne Flags → nur personenbezogene Daten exportieren
- [ ] Verschlüsselter Datenexport (ZIP mit Passwort) für E-Mail-Versand
- [ ] Automatische Löschung temporärer Export-Dateien

---

## Zusätzliche Features (User-Wünsche)

### Dashboard & Navigation
- [x] Enhanced Dashboard mit Tagesübersicht — `src/dashboard_enhanced.twig`
- [x] Dashboard-API (Projekte heute, überfällige Rückgaben, offene Posten) — `src/api/dashboard/overview.php`
- [x] Globale Live-Schnellsuche — `src/api/search/quick.php` + Template-Integration
- [x] Verfügbarkeitskalender — `src/business/availability.php` + `availability.twig`
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
- [ ] QR-Code auf Equipment-Label mit Link zur Asset-Seite
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

### Mobile & UX
- [ ] Progressive Web App (PWA) für mobile Nutzung
- [ ] Barcode-Scanner über Handy-Kamera (ohne extra App)
- [ ] Offline-Modus für Inventur (Sync bei Internetverbindung)
- [ ] Dark Mode (DB-Feld existiert bereits)
- [ ] Touch-optimierte UI für Tablets (Lager-Nutzung)
- [ ] Schnellerfassung: Projekt anlegen in unter 30 Sekunden

### Integrationen
- [ ] Google Calendar / Outlook Sync (bidirektional, nicht nur ICS-Export)
- [ ] Stripe/PayPal Zahlungslinks auf Rechnungen optional
- [ ] Überweisungs QR Code mit allen wichtigen Daten auf der Rechnung 
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
| Phase 1 - KUR              | 5/7       | 2     | 71%         |
| Phase 1 - GoBD             | 8/11      | 3     | 73%         |
| Phase 1 - ZUGFeRD          | 1/5       | 4     | 20%         |
| Phase 1 - DSGVO            | 6/10      | 4     | 60%         |
| Phase 1 - DB & Lokalisierung | 10/10   | 0     | 100%        |
| Phase 2 - Angebotswesen    | 4/8       | 4     | 50%         |
| Phase 2 - Rechnungswesen   | 6/15      | 9     | 40%         |
| Phase 2 - Buchhaltung      | 2/8       | 6     | 25%         |
| Phase 2 - Kunden           | 5/13      | 8     | 38%         |
| Phase 3 - Reporting        | 4/11      | 7     | 36%         |
| Phase 3 - Logistik         | 4/8       | 4     | 50%         |
| Phase 3 - Code-Qualität    | 0/8       | 8     | 0%          |
| Sicherheit - KRITISCH      | 4/4       | 0     | 100%        |
| Sicherheit - API           | 7/12      | 5     | 58%         |
| Sicherheit - Session/Auth  | 3/6       | 3     | 50%         |
| Sicherheit - Datenbank     | 1/5       | 4     | 20%         |
| Sicherheit - Dateien       | 0/3       | 3     | 0%          |
| Sicherheit - DSGVO         | 0/3       | 3     | 0%          |
| Extra - Dashboard/Nav      | 4/8       | 4     | 50%         |
| Extra - Projekte           | 2/8       | 6     | 25%         |
| Extra - Multi-Business     | 5/9       | 4     | 56%         |
| Extra - Equipment          | 6/13      | 7     | 46%         |
| Extra - Finanzen           | 3/7       | 4     | 43%         |
| Extra - Kommunikation      | 0/7       | 7     | 0%          |
| Extra - RFID               | 1/5       | 4     | 20%         |
| Extra - Mobile/UX          | 0/6       | 6     | 0%          |
| Extra - Integrationen      | 0/6       | 6     | 0%          |
| Extra - Dokumentation      | 3/7       | 4     | 43%         |
| Extra - DevOps             | 0/8       | 8     | 0%          |
| **GESAMT**                  | **95/236**| **141**| **40%**    |

---

## Priorisierte Empfehlung (nächste Schritte)

### SOFORT - Sicherheitslücken schließen (VOR Produktivbetrieb!)
1. **CORS Wildcard entfernen** (`apiHead.php`: `Access-Control-Allow-Origin: *`)
2. **CSRF-Token-Schutz** implementieren (kein einziger Endpoint hat Tokens!)
3. **Partner-Code sicher machen** (`md5(time())` → `random_bytes()`)
4. **Session-Cookie absichern** (HttpOnly + Secure + SameSite)
5. **Input-Validierung in Partner-APIs** (aktuell 0 Validierung!)
6. **IP-Logging in DSGVO-Service fixen** (Proxy-Header beachten)

### Sofort umsetzen (Blocker für legalen Betrieb)
7. KUR-Toggle in Business-Settings-UI
8. Leistungszeitraum auf Rechnungen (GoBD-Pflicht!)
9. Unveränderbarkeit der Rechnung nach Erstellung (GoBD-Pflicht!)
10. Rate-Limiting für Login (Brute-Force-Schutz)

### Bald umsetzen (Komfort + Compliance)
11. Automatischer Rechnungsversand per E-Mail
12. Mahnbriefe als PDF generieren + versenden
13. Lieferschein-Nummer über SequenceService (statt `rand()`)
14. Kundenhistorie-Übersichtsseite
15. Foreign Keys für neue Tabellen (Datenintegrität)
16. Angebots-Vorlagen mit Textbausteinen
17. Cron-Job für wiederkehrende Projekte

### Mittelfristig (Skalierung + Professionalisierung)
18. ZUGFeRD/XRechnung implementieren (`horstoeko/zugferd` installieren)
19. Teilrechnungen / Abschlagsrechnungen
20. Unit Tests für kritische Services (PHPUnit)
21. Kunden-Portal (Self-Service)
22. Equipment-Auslastungsberichte
23. Docker-Compose Produktions-Setup mit SSL
24. 2-Faktor-Authentifizierung (TOTP)

---

*Zuletzt aktualisiert: 04.03.2026*
