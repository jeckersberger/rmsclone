# MyRMS - Lokale Entwicklungsumgebung

## Voraussetzungen

- Docker Desktop (oder Docker + Docker Compose)
- PhpStorm / IntelliJ mit PHP-Plugin (oder jede andere IDE)

## Schnellstart

```bash
# 1. Repository klonen (falls noch nicht geschehen)
git clone <repo-url> && cd rmsclone

# 2. Container starten
docker compose up -d

# 3. Ersten Build abwarten (dauert 2-3 Minuten beim ersten Mal)
docker compose logs -f app
```

Beim ersten Start passiert automatisch:
1. PHP 8.1 + Apache + Extensions werden gebaut
2. `composer install` wird ausgefuehrt
3. Datenbank-Migrationen werden ausgefuehrt
4. Seed-Daten werden eingespielt

## URLs nach dem Start

| Service | URL | Beschreibung |
|---------|-----|--------------|
| **App** | http://localhost:8080 | MyRMS Hauptanwendung |
| **phpMyAdmin** | http://localhost:8082 | Datenbank-Verwaltung |
| **Mailpit** | http://localhost:8083 | E-Mail-Testumgebung (faengt alle E-Mails ab) |
| **S3 Mock** | http://localhost:8081 | Datei-Upload Emulation |

## Datenbank-Zugangsdaten

| Parameter | Wert |
|-----------|------|
| Host | `localhost` (oder `db` aus Container) |
| Port | `3306` |
| Datenbank | `myrms` |
| Benutzer | `myrms` |
| Passwort | `myrms_dev` |
| Root-Passwort | `root_dev` |

## PhpStorm / IntelliJ Einrichtung

### 1. Projekt oeffnen
Oeffne den Ordner `rmsclone/` als Projekt in PhpStorm.

### 2. PHP Interpreter konfigurieren (Docker)
1. **Settings > PHP** > CLI Interpreter > `...` > `+` > "From Docker, Vagrant, ..."
2. **Docker Compose** waehlen
3. Service: `app`
4. PhpStorm erkennt automatisch PHP 8.1

### 3. Datenbank in PhpStorm verbinden
1. **Database** Tab (rechts) > `+` > Data Source > MySQL
2. Host: `localhost`, Port: `3306`
3. User: `myrms`, Password: `myrms_dev`
4. Database: `myrms`
5. **Test Connection** klicken

### 4. Server-Konfiguration (fuer Debugging)
1. **Settings > PHP > Servers** > `+`
2. Name: `MyRMS Docker`
3. Host: `localhost`, Port: `8080`
4. Debugger: Xdebug (optional, siehe unten)
5. **Path mapping**: `/pfad/zu/rmsclone` → `/var/www/html`

### 5. Run Configuration (optional)
Du brauchst keine extra Run Configuration — der Apache laeuft im Docker-Container.
Einfach im Browser http://localhost:8080 aufrufen.

## Haeufige Befehle

```bash
# Container starten
docker compose up -d

# Container stoppen
docker compose down

# Logs anschauen
docker compose logs -f app

# In den Container springen (fuer CLI-Befehle)
docker compose exec app bash

# Migrationen manuell ausfuehren
docker compose exec app php vendor/bin/phinx migrate -e development

# Seeds ausfuehren
docker compose exec app php vendor/bin/phinx seed:run

# Composer install (nach composer.json Aenderung)
docker compose exec app composer install

# Datenbank komplett zuruecksetzen
docker compose down -v
docker compose up -d

# Container neu bauen (nach Dockerfile-Aenderung)
docker compose up -d --build
```

## Architektur der lokalen Umgebung

```
┌─────────────────────────────────────────────────────┐
│  Docker Compose                                      │
│                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │ app      │  │ db       │  │ s3filestore      │  │
│  │ PHP 8.1  │──│ MySQL 8  │  │ S3 Mock          │  │
│  │ Apache   │  │          │  │                    │  │
│  │ :8080    │  │ :3306    │  │ :8081             │  │
│  └──────────┘  └──────────┘  └──────────────────┘  │
│       │                                              │
│  ┌──────────┐  ┌──────────┐                         │
│  │phpmyadmin│  │ mailpit  │                         │
│  │ :8082    │  │ :8083    │                         │
│  └──────────┘  └──────────┘                         │
└─────────────────────────────────────────────────────┘
```

## Troubleshooting

### "Could not connect to database"
- Warte 10-15 Sekunden nach `docker compose up` — MySQL braucht einen Moment
- Pruefe: `docker compose ps` — alle Container muessen "running" sein
- Pruefe Logs: `docker compose logs db`

### Port bereits belegt
- Falls Port 8080/3306/etc. schon belegt ist:
  Aendere den Port in `docker-compose.yml` (linke Seite des `:`)
  z.B. `"8090:80"` statt `"8080:80"`

### Migrationen schlagen fehl
- Beim ersten Start ist das normal (Tabellen existieren noch nicht)
- Manuell ausfuehren: `docker compose exec app php vendor/bin/phinx migrate -e development`

### Composer-Fehler
- `docker compose exec app composer install --no-interaction`

### Aenderungen werden nicht sichtbar
- PHP-Dateien: Sofort sichtbar (Volume-Mount)
- Twig-Templates: Sofort sichtbar (dev_mode = kein Cache)
- Composer/Dockerfile: `docker compose up -d --build`
