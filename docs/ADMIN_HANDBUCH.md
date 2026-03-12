# AdamRMS - Admin-Handbuch

## Installation & Konfiguration

### Voraussetzungen
- PHP 8.1+
- MySQL 8.0 / MariaDB 10.6+
- Composer
- Node.js (fuer Frontend-Build)
- Optional: ClamAV (Virenscanner), RFID-Gateway

### Docker-Installation (empfohlen)

```bash
# 1. Repository klonen
git clone <repository-url> adamrms
cd adamrms

# 2. Umgebungsvariablen konfigurieren
cp .env.prod.example .env.prod
nano .env.prod

# 3. Container starten
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d

# 4. Datenbank-Migrationen ausfuehren
docker compose -f docker-compose.prod.yml exec app php vendor/bin/phinx migrate
```

### Umgebungsvariablen

| Variable | Beschreibung | Beispiel |
|----------|-------------|---------|
| `DB_HOSTNAME` | Datenbank-Host | `db` |
| `DB_DATABASE` | Datenbankname | `adamrms` |
| `DB_USERNAME` | DB-Benutzer | `adamrms_app` |
| `DB_PASSWORD` | DB-Passwort | (sicher generieren) |
| `ROOT_URL` | Basis-URL der Anwendung | `https://rms.example.de` |
| `CORS_ALLOWED_ORIGIN` | Erlaubte CORS-Origin | `https://rms.example.de` |
| `ENCRYPTION_KEY` | AES-256 Schluessel (64 Hex) | (generieren: `openssl rand -hex 32`) |
| `AI_API_KEY` | Claude/OpenAI API-Key | `sk-...` |
| `AI_PROVIDER` | KI-Anbieter | `claude` oder `openai` |

### Datenbank-Sicherheit

Siehe `docs/DB_SECURITY.md` fuer das empfohlene 3-Benutzer-Modell (App/Migrate/Backup).

### Cronjobs

```crontab
# Taegliche Aufgaben
0 8 * * * php /app/src/cron/project-reminders.php
0 6 * * * php /app/src/cron/return-reminders.php
0 7 * * * php /app/src/cron/recurring-projects.php

# Stuendliche Aufgaben
0 * * * * php /app/src/cron/auto-invoice-email.php
15 * * * * php /app/src/cron/cleanup-temp-exports.php

# Woechentliche Aufgaben
0 2 * * 0 php /app/src/cron/database-backup.php
0 3 1 * * php /app/src/cron/kur-threshold-check.php
0 4 * * 1 php /app/src/cron/gobd-archive-check.php
0 5 1 * * php /app/src/cron/quote-expiry-check.php
```

### Health-Check

Endpoint: `GET /api/health.php`

Prueft: Datenbank, Disk-Space, PHP-Version, Extensions.
Ideal fuer Docker HEALTHCHECK und Monitoring-Tools (Uptime Robot, Prometheus).

---

## Features

### Sicherheit
- **2FA (TOTP)**: Benutzer koennen TOTP-basierte 2-Faktor-Authentifizierung aktivieren
- **Rate Limiting**: Automatisch fuer Login und API-Endpunkte
- **CSRF-Schutz**: Token-basiert fuer alle POST-Requests
- **Verschluesselung**: AES-256-GCM fuer sensible Daten (IBAN, Steuernummer)
- **Passwort-Policy**: Min. 10 Zeichen, Komplexitaetsregeln, Breach-Check

### Rechnungswesen
- **Dokumenten-Workflow**: Angebot -> Auftragsbestaetigung -> Rechnung -> Mahnung
- **ZUGFeRD/XRechnung**: Automatische Generierung fuer B2G und B2B
- **DATEV-Export**: Kompatibel mit SKR03/SKR04
- **Mahnwesen**: 3-stufige Eskalation mit automatischem Versand
- **SEPA**: Lastschrift-Mandatsverwaltung
- **Kassenbuch**: Fuer Bargeschaefte
- **UStVA**: Umsatzsteuervoranmeldung vorbereiten

### Equipment
- **Lifecycle**: Bestellung -> Eingang -> Inbetriebnahme -> Betrieb -> Ausmusterung
- **AfA-Rechner**: Linear/degressiv nach deutschem Steuerrecht
- **RFID**: Tag-Zuordnung, Gateway-Anbindung, automatische Inventur
- **Konflikterkennung**: Warnung bei Doppelbuchungen
- **Wartungsplanung**: Intervall-basierte Wartungserinnerungen

### Kommunikation
- **E-Mail-Vorlagen**: Konfigurierbar pro Template-Typ (Twig-Platzhalter)
- **Automatische Benachrichtigungen**: Projektbestaetigung, Erinnerungen, Feedback
- **Kunden-Portal**: Self-Service mit Token-basiertem Zugang
- **Digitale Unterschriften**: Base64-PNG auf Lieferscheinen/Angeboten

### Integrationen
- **Webhooks**: Automatische Benachrichtigung externer Systeme bei Events
- **GiroCode**: EPC-QR-Codes auf Rechnungen fuer Banking-Apps
- **Kalender-Sync**: ICS-Export mit Token-Feed fuer Google/Outlook
- **KI-Asset-Lookup**: Produktdaten automatisch per Claude/OpenAI suchen

### Mobile
- **PWA**: Progressive Web App mit Offline-Unterstuetzung
- **Dark Mode**: Umschaltbar pro Benutzer
- **Schnellerfassung**: Projekt in unter 30 Sekunden anlegen

---

## Backup & Wiederherstellung

### Automatische Backups
`src/cron/database-backup.php` erstellt taeglich komprimierte MySQL-Dumps.

### Manuelle Wiederherstellung
```bash
gunzip < /data/backups/adamrms_YYYYMMDD_HHMMSS.sql.gz | mysql -u root adamrms
```

### Verschluesselung
Sensible Daten werden mit AES-256-GCM verschluesselt. Der `ENCRYPTION_KEY` muss sicher aufbewahrt werden - ohne ihn sind verschluesselte Daten verloren.

---

## Fehlerbehebung

### Haeufige Probleme

| Problem | Loesung |
|---------|---------|
| Login fehlgeschlagen | Account-Lockout pruefen (15 Fehlversuche = 30 Min Sperre) |
| 2FA-Code ungueltig | Server-Uhrzeit pruefen (NTP), Backup-Codes verwenden |
| E-Mails kommen nicht an | SMTP-Konfiguration pruefen, Log in `/var/log/` |
| Upload fehlgeschlagen | MIME-Type und Dateigr. pruefen, ClamAV-Status |
| Health-Check 503 | Datenbank-Verbindung oder Disk-Space pruefen |
