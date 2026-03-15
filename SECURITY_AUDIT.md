# Security Audit & Code Review – Adam RMS (MyRMS Clone)

**Datum:** 2026-03-15
**Durchgeführt nach:** CodingAgent Framework (Senior Software Engineer Review)
**Umfang:** Vollständige Codebase-Analyse (690 PHP-Dateien, 89 Migrationen, Docker/Deployment)
**Gesamtbewertung:** 7/10

---

## Inhaltsverzeichnis

1. [Projektübersicht](#1-projektübersicht)
2. [Architektur-Bewertung](#2-architektur-bewertung)
3. [Sicherheits-Audit (OWASP Top 10)](#3-sicherheits-audit-owasp-top-10)
4. [Datenbank & Datenmodell](#4-datenbank--datenmodell)
5. [Docker & Deployment](#5-docker--deployment)
6. [Tests & Code-Qualität](#6-tests--code-qualität)
7. [Maßnahmenkatalog](#7-maßnahmenkatalog)

---

## 1. Projektübersicht

Adam RMS ist ein **Mietverwaltungssystem** für Theater-, AV- und Broadcast-Equipment. Die angepasste Version (MyRMS) erweitert das Original um deutsche Compliance (GoBD, DSGVO, DATEV, ZUGFeRD, SEPA).

| Komponente | Technologie |
|-----------|-----------|
| Backend | PHP 8.3 (prozedural, kein Framework) |
| Templating | Twig 3.x |
| Datenbank | MySQL 8.0 |
| Frontend | AdminLTE (Bootstrap 4), jQuery |
| PDF | Dompdf (Server), pdfmake (Client) |
| Dateispeicher | AWS S3 oder lokal |
| Auth | JWT + HybridAuth (Social Login) |
| Migrationen | Phinx (89 Migrationsdateien) |
| Deployment | Docker + Docker Compose |

**Umfang:** 434 API-Endpoints, 105 Service-Klassen, 30+ Business-Module, Multi-Tenant-fähig

---

## 2. Architektur-Bewertung

### Stärken
- **Modulare Service-Schicht**: 105 wiederverwendbare Service-Klassen mit klarer Trennung von HTTP-Logik
- **Multi-Tenancy**: Vollständige Instanz-Isolation mit rollenbasierter Zugriffskontrolle
- **Deutsche Compliance**: GoBD, DSGVO, DATEV, ZUGFeRD, SEPA, XRechnung integriert
- **API-Schicht**: Zentraler API-Handler mit CORS, CSRF, Input-Sanitization
- **Feature-getriebene Struktur**: Logische Gruppierung nach Geschäftsdomänen

### Schwächen
- **Kein Framework**: Prozeduraler Code ohne standardisiertes MVC — erhöht Wartungsaufwand
- **Globale Variablen**: `$DBLIB`, `$AUTH`, `$CONFIG` als Globals statt Dependency Injection
- **File-basiertes Routing**: URL-Pfade mappen direkt auf PHP-Dateien — keine zentrale Route-Definition
- **Frontend veraltet**: jQuery + AdminLTE statt modernem Frontend-Framework

---

## 3. Sicherheits-Audit (OWASP Top 10)

### KRITISCH

#### 3.1 Schwaches Passwort-Hashing
**Schweregrad:** KRITISCH
**Betroffene Dateien:**
- `src/api/login/signup.php` (Zeile 20)
- `src/api/login/login.php` (Zeile 24)
- `src/api/account/changePass.php` (Zeile 16)

**Problem:** Eigenes Salt-basiertes Hashing mit `hash()` statt `password_hash()`:
```php
hash($data['users_hash'], $data['users_salty1'] . $_POST['password'] . $data['users_salty2']);
```

**Risiko:** Custom-Hashing-Schemata sind anfällig für moderne Angriffe. Kein Work-Factor, kein Timing-safe Vergleich.

**Empfehlung:** Migration zu `password_hash(PASSWORD_ARGON2ID)` / `password_verify()`

---

#### 3.2 MD5 für sicherheitsrelevante Operationen
**Schweregrad:** KRITISCH
**Betroffene Dateien:**
- `src/common/headSecure.php` (Zeile 34)
- `src/api/apiHeadSecure.php` (Zeile 26)
- `src/common/libs/Auth/main.php`

**Problem:** MD5 ist kryptographisch gebrochen. Wird für E-Mail-Hashes und Verifizierungscodes verwendet.

**Empfehlung:** `hash('sha256', ...)` oder `bin2hex(random_bytes(32))` für Tokens

---

#### 3.3 Hardcoded Secrets in Scripts
**Schweregrad:** KRITISCH
**Betroffene Dateien:**
- `install.sh` (Zeile 31-32) — Cloudflare Tunnel Token im Klartext
- `install-nas.sh` (Zeile 15-16) — Gleicher Token
- `docker-compose.synology-cloudflare.yml` (Zeile 34-35) — Hardcoded Domain

**Risiko:** Credentials sichtbar in Git-History und Container-Umgebungsvariablen

**Empfehlung:**
- Sofort Token rotieren
- Alle Secrets aus Quellcode entfernen
- Docker Secrets oder externen Secrets-Manager verwenden

---

### HOCH

#### 3.4 SQL-Injection in LIKE-Klauseln
**Schweregrad:** HOCH
**Betroffene Dateien:**
- `src/api/ai/search.php` (Zeile 75) — Unvalidierter AI-Output in SQL
- `src/api/groups/search.php` (Zeile 9) — `$DBLIB->escape()` statt Parameterisierung

**Empfehlung:** Alle LIKE-Klauseln auf parametrisierte Queries umstellen

---

#### 3.5 DB-Passwörter in Health-Checks
**Schweregrad:** HOCH
**Betroffene Dateien:**
- `docker-compose.prod.yml` (Zeile 114)

**Problem:** MySQL-Root-Passwort im Klartext in `mysqladmin ping`-Kommando — sichtbar in Prozesslisten

**Empfehlung:** TCP-basierte Health-Checks oder dedizierter Health-Check-User

---

#### 3.6 Datenbank-Port exponiert
**Schweregrad:** HOCH
**Betroffene Dateien:**
- `docker-compose.synology.yml` (Zeile 89-90) — Port 3306 extern erreichbar

**Empfehlung:** Externe Port-Mappings entfernen, nur interne Docker-Netzwerke verwenden

---

### MITTEL

#### 3.7 Fehlende Security-Headers
**Betroffene Datei:** `src/api/apiHead.php` (Zeile 23-26)

Vorhanden: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
**Fehlend:** `Content-Security-Policy`, `Strict-Transport-Security`, `Permissions-Policy`

---

#### 3.8 OAuth State-Parameter nicht validiert
**Betroffene Datei:** `src/login/oauth/google.php` (Zeile 36-78)

Keine explizite CSRF-Token-Validierung für OAuth-Callback

---

#### 3.9 Schwache DSGVO-Export-Passwort-Validierung
**Betroffene Datei:** `src/api/dsgvo/export.php` (Zeile 25-27)

Nur Längenpüfung (8 Zeichen), keine Komplexitätsanforderungen

---

#### 3.10 Temporäre Dateien nicht robust bereinigt
**Betroffene Datei:** `src/api/dsgvo/export.php` (Zeile 30-57)

`sys_get_temp_dir()` auf Multi-Tenant-Systemen problematisch, kein `try/catch`

---

### NIEDRIG

- **Twig Debug in Dev-Mode** — Sicherstellen, dass `DEV_MODE` in Produktion deaktiviert ist
- **JWT HS256** — Asymmetrisches Verfahren (RS256) wäre sicherer
- **Legacy `sanitizeStringMYSQL()`** — Schrittweise auf Prepared Statements migrieren

### Positive Befunde
- CSRF-Schutz via `CsrfService` implementiert
- Prepared Statements über MysqliDb-Library als Standard
- ClamAV-Virenscanning für Uploads verfügbar
- Rate-Limiting für Login-Versuche vorhanden
- TOTP (Zwei-Faktor-Authentifizierung) implementiert
- Login-Audit-Logging mit IP-Adressen
- Input-Sanitization auf API-Ebene (`htmlspecialchars`)

---

## 4. Datenbank & Datenmodell

### Stärken
- **211 Foreign-Key-Constraints** mit CASCADE-Verhalten — solide referenzielle Integrität
- **288+ Indizes** für Query-Performance
- **Collation-Fix**: Migration von `latin1_swedish_ci` → `utf8mb4_0900_ai_ci` korrekt durchgeführt
- **DSGVO-Log-Tabelle**: Trackt Exports, Löschanfragen, Anonymisierungen
- **Finanz-Daten**: `DECIMAL(12,2)` für Beträge, SHA-256 für Transaktionsdeduplizierung
- **Sicherheits-Features**: TOTP-Felder, Login-Log, Rate-Limiting-Tabellen

### Schwächen
- **Basis-Migration mit latin1**: Wurde nachträglich korrigiert, aber zeigt fehlende Initialplanung
- **Default-User-Seeder**: Hardcoded Test-Credentials in `db/seeds/DefaultUserSeeder.php`

---

## 5. Docker & Deployment

### Stärken
- Non-Root-User (`www-data`) in Production-Dockerfiles
- Multi-Stage-Build im Haupt-Dockerfile
- Gute Nginx-SSL-Konfiguration (TLS 1.2/1.3, starke Cipher, HSTS)
- Resource-Limits in Production/Staging/Synology Compose-Dateien
- Backup/Restore-Scripts vorhanden

### Schwächen

| Problem | Schweregrad |
|---------|-------------|
| Hardcoded Secrets in install-Scripts | KRITISCH |
| DB-Passwort in Health-Checks sichtbar | HOCH |
| Kein Secrets-Management (Docker Secrets, Vault) | HOCH |
| Keine automatisierte Backup-Planung | HOCH |
| Base-Image-Versionen nicht gepinnt (`latest`) | MITTEL |
| Volume-Mounts in Production (statt immutable Images) | MITTEL |
| Kein zentrales Logging | MITTEL |
| Dev-Dockerfile nutzt PHP 8.1 (veraltet) | NIEDRIG |

---

## 6. Tests & Code-Qualität

### Test-Abdeckung: UNZUREICHEND

| Metrik | Wert |
|--------|------|
| Getestete Services | 6 von 105 (**6%**) |
| Testmethoden | 76 |
| Testcode | 1.544 Zeilen |
| Integrationstests | **Keine** |
| Auth-Tests | **Keine** |
| API-Tests | **Keine** |

**Getestete Services:**
- SequenceService, ReportExportService, DunningService
- ProfitCalculationService, UtilizationReportService, DocumentLifecycleService

**Kritisch fehlende Tests:**
- Authentifizierung / Autorisierung
- Payment-Processing (Stripe)
- Bank-Import (komplexe Business-Logik)
- AI-Features (ClaudeService, ChatService)
- File-Upload-Validierung
- DSGVO-Compliance

### Code-Qualität-Tools

| Tool | Konfiguration | Bewertung |
|------|--------------|-----------|
| PHPStan | Level 5, nur `src/services/` | Zu niedrig, zu eingeschränkt |
| PHP-CS-Fixer | PSR-12, `src/services/`, `src/api/`, `src/business/`, `src/cron/` | Gut konfiguriert |
| PHPUnit | Unit-Tests only, keine DB-Tests | Unzureichend |

---

## 7. Maßnahmenkatalog

### Sofort (diese Woche)

| # | Maßnahme | Aufwand |
|---|----------|---------|
| 1 | Cloudflare Tunnel Token rotieren und aus Code entfernen | 1h |
| 2 | Alle hardcoded Secrets durch Umgebungsvariablen ersetzen | 2h |
| 3 | `password_hash(PASSWORD_ARGON2ID)` implementieren + Migration bestehender Hashes | 4h |
| 4 | MD5 durch SHA-256 / `random_bytes()` ersetzen | 2h |
| 5 | DB-Health-Checks ohne Passwort im Klartext | 1h |

### Kurzfristig (2 Wochen)

| # | Maßnahme | Aufwand |
|---|----------|---------|
| 6 | Security-Headers (CSP, HSTS, Permissions-Policy) hinzufügen | 2h |
| 7 | OAuth State-Parameter-Validierung | 2h |
| 8 | Externe DB-Port-Mappings entfernen | 1h |
| 9 | SQL-Injection in LIKE-Klauseln fixen | 3h |
| 10 | File-Upload auf Whitelist-Ansatz umstellen | 2h |

### Mittelfristig (4 Wochen)

| # | Maßnahme | Aufwand |
|---|----------|---------|
| 11 | Test-Abdeckung auf 50%+ erhöhen (Auth, Payment, DSGVO) | 20h |
| 12 | Integrationstests mit echter Datenbank einführen | 8h |
| 13 | PHPStan auf Level 7+ und alle Verzeichnisse erweitern | 4h |
| 14 | Docker Secrets oder externen Secrets-Manager implementieren | 4h |
| 15 | Automatisierte Backup-Planung mit Verifikation | 4h |
| 16 | Base-Image-Versionen pinnen | 1h |

### Langfristig (laufend)

| # | Maßnahme |
|---|----------|
| 17 | Twig-Templates auf XSS auditieren |
| 18 | JWT von HS256 auf RS256 migrieren |
| 19 | Legacy `sanitizeStringMYSQL()` eliminieren |
| 20 | Volume-Mounts in Production durch immutable Images ersetzen |
| 21 | Zentrales Logging einführen (ELK/Grafana) |
| 22 | Dependency-Updates automatisieren (Renovate/Dependabot) |

---

## Zusammenfassung

**Adam RMS (MyRMS)** ist ein **funktional umfangreiches, produktionsreifes System** mit starker deutscher Compliance-Abdeckung. Die größten Risiken liegen in:

1. **Kryptographie**: Veraltetes Passwort-Hashing und MD5-Nutzung
2. **Secrets-Management**: Hardcoded Credentials in Git
3. **Test-Abdeckung**: Nur 6% der Services getestet
4. **Docker-Security**: Exponierte Ports und Passwörter in Health-Checks

Die positiven Aspekte (CSRF-Schutz, Prepared Statements, Rate-Limiting, TOTP, ClamAV) zeigen, dass Sicherheitsbewusstsein vorhanden ist. Die identifizierten Lücken sind mit vertretbarem Aufwand schließbar.

---

*Erstellt mit dem CodingAgent Framework — Senior Software Engineer Security Review*
