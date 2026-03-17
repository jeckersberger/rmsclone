# MyRMS — Projektdokumentation

Konsolidierte Projektdokumentation. Stand: 18. März 2026
Branch: `feature/rfid-cases-invoices-android`

> **Hinweis:** Die UI/UX-Roadmap und Erweiterungspläne befinden sich in [ROADMAP.md](ROADMAP.md).

---

# Inhaltsverzeichnis

1. [Analyse & Empfehlungen (AdamRMS als EasyJob-Alternative)](#1-analyse--empfehlungen)
2. [Release 1.1 — Implementierungsplan](#2-release-11--implementierungsplan)
3. [Feature-Übersicht (Release 1.1)](#3-feature-übersicht)
4. [TODO-Tracker](#4-todo-tracker)
5. [Security Audit](#5-security-audit)
6. [Incoming Invoices Setup](#6-incoming-invoices-setup)
7. [Refactoring: AiAssetLookupService](#7-refactoring-aiassetlookupservice)
8. [Entwicklungsumgebung (Development)](#8-entwicklungsumgebung)
9. [Synology-Installation](#9-synology-installation)

---

# 1. Analyse & Empfehlungen

*Ursprüngliche Datei: ANALYSE_UND_EMPFEHLUNGEN.md — Erstellt: 28.02.2026*

## 1.1 Was ist AdamRMS?

**AdamRMS** ist ein Open-Source **Rental Management System** (Verleih-Management-System),
ursprünglich entwickelt für Theater-, AV- und Broadcast-Unternehmen. Es verwaltet Equipment
(Assets), Projekte (Aufträge/Jobs), Kunden, Crew-Planung und Finanzen.

**Lizenz:** AGPL-3.0 - Änderungen am Quellcode müssen Open Source bleiben!

## 1.2 Tech-Stack

| Komponente       | Technologie                                              |
|------------------|----------------------------------------------------------|
| Backend          | PHP 8.0+ (prozedural, kein Framework)                    |
| Templating       | Twig 3.7                                                 |
| Datenbank        | MySQL/MariaDB (mysqli)                                   |
| Frontend         | AdminLTE (Bootstrap 4), jQuery                           |
| PDF-Erzeugung    | pdfmake (Client-seitig, JS-basiert) + Dompdf (serverseitig) |
| Dateispeicher    | AWS S3                                                   |
| E-Mail           | SendGrid, Mailgun, Postmark, PHPMailer                   |
| Auth             | JWT-basiert, HybridAuth (Social Login)                   |
| Billing          | Stripe                                                   |
| Barcode          | picqer/php-barcode-generator + bacon/bacon-qr-code       |
| Kalender         | eluceo/ical (ICS-Export)                                  |
| Suche            | Fuse.js (fuzzy search)                                   |
| Migrations       | Phinx                                                    |
| Deployment       | Docker                                                   |
| Monitoring       | Sentry                                                   |

## 1.3 Vorhandene Features (IST-Zustand)

### Asset-/Equipment-Verwaltung
- Asset-Typen mit Kategorien und Gruppen
- Individuelle Assets mit Tags, Barcodes, QR-Codes
- Tages- und Wochenpreise pro Asset-Typ oder individuell
- Gewichts-Tracking, Wertermittlung
- Definierbare Felder pro Asset-Typ
- Barcode-Scanning, Asset-Import/Export

### Projektverwaltung (= Aufträge/Jobs)
- Projekte mit Start-/End-/Liefer-/Abhol-Daten
- Projekttypen, Projektstatus (konfigurierbar mit Farben/Icons)
- Unterprojekte, Projektmanager-Zuweisung
- Asset-Zuweisungen mit Status-Board (Kanban-artig für Dispatch)
- Rabatte, benutzerdefinierte Preise, Projektnotizen, Datei-Anhänge

### Finanzwesen
- Equipment-Kalkulation (SubTotal, Rabatte, Total)
- Zahlungskategorien, Zahlungseingang-Tracking, Finanz-Cache
- Rechnungs-/Angebots-/Lieferschein-PDF-Generierung
- Währung pro Instance konfigurierbar, moneyphp Library

### Dokumenten-System (DocumentRenderer)
- Serverseitige PDF-Erzeugung via Dompdf
- Nummernkreise mit automatischem Jahres-Reset
- KUR-Implementierung, MwSt-Berechnung, Rabatt-Berechnung
- Export-Protokollierung

### Weitere Features
- Kundenverwaltung, Standorte/Venues, Crew-Planung
- Wartung, Training/Module, CMS, Kalender
- Hersteller, Benutzer, Multi-Instance, Signup Codes
- Such-Funktion, Audit Log, Stripe Billing

## 1.4 Datenbank-Struktur (wichtigste Tabellen)

| Tabelle                    | Zweck                                        |
|----------------------------|----------------------------------------------|
| `instances`                | Firmen/Mandanten                             |
| `users` / `userInstances`  | Benutzer und Zuordnung zu Firmen             |
| `projects`                 | Projekte/Aufträge                            |
| `clients`                  | Kunden                                       |
| `assets` / `assetTypes`    | Equipment-Gegenstände / Typen                |
| `assetsAssignments`        | Zuweisungen Asset → Projekt                  |
| `payments`                 | Zahlungen/Posten                             |
| `locations`                | Standorte/Venues                             |
| `crewAssignments`          | Crew-Zuweisungen                             |
| `manufacturers`            | Hersteller                                   |
| `auditLog`                 | Änderungsprotokoll                           |

## 1.5 Stärken

1. Solide Grundstruktur für Verleih-Management
2. Multi-Mandanten-fähig (Instances)
3. Feingranulares Berechtigungssystem
4. Equipment-Verwaltung ausgereift
5. Finanzmodul mit korrekter Geld-Arithmetik (moneyphp)
6. Docker-Deployment
7. Ansätze für deutsche Anpassung (DocumentRenderer, SequenceService)
8. PDF-Generierung funktioniert
9. API vorhanden

## 1.6 Schwächen und Verbesserungsbedarf

### KRITISCH — Fehlende deutsche rechtliche Anforderungen (bei Analyse-Erstellung)
- KUR teilweise vorhanden, aber UI/Pflichthinweis/Umsatzgrenze fehlten
- GoBD-Pflichtangaben fehlten (Steuernummer, Leistungszeitraum, etc.)
- XRechnung/ZUGFeRD fehlten
- DSGVO-Funktionen fehlten

### WICHTIG — Fehlende Business-Features (bei Analyse-Erstellung)
- Angebotswesen, erweitertes Rechnungswesen, Buchhaltungsanbindung
- Erweiterte Kundenverwaltung

### Architektur-Empfehlungen
- **Kurzfristig:** i18n-Layer, Settings erweitern, DocumentRenderer erweitern
- **Mittelfristig:** ZUGFeRD-Package, Service-Layer, REST-API
- **Langfristig:** Ggf. Neuaufbau auf Laravel/Symfony + Vue/React

## 1.7 Fazit

AdamRMS ist eine solide Grundlage für die Equipment-Verwaltung. Für den Einsatz in einem deutschen Kleinunternehmen fehlten deutsche Lokalisierung, GoBD, KUR, DSGVO und Buchhaltungsanbindung — all das wurde inzwischen implementiert (siehe TODO-Tracker).

---

# 2. Release 1.1 — Implementierungsplan

*Ursprüngliche Datei: RELEASE_1.1_PLAN.md — Stand: 17.03.2026*

## 2.1 Status-Übersicht

### Migrations-Analyse
- **127 Migrations** total, alle PHP-Syntax korrekt
- **0 Tabellenkonflikte**, alle 167 Tabellen einzigartig
- **147 Foreign Keys** — alle valide

### Was fertig ist
- ✅ RFID/Scanner System (TID-Pairing, Universal Scan, Tag Format Service)
- ✅ Federation (Handshake, Tag Lookup, Cross-Instance, Foreign Loans)
- ✅ Stock Items (Two-Tier Model, Assignments, Warnings)
- ✅ External Items (Fremdmaterial-Verwaltung)
- ✅ Case/Box Management (Content Definition, Verification)
- ✅ Packlisten (Generierung, Scan-Abhaken, Multi-Entity)
- ✅ Android App Struktur (12 Screens, API Layer, Partner-Anzeige)
- ✅ Label-Druck System (Templates, ZPL)
- ✅ Company Code System (MD5, Collision Detection)

## 2.2 Phasen

| Phase | Inhalt | Aufwand | Priorität |
|-------|--------|---------|-----------|
| Phase 1 | Kritische Fixes (QR+RFID Dual-Scanning) | 1 Session | KRITISCH |
| Phase 2 | Finance & Banking (11 Migrations, 18 Services) | 2-3 Sessions | HOCH |
| Phase 3 | Dokumente & Workflow (8 Migrations, 8 Services) | 2 Sessions | HOCH |
| Phase 4 | Compliance & Sicherheit (6 Migrations, 12 Services) | 1-2 Sessions | HOCH |
| Phase 5 | Kommunikation & AI (6 Migrations, 5 Services) | 1-2 Sessions | MITTEL |
| Phase 6 | Client & Projekt-Management (15 Services) | 1-2 Sessions | MITTEL |
| Phase 7 | Asset-Erweiterungen & Reporting | 1 Session | MITTEL |
| Phase 8 | Integrations & Sonstiges | 1 Session | NIEDRIG |
| Phase 9 | Finaler Test & Release | 2-3 Sessions | KRITISCH |
| **Gesamt** | | **~12-18 Sessions** | |

## 2.3 Offene Fragen an den Auftraggeber

1. **FinTS/HBCI:** Welche Bank(en)? Echte Testbank oder Simulation?
2. **DATEV:** Steuerberater mit DATEV? SKR03/SKR04?
3. **ZUGFeRD/XRechnung:** Rechnungen an Behörden?
4. **Stripe:** Online-Zahlung direkt über die Software?
5. **AI-Features (Claude API):** KI-gestützte Funktionen? Anthropic API-Key?
6. **IMAP Inbox:** E-Mails direkt empfangen und verarbeiten?
7. **SMS-Benachrichtigungen:** SMS-Versand nötig?
8. **Kunden-Portal:** Kunden Self-Service?
9. **KUR:** Kleinunternehmer nach §19 UStG oder regelbesteuert?
10. **Bewirtungsbelege:** Erfassung für die Steuer?

---

# 3. Feature-Übersicht

*Ursprüngliche Datei: FEATURES.md — Stand: Release 1.1*

## 3.1 RFID/Scanner System

### TID-basiertes RFID-Pairing
System liest die fabrikseitig eingebrannte TID (Tag Identifier, Bank 02) statt der überschreibbaren EPC-Bank. TID ist einmalig pro Chip und nicht überschreibbar.

- DB-Spalten: `assets.assets_rfidTid`, `stock_instances.rfid_tid`, `external_items.rfid_tid`
- Service: `src/services/TagFormatService.php`

### Tag-Formate
- Neues Format: `RMS-a3f7b2c1-A-000042`
- QR-Format: `RMS://a3f7b2c1/A/000042`
- Legacy-Formate, Binary EPC, Raw RFID

### Universal Scan Handler
Zentraler Scan-Endpunkt (`/api/rfid/scan.php`) mit 20+ Actions: scan, bulk_scan, universal_scan, box_scan, pair_tid, inventory_scan, etc.

Service: `src/services/RfidService.php` (927 Zeilen)

### Weitere RFID-Features
- Label-Druck System (JSON-Templates, ZPL/Zebra)
- Inventur-Sessions
- Scan-Historie

## 3.2 Federation (Partner-Netzwerk)

- Server-zu-Server HTTPS REST API mit bidirektionalem Handshake
- Tag Lookup über Partner-Netzwerk
- Cross-Instance Lookup Service (Lokal → Partner-Links → Federated Remote)
- Equipment-Katalog-Austausch und Anfragen
- Foreign Loans Tracking
- Federation Logging (Audit Trail)

## 3.3 Stock Items (Artikel-Verwaltung)

Zwei-Schicht-Modell: `stock_items` (Artikeltypen) + `stock_instances` (physische Exemplare mit RFID). 18+ Actions über `/api/stock/items.php`.

## 3.4 External Items (Fremdmaterial)

Geliehenes/gemietetes Equipment von anderen Firmen. Owner-Tracking, Return Date, Status, Barcode/RFID Support. 9 API-Actions.

## 3.5 Case/Box Management

- Case Content Definition (Soll-Inhalt, Typ-basiertes Matching)
- Case Content Verification (Soll/Ist bei Checkout/Checkin)
- Acknowledgement-Workflow für Abweichungen

## 3.6 Packlisten

- Automatische Generierung aus Projekt-Zuweisungen
- Multi-Entity: Assets + Stock Instances + External Items
- Scan-basiertes Abhaken
- Gewicht-Tracking, PDF-Export

## 3.7 Android App (Chafon CF-H906)

Kotlin, Jetpack Compose, 12 UI-Screens:
LoginScreen, MainMenuScreen, CheckoutScreen, CheckinScreen, BoxScanScreen, InventoryScreen, PackingListScreen, LocationScreen, ExternalItemScreen, CaseVerifyScreen, TagPairScreen, SettingsScreen.

RFID Manager für Chafon CF-H906 UHF SDK, Hardware-Trigger, 2D Barcode Receiver, Auto-Update via GitHub Releases.

## 3.8 Web-Interface Erweiterungen

RFID Scanner UI, Stock Management, Case Management, Packing List UI, AdminLTE Dark-Pink Design.

## 3.9 Datenbank-Erweiterungen

18+ neue Tabellen (stock_items, stock_instances, external_items, partner_servers, foreign_loans, case_contents, packing_lists, etc.) + neue Spalten und Indizes auf bestehenden Tabellen.

## 3.10 Infrastruktur

- Docker: PHP 8.3 + MySQL 8.0 + Nginx
- Hardware: Chafon CF-H906 UHF RFID PDA (Android 9.0, UHF 865-868MHz, 2D Barcode, IP65)
- MeekroDB: `getOne('table', null, ['columns'])`, keine OR-Placeholder, kein standalone groupBy()

## 3.11 Offene Features / TODOs

1. Chafon SDK Integration (Stubs mit TODOs vorhanden)
2. QR+RFID Dual-Scanning (BroadcastReceiver → Scan-Pipeline)
3. Verbrauchsgegenstände (Consumables) — noch nicht implementiert
4. Standort-Tracking (Location Assignment)
5. Federation Equipment Auto-Sharing
6. Offline Mode (Android) — teilweise implementiert
7. Federation Freundescode Pairing Flow
8. KI-unterstützung bei Asset-Anlage

---

# 4. TODO-Tracker

*Ursprüngliche Datei: TODO.md — Stand: 08.03.2026*

## 4.1 Fortschritt

| Bereich | Umgesetzt | Offen | Fortschritt |
|---------|-----------|-------|-------------|
| Phase 1 - KUR | 7/7 | 0 | **100%** ✅ |
| Phase 1 - GoBD | 11/11 | 0 | **100%** ✅ |
| Phase 1 - ZUGFeRD | 5/5 | 0 | **100%** ✅ |
| Phase 1 - DSGVO | 10/10 | 0 | **100%** ✅ |
| Phase 1 - DB & Lokalisierung | 10/10 | 0 | **100%** ✅ |
| Phase 2 - Angebotswesen | 8/8 | 0 | **100%** ✅ |
| Phase 2 - Rechnungswesen | 15/15 | 0 | **100%** ✅ |
| Phase 2 - Buchhaltung | 9/9 | 0 | **100%** ✅ |
| Phase 2 - Kunden | 13/13 | 0 | **100%** ✅ |
| Phase 3 - Reporting | 11/11 | 0 | **100%** ✅ |
| Phase 3 - Logistik | 8/8 | 0 | **100%** ✅ |
| Phase 3 - Code-Qualität | 8/8 | 0 | **100%** ✅ |
| Sicherheit komplett | 33/33 | 0 | **100%** ✅ |
| Extra Features komplett | 108/109 | 1 | **99%** ✅ |
| **GESAMT** | **270/271** | **1** | **99.6%** |

## 4.2 Offene Verbesserungen

### KI-Features UI-Integration
- [ ] KI-Buttons in Projekt-Detailseite (Asset-Zuteilung, Crew-Optimierung, Dokument-Check)
- [ ] KI-Button in Kunden-Detailseite (Risikobewertung)
- [ ] KI-Reply-Button in E-Mail-Inbox
- [ ] KI-Prognose-Button in Finanz-Dashboard
- [ ] KI-Wartungsprognose in Maintenance-Seite
- [ ] KI-Duplikat-Check in Kunden- und Asset-Listen
- [ ] `setUserId()` in allen AI-Endpoints

### Technische Verbesserungen
- [ ] Rate Limiting für neue AI-Endpoints
- [ ] API-Test Button: Fehler-Feedback verbessern
- [ ] Cronjob `ai-background.php` um neue Features erweitern
- [ ] Einheitliche Berechtigungsprüfung für alle AI-Endpoints

### Geplante neue Features

**KI-Chat (Programmsteuerung per natürlicher Sprache):**
- [x] DB-Migration + Feature-Toggle + ChatService mit Tool-Use
- [ ] Chat API-Endpoint, Frontend-UI, weitere Tools, Export/Archiv

**Verleih-Workflow Verbesserungen:**
- [ ] Überfällige Rückgaben Dashboard + API-Endpoint
- [ ] Verfügbarkeits-Kalender erweitern (Resource-Timeline-View)
- [ ] Check-in/Check-out Verbesserungen (Foto-Upload, Schadensvergleich)
- [ ] Dashboard-Widget + E-Mail-Erinnerungen bei überfälligen Rückgaben

---

# 5. Security Audit

*Ursprüngliche Datei: SECURITY-AUDIT.md — Datum: 15.03.2026, Version 2.0*

**Scope:** Gesamte Codebase (690+ PHP-Dateien, 434 API-Endpoints)

## 5.1 Durchgeführte Sicherheits-Fixes

| # | Schwere | Problem | Fix |
|---|---------|---------|-----|
| 1 | KRITISCH | SQL Injection in assets/transfer.php | Parametrisierte Queries |
| 2 | KRITISCH | Schwaches Password Hashing (SHA2/SHA3) | Argon2ID + transparentes Upgrade |
| 3 | KRITISCH | Unvollständiger Logout | session_destroy() + Cookie-Invalidierung |
| 4 | KRITISCH | Session-Invalidierung bei Passwortwechsel | Alle anderen Auth-Tokens invalidieren |
| 5 | HOCH | SSRF in Federation/Webhook | UrlSecurityService (RFC 1918 Blocklist) |
| 6 | HOCH | CSV Injection in Asset-Export | sanitizeCsvCell() mit Apostroph-Prefix |
| 7 | HOCH | Path Traversal in uploadSuccess.php | basename() + Extension-Whitelist |

## 5.2 Weitere Fixes aus Session 1
- LIKE Wildcard Escaping, Rate Limiting, Login Logging
- Password Policy, TOTP 2FA, Session Regeneration, Account Lockout

## 5.3 OWASP Top 10 Abdeckung

| Standard | Status |
|----------|--------|
| A01 Broken Access Control | ⚠️ Teilweise (486 duplizierte Permission-Checks) |
| A02 Cryptographic Failures | ✅ Argon2ID + random_bytes |
| A03 Injection | ✅ SQL Injection gefixt |
| A04 Insecure Design | ⚠️ Teilweise (Score 6.2/10) |
| A05 Security Misconfiguration | ⚠️ CSP-Headers teilweise |
| A06 Vulnerable Components | ⚠️ composer audit empfohlen |
| A07 Auth Failures | ✅ Komplett abgedeckt |
| A08 Data Integrity | ⚠️ Kein SRI auf CDN-Assets |
| A09 Logging/Monitoring | ⚠️ Teilweise |
| A10 SSRF | ✅ UrlSecurityService |

## 5.4 Verbleibende Empfehlungen

**HOCH:** Zentrales Permission-Middleware, OAuth Session-Linking, Password-Reset-Codes, CSP-Headers
**MITTEL:** Dependency Audit in CI, globale Variablen → DI, Test Coverage erhöhen, die() → finish()
**NIEDRIG:** SRI für CDN-Assets, zentraler Router, N+1 Query Fixes

**Scores:** Architektur 6.2/10, Security 7.5/10 (von ~4/10), Tech-Debt ~37-51 Wochen

---

# 6. Incoming Invoices Setup

*Ursprüngliche Datei: INCOMING_INVOICES_SETUP.md — Datum: 16.03.2026*

GoBD-konformes Eingangsrechnungs- und Belegsystem.

## 6.1 Komponenten

| Komponente | Pfad |
|------------|------|
| Migration | `db/migrations/20260316180000_incoming_invoices.php` |
| Service | `src/services/IncomingInvoiceService.php` (23 public methods) |
| API | `src/api/incoming/invoices.php` (20 Actions) |
| UI | `src/incoming.twig` |

## 6.2 Tabellen

- `incoming_invoices` — Haupttabelle (36 Felder, 6 Status, 5 Dokumenttypen)
- `incoming_invoice_files` — Dokument-Anhänge mit SHA-256 Hashing
- `incoming_invoice_audit_log` — Immutable Audit Trail
- `expense_categories` — SKR03/SKR04 Kontenzuordnung (15 vordefinierte Kategorien)
- `gobd_retention_config` — Aufbewahrungsfristen (10 Jahre Rechnungen, 6 Jahre Lieferscheine)

## 6.3 GoBD-Compliance Features

- **Unveränderbarkeit:** Soft-delete, SHA-256, Field-level Change Tracking
- **Nachvollziehbarkeit:** Audit Trail (User, IP, Timestamp, Feld, Alt→Neu)
- **Ordnung:** SKR03/SKR04, Projekt-Zuordnung, Vendor-Indexierung
- **Vollständigkeit:** Pflichtfelder, Dokumenttyp-Klassifikation
- **Zeitgerechte Buchung:** gobd_recorded_at, 10-Tage-Frist-Warnung
- **Aufbewahrungsfristen:** Automatische Berechnung, Compliance-Warnungen

## 6.4 API-Endpunkte

```
GET  /api/incoming/invoices.php?action=list&status=draft
GET  /api/incoming/invoices.php?action=get&id=123
POST /api/incoming/invoices.php?action=create
POST /api/incoming/invoices.php?action=verify&id=123
POST /api/incoming/invoices.php?action=mark_paid&id=123
GET  /api/incoming/invoices.php?action=compliance_check
GET  /api/incoming/invoices.php?action=audit_log&id=123
GET  /api/incoming/invoices.php?action=search_vendor&q=Supplier
GET  /api/incoming/invoices.php?action=duplicate_check&vendor=...&amount=...
```

---

# 7. Refactoring: AiAssetLookupService

*Ursprüngliche Datei: REFACTOR_SUMMARY.md*

## Breaking Change

AiAssetLookupService nutzt jetzt den zentralen ClaudeService statt eigenständiger cURL-Aufrufe.

**Alt:** `$service = new AiAssetLookupService($db);`
**Neu:** `$service = new AiAssetLookupService($db, new ClaudeService($db, $instanceId));`

### Entfernt
- `$apiKey = getenv('AI_API_KEY')` → kommt aus ClaudeService
- `callClaude()` / `callOpenAi()` → `$claudeService->ask()`
- `apiProvider`-Logik → nur Claude Messages API

### Neu
- Feature-Flag-Prüfung: `isFeatureEnabled('asset_lookup')`
- Usage-Logging automatisch
- Verbesserter Prompt mit weight_kg, new_price_eur, dimensions_mm, power_consumption_watts, confidence

---

# 8. Entwicklungsumgebung

*Ursprüngliche Datei: DEVELOPMENT.md*

## 8.1 Voraussetzungen

Docker Desktop (oder Docker + Docker Compose) + IDE

## 8.2 Schnellstart

```bash
git clone <repo-url> && cd rmsclone
docker compose up -d
docker compose logs -f app   # Ersten Build abwarten (2-3 Min)
```

## 8.3 URLs

| Service | URL | Beschreibung |
|---------|-----|--------------|
| **App** | http://localhost:8080 | MyRMS Hauptanwendung |
| **phpMyAdmin** | http://localhost:8082 | Datenbank-Verwaltung |
| **Mailpit** | http://localhost:8083 | E-Mail-Testumgebung |
| **S3 Mock** | http://localhost:8081 | Datei-Upload Emulation |

## 8.4 Datenbank-Zugangsdaten

Host: `localhost:3306`, DB: `myrms`, User: `myrms`, Password: `myrms_dev`, Root: `root_dev`

## 8.5 PhpStorm Einrichtung

1. Ordner `rmsclone/` als Projekt öffnen
2. PHP Interpreter: Docker Compose → Service `app`
3. Database: MySQL → localhost:3306, myrms/myrms_dev
4. Server: localhost:8080, Path mapping: `/pfad/zu/rmsclone` → `/var/www/html`

## 8.6 Häufige Befehle

```bash
docker compose up -d                                          # Starten
docker compose down                                           # Stoppen
docker compose logs -f app                                    # Logs
docker compose exec app bash                                  # Shell
docker compose exec app php vendor/bin/phinx migrate -e development  # Migrationen
docker compose exec app php vendor/bin/phinx seed:run         # Seeds
docker compose exec app composer install                      # Composer
docker compose down -v && docker compose up -d                # DB Reset
docker compose up -d --build                                  # Rebuild
```

## 8.7 Architektur

```
┌──────────────────────────────────────────────────┐
│  Docker Compose                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────────┐   │
│  │ app      │  │ db       │  │ s3filestore  │   │
│  │ PHP 8.1  │──│ MySQL 8  │  │ S3 Mock      │   │
│  │ Apache   │  │          │  │              │   │
│  │ :8080    │  │ :3306    │  │ :8081        │   │
│  └──────────┘  └──────────┘  └──────────────┘   │
│       │                                           │
│  ┌──────────┐  ┌──────────┐                      │
│  │phpmyadmin│  │ mailpit  │                      │
│  │ :8082    │  │ :8083    │                      │
│  └──────────┘  └──────────┘                      │
└──────────────────────────────────────────────────┘
```

## 8.8 Troubleshooting

- **"Could not connect to database":** Warte 10-15 Sek. nach docker compose up
- **Port belegt:** Port in docker-compose.yml ändern
- **Migrationen fehlgeschlagen:** Manuell ausführen mit phinx migrate
- **Änderungen nicht sichtbar:** PHP/Twig sofort, Dockerfile → rebuild

---

# 9. Synology-Installation

*Ursprüngliche Datei: SYNOLOGY-SETUP.md*

## 9.1 Voraussetzungen

Synology DiskStation mit DSM 7.x + Container Manager + SSH-Zugang

## 9.2 Installation

```bash
ssh dein-user@DEINE-NAS-IP
cd /volume1/docker
git clone https://github.com/DEIN-REPO/myrms.git myrms
cd myrms
cp .env.synology.example .env.synology
nano .env.synology  # ROOT_URL, DB_PASSWORD, MYSQL_ROOT_PASSWORD, JWTKey anpassen
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d
```

## 9.3 HTTPS (Optional)

Synology Reverse Proxy: Systemsteuerung → Anmeldeportal → Erweitert → Reverse Proxy
- Quelle: HTTPS, myrms.deine-domain.de:443
- Ziel: HTTP, localhost:80

## 9.4 Nützliche Befehle

```bash
# Status
docker compose -f docker-compose.synology.yml --env-file .env.synology ps
# Logs
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f app
# Backup
docker exec myrms-db mysqldump -u root -pDEIN_ROOT_PW myrms > backup.sql
# Restore
docker exec -i myrms-db mysql -u root -pDEIN_ROOT_PW myrms < backup.sql
```
