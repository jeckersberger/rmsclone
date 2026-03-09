# RMS Clone - Installation auf Synology DiskStation

## Voraussetzungen

- Synology DiskStation mit **DSM 7.x**
- **Container Manager** (ehemals "Docker") aus dem Paketzentrum installiert
- SSH-Zugang aktiviert (Systemsteuerung > Terminal & SNMP > SSH aktivieren)
- Die **Originalversion** läuft bereits und belegt ihre Ports (80/443, 3306)

## Schritt 1: Repository auf die Synology kopieren

Per SSH auf die Synology verbinden:

```bash
ssh dein-user@DEINE-NAS-IP
```

In einen geeigneten Ordner wechseln und das Repo klonen:

```bash
cd /volume1/docker
git clone https://github.com/DEIN-REPO/rmsclone.git rmsclone
cd rmsclone
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
| `ROOT_URL` | `http://DEINE-NAS-IP:8090` oder deine Domain |
| `DB_PASSWORD` | Sicheres Passwort |
| `MYSQL_ROOT_PASSWORD` | Anderes sicheres Passwort |
| `CONFIG_AUTH_JWTKey` | Generieren mit `openssl rand -hex 32` |

**Port-Konflikte vermeiden:**

Die Standardports sind so gewählt, dass sie nicht mit der Originalversion kollidieren:

| Service | Original-RMS | RMS Clone |
|---------|-------------|-----------|
| Web-App | 80 / 443 | **8090** |
| MySQL | 3306 | **3307** |

Falls du die Ports der Originalversion nicht kennst, prüfe mit:

```bash
docker ps --format "table {{.Names}}\t{{.Ports}}"
```

## Schritt 3: Container starten

```bash
cd /volume1/docker/rmsclone
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d
```

Der erste Start dauert etwas, weil:
1. Das Docker-Image gebaut wird
2. Die Datenbank initialisiert wird
3. Die Migrationen laufen

Fortschritt prüfen:

```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f
```

## Schritt 4: Zugriff testen

Öffne im Browser:

```
http://DEINE-NAS-IP:8090
```

## Schritt 5 (Optional): HTTPS mit Synology Reverse Proxy

Statt Nginx + Let's Encrypt selbst zu managen, nutze den eingebauten Synology Reverse Proxy:

1. **Systemsteuerung** > **Anmeldeportal** > **Erweitert** > **Reverse Proxy**
2. **Erstellen** klicken:
   - **Beschreibung:** RMS Clone
   - **Quelle:**
     - Protokoll: HTTPS
     - Hostname: `rmsclone.deine-domain.de`
     - Port: 443
   - **Ziel:**
     - Protokoll: HTTP
     - Hostname: `localhost`
     - Port: 8090
3. SSL-Zertifikat über **Systemsteuerung > Sicherheit > Zertifikat** einrichten (Synology kann automatisch Let's Encrypt Zertifikate beziehen)

Danach `ROOT_URL` in `.env.synology` auf `https://rmsclone.deine-domain.de` ändern und Container neu starten:

```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d
```

## Nützliche Befehle

```bash
# Status prüfen
docker compose -f docker-compose.synology.yml --env-file .env.synology ps

# Logs anzeigen
docker compose -f docker-compose.synology.yml --env-file .env.synology logs -f app

# Stoppen
docker compose -f docker-compose.synology.yml --env-file .env.synology down

# Neu bauen (nach Code-Änderungen)
docker compose -f docker-compose.synology.yml --env-file .env.synology up -d --build

# Datenbank-Backup
docker exec rmsclone-db mysqldump -u root -pDEIN_ROOT_PW rmsclone > backup.sql

# Datenbank wiederherstellen
docker exec -i rmsclone-db mysql -u root -pDEIN_ROOT_PW rmsclone < backup.sql
```

## Troubleshooting

### "Port already in use"
Ein anderer Container nutzt Port 8090 oder 3307. Ändere `APP_PORT` oder `DB_EXTERNAL_PORT` in `.env.synology`.

### Container startet nicht
```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology logs app
```

### Datenbank-Verbindung fehlgeschlagen
Warte bis der Health-Check der DB grün ist:
```bash
docker compose -f docker-compose.synology.yml --env-file .env.synology ps
```
Die DB braucht beim ersten Start ca. 30-60 Sekunden.

### Migrationen schlagen fehl
```bash
docker exec rmsclone-app php vendor/bin/phinx migrate -e production
```
