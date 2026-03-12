# MyRMS - Installation auf Synology DiskStation

## Voraussetzungen

- Synology DiskStation mit **DSM 7.x**
- **Container Manager** (ehemals "Docker") aus dem Paketzentrum installiert
- SSH-Zugang aktiviert (Systemsteuerung > Terminal & SNMP > SSH aktivieren)

## Schritt 1: Repository auf die Synology kopieren

Per SSH auf die Synology verbinden:

```bash
ssh dein-user@DEINE-NAS-IP
```

In einen geeigneten Ordner wechseln und das Repo klonen:

```bash
cd /volume1/docker
git clone https://github.com/DEIN-REPO/myrms.git myrms
cd myrms
```

Alternativ kannst du das Repo auch per File Station hochladen.

## Schritt 2: Konfiguration erstellen

```bash
cp .env.synology.example .env.synology
nano .env.synology
```

**Wichtige Werte die du anpassen MUSST:**

| Variable | Was eintragen |
|----------|--------------|
| `ROOT_URL` | `http://DEINE-NAS-IP` oder deine Domain |
| `DB_PASSWORD` | Sicheres Passwort |
| `MYSQL_ROOT_PASSWORD` | Anderes sicheres Passwort |
| `CONFIG_AUTH_JWTKey` | Generieren mit `openssl rand -hex 32` |

## Schritt 3: Alte Version stoppen (falls vorhanden)

Falls eine alte Adam RMS Version laeuft, stoppe diese zuerst:

```bash
# Alte Container finden und stoppen
docker ps --format "table {{.Names}}\t{{.Ports}}"
# Alte Container stoppen (Name anpassen)
docker stop <alter-container-name>
docker rm <alter-container-name>
```

## Schritt 4: Container starten

```bash
cd /volume1/docker/myrms
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d
```

Der erste Start dauert etwas, weil:
1. Das Docker-Image gebaut wird
2. Die Datenbank initialisiert wird
3. Die Migrationen laufen

Fortschritt pruefen:

```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f
```

## Schritt 5: Zugriff testen

Oeffne im Browser:

```
http://DEINE-NAS-IP
```

## Schritt 6 (Optional): HTTPS mit Synology Reverse Proxy

Nutze den eingebauten Synology Reverse Proxy:

1. **Systemsteuerung** > **Anmeldeportal** > **Erweitert** > **Reverse Proxy**
2. **Erstellen** klicken:
   - **Beschreibung:** MyRMS
   - **Quelle:**
     - Protokoll: HTTPS
     - Hostname: `myrms.deine-domain.de`
     - Port: 443
   - **Ziel:**
     - Protokoll: HTTP
     - Hostname: `localhost`
     - Port: 80
3. SSL-Zertifikat ueber **Systemsteuerung > Sicherheit > Zertifikat** einrichten

Danach `ROOT_URL` in `.env.synology` auf `https://myrms.deine-domain.de` aendern und Container neu starten:

```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d
```

## Nuetzliche Befehle

```bash
# Status pruefen
docker compose -f docker-compose.synology.yml --env-file .env.synology ps

# Logs anzeigen
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f app

# Stoppen
docker compose -f docker-compose.synology.yml --env-file .env.synology down

# Neu bauen (nach Code-Aenderungen)
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d --build

# Datenbank-Backup
docker exec myrms-db mysqldump -u root -pDEIN_ROOT_PW myrms > backup.sql

# Datenbank wiederherstellen
docker exec -i myrms-db mysql -u root -pDEIN_ROOT_PW myrms < backup.sql
```

## Troubleshooting

### "Port already in use"
Ein anderer Container oder Dienst nutzt Port 80 oder 3306. Stoppe die alte Version zuerst, oder aendere `APP_PORT` / `DB_EXTERNAL_PORT` in `.env.synology`.

### Container startet nicht
```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology logs app
```

### Datenbank-Verbindung fehlgeschlagen
Warte bis der Health-Check der DB gruen ist:
```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology ps
```
Die DB braucht beim ersten Start ca. 30-60 Sekunden.

### Migrationen schlagen fehl
```bash
docker exec myrms-app php vendor/bin/phinx migrate -e production
```
