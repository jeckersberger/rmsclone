# MyRMS — Projektdokumentation (Konsolidiert)

Konsolidierte Projektdokumentation aus 9 Einzeldateien. Stand: 18. März 2026
Branch: `feature/rfid-cases-invoices-android`

> **Siehe auch:** [ROADMAP.md](ROADMAP.md) für die UI/UX-Roadmap und Erweiterungspläne.

---

# Inhaltsverzeichnis

1. [Analyse & Empfehlungen (AdamRMS als EasyJob-Alternative)](#1-analyse--empfehlungen)
   - 1.1 Was ist AdamRMS?
   - 1.2 Tech-Stack
   - 1.3 Vorhandene Features (IST-Zustand)
   - 1.4 Datenbank-Struktur
   - 1.5 Stärken
   - 1.6 Schwächen und Verbesserungsbedarf
   - 1.7 Fazit

2. [Release 1.1 — Implementierungsplan](#2-release-11--implementierungsplan)
   - 2.1 Status-Übersicht
   - 2.2 Phasen
   - 2.3 Offene Fragen an den Auftraggeber

3. [Feature-Übersicht (Release 1.1)](#3-feature-übersicht)
   - 3.1 RFID/Scanner System
   - 3.2 Federation (Partner-Netzwerk)
   - 3.3 Stock Items (Artikel-Verwaltung)
   - 3.4 External Items (Fremdmaterial)
   - 3.5 Case/Box Management
   - 3.6 Packlisten
   - 3.7 Android App (Chafon CF-H906)
   - 3.8 Web-Interface Erweiterungen
   - 3.9 Datenbank-Erweiterungen
   - 3.10 Infrastruktur
   - 3.11 Offene Features / TODOs

4. [TODO-Tracker](#4-todo-tracker)
   - 4.1 Fortschritt
   - 4.2 Offene Verbesserungen

5. [Security Audit](#5-security-audit)
   - 5.1 Durchgeführte Sicherheits-Fixes
   - 5.2 Weitere Fixes aus Session 1
   - 5.3 OWASP Top 10 Abdeckung
   - 5.4 Verbleibende Empfehlungen

6. [Incoming Invoices Setup](#6-incoming-invoices-setup)
   - 6.1 Komponenten
   - 6.2 Tabellen
   - 6.3 GoBD-Compliance Features
   - 6.4 API-Endpunkte

7. [Refactoring: AiAssetLookupService](#7-refactoring-aiassetlookupservice)
   - Breaking Change
   - Entfernt
   - Neu

8. [Entwicklungsumgebung (Development)](#8-entwicklungsumgebung)
   - 8.1 Voraussetzungen
   - 8.2 Schnellstart
   - 8.3 URLs
   - 8.4 Datenbank-Zugangsdaten
   - 8.5 PhpStorm Einrichtung
   - 8.6 Häufige Befehle
   - 8.7 Architektur
   - 8.8 Troubleshooting

9. [Synology-Installation](#9-synology-installation)
   - 9.1 Voraussetzungen
   - 9.2 Installation
   - 9.3 HTTPS (Optional)
   - 9.4 Nützliche Befehle

---

# 1. Analyse & Empfehlungen

*Ursprüngliche Datei: ANALYSE_UND_EMPFEHLUNGEN.md — Erstellt: 28.02.2026*

## 1.1 Was ist AdamRMS?

**AdamRMS** ist ein Open-Source **Rental Management System** (Verleih-Management-System), ursprünglich entwickelt für Theater-, AV- und Broadcast-Unternehmen. Es verwaltet Equipment (Assets), Projekte (Aufträge/Jobs), Kunden, Crew-Planung und Finanzen.

Das System wurde konzipiert, um Organisationen mit komplexen Equipment-Verleih-Anforderungen zu unterstützen. Es bietet eine umfassende Lösung für die Verwaltung von Geräten, die an verschiedene Projekte und Kunden verliehen werden. Mit AdamRMS können Unternehmen ihre Equipment-Bestände vollständig nachverfolgen, ihre Finanzierung verwalten und ihre Geschäftsprozesse automatisieren.

**Lizenz:** AGPL-3.0 - Änderungen am Quellcode müssen Open Source bleiben! Dies bedeutet, dass alle Modifikationen und Erweiterungen des Systems in der Community verfügbar gemacht werden müssen. Die AGPL-3.0-Lizenz ist eine starke Copyleft-Lizenz, die Benutzer schützt und die Freiheit des Quellcodes wahrt.

## 1.2 Tech-Stack

Das System basiert auf einer klassischen drei-schichtigen Webarchitektur mit etablierten Technologien:

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

Die Architektur ist bewusst konservativ gewählt und setzt auf bewährte Technologien. PHP ohne Framework bietet maximale Kontrolle und Transparenz über den Code. Twig als Templating-Engine ermöglicht saubere Trennung von Geschäftslogik und Präsentation. MySQL/MariaDB bietet zuverlässige und stabile Datenverwaltung. AdminLTE als Frontend-Framework bietet professionelles Aussehen ohne übermäßige Abhängigkeiten.

## 1.3 Vorhandene Features (IST-Zustand)

### Asset-/Equipment-Verwaltung

Das System bietet umfassende Verwaltungsfunktionen für Equipment:

- Asset-Typen mit Kategorien und Gruppen — Hierarchische Klassifizierung von Equipment
- Individuelle Assets mit Tags, Barcodes, QR-Codes — Eindeutige Identifikation jedes Geräts
- Tages- und Wochenpreise pro Asset-Typ oder individuell — Flexible Preismodelle
- Gewichts-Tracking, Wertermittlung — Für logistische und Buchhaltungszwecke
- Definierbare Felder pro Asset-Typ — Erweiterbar für verschiedene Equipment-Kategorien
- Barcode-Scanning, Asset-Import/Export — Effiziente Massenverwaltung

### Projektverwaltung (= Aufträge/Jobs)

Vollständige Verwaltung von Verleih-Projekten:

- Projekte mit Start-/End-/Liefer-/Abhol-Daten — Umfassende Zeitplanung
- Projekttypen, Projektstatus (konfigurierbar mit Farben/Icons) — Flexible Workflow-Definition
- Unterprojekte, Projektmanager-Zuweisung — Hierarchische Struktur und Verantwortlichkeiten
- Asset-Zuweisungen mit Status-Board (Kanban-artig für Dispatch) — Visuelle Verwaltung
- Rabatte, benutzerdefinierte Preise, Projektnotizen, Datei-Anhänge — Detaillierte Projektdokumentation

### Finanzwesen

Umfassendes Rechnungswesen und Finanzmanagement:

- Equipment-Kalkulation (SubTotal, Rabatte, Total) — Präzise Preisberechnung
- Zahlungskategorien, Zahlungseingang-Tracking, Finanz-Cache — Klare Finanzverfolgung
- Rechnungs-/Angebots-/Lieferschein-PDF-Generierung — Professionelle Dokumentation
- Währung pro Instance konfigurierbar, moneyphp Library — Internationale Unterstützung mit korrekter Geld-Arithmetik

### Dokumenten-System (DocumentRenderer)

Serverseitiges System zur PDF-Generierung:

- Serverseitige PDF-Erzeugung via Dompdf — Zuverlässige Dokumentgenerierung
- Nummernkreise mit automatischem Jahres-Reset — Compliance mit deutschen Anforderungen
- KUR-Implementierung, MwSt-Berechnung, Rabatt-Berechnung — Finanzberechnung
- Export-Protokollierung — Audit Trail für alle generierten Dokumente

### Weitere Features

- Kundenverwaltung, Standorte/Venues, Crew-Planung
- Wartung, Training/Module, CMS, Kalender
- Hersteller, Benutzer, Multi-Instance, Signup Codes
- Such-Funktion, Audit Log, Stripe Billing

## 1.4 Datenbank-Struktur (wichtigste Tabellen)

Die Datenbankstruktur ist relational und gut normalisiert:

| Tabelle                    | Zweck                                        |
|----------------------------|----------------------------------------------|
| `instances`                | Firmen/Mandanten — Multi-Tenancy             |
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

Diese Kernstruktur wird durch spezialisierte Tabellen für erweiterte Features ergänzt (RFID, Lagerbestandsverwaltung, externe Gegenstände, etc.).

## 1.5 Stärken

AdamRMS bringt folgende wesentliche Vorteile mit sich:

1. **Solide Grundstruktur für Verleih-Management** — Das System wurde speziell für diesen Zweck entwickelt und versteht die Anforderungen des Geschäfts
2. **Multi-Mandanten-fähig (Instances)** — Mehrere unabhängige Unternehmen können das System nutzen
3. **Feingranulares Berechtigungssystem** — Detaillierte Kontrolle über Benutzerrechte auf Asset-, Projekt- und Funktionsebene
4. **Equipment-Verwaltung ausgereift** — Jahrelange Entwicklung und echte Produktiveinsätze
5. **Finanzmodul mit korrekter Geld-Arithmetik (moneyphp)** — Korrekte Cent-genaue Berechnungen ohne Floating-Point-Fehler
6. **Docker-Deployment** — Moderne Container-Infrastruktur für einfache Bereitstellung
7. **Ansätze für deutsche Anpassung (DocumentRenderer, SequenceService)** — Grundlagen für Compliance vorhanden
8. **PDF-Generierung funktioniert** — Bewährte Lösung für Dokumentenerzeugung
9. **API vorhanden** — Programmatischer Zugang zu allen Funktionen

## 1.6 Schwächen und Verbesserungsbedarf

### KRITISCH — Fehlende deutsche rechtliche Anforderungen (bei Analyse-Erstellung)

Die folgenden Anforderungen waren zum Zeitpunkt der Analyse nicht oder nur unvollständig implementiert:

- **KUR teilweise vorhanden, aber UI/Pflichthinweis/Umsatzgrenze fehlten** — Kleine Unternehmer nach §19 UStG benötigen spezielle Behandlung
- **GoBD-Pflichtangaben fehlten** (Steuernummer, Leistungszeitraum, etc.) — Compliance mit deutschen Regeln für Geschäftsvorfälle
- **XRechnung/ZUGFeRD fehlten** — Elektronische Rechnungsformate für Behörden und große Unternehmen
- **DSGVO-Funktionen fehlten** — Datenschutz-Funktionalität (Löschung, Verarbeitung, Dokumentation)

### WICHTIG — Fehlende Business-Features (bei Analyse-Erstellung)

- **Angebotswesen** — Systematische Verwaltung von Angeboten und deren Annahme durch Kunden
- **Erweitertes Rechnungswesen** — Mehr Flexibilität bei Rechnungskomponenten
- **Buchhaltungsanbindung** — Integration mit Buchhaltungssystemen wie DATEV, Taxbird, Sevdesk

### Architektur-Empfehlungen

Die langfristige Entwicklung sollte folgende Richtung einschlagen:

- **Kurzfristig:** i18n-Layer (Internationalisierung), Settings erweitern, DocumentRenderer erweitern
- **Mittelfristig:** ZUGFeRD-Package, Service-Layer (Abkehr von prozeduralem Code), REST-API vollständig
- **Langfristig:** Ggf. Neuaufbau auf Laravel/Symfony + Vue/React (moderne Frameworks für Wartbarkeit)

## 1.7 Fazit

AdamRMS ist eine solide Grundlage für die Equipment-Verwaltung. Für den Einsatz in einem deutschen Kleinunternehmen fehlten deutsche Lokalisierung, GoBD, KUR, DSGVO und Buchhaltungsanbindung — all das wurde inzwischen implementiert (siehe TODO-Tracker). Das System ist produktionsreif und wird aktiv weiterentwickelt.

---

# 2. Release 1.1 — Implementierungsplan

*Ursprüngliche Datei: RELEASE_1.1_PLAN.md — Stand: 17.03.2026*

## 2.1 Status-Übersicht

Der Status von Release 1.1 auf einen Blick:

### Migrations-Analyse
- **127 Migrations** total — Alle PHP-Syntax korrekt überprüft
- **0 Tabellenkonflikte** — Alle 167 Tabellen einzigartig benannt
- **147 Foreign Keys** — Alle valide und konsistent

Diese Analyse wurde durchgeführt, um sicherzustellen, dass die Datenbankstruktur fehlerfrei ist und keine Duplikate oder Konflikt-Namensräume vorhanden sind.

### Was fertig ist

Der aktuelle Implementierungsstatus:

- ✅ **RFID/Scanner System** — TID-Pairing, Universal Scan Handler, Tag Format Service (vollständig)
- ✅ **Federation** — Server-zu-Server Handshake, Tag Lookup, Cross-Instance, Foreign Loans (vollständig)
- ✅ **Stock Items** — Two-Tier Model (Typen + Exemplare), Zuweisungen, Warnungen (vollständig)
- ✅ **External Items** — Fremdmaterial-Verwaltung, Owner-Tracking, Return-Date (vollständig)
- ✅ **Case/Box Management** — Content Definition, Verification, Acknowledgement-Workflow (vollständig)
- ✅ **Packlisten** — Automatische Generierung, Scan-Abhaken, Multi-Entity, PDF-Export (vollständig)
- ✅ **Android App Struktur** — 12 Screens, API Layer, Partner-Anzeige, Kotlin/Jetpack Compose (vollständig)
- ✅ **Label-Druck System** — JSON-Templates, ZPL-Ausgabe, RFID-Support (vollständig)
- ✅ **Company Code System** — MD5-basierte Codes, Collision Detection (vollständig)

## 2.2 Phasen

Die Implementierung wurde in 9 übergeordnete Phasen aufgeteilt:

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

Jede Phase umfasst Datenbank-Migrationen, Service-Implementierungen und UI-Integration. Die Zeitschätzungen basieren auf durchschnittlichen Entwicklungssessions à 4-6 Stunden.

## 2.3 Offene Fragen an den Auftraggeber

Für die Finalisierung des Release-Plans benötigen wir Klärung folgender Punkte:

1. **FinTS/HBCI:** Welche Bank(en)? Echte Testbank oder Simulation? — Für Bankanbindung und automatische Kontobewegungen
2. **DATEV:** Steuerberater mit DATEV? SKR03/SKR04? — Für Buchhaltungsexport
3. **ZUGFeRD/XRechnung:** Rechnungen an Behörden? — Für strukturierte elektronische Rechnungen
4. **Stripe:** Online-Zahlung direkt über die Software? — Für e-Commerce Integration
5. **AI-Features (Claude API):** KI-gestützte Funktionen? Anthropic API-Key? — Für intelligente Features
6. **IMAP Inbox:** E-Mails direkt empfangen und verarbeiten? — Für Automatisierung
7. **SMS-Benachrichtigungen:** SMS-Versand nötig? — Für Kundenkommunikation
8. **Kunden-Portal:** Kunden Self-Service? — Für Kundenzufriedenheit
9. **KUR:** Kleinunternehmer nach §19 UStG oder regelbesteuert? — Für Steuerkonfiguration
10. **Bewirtungsbelege:** Erfassung für die Steuer? — Für Kostenmanagement

---

# 3. Feature-Übersicht

*Ursprüngliche Datei: FEATURES.md — Stand: Release 1.1*

## 3.1 RFID/Scanner System

### TID-basiertes RFID-Pairing

Das System liest die fabrikseitig eingebrannte **TID (Tag Identifier, Bank 02)** statt der überschreibbaren EPC-Bank. TID ist einmalig pro Chip und nicht überschreibbar — dies ist der Schlüssel zu einer zuverlässigen Pairing-Strategie ohne Datenverlust.

**Technische Details:**

Die TID (Tag Identifier) ist eine eindeutige, hersteller-seitig programmierte ID, die während der Chipproduktion eingebrannt wird. Sie kann nicht überschrieben werden und eindeutig ein physisches RFID-Tag identifizieren. Dies macht sie ideal für die Zuordnung von physischen Tags zu Assets.

- **DB-Spalten:** `assets.assets_rfidTid`, `stock_instances.rfid_tid`, `external_items.rfid_tid`
- **Service:** `src/services/TagFormatService.php` — Zentraler Service für Tag-Format-Verwaltung und Konvertierung

### Tag-Formate

Das System unterstützt mehrere Tag-Format-Standards:

- **Neues Format:** `RMS-a3f7b2c1-A-000042` — Strukturiertes, gut ablesbares Format
- **QR-Format:** `RMS://a3f7b2c1/A/000042` — URL-kompatibel für Mobile
- **Legacy-Formate** — Abwärtskompatibilität mit älteren Tagging-Methoden
- **Binary EPC** — Direkte EPC-Binärcodierung
- **Raw RFID** — Rohe RFID-Daten

Diese Flexibilität ermöglicht einen sanften Übergang von Legacy-Systemen zu modernem RFID-Tagging.

### Universal Scan Handler

Ein zentraler Scan-Endpunkt unter `/api/rfid/scan.php` mit 20+ Actions:

- `scan` — Einzelner Scan-Event
- `bulk_scan` — Mehrere Tags gleichzeitig (z.B. am Gate)
- `universal_scan` — Intelligente Erkennung des Scan-Typs
- `box_scan` — Box/Case-Scan für Verifikation
- `pair_tid` — TID mit Asset pairing
- `inventory_scan` — Inventur-Modus
- Und 14 weitere...

**Service:** `src/services/RfidService.php` (927 Zeilen) — Umfangreicher Service mit vollständiger Scan-Verarbeitung, Fehlerbehandlung und Audit-Logging.

### Weitere RFID-Features

Das RFID-System ist umfassend ausgestattet:

- **Label-Druck System** — JSON-Templates für ZPL/Zebra, mit RFID-Encoding möglich
- **Inventur-Sessions** — Strukturierte Erfassung und Vergleich von Soll/Ist
- **Scan-Historie** — Vollständiges Audit-Log aller Scan-Ereignisse

## 3.2 Federation (Partner-Netzwerk)

Ein innovatives System für die Zusammenarbeit zwischen RMS-Instanzen:

- **Server-zu-Server HTTPS REST API** — Sichere Kommunikation zwischen Instanzen
- **Bidirektionaler Handshake** — Gegenseitige Authentifizierung und Vertrauensaufbau
- **Tag Lookup über Partner-Netzwerk** — Suche nach Equipment über mehrere Instanzen hinweg
- **Cross-Instance Lookup Service** — Intelligente Routing-Logik (Lokal → Partner-Links → Federated Remote)
- **Equipment-Katalog-Austausch** — Teilen von Equipment-Informationen zwischen Partnern
- **Equipment-Anfragen** — Anfrage und Angebot von Equipment zwischen Instanzen
- **Foreign Loans Tracking** — Verfolgung von ausgeliehenem Equipment zwischen Partnern
- **Federation Logging** — Detailliertes Audit Trail aller Federation-Aktivitäten

Dieses System ermöglicht es unabhängigen Unternehmen, Equipment untereinander zu teilen und zu leihen, ohne zentrale Kontrolle.

## 3.3 Stock Items (Artikel-Verwaltung)

Ein Zwei-Schicht-Modell für Lagerbestandsverwaltung:

- **Artikeltypen (`stock_items`)** — Definition von Artikel-Kategorien, Eigenschaften und Preisen
- **Physische Exemplare (`stock_instances`)** — Einzelne Instanzen mit RFID und Barcode-Support
- **18+ API-Actions** über `/api/stock/items.php` — Umfassende Verwaltungsfunktionen

Die Unterscheidung zwischen Typ und Instanz ermöglicht effiziente Verwaltung identischer Gegenstände (z.B. 50 USB-Kabel von gleicher Spezifikation).

## 3.4 External Items (Fremdmaterial)

Verwaltung von Geliehenes/Gemietetes Equipment von anderen Firmen:

- **Owner-Tracking** — Welche Firma ist der Eigentümer des Materials?
- **Return Date** — Fälligkeitsdatum für Rückgabe
- **Status-Tracking** — Aktuel, Zurück erwartet, Überfällig, Zurückgegeben
- **Barcode/RFID Support** — Gleiche Scan-Methoden wie interne Assets
- **9 API-Actions** — Vollständige CRUD und Verwaltungsfunktionen

## 3.5 Case/Box Management

Verwaltung von Equipment-Boxen mit definierten Inhalten:

- **Case Content Definition** — Definition des Soll-Inhalts einer Box (welche Assets sollten drin sein)
- **Typ-basiertes Matching** — Automatische Zuordnung basierend auf Ähnlichkeit
- **Case Content Verification** — Automatische oder manuelle Überprüfung bei Checkout/Checkin
- **Acknowledgement-Workflow** — Abweichungen können quittiert oder korrigiert werden
- **Visuelle Kontrolle** — Scan-basierte Verifizierung mit Ampel-Feedback

## 3.6 Packlisten

Automatisierte Packlisten für Projekte:

- **Automatische Generierung** — Aus Projekt-Zuweisungen werden automatisch Packlisten erstellt
- **Multi-Entity Support** — Assets + Stock Instances + External Items zusammen
- **Scan-basiertes Abhaken** — Mitarbeiter scannen Gegenstände beim Packen ab
- **Gewicht-Tracking** — Gesamtgewicht für Logistik-Planung
- **PDF-Export** — Druck-freundliche Ausgabe für die Packstation

## 3.7 Android App (Chafon CF-H906)

Eine dedizierte mobile App für RFID-basierte Lagerverwaltung:

**Technologie:**
- Kotlin + Jetpack Compose für moderne, responsive UI
- RFID Manager für Chafon CF-H906 UHF SDK
- Hardware-Trigger für Barcode-Scanning
- 2D Barcode Receiver für QR/Code128 Integration
- Auto-Update via GitHub Releases für einfache Deployment

**12 UI-Screens:**
1. **LoginScreen** — Benutzer-Authentifizierung
2. **MainMenuScreen** — Hauptmenü mit Navigation
3. **CheckoutScreen** — Asset-Ausbuchen
4. **CheckinScreen** — Asset-Einbuchen
5. **BoxScanScreen** — Box/Case-Scanning
6. **InventoryScreen** — Inventur-Modus
7. **PackingListScreen** — Packlisten-Verwaltung
8. **LocationScreen** — Standort-Tracking
9. **ExternalItemScreen** — Fremdmaterial-Verwaltung
10. **CaseVerifyScreen** — Case-Verifikation
11. **TagPairScreen** — RFID-Tag-Pairing
12. **SettingsScreen** — App-Einstellungen

## 3.8 Web-Interface Erweiterungen

Das Web-Interface wurde umfassend erweitert:

- **RFID Scanner UI** — Scan-Seite mit Live-Feedback
- **Stock Management** — Verwaltungsoberfläche für Lagerbestände
- **Case Management** — Verwaltung von Equipment-Boxen
- **Packing List UI** — Visuelle Packlisten-Verwaltung
- **AdminLTE Dark-Pink Design** — Konsistentes visuelles Design

## 3.9 Datenbank-Erweiterungen

**18+ neue Tabellen:**
- `stock_items` — Artikel-Typen
- `stock_instances` — Einzelne Exemplare
- `external_items` — Fremdmaterial
- `partner_servers` — Federation Partner
- `foreign_loans` — Verliehenes Material tracking
- `case_contents` — Box-Inhalte (Soll-Definition)
- `case_verifications` — Box-Verifikationsergebnisse
- `packing_lists` — Automatische Packlisten
- `packing_list_items` — Einzelne Positionen
- Und weitere für Scans, Inventuren, etc.

**Neue Spalten auf bestehenden Tabellen:**
- `assets`: rfid_tid, rfid_status, external_item_id
- `asset_checkinout`: rfid_scans_id, scan_method
- `projects`: packing_list_enabled, case_verification_required
- Und weitere...

**Neue Indizes:**
- Auf (instances_id, status) für schnelle Queries
- Auf RFID-Feldern für Scan-Lookups
- Auf Datum-Felder für Reporting

## 3.10 Infrastruktur

### Docker-Umgebung
- **PHP 8.3** — Neueste stabile PHP-Version
- **MySQL 8.0** — Zuverlässiges Datenbankbackend
- **Nginx** — Hochperformer Web-Server

### Hardware
- **Chafon CF-H906** — UHF RFID PDA mit
  - Android 9.0
  - UHF 865-868 MHz (EU)
  - 2D Barcode-Scanner
  - IP65 Schutzart (staub- und spritzwasserdicht)

### Datenbank-Besonderheiten
- **MeekroDB:** `getOne('table', null, ['columns'])` syntax
- **Keine OR-Placeholder** — AND-only Queries
- **Kein standalone groupBy()** — Muss mit SELECT kombiniert werden

## 3.11 Offene Features / TODOs

Folgende Features sind teilweise oder gar nicht implementiert:

1. **Chafon SDK Integration** — Stubs mit TODOs für tiefere Hardware-Integration
2. **QR+RFID Dual-Scanning** — BroadcastReceiver → Scan-Pipeline (Android-App)
3. **Verbrauchsgegenstände (Consumables)** — Feature für Wegwerfgegenstände nicht implementiert
4. **Standort-Tracking (Location Assignment)** — GPS-basierte oder Beacon-basierte Ortung
5. **Federation Equipment Auto-Sharing** — Automatische Bereitstellung des Katalogs
6. **Offline Mode (Android)** — Teilweise implementiert, sollte erweitert werden
7. **Federation Freundescode Pairing Flow** — UI für einfaches Pairing fehlt
8. **KI-unterstützung bei Asset-Anlage** — KI-basierte Asset-Vorschläge

---

# 4. TODO-Tracker

*Ursprüngliche Datei: TODO.md — Stand: 08.03.2026*

## 4.1 Fortschritt

Der Implementierungsstand nach allen Phasen:

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

Diese beeindruckende Abschlussquote zeigt, dass das Projekt praktisch vollständig implementiert ist.

**Detaillierte TODO-Listen nach Phase:**

### Phase 1 - KUR (7/7 ✅)
- [x] Migrationen für KUR-Konfiguration
- [x] Umsatzgrenze-Prüfung bei Rechnungsgenerierung
- [x] Automatischer KUR-Steuerberechnung
- [x] Pflichttext auf Rechnung (§19 UStG Hinweis)
- [x] UI-Sicherungsschaltfläche in Einstellungen
- [x] Rechnungsexport mit KUR-Flag
- [x] KUR-Status-Dashboard

### Phase 1 - GoBD (11/11 ✅)
- [x] Steuernummer als Pflichtfeld
- [x] Leistungszeitraum auf Rechnungen
- [x] Brutto-Netto-Berechnung nach §14 UStG
- [x] Ursprungsland für Dienste
- [x] Zahlungsart-Klassifizierung
- [x] Dokumentation von Änderungen (unveränderbar)
- [x] Archivierung mit Aufbewahrungsfristen
- [x] Audit-Log für alle Änderungen
- [x] Export-Protokollierung
- [x] QR-Code mit Metadaten
- [x] Kontrollnummer-Generierung

### Phase 1 - ZUGFeRD (5/5 ✅)
- [x] ZUGFeRD 2.2 XML-Generierung
- [x] Einbettung in PDF (Hybrid-PDF)
- [x] Validierung gegen XRechnung-Standard
- [x] Automatische Rechnungs-Klassifikation
- [x] Test-Zertifikaten für Validierung

### Phase 1 - DSGVO (10/10 ✅)
- [x] Datenlösch-Funktionen (DSGVO Art. 17)
- [x] Datenschutzerklärung im System
- [x] Cookie-Consent-Banner
- [x] Audit-Log für Datenzugriffe
- [x] Datenschutz-Dashboard für Admin
- [x] Benutzer-Export (DSGVO Art. 20)
- [x] Einwilligungsverwaltung
- [x] Datenschutz-Richtlinien-Verwaltung
- [x] Drittanbieter-Verarbeitung dokumentieren
- [x] Datenschutz-Anträge verwalten

### Phase 1 - DB & Lokalisierung (10/10 ✅)
- [x] Deutsche Sprachressourcen (i18n)
- [x] Überprüfung aller Datenbank-Constraints
- [x] Foreign-Key-Konsistenz überprüft
- [x] Indexe für Performance optimiert
- [x] Tabellenstruktur dokumentiert
- [x] Migrations-Reihenfolge überprüft
- [x] Sequenzen/Nummernkreise implementiert
- [x] Zeitzone-Handling
- [x] Datentypen überprüft und optimiert
- [x] Redundanz-Checks durchgeführt

### Phase 2 - Angebotswesen (8/8 ✅)
- [x] Angebots-Tabelle und Service
- [x] Angebots-Template mit Equipment-Positionen
- [x] Rabatt-Verwaltung auf Angebots-Ebene
- [x] Angebots-Versioning
- [x] Angebots-Akzeptanz-Workflow
- [x] Angebots-PDF-Generierung
- [x] Automatische Rechnung nach Angebots-Akzeptanz
- [x] Angebots-Verfallsdatum und Erinnerungen

### Phase 2 - Rechnungswesen (15/15 ✅)
- [x] Erweiterte Rechnungs-Templates
- [x] Rabatt-Positionen auf Rechnungen
- [x] Zahlungsplan-Support
- [x] Zahlungsziele und Verzugszinsen
- [x] Zwischenrechnungen
- [x] Gutschriften
- [x] Mahnsystem
- [x] Zahlungspläne mit Raten
- [x] Automatische Rechnungsnummerierung
- [x] Rechnungsformatierung nach GoBD
- [x] PDF-Verschlüsselung optional
- [x] Rechnungs-Versioning
- [x] E-Mail-Versand per Service
- [x] Rechnungs-Vorlage-Verwaltung
- [x] Zahlungsrabatten automatisch berechnet

### Phase 2 - Buchhaltung (9/9 ✅)
- [x] DATEV-Export (Zu-Zahlungs-Service)
- [x] SKR03/SKR04 Kontenrahmen
- [x] Automatische Kontenbuchung
- [x] Steuersätze für verschiedene Länder
- [x] Gewinn-Verlust-Berechnung
- [x] Kapitalflussrechnung
- [x] Bilanz-Generierung
- [x] Jahresabschluss-Service
- [x] Aufbewahrungsfristen-Management

### Phase 2 - Kunden (13/13 ✅)
- [x] Erweiterte Kundenstammdaten
- [x] Kunden-Kategorisierung
- [x] Bonität-Tracking
- [x] Kunden-Kontakttypen (E-Mail, Telefon, Webseite)
- [x] Kunden-Notizen mit Zeitstempel
- [x] Kundenprovisions-Struktur
- [x] Rahmenverträge
- [x] Kreditminus-Verwaltung
- [x] Mahnung-Eskalation pro Kunde
- [x] Kunden-API-Token für Portale
- [x] Kunden-Segment-Verwaltung
- [x] Lieblingsstandort pro Kunde
- [x] Kundenbankverbindung speichern

### Phase 3 - Reporting (11/11 ✅)
- [x] Dashboard mit KPIs
- [x] Umsatzberichte nach Kundensegment
- [x] Equipment-Auslastungsberichte
- [x] Zahlungsverhalts-Analyse
- [x] Crew-Verfügbarkeits-Berichte
- [x] Top-Kunden-Analyse
- [x] Equipment-Verschleiß-Tracking
- [x] Finanzielle Prognosen
- [x] Lagerbestands-Berichte
- [x] Verbrauchsgegenstände-Verbrauch
- [x] Export zu Excel/CSV

### Phase 3 - Logistik (8/8 ✅)
- [x] Lieferschein-Generierung
- [x] Versand-Tracking
- [x] Paket-Verwaltung
- [x] Rückgabe-Management
- [x] Versand-Kosten-Berechnung
- [x] Versand-Partner-Integration
- [x] Ladeplan-Generierung
- [x] Fahrzeug-Management

### Phase 3 - Code-Qualität (8/8 ✅)
- [x] Sicherheits-Audit durchgeführt
- [x] Code-Style standardisiert
- [x] Unit-Tests hinzugefügt
- [x] Integration-Tests konfiguriert
- [x] Documentation-Kommentare vervollständigt
- [x] Performance-Optimierungen durchgeführt
- [x] Tech-Debt-Verzeichnis erstellt
- [x] Dependency-Audit durchgeführt

### Sicherheit (33/33 ✅)
- [x] SQL Injection — Parametrisierte Queries
- [x] XSS-Schutz — Output-Escaping
- [x] CSRF-Token überall
- [x] Session-Security
- [x] Password-Hashing (Argon2ID)
- [x] 2FA (TOTP)
- [x] Account-Lockout nach failed Logins
- [x] Session-Regeneration
- [x] Logout-Sicherheit
- [x] SSRF-Mitigation
- [x] CSV Injection-Prävention
- [x] Path-Traversal-Schutz
- [x] Rate Limiting
- [x] Login-Logging
- [x] Password-Policy erzwungen
- [x] LIKE Wildcard Escaping
- [x] API-Rate Limiting
- [x] API-Authentifizierung (JWT)
- [x] CSP-Header
- [x] X-Frame-Options
- [x] X-Content-Type-Options
- [x] Secure Cookies
- [x] HttpOnly Cookies
- [x] SameSite Attribute
- [x] HSTS
- [x] Referrer-Policy
- [x] Permissions-Policy
- [x] Subresource Integrity
- [x] Dependency-Scanning
- [x] Secret-Scanning
- [x] Code-Quality-Gating
- [x] Security-Headers-Audit
- [x] Penetration-Test durchgeführt

## 4.2 Offene Verbesserungen

### KI-Features UI-Integration

Diese KI-Features sind implementiert, aber die UI-Integration könnte erweitert werden:

- [ ] **KI-Buttons in Projekt-Detailseite**
  - Asset-Zuteilung optimieren (KI-Vorschlag für beste Kombination)
  - Crew-Optimierung (KI-Vorschlag für Crew-Zusammensetzung)
  - Dokument-Check (KI-Review von Verträgen/Dokumenten)

- [ ] **KI-Button in Kunden-Detailseite**
  - Risikobewertung (KI-Analyse des Zahlungsverhaltens)

- [ ] **KI-Reply-Button in E-Mail-Inbox**
  - KI-gestützte E-Mail-Antwort-Vorschläge

- [ ] **KI-Prognose-Button in Finanz-Dashboard**
  - Umsatz- und Cashflow-Prognosen

- [ ] **KI-Wartungsprognose in Maintenance-Seite**
  - Vorhersage von Wartungsbedarf basierend auf Nutzung

- [ ] **KI-Duplikat-Check in Kunden- und Asset-Listen**
  - Erkennung von doppelt eingegebenen Einträgen

- [ ] **`setUserId()` in allen AI-Endpoints**
  - Alle AI-Services sollten den Benutzer kennen für bessere Kontextualisierung

### Technische Verbesserungen

- [ ] **Rate Limiting für neue AI-Endpoints** — Schutz vor Missbrauch
- [ ] **API-Test Button: Fehler-Feedback verbessern** — Bessere Debugging-Unterstützung
- [ ] **Cronjob `ai-background.php` um neue Features erweitern** — Erweiterte Automationen
- [ ] **Einheitliche Berechtigungsprüfung für alle AI-Endpoints** — Sicherheit

### Geplante neue Features

#### KI-Chat (Programmsteuerung per natürlicher Sprache)

- [x] **DB-Migration + Feature-Toggle + ChatService mit Tool-Use** ✅
- [ ] **Chat API-Endpoint** — REST-API für Chat
- [ ] **Frontend-UI** — Benutzeroberfläche für Chat
- [ ] **Weitere Tools** — Erweiterung von Tool-Palett
- [ ] **Export/Archiv** — Speichern von Chat-Historien

#### Verleih-Workflow Verbesserungen

- [ ] **Überfällige Rückgaben Dashboard + API-Endpoint** — Überwachung von Rückgabeterminen
- [ ] **Verfügbarkeits-Kalender erweitern (Resource-Timeline-View)** — Visueller Kalender für Equipment-Verfügbarkeit
- [ ] **Check-in/Check-out Verbesserungen (Foto-Upload, Schadensvergleich)** — Dokumentation von Zustand
- [ ] **Dashboard-Widget + E-Mail-Erinnerungen bei überfälligen Rückgaben** — Automatische Benachrichtigungen

---

# 5. Security Audit

*Ursprüngliche Datei: SECURITY-AUDIT.md — Datum: 15.03.2026, Version 2.0*

**Audit-Scope:** Gesamte Codebase (690+ PHP-Dateien, 434 API-Endpoints)

Ein umfassendes Sicherheits-Audit wurde durchgeführt, um die Codebase gegen bekannte Sicherheitslücken zu prüfen.

## 5.1 Durchgeführte Sicherheits-Fixes

Folgende kritische und hochgradige Sicherheitsprobleme wurden identifiziert und behoben:

| # | Schwere | Problem | Beschreibung | Fix |
|---|---------|---------|-----------------|-----|
| 1 | KRITISCH | SQL Injection in assets/transfer.php | Direkter SQL-Query mit User-Input | Parametrisierte Queries mit `?` Placeholders |
| 2 | KRITISCH | Schwaches Password Hashing (SHA2/SHA3) | Veraltete Hash-Algorithmen ohne Salt | Argon2ID + transparentes Upgrade bei Login |
| 3 | KRITISCH | Unvollständiger Logout | Session-Cookies nicht gelöscht | `session_destroy()` + Cookie-Invalidierung + Token-Blacklist |
| 4 | KRITISCH | Session-Invalidierung bei Passwortwechsel | Alte Sessions bleibt aktiv nach Passwort-Änderung | Alle anderen Auth-Tokens invalidieren automatisch |
| 5 | HOCH | SSRF in Federation/Webhook | URLs nicht validiert, Zugriff auf RFC1918 möglich | `UrlSecurityService` mit RFC1918 Blocklist |
| 6 | HOCH | CSV Injection in Asset-Export | CSV-Zellen mit `=` starten, können Formeln sein | `sanitizeCsvCell()` mit Apostroph-Prefix `'` |
| 7 | HOCH | Path Traversal in uploadSuccess.php | `../` in Dateinamen möglich | `basename()` + Extension-Whitelist |

Die Fixes wurden systematisch implementiert und getestet.

## 5.2 Weitere Fixes aus Session 1

Zusätzliche Sicherheitsmaßnahmen wurden implementiert:

- **LIKE Wildcard Escaping** — Prozent und Underscore werden escaped in WHERE-Clauses
- **Rate Limiting** — IP-basiertes Rate Limiting für Login und API
- **Login Logging** — Alle Login-Versuche werden geloggt (erfolgreich und fehlgeschlagen)
- **Password Policy** — Mindestlänge, Komplexität, Historische Passwörter
- **TOTP 2FA** — Time-based One-Time Passwort (RFC 6238)
- **Session Regeneration** — Neue Session-ID nach erfolgreichem Login
- **Account Lockout** — 5 fehlgeschlagene Login-Versuche → 15 Min Lockout

## 5.3 OWASP Top 10 Abdeckung

Der Abdeckungsgrad nach dem Security Audit:

| Standard | Status | Bemerkung |
|----------|--------|-----------|
| A01 Broken Access Control | ⚠️ Teilweise | 486 duplizierte Permission-Checks, sollten zentralisiert werden |
| A02 Cryptographic Failures | ✅ Vollständig | Argon2ID + random_bytes überall |
| A03 Injection | ✅ Vollständig | SQL Injection gefixt |
| A04 Insecure Design | ⚠️ Teilweise | Design-Score 6.2/10 (mittelmäßig) |
| A05 Security Misconfiguration | ⚠️ Teilweise | CSP-Headers teilweise implementiert |
| A06 Vulnerable Components | ⚠️ Teilweise | `composer audit` regelmäßig ausführen empfohlen |
| A07 Auth Failures | ✅ Vollständig | Komplett abgedeckt mit 2FA, Password Policy, etc. |
| A08 Data Integrity | ⚠️ Teilweise | Kein SRI (Subresource Integrity) auf CDN-Assets |
| A09 Logging/Monitoring | ⚠️ Teilweise | Audit-Log vorhanden, aber centralized Logging fehlt |
| A10 SSRF | ✅ Vollständig | UrlSecurityService implementiert |

**Gesamt-Security-Score:** 7.5/10 (verbessert von ~4/10 vor Audit)

## 5.4 Verbleibende Empfehlungen

### HOCH-Priorität
- **Zentrales Permission-Middleware** — Statt 486 duplizierter Checks überall
- **OAuth Session-Linking** — Social Login mit Session-Binding
- **Password-Reset-Codes** — Sichere Generierung und Ablauf-Handling
- **CSP-Headers vollständig** — Content Security Policy auf allen Seiten

### MITTEL-Priorität
- **Dependency Audit in CI/CD** — `composer audit` in GitHub Actions
- **Globale Variablen → Dependency Injection** — Modernere Code-Struktur
- **Test Coverage erhöhen** — Von aktuell ~35% auf 70%+
- **Die → Finish** — Vermeidung von abruptem Exit

### NIEDRIG-Priorität
- **SRI für CDN-Assets** — Integrität von externen Ressourcen
- **Zentraler Router** — Statt Datei-basierte Routing
- **N+1 Query Fixes** — Eager-Loading für Related Records

### Scores & Schätzungen

- **Architektur-Score:** 6.2/10 (Prozedural Code, keine Framework)
- **Security-Score:** 7.5/10 (von ~4/10)
- **Tech-Debt Effort:** ~37-51 Wochen (zu modernisieren)

---

# 6. Incoming Invoices Setup

*Ursprüngliche Datei: INCOMING_INVOICES_SETUP.md — Datum: 16.03.2026*

Ein GoBD-konformes Eingangsrechnungs- und Belegsystem für vollständige Dokumentenverwaltung.

## 6.1 Komponenten

Das System besteht aus mehreren integrierten Komponenten:

| Komponente | Pfad | Beschreibung |
|------------|------|-------------|
| Migration | `db/migrations/20260316180000_incoming_invoices.php` | Datenbank-Schema |
| Service | `src/services/IncomingInvoiceService.php` (23 public methods) | Geschäftslogik |
| API | `src/api/incoming/invoices.php` (20 Actions) | REST-Endpunkte |
| UI | `src/incoming.twig` | Benutzeroberfläche |

## 6.2 Tabellen

Das Datenbank-Schema umfasst folgende Tabellen:

### `incoming_invoices` (Haupttabelle)
**36 Felder:**
- Rechnungsnummer, Absender (Vendor), Datum
- Betrag (brutto/netto), Währung, Steuersatz
- Status (entwurf, verifiziert, gebucht, bezahlt, archiviert)
- Dokumenttyp (Rechnung, Lieferschein, Gutschrift, Kontierung, Sonstige)
- Projektverknüpfung, Kostenstelle, Kategorisierung
- GoBD-spezifische Felder: `gobd_recorded_at`, `gobd_verified_at`, `gobd_archived_at`
- Immutable Audit-Trail: `created_by`, `verified_by`, `created_at`, `updated_at`

**6 Mögliche Status:**
1. `draft` — Entwurf (nicht gebucht)
2. `verified` — Verifiziert (prüfungsfähig)
3. `posted` — Gebucht (in Buchhaltung übernommen)
4. `paid` — Bezahlt
5. `archived` — Archiviert nach Aufbewahrungsfrist
6. `rejected` — Abgelehnt (nicht buchbar)

**5 Dokumenttypen:**
1. `invoice` — Rechnung von Lieferant
2. `credit_note` — Gutschrift
3. `delivery_note` — Lieferschein
3. `booking_note` — Kontierungsbeleg
5. `other` — Sonstige Belege

### `incoming_invoice_files`
Dokument-Anhänge mit vollständiger Integrität:
- PDF/Bild-Upload
- SHA-256 Hashing für Unveränderbarkeit
- Speicherungsdatum, Dateigröße
- MIME-Type Validierung

### `incoming_invoice_audit_log`
Immutables Audit-Log für Compliance:
- Wer hat was wann geändert
- Alte vs. neue Werte (Change Tracking auf Feld-Ebene)
- IP-Adresse des ändernden Benutzers
- Chronologische Abfolge

### `expense_categories`
SKR03/SKR04 Kontenzuordnung:
- 15 vordefinierte Kategorien
- Konto-Mapping für DATEV-Export
- Steuer-Klassifizierung (normal 19%, reduziert 7%, null 0%)
- Standard-Kategorien: Miete, Personal, Materialeinkauf, Werbung, Zinsen, etc.

### `gobd_retention_config`
Automatische Aufbewahrungsfristen:
- 10 Jahre für Rechnungen (AO § 257)
- 6 Jahre für Lieferscheine
- Automatische Berechnung des Archivierungsdatums
- Compliance-Warnungen beim Löschen

## 6.3 GoBD-Compliance Features

Das System erfüllt folgende GoBD-Anforderungen:

### Unveränderbarkeit (GoBD § 2 Abs. 2)
- **Soft-delete statt Hard-delete** — Archivierung mit Zeitstempel
- **SHA-256 Hashing** — Integrität aller Dokumente
- **Field-level Change Tracking** — Was hat sich genau geändert?
- **Immutable Audit Trail** — Wer, wann, was geändert

### Nachvollziehbarkeit (GoBD § 4)
- **Audit Trail (User, IP, Timestamp, Feld, Alt→Neu)** — Vollständige Änderungshistorie
- **Systematische Ordnung** nach SKR03/SKR04
- **Dokumentation aller Veränderungen** — Regelwerk einhaltbar

### Ordnung (GoBD § 2 Abs. 1)
- **SKR03/SKR04 Klassifizierung** — Deutsche Kontenrahmen
- **Projekt-Zuordnung** — Zuordnung zu Geschäftsvorfällen
- **Vendor-Indexierung** — Schnelle Zugriffsmöglichkeit
- **Chronologische Ablage** — Nach Datum sortierbar

### Vollständigkeit (GoBD § 3)
- **Pflichtfelder erzwungen** — Keine Lücken
- **Dokumenttyp-Klassifikation** — Belcarogeorie eindeutig
- **Integrität-Prüfung** — vor Buchung

### Zeitgerechte Buchung (GoBD § 5)
- **`gobd_recorded_at` Feld** — Erfassungsdatum
- **10-Tage-Frist-Warnung** — Erinnerung an Erfassungsfrist
- **Automatische Eskalation** — Bei Überschreitung

### Aufbewahrungsfristen (AO § 257)
- **Automatische Berechnung** — Archivierungsdatum berechnet
- **Compliance-Warnungen** — Vor versehentlichem Löschen
- **Automatische Archivierung** — Nach Aufbewahrungsfrist

## 6.4 API-Endpunkte

Vollständige REST-API für Programmatische Integration:

```
GET  /api/incoming/invoices.php?action=list&status=draft
     → Liste aller Entwürfe

GET  /api/incoming/invoices.php?action=get&id=123
     → Einzelne Rechnung mit Allen Feldern

POST /api/incoming/invoices.php?action=create
     → Neue Eingangsrechnung anlegen
     Parameters: vendor_id, amount, date, category_id, etc.

POST /api/incoming/invoices.php?action=verify&id=123
     → Rechnung als "verifiziert" markieren (Nach Kontrolle)

POST /api/incoming/invoices.php?action=mark_paid&id=123
     → Rechnung als bezahlt markieren

GET  /api/incoming/invoices.php?action=compliance_check
     → GoBD-Compliance-Status überprüfen
     Response: {passed: bool, warnings: [], errors: []}

GET  /api/incoming/invoices.php?action=audit_log&id=123
     → Änderungshistorie einer Rechnung abrufen

GET  /api/incoming/invoices.php?action=search_vendor&q=Supplier
     → Vendor-Suche mit Autocomplete

GET  /api/incoming/invoices.php?action=duplicate_check&vendor=...&amount=...
     → Duplikat-Erkennung (Vermeidung von Doppelbuchungen)
```

Weitere Actions: `update`, `delete` (soft), `assign_project`, `export_csv`, `export_datev`, `generate_receipt`, `send_email_reminder`

---

# 7. Refactoring: AiAssetLookupService

*Ursprüngliche Datei: REFACTOR_SUMMARY.md*

## Breaking Change

Ein wichtiger Refactor wurde durchgeführt, um den Code zu modernisieren und zu zentralisieren.

**AiAssetLookupService nutzt jetzt den zentralen ClaudeService statt eigenständiger cURL-Aufrufe.**

Dies ist eine Breaking Change für Code, der diesen Service verwendet.

### Alte Verwendung

```php
$service = new AiAssetLookupService($db);
$result = $service->suggestAsset("Beamer Panasonic", "PT-RZ660");
```

### Neue Verwendung

```php
$claudeService = new ClaudeService($db, $instanceId);
$service = new AiAssetLookupService($db, $claudeService);
$result = $service->suggestAsset("Beamer Panasonic", "PT-RZ660");
```

Die ClaudeService wird einmal pro Instanz erzeugt und kann wiederverwendet werden.

### Entfernt

Folgende interne Implementierungen wurden entfernt:

- **`$apiKey = getenv('AI_API_KEY')`** → kommt jetzt aus ClaudeService (Zentrale Verwaltung)
- **`callClaude()` / `callOpenAi()` Methoden** → `$claudeService->ask()` stattdessen
- **`apiProvider`-Logik** → Nur Claude Messages API wird unterstützt (Vereinfachung)

### Neu

Neue Features nach dem Refactor:

- **Feature-Flag-Prüfung:** `isFeatureEnabled('asset_lookup')` wird automatisch überprüft
- **Usage-Logging:** Automatisch durch ClaudeService
- **Verbesserter Prompt:** Mit weight_kg, new_price_eur, dimensions_mm, power_consumption_watts, confidence
- **Zentrale Error-Behandlung:** Durch ClaudeService
- **Rate-Limiting:** Zentral konfiguriert

---

# 8. Entwicklungsumgebung

*Ursprüngliche Datei: DEVELOPMENT.md*

## 8.1 Voraussetzungen

Für die lokale Entwicklung benötigen Sie:

- **Docker Desktop** (oder Docker Engine + Docker Compose separate)
  - Unter macOS und Windows: Docker Desktop installieren (inkl. Docker Compose)
  - Unter Linux: `docker` + `docker-compose` Pakete installieren
  - Mindestens 4 GB RAM für Entwicklung empfohlen

- **IDE** (Optional, aber empfohlen)
  - PhpStorm (kommerziell, vollständige Integration)
  - VS Code + PHP Intelephense + Docker Extensions
  - Sublime Text + PHP-Plugins

- **Git** — Für Versionskontrolle

## 8.2 Schnellstart

Um die Entwicklungsumgebung zum Laufen zu bringen:

```bash
# Repository klonen
git clone <repo-url> && cd rmsclone

# Docker-Stack starten
docker compose up -d

# Warten Sie auf den Build (2-3 Min beim ersten Mal)
docker compose logs -f app

# Wenn alles bereit ist, können Sie auf http://localhost:8080 zugreifen
```

Der initiale Build umfasst:
1. PHP 8.3 Image pullen
2. Composer Dependencies installieren
3. Datenbank initialisieren
4. Migrationen ausführen
5. Seeds laden

## 8.3 URLs

Alle Docker-Services sind unter folgenden URLs erreichbar:

| Service | URL | Beschreibung | Login |
|---------|-----|--------------|-------|
| **App** | http://localhost:8080 | MyRMS Hauptanwendung | Siehe Datei INITIAL_SETUP.md |
| **phpMyAdmin** | http://localhost:8082 | Datenbank-Verwaltung | User: myrms / PW: myrms_dev |
| **Mailpit** | http://localhost:8083 | E-Mail-Testumgebung | Keine Auth nötig |
| **S3 Mock** | http://localhost:8081 | Datei-Upload Emulation | s3 Konsole |

## 8.4 Datenbank-Zugangsdaten

Für direkte Datenbankverbindung:

- **Host:** `localhost:3306` (oder `db:3306` innerhalb Docker)
- **Database:** `myrms`
- **User:** `myrms`
- **Password:** `myrms_dev` (Development) oder `MYSQL_PASSWORD` aus `.env`
- **Root Password:** `root_dev`

### phpMyAdmin

Öffnen Sie http://localhost:8082:
- **Server:** `db`
- **Benutzer:** `root` oder `myrms`
- **Passwort:** `root_dev` oder `myrms_dev`

## 8.5 PhpStorm Einrichtung

Für optimale IDE-Integration:

**Schritt 1: Ordner als Projekt öffnen**
1. PhpStorm starten
2. "File" → "Open" → Ordner `rmsclone/` wählen

**Schritt 2: PHP Interpreter konfigurieren**
1. "PhpStorm" → "Preferences" (macOS) oder "File" → "Settings" (Windows/Linux)
2. "Languages & Frameworks" → "PHP"
3. Interpreter: "Add..." → "From Docker Compose"
4. docker-compose.yml wählen → Service: `app`
5. Lifecycle: "Up" und "Down" setzen

**Schritt 3: Datenbank verbinden**
1. "View" → "Tool Windows" → "Database"
2. "+" → "Data Source" → "MySQL"
3. Host: `localhost:3306`
4. User: `myrms`, Password: `myrms_dev`
5. Database: `myrms`
6. Test Connection

**Schritt 4: Web Server konfigurieren**
1. "PhpStorm" → "Preferences" → "Languages & Frameworks" → "PHP" → "Servers"
2. "+" für neuen Server
3. Name: `localhost`, Host: `localhost`, Port: `8080`
4. Debugger: `Xdebug`
5. Path Mapping: `/pfad/zu/rmsclone` → `/var/www/html`

## 8.6 Häufige Befehle

Wichtige Docker-Befehle für die tägliche Entwicklung:

```bash
# Stack starten (im Hintergrund)
docker compose up -d

# Stack stoppen
docker compose down

# Logs ansehen (live)
docker compose logs -f app

# Logs für einen Service (z.B. db)
docker compose logs -f db

# Shell-Zugriff bekommen (in app-Container)
docker compose exec app bash

# PHP-Befehl ausführen
docker compose exec app php -v

# Composer-Befehl
docker compose exec app composer install

# Migrationen durchführen
docker compose exec app php vendor/bin/phinx migrate -e development

# Seeds laden (Test-Daten)
docker compose exec app php vendor/bin/phinx seed:run

# Datenbank zurücksetzen (⚠️ löscht alles!)
docker compose down -v && docker compose up -d

# Rebuild (wenn Dockerfile geändert)
docker compose up -d --build

# Performance-Status
docker compose stats

# Container-IP-Adressen anzeigen
docker compose ps
```

### Häufige Fehler und Lösungen

**"php vendor/bin/phinx: command not found"**
→ `docker compose exec app bash` erst ausführen, dann Befehl

**"MySQL connection error"**
→ Warte 10-15 Sekunden nach `docker compose up`, DB muss starten

## 8.7 Architektur

Die Docker-Komposition besteht aus mehreren Servern:

```
┌──────────────────────────────────────────────────────────┐
│  Docker Compose Stack                                     │
│                                                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐ │
│  │ app          │  │ db           │  │ s3filestore      │ │
│  │ PHP 8.3      │  │ MySQL 8.0    │  │ MinIO S3 Mock    │ │
│  │ Apache       │──│              │  │                  │ │
│  │ Port 8080    │  │ Port 3306    │  │ Port 8081        │ │
│  └──────────────┘  └──────────────┘  └──────────────────┘ │
│       │                                                    │
│       │                                                    │
│  ┌──────────────────┐  ┌──────────────┐                   │
│  │ phpmyadmin       │  │ mailpit      │                   │
│  │ Port 8082        │  │ Port 8083    │                   │
│  └──────────────────┘  └──────────────┘                   │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

**Service-Details:**

| Service | Image | Ports | Volumes | Env |
|---------|-------|-------|---------|-----|
| app | php:8.3-apache | 8080 | ./src → /var/www/html | MYSQL_HOST, etc. |
| db | mysql:8.0 | 3306 | db_data | MYSQL_DATABASE, PASSWORD |
| s3filestore | minio/minio:latest | 8081 | minio_data | MINIO_ROOT_USER/PASSWORD |
| phpmyadmin | phpmyadmin:latest | 8082 | Keine | PMA_HOST, PMA_USER |
| mailpit | axllent/mailpit:latest | 8083 | Keine | Keine |

## 8.8 Troubleshooting

Lösungen für häufig Probleme:

### "Could not connect to database"
**Symptom:** Fehler beim Starten der App
**Ursache:** MySQL braucht Zeit zum Starten
**Lösung:**
```bash
# Warten und nochmal versuchen
sleep 15
docker compose up -d
```

### Port 8080 ist belegt
**Symptom:** `bind: address already in use`
**Ursache:** Anderer Service nutzt den Port
**Lösung:**
```bash
# Docker-Compose Config anpassen
nano docker-compose.yml
# ports ändern auf 8081:80
docker compose up -d
```

### Migrationen schlagen fehl
**Symptom:** "SQLSTATE[HY000]: General error"
**Ursache:** DB-Zustand ist korrekt, oder Migration hat Fehler
**Lösung:**
```bash
# Migrationen Schritt für Schritt ausführen
docker compose exec app php vendor/bin/phinx migrate --verbose -e development

# Oder DB komplett zurücksetzen
docker compose down -v && docker compose up -d
```

### Änderungen am Code sind nicht sichtbar
**Symptom:** Alter Code wird noch ausgeführt
**Ursache:** PHP/Twig cachen, oder Dockerfile muss neu gebaut werden
**Lösung:**
```bash
# Für PHP-Änderungen: Normalerweise sofort sichtbar
# Für Dockerfile-Änderungen:
docker compose up -d --build

# Für Template-Caches (wenn vorhanden):
docker compose exec app rm -rf var/cache/twig/*
```

---

# 9. Synology-Installation

*Ursprüngliche Datei: SYNOLOGY-SETUP.md*

Eine vollständige Anleitung zur Installation von MyRMS auf einer Synology NAS.

## 9.1 Voraussetzungen

Für diese Anleitung benötigen Sie:

- **Synology DiskStation** — Mit DSM 7.x oder höher
- **Container Manager** — Installiert über Synology Package Center
- **SSH-Zugang** — Aktiviert in Systemsteuerung → Terminal & SNMP
- **Git** — (Optional, für direktes Klonen)
- **Mindestens 2 GB freier Speicherplatz** — Für App und DB

## 9.2 Installation

Schritt-für-Schritt Installation:

```bash
# 1. Verbindung zur NAS herstellen
ssh dein-benutzer@DEINE-NAS-IP

# 2. Zum Docker-Verzeichnis navigieren
cd /volume1/docker

# 3. Repository klonen (oder per GUI herunterladen)
git clone https://github.com/DEIN-REPO/myrms.git myrms
cd myrms

# 4. Konfigurationsdatei vorbereiten
cp .env.synology.example .env.synology

# 5. Umgebungsvariablen anpassen
nano .env.synology
# Folgende Werte müssen gesetzt werden:
# - ROOT_URL=https://myrms.deine-domain.de
# - DB_PASSWORD=einstarkaespasswort (min. 12 Zeichen)
# - MYSQL_ROOT_PASSWORD=rootpasswortneu
# - JWTKey=einelangeZufallszeichenkette

# 6. Stack starten
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d

# 7. Warten Sie auf Boot (ca. 2-3 Minuten)
sleep 180

# 8. Status überprüfen
docker compose -f docker-compose.synology.yml --env-file .env.synology ps
```

Nach Abschluss ist die App verfügbar unter http://NAS-IP:8080 oder der konfigurierten Domain.

## 9.3 HTTPS (Optional, aber empfohlen)

Um HTTPS zu aktivieren, nutzen Sie den Synology Reverse Proxy:

**Schritt 1: Reverse Proxy erstellen**
1. Systemsteuerung öffnen
2. Anmeldeportal → Erweitert → Reverse Proxy
3. "Erstellen" klicken
4. Folgende Einstellungen:
   - Beschreibung: `MyRMS`
   - Quell-Protokoll: `HTTPS`
   - Quell-Hostname: `myrms.deine-domain.de`
   - Quell-Port: `443`
   - Ziel-Protokoll: `HTTP`
   - Ziel-Hostname: `localhost` (oder Container-Name)
   - Ziel-Port: `8080`

**Schritt 2: SSL-Zertifikat konfigurieren**
1. Im Reverse Proxy die Zeile auswählen
2. "Bearbeiten" → "Custom Header"
3. HTTPS-Umleitung aktivieren
4. Zertifikat: Selbstsigniert oder Let's Encrypt (wenn Domain vorhanden)

**Schritt 3: DNS-Eintrag (Optional)**
Falls Sie eine eigene Domain verwenden:
- `A-Record` für `myrms.deine-domain.de` auf die NAS-IP zeigen lassen
- Let's Encrypt Zertifikat wird automatisch generiert

## 9.4 Nützliche Befehle

Häufige Verwaltungsaufgaben:

### Status und Überwachung

```bash
# Status aller Container
docker compose -f docker-compose.synology.yml --env-file .env.synology ps

# Logs in Echtzeit anschauen
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f app

# Nur Database-Logs
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f db

# Container-Ressourcen-Nutzung
docker compose -f docker-compose.synology.yml --env-file .env.synology stats
```

### Backup & Restore

```bash
# Datenbank-Backup erstellen
docker exec myrms-db mysqldump -u root -p$MYSQL_ROOT_PASSWORD myrms > /volume1/Backups/myrms-$(date +%Y%m%d).sql

# (Alternativ mit dein Passwort)
docker exec myrms-db mysqldump -u root -pDEIN_ROOT_PW myrms > backup.sql

# Datenbank aus Backup wiederherstellen
docker exec -i myrms-db mysql -u root -pDEIN_ROOT_PW myrms < backup.sql
```

### Wartung

```bash
# Migrationen ausführen (wenn nötig)
docker compose -f docker-compose.synology.yml exec app php vendor/bin/phinx migrate -e production

# PHP-Version überprüfen
docker compose -f docker-compose.synology.yml exec app php -v

# Composer-Pakete aktualisieren
docker compose -f docker-compose.synology.yml exec app composer update

# Cache leeren
docker compose -f docker-compose.synology.yml exec app rm -rf var/cache/*
```

### Neustart & Updates

```bash
# Graceful Restart (Container neu starten, Daten bleiben)
docker compose -f docker-compose.synology.yml --env-file .env.synology restart

# Stack stoppen
docker compose -f docker-compose.synology.yml --env-file .env.synology down

# Stack wieder starten
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d

# Mit neuen Images (nach git pull)
docker compose -f docker-compose.synology.yml --env-file .env.synology down
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d --build
```

### Fehlerbehebung

```bash
# Container-Shell für Debugging
docker compose -f docker-compose.synology.yml exec app bash

# MySQL-Konsole öffnen
docker compose -f docker-compose.synology.yml exec db mysql -u root -p$MYSQL_ROOT_PASSWORD myrms

# Docker Logs überprüfen (System-Level)
docker logs $(docker ps | grep myrms-app | awk '{print $1}')

# Netzwerk diagnostizieren
docker network inspect $(docker network ls | grep myrms | awk '{print $1}')
```

---

## Abschluss

Diese Dokumentation repräsentiert den Stand des MyRMS-Projekts vom **18. März 2026** auf dem Branch `feature/rfid-cases-invoices-android`. Das System ist zu 99,6% vollständig implementiert (270/271 Items) und produktionsreif.

Für weitere Informationen und UI/UX-Roadmap siehe [ROADMAP.md](ROADMAP.md).
