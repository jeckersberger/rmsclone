# AdamRMS - Analyse und Empfehlungen für den Einsatz als EasyJob-Alternative

## 1. Was ist AdamRMS?

**AdamRMS** ist ein Open-Source **Rental Management System** (Verleih-Management-System),
ursprünglich entwickelt für Theater-, AV- und Broadcast-Unternehmen. Es verwaltet Equipment
(Assets), Projekte (Aufträge/Jobs), Kunden, Crew-Planung und Finanzen.

**Lizenz:** AGPL-3.0 - Änderungen am Quellcode müssen Open Source bleiben!

---

## 2. Tech-Stack

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

---

## 3. Vorhandene Features (IST-Zustand)

### 3.1 Asset-/Equipment-Verwaltung
- Asset-Typen mit Kategorien und Gruppen
- Individuelle Assets mit Tags, Barcodes, QR-Codes
- Tages- und Wochenpreise pro Asset-Typ oder individuell
- Gewichts-Tracking (Masse)
- Wertermittlung (Value)
- Definierbare Felder pro Asset-Typ
- Barcode-Scanning
- Asset-Import (Server-Admin)
- Asset-Export (Excel/CSV via PhpSpreadsheet)

### 3.2 Projektverwaltung (= Aufträge/Jobs)
- Projekte mit Start-/End-/Liefer-/Abhol-Daten
- Projekttypen (konfigurierbar)
- Projektstatus (konfigurierbar mit Farben/Icons)
- Unterprojekte (Sub-Projects)
- Projektmanager-Zuweisung
- Asset-Zuweisungen mit Status-Board (Kanban-artig für Dispatch)
- Rabatte pro Asset-Zuweisung (prozentual)
- Benutzerdefinierte Preise pro Zuweisung
- Projeknotizen
- Datei-Anhänge (S3)

### 3.3 Finanzwesen
- Equipment-Kalkulation (SubTotal, Rabatte, Total)
- Zahlungskategorien: Einnahmen, Sales, Sub-Hires, Personalkosten
- Zahlungseingang-Tracking
- Finanz-Cache (Performance-Optimierung)
- Ledger (Zahlungsübersicht)
- Rechnungs-/Angebots-/Lieferschein-PDF-Generierung (Client-seitig mit pdfmake)
- Währung pro Instance konfigurierbar (ISO-Währungen)
- Money-Library (moneyphp) für präzise Geldberechnung

### 3.4 Dokumenten-System (neu hinzugefügt - DocumentRenderer)
- Serverseitige PDF-Erzeugung via Dompdf
- Twig-basierte Vorlagen für Rechnungen, Angebote, Lieferscheine
- Nummernkreise mit automatischem Jahres-Reset (RE-2026-0001, AN-2026-0001, LS-2026-0001)
- **Kleinunternehmerregelung (KUR) bereits grundlegend implementiert!**
- MwSt-Berechnung (wenn KUR deaktiviert)
- Rabatt-Berechnung
- Deutsche Dateinamen (Rechnung, Angebot, Lieferschein)
- Export-Protokollierung

### 3.5 Kundenverwaltung (Clients)
- Name, Adresse, E-Mail, Telefon, Website, Notizen
- Archivierung
- Offene Posten pro Kunde
- Suche über alle Felder

### 3.6 Weitere Features
- **Standorte/Venues:** Hierarchische Standortverwaltung
- **Crew-Planung:** Crew-Zuweisungen, offene Stellen, Bewerbungen
- **Wartung (Maintenance):** Wartungsaufträge mit Nachrichten-Thread
- **Training/Module:** Schulungsmodule mit Zertifizierungen
- **CMS:** Interne Seiten/Wiki
- **Kalender:** Kalender-Integration mit ICS-Export
- **Hersteller:** Herstellerverwaltung für Assets
- **Benutzer:** Mehrbenutzer-System mit Rollen und Berechtigungen
- **Multi-Instance:** Mehrere Firmen/Abteilungen in einer Installation
- **Signup Codes:** Einladungslinks für neue Benutzer
- **Trusted Domains:** Auto-Join basierend auf E-Mail-Domain
- **Such-Funktion:** Globale Suche
- **Audit Log:** Änderungsprotokoll
- **Stripe Billing:** SaaS-Billing für die Plattform selbst

---

## 4. Datenbank-Struktur (wichtigste Tabellen)

| Tabelle                    | Zweck                                        |
|----------------------------|----------------------------------------------|
| `instances`                | Firmen/Mandanten                             |
| `users` / `userInstances`  | Benutzer und Zuordnung zu Firmen             |
| `projects`                 | Projekte/Aufträge                            |
| `projectsTypes`            | Projekttypen                                 |
| `projectsStatuses`         | Projektstatus-Definitionen (nicht vorhanden) |
| `clients`                  | Kunden                                       |
| `assets`                   | Einzelne Equipment-Gegenstände               |
| `assetTypes`               | Equipment-Typen (Katalog)                    |
| `assetCategories`          | Kategorien                                   |
| `assetCategoriesGroups`    | Kategorie-Gruppen                            |
| `assetsAssignments`        | Zuweisungen Asset -> Projekt                 |
| `assetsAssignmentsStatus`  | Status der Zuweisung (Dispatch-Board)        |
| `payments`                 | Zahlungen/Posten                             |
| `projectsFinanceCache`     | Finanz-Cache pro Projekt                     |
| `locations`                | Standorte/Venues                             |
| `crewAssignments`          | Crew-Zuweisungen                             |
| `maintenanceJobs`          | Wartungsaufträge                             |
| `manufacturers`            | Hersteller                                   |
| `s3files`                  | Datei-Referenzen (S3)                        |
| `auditLog`                 | Änderungsprotokoll                           |

---

## 5. Stärken des Projekts

1. **Solide Grundstruktur** für Verleih-Management vorhanden
2. **Multi-Mandanten-fähig** (Instances) - gut für eventuelle Skalierung
3. **Feingranulares Berechtigungssystem** (Instance-Level Permissions)
4. **Equipment-Verwaltung ist ausgereift** (Assets, Typen, Kategorien, Barcodes, Preise)
5. **Finanzmodul** mit korrekter Geld-Arithmetik (moneyphp-Library)
6. **Docker-Deployment** vereinfacht die Installation
7. **Ansätze für deutsche Anpassung** bereits vorhanden (DocumentRenderer, SequenceService)
8. **PDF-Generierung** funktioniert (sowohl Client- als auch Server-seitig)
9. **API vorhanden** (wenn auch nicht REST-konform)

---

## 6. Schwächen und Verbesserungsbedarf

### 6.1 KRITISCH - Fehlende deutsche rechtliche Anforderungen

#### 6.1.1 Kleinunternehmerregelung (KUR) - teilweise vorhanden
**Status:** Grundlogik in `DocumentRenderer.php` existiert, aber:
- [ ] KUR-Einstellung fehlt in der Business-Settings-UI
- [ ] Pflichthinweis auf Rechnungen fehlt: *"Gemäß § 19 UStG wird keine Umsatzsteuer berechnet"*
- [ ] Umsatzgrenze-Tracking fehlt (22.000 EUR/Jahr seit 2020, ab 2025: 25.000 EUR)
- [ ] Warnung bei Annäherung an die Umsatzgrenze fehlt

#### 6.1.2 GoBD-Konforme Rechnungen (§ 14 UStG)
Pflichtangaben auf Rechnungen, die aktuell FEHLEN:
- [ ] Steuernummer oder USt-IdNr. des Rechnungsstellers
- [ ] Fortlaufende Rechnungsnummer (Nummernkreise existieren, aber nicht GoBD-geprüft)
- [ ] Leistungszeitraum / Lieferdatum
- [ ] Steuersatz und Steuerbetrag (getrennt ausgewiesen)
- [ ] Netto-, MwSt- und Bruttobetrag getrennt
- [ ] Bei KUR: Hinweis auf Steuerbefreiung
- [ ] Aufbewahrungspflicht (10 Jahre) - keine automatische Archivierung
- [ ] Unveränderbarkeit der Rechnung nach Erstellung

#### 6.1.3 XRechnung / ZUGFeRD
- [ ] Für B2B und öffentliche Auftraggeber ist seit 2020 XRechnung Pflicht
- [ ] ZUGFeRD als hybrides Format (PDF/A-3 + XML) ist die pragmatischere Lösung
- [ ] Aktuell keine Unterstützung für strukturierte elektronische Rechnungen

#### 6.1.4 DSGVO (Datenschutz)
- [ ] Kein Löschkonzept für Kundendaten
- [ ] Keine Datenexport-Funktion (Art. 20 DSGVO - Datenportabilität)
- [ ] Keine Cookie-Consent-Verwaltung
- [ ] Auftragsverarbeitungsvertrag-Vorlage fehlt (bei SaaS/Cloud)
- [ ] Audit-Log ist vorhanden, aber nicht DSGVO-konform dokumentiert

### 6.2 WICHTIG - Fehlende Business-Features

#### 6.2.1 Angebotswesen
- [ ] Angebots-Vorlagen mit Textbausteinen
- [ ] Angebots-Gültigkeit (Datum)
- [ ] Angebot -> Auftrag -> Rechnung Workflow
- [ ] Angebots-Tracking (versendet, angenommen, abgelehnt)
- [ ] Angebots-Versionen

#### 6.2.2 Rechnungswesen erweitern
- [ ] Teilrechnungen / Abschlagsrechnungen
- [ ] Stornorechnung / Gutschrift
- [ ] Mahnwesen (Zahlungserinnerung, 1./2./3. Mahnung)
- [ ] Zahlungsbedingungen (Zahlungsziel, Skonto)
- [ ] SEPA-Lastschrift-Mandatsverwaltung
- [ ] Automatischer Rechnungsversand per E-Mail
- [ ] Wiederkehrende Rechnungen (für Dauermieten)

#### 6.2.3 Buchhaltungsanbindung
- [ ] DATEV-Export (für den Steuerberater)
- [ ] SKR03/SKR04 Kontenzuordnung
- [ ] BWA-Auswertung (Betriebswirtschaftliche Auswertung)
- [ ] EÜR-Unterstützung (Einnahmenüberschussrechnung)
- [ ] Export für lexoffice, sevDesk, DATEV oder ähnliche
- [ ] Bankanbindung (FinTS/HBCI) für automatischen Zahlungsabgleich

#### 6.2.4 Kundenverwaltung erweitern
- [ ] Ansprechpartner (mehrere pro Kunde)
- [ ] Kundennummern (fortlaufend)
- [ ] Kundenkategorien / Tags
- [ ] Kundenhistorie (alle Projekte, Angebote, Rechnungen)
- [ ] Kommunikationsprotokoll
- [ ] Zahlungsbedingungen pro Kunde (Standard-Zahlungsziel)
- [ ] Kreditlimit
- [ ] USt-IdNr. des Kunden

### 6.3 EMPFOHLEN - Verbesserung bestehender Features

#### 6.3.1 UI/UX Verbesserungen
- [ ] **Komplett deutsche Oberfläche** - aktuell alles auf Englisch
- [ ] i18n/Lokalisierung einführen (Twig i18n Extension oder gettext)
- [ ] Deutsche Datumsformate (DD.MM.YYYY statt DD/MM/YYYY)
- [ ] Deutsche Zahlenformate (1.234,56 statt 1,234.56)
- [ ] Responsive Design verbessern (AdminLTE 3 ist ok, aber teilweise überladen)
- [ ] Dashboard mit KPIs anpassen (Umsatz, offene Posten, anstehende Projekte)

#### 6.3.2 Code-Qualität
- [ ] **Kein MVC-Framework** - der Code ist prozedural und schwer wartbar
- [ ] SQL-Injection-Risiken bei einigen Stellen (LIKE-Suche in `clients.php`)
- [ ] Globale Variablen werden extensiv genutzt (`global $DBLIB, $AUTH, $bCMS`)
- [ ] Keine Unit-Tests vorhanden
- [ ] Keine API-Versionierung
- [ ] API ist nicht RESTful (POST für alles, kein Standard-Routing)
- [ ] Datenbank nutzt `latin1` statt `utf8mb4` (Problem für deutsche Umlaute!)

#### 6.3.3 Kalender/Planung
- [ ] Verfügbarkeitskalender für Equipment
- [ ] Kollisionserkennung (Doppelbuchungen verhindern)
- [ ] Kapazitätsplanung
- [ ] Drag & Drop im Kalender
- [ ] Google Calendar / Outlook Sync (bidirektional)

#### 6.3.4 Reporting/Auswertungen
- [ ] Umsatzauswertung nach Zeitraum, Kunde, Kategorie
- [ ] Auslastungsberichte für Equipment
- [ ] Offene-Posten-Liste
- [ ] Forecast/Prognose
- [ ] Export nach Excel/PDF

#### 6.3.5 Logistik
- [ ] Packlisten-Generierung
- [ ] Transportplanung
- [ ] Lager-Standortverwaltung
- [ ] Check-in/Check-out-Workflow mit Zustandsprotokoll
- [ ] Versicherungswerte-Tracking

---

## 7. Empfohlene Umsetzungspriorität

### Phase 1: Grundlagen (Muss für legalen Betrieb in DE)
| Priorität | Aufgabe | Aufwand |
|-----------|---------|--------|
| P1 | DB auf `utf8mb4` umstellen | Mittel |
| P1 | Deutsche UI-Texte (Lokalisierung) | Hoch |
| P1 | KUR vollständig implementieren (UI + Pflichthinweis) | Mittel |
| P1 | GoBD-konforme Rechnungen (alle Pflichtangaben) | Hoch |
| P1 | Steuernummer/USt-IdNr. in Business-Settings | Niedrig |
| P1 | Deutsche Datums-/Zahlenformate | Mittel |
| P1 | DSGVO Basis (Löschkonzept, Datenschutzinfo) | Mittel |

### Phase 2: Kerngeschäft (für produktiven Einsatz)
| Priorität | Aufgabe | Aufwand |
|-----------|---------|--------|
| P2 | Angebot -> Auftrag -> Rechnung Workflow | Hoch |
| P2 | Mahnwesen | Mittel |
| P2 | Zahlungsbedingungen & Skonto | Niedrig |
| P2 | Erweiterte Kundenverwaltung | Mittel |
| P2 | Verfügbarkeitskalender + Kollisionserkennung | Hoch |
| P2 | DATEV-Export oder lexoffice-Anbindung | Mittel |
| P2 | EÜR-Unterstützung | Mittel |

### Phase 3: Professionalisierung
| Priorität | Aufgabe | Aufwand |
|-----------|---------|--------|
| P3 | ZUGFeRD/XRechnung | Hoch |
| P3 | Bankanbindung (automatischer Zahlungsabgleich) | Hoch |
| P3 | Umfassende Reports/Dashboards | Mittel |
| P3 | Packlisten und Logistik-Features | Mittel |
| P3 | Wiederkehrende Rechnungen | Niedrig |
| P3 | Check-in/Check-out mit Zustandsprotokoll | Mittel |
| P3 | Unit Tests & Code-Refactoring | Hoch |

---

## 8. Architektur-Empfehlungen

### 8.1 Kurzfristig (mit bestehendem Stack)
1. **i18n-Layer einführen:** Twig `trans` Filter oder gettext für alle UI-Texte
2. **Settings-Tabelle erweitern:** `instances`-Tabelle um Felder für Steuernummer, USt-IdNr., KUR-Flag, Standard-Zahlungsziel, IBAN, BIC, Bankname ergänzen
3. **DocumentRenderer erweitern:** Die vorhandene Klasse ist ein guter Ansatzpunkt - um Pflichtfelder ergänzen
4. **Twig-Templates** für Rechnungen mit allen GoBD-Pflichtangaben erstellen

### 8.2 Mittelfristig
1. **Composer-Packages hinzufügen:**
   - `horstoeko/zugferd` für ZUGFeRD-Unterstützung
   - `setasign/fpdi` für PDF/A-Konformität
2. **Service-Layer einführen:** Geschäftslogik aus den PHP-Dateien in Service-Klassen extrahieren
3. **Echte REST-API** aufbauen (z.B. mit Slim Framework als Middleware)

### 8.3 Langfristig / Alternative
Falls der Umbau zu aufwändig wird, könnte ein Neuaufbau auf Basis moderner Frameworks sinnvoll sein:
- **Laravel** (PHP) oder **Symfony** als Backend
- **Vue.js** oder **React** als Frontend
- Die bestehende Datenstruktur und Geschäftslogik als Vorlage nutzen

---

## 9. Fazit

**AdamRMS ist eine solide Grundlage**, besonders für die Equipment-Verwaltung und Projektzuordnung.
Die Kernfunktionalität für ein Verleih-Unternehmen ist vorhanden.

**Für den Einsatz in einem deutschen Kleinunternehmen fehlen jedoch wesentliche Dinge:**
- Deutsche Lokalisierung (komplett)
- Rechtskonforme Rechnungsstellung (GoBD)
- Vollständige KUR-Implementierung
- DSGVO-Konformität
- Buchhaltungsanbindung (DATEV/EÜR)

Es gibt bereits Ansätze für die deutsche Anpassung (`DocumentRenderer`, `SequenceService`),
die zeigen, dass jemand schon begonnen hat, das System für den deutschen Markt anzupassen.
Diese können als Ausgangspunkt genutzt werden.

**Empfehlung:** Mit den Phase-1-Aufgaben starten (ca. 4-8 Wochen Entwicklungszeit),
dann ist das System für den Grundbetrieb in einem deutschen Kleinunternehmen einsetzbar.
Die weiteren Phasen können inkrementell im laufenden Betrieb umgesetzt werden.

---

*Analyse erstellt am: 28. Februar 2026*
*Basierend auf: AdamRMS (GitHub: adam-rms/adam-rms)*
