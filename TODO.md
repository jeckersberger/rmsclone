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
- [ ] KUR-Einstellung in Business-Settings-UI (Toggle)

### 6.1.2 GoBD-Konforme Rechnungen
- [x] Steuernummer/USt-IdNr. in DB — `instances_taxNumber`, `instances_vatId`
- [x] Fortlaufende Rechnungsnummer — `src/services/SequenceService.php`
- [x] Nummernkreise (RE-2026-0001, AN-2026-0001, LS-2026-0001) — `SequenceService`
- [x] Steuersatz und Steuerbetrag getrennt — `DocumentRenderer.php`
- [x] Netto-/MwSt-/Bruttobetrag getrennt — `DocumentRenderer.php`
- [x] KUR-Hinweis auf Steuerbefreiung — `DocumentRenderer.php`
- [ ] Leistungszeitraum/Lieferdatum auf Rechnung
- [ ] Aufbewahrungspflicht (10 Jahre) - automatische Archivierung
- [ ] Unveränderbarkeit der Rechnung nach Erstellung

### 6.1.3 XRechnung / ZUGFeRD
- [x] DB-Tabellen für ZUGFeRD-Struktur — `db/migrations/20260228130000_phase3_zugferd_reports_logistics.php`
- [ ] horstoeko/zugferd Composer-Package installieren
- [ ] ZUGFeRD PDF/A-3 + XML Generierung
- [ ] XRechnung Unterstützung

### 6.1.4 DSGVO (Datenschutz)
- [x] Löschkonzept für Kundendaten — `src/services/DsgvoService.php` (Art. 17, Aufbewahrungsfristen § 147 AO)
- [x] Datenexport-Funktion (Art. 20 DSGVO - Datenportabilität) — `src/api/dsgvo/export.php` + `DsgvoService.php`
- [ ] Cookie-Consent-Verwaltung
- [x] DSGVO-konforme Dokumentation des Audit-Logs — `dsgvo_log` Tabelle + `src/api/dsgvo/log.php`
- [x] DSGVO-Verwaltungsoberfläche — `src/business/dsgvo.php` + `dsgvo.twig`
- [x] Aufbewahrungsfristen-Prüfung — `src/api/dsgvo/retentionCheck.php`
- [x] Löschvorschläge (Clients > 10 Jahre inaktiv) — `src/api/dsgvo/deletionSuggestions.php`

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
- [ ] Angebots-Vorlagen mit Textbausteinen
- [ ] Angebots-Gültigkeit (Ablaufdatum)
- [ ] Angebots-Versionen

### 6.2.2 Rechnungswesen
- [x] Mahnwesen (Vorschlagssystem) — `src/business/dunning.php` + `dunning.twig`
- [x] Mahnstufen-Eskalation (Erinnerung → 1./2./3. Mahnung) — `DunningService`
- [x] Stornorechnung/Gutschrift — `DocumentLifecycleService` (credit_note, cancellation)
- [x] Zahlungsbedingungen in DB — `instances_paymentTermDays`, `clients_paymentTermDays`
- [ ] Teilrechnungen / Abschlagsrechnungen
- [ ] SEPA-Lastschrift-Mandatsverwaltung
- [ ] Automatischer Rechnungsversand per E-Mail
- [x] Wiederkehrende Projekte (Vorlage) — `src/services/RecurringProjectService.php`

### 6.2.3 Buchhaltungsanbindung
- [x] DATEV-Export — `src/services/DatevExportService.php` + `src/business/datev.php`
- [x] EÜR-Unterstützung — `src/business/euer.php` + `euer.twig`
- [ ] SKR03/SKR04 Kontenzuordnung
- [ ] BWA-Auswertung (Betriebswirtschaftliche Auswertung)
- [ ] Export für lexoffice, sevDesk
- [ ] Bankanbindung (FinTS/HBCI) für automatischen Zahlungsabgleich

### 6.2.4 Kundenverwaltung erweitern
- [x] Kundennummern — `clients_customerNumber` in DB
- [x] USt-IdNr. des Kunden — `clients_vatId` in DB
- [x] Zahlungsbedingungen pro Kunde — `clients_paymentTermDays` in DB
- [x] Kundenspezifische Preise — `src/services/CustomerPricingService.php`
- [ ] Ansprechpartner (mehrere pro Kunde)
- [ ] Kundenkategorien / Tags
- [ ] Kundenhistorie (alle Projekte, Angebote, Rechnungen) - Übersichtsseite
- [ ] Kommunikationsprotokoll
- [ ] Kreditlimit

---

## Phase 3: Professionalisierung

### Reporting/Auswertungen
- [x] Reports-Dashboard — `src/business/reports.php` + `reports.twig`
- [x] Gewinn-Dashboard (Projekt-Profit, Marge) — `src/business/profit.php` + `profit.twig`
- [x] Umsatzprognose/Forecast — `src/services/ProfitCalculationService.php`
- [x] Steuer-Export (CSV) — `src/services/SteuerExportService.php`
- [ ] Auslastungsberichte für Equipment
- [ ] Export nach Excel/PDF für Reports

### Logistik
- [x] Packlisten-Generierung — `src/services/PackingListService.php`
- [x] Lieferschein-Service — `src/services/DeliveryNoteService.php` + `src/api/deliveryNote/generate.php`
- [x] Check-in/Check-out mit Zustandsprotokoll — `src/services/DamageReportService.php`
- [ ] Transportplanung
- [ ] Multi-Lager/Standortverwaltung

### Code-Qualität
- [ ] Unit Tests
- [ ] SQL-Injection Audit (LIKE-Suche in clients.php etc.)
- [ ] Code-Refactoring (Service-Layer konsequent nutzen)

---

## Zusätzliche Features (User-Wünsche)

### Dashboard & Navigation
- [x] Enhanced Dashboard mit Tagesübersicht — `src/dashboard_enhanced.twig`
- [x] Dashboard-API (Projekte heute, überfällige Rückgaben, offene Posten) — `src/api/dashboard/overview.php`
- [x] Globale Live-Schnellsuche — `src/api/search/quick.php` + Template-Integration
- [x] Verfügbarkeitskalender — `src/business/availability.php` + `availability.twig`

### Projekt-Features
- [x] Projekt-Klonen — `src/api/projects/clone.php` + UI in `project_index.twig`
- [x] Wiederkehrende Projekte — `src/business/recurring.php` + `recurring.twig`
- [ ] Drag & Drop Kalender-Ansicht für Projekte

### Multi-Business Kooperation
- [x] Partner-Code-System — `src/services/PartnerService.php`
- [x] Partner einladen/annehmen — `src/api/partner/invite.php`, `accept.php`
- [x] Partner-Equipment durchsuchen — `src/api/partner/equipment.php`
- [x] Equipment-Anfragen an Partner — `src/api/partner/request.php`
- [x] Partner-Management UI — `src/business/partners.php` + `partners.twig`
- [ ] Partner-Preisabstimmung automatisch synchronisieren
- [ ] Partner-Auftragsverwaltung (cross-business Projekte)

### Equipment-Verwaltung
- [x] Inventur/Barcode-Scanner — `src/business/inventory.php` + `inventory.twig`
- [x] Wartungsintervall-Tracking — `src/services/MaintenanceScheduleService.php`
- [x] Wartungsplan-UI — `src/business/maintenance-schedule.php` + `maintenance-schedule.twig`
- [x] Schadensmeldungen — `src/business/damage-reports.php` + `damage-reports.twig`
- [x] Kautionsverwaltung — `src/services/DepositService.php` + API
- [x] Versicherungsnachweis-Verwaltung — `src/services/InsuranceService.php`

### Finanzen & Kunden
- [x] Kundenspezifische Preislisten — `src/services/CustomerPricingService.php` + API
- [x] Projekt-Gewinnberechnung — `src/services/ProfitCalculationService.php` + UI
- [x] Umsatzprognose — `ProfitCalculationService.php` Forecast

### Kommunikation
- [ ] WhatsApp/SMS Benachrichtigungen
- [ ] Digitale Unterschrift
- [ ] Kunden-Portal (Self-Service)

### RFID-Integration (Konzept fertig)
- [x] RFID-Konzept dokumentiert — `docs/RFID_KONZEPT.md`
- [ ] RfidService.php implementieren
- [ ] RFID Gateway-Anbindung
- [ ] RFID Label-Druck

### Dokumentation
- [x] System-Visualisierungen (ASCII) — `docs/VISUALISIERUNGEN.md`
- [x] RFID-Konzept — `docs/RFID_KONZEPT.md`
- [x] Analyse & Empfehlungen — `ANALYSE_UND_EMPFEHLUNGEN.md`

---

## Zusammenfassung

| Bereich                    | Umgesetzt | Offen | Fortschritt |
|----------------------------|-----------|-------|-------------|
| Phase 1 - KUR              | 4/5       | 1     | 80%         |
| Phase 1 - GoBD             | 6/9       | 3     | 67%         |
| Phase 1 - ZUGFeRD          | 1/4       | 3     | 25%         |
| Phase 1 - DSGVO            | 6/7       | 1     | 86%         |
| Phase 1 - DB & Lokalisierung | 10/10   | 0     | 100%        |
| Phase 2 - Angebotswesen    | 3/6       | 3     | 50%         |
| Phase 2 - Rechnungswesen   | 5/8       | 3     | 63%         |
| Phase 2 - Buchhaltung      | 2/6       | 4     | 33%         |
| Phase 2 - Kunden           | 4/9       | 5     | 44%         |
| Phase 3 - Reporting        | 4/6       | 2     | 67%         |
| Phase 3 - Logistik         | 3/5       | 2     | 60%         |
| Phase 3 - Code-Qualität    | 0/3       | 3     | 0%          |
| Extra - Dashboard/Nav      | 4/4       | 0     | 100%        |
| Extra - Projekte           | 2/3       | 1     | 67%         |
| Extra - Multi-Business     | 5/7       | 2     | 71%         |
| Extra - Equipment          | 6/6       | 0     | 100%        |
| Extra - Finanzen           | 3/3       | 0     | 100%        |
| Extra - Kommunikation      | 0/3       | 3     | 0%          |
| Extra - RFID               | 1/4       | 3     | 25%         |
| **GESAMT**                  | **72/108**| **36**| **67%**     |

---

*Zuletzt aktualisiert: 01.03.2026*
