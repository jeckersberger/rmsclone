# ==============================================================================
# MyRMS - Deploy auf NAS via SSH (PowerShell / Windows)
# ==============================================================================
# Dieses Script fuehrst du auf deinem LOKALEN Windows-Rechner aus.
# Es verbindet sich per SSH zum NAS und richtet alles automatisch ein.
#
# Verwendung (PowerShell als Admin oeffnen):
#   cd C:\Users\jecke\rmsclone
#   .\deploy-to-nas.ps1
#
# Falls "Execution Policy" Fehler:
#   Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
# ==============================================================================

$ErrorActionPreference = "Stop"

function Write-Color($color, $text) {
    Write-Host $text -ForegroundColor $color
}

# ==============================================================================
# Banner
# ==============================================================================
Write-Host ""
Write-Color Cyan "================================================================"
Write-Color Cyan "  MyRMS - Deploy auf NAS via SSH"
Write-Color Cyan "  Ziel: https://dash.je-soundulight"
Write-Color Cyan "================================================================"
Write-Host ""

$DOMAIN = "dash.je-soundulight"
$SCRIPT_DIR = $PSScriptRoot
$CREDENTIALS_FILE = Join-Path $SCRIPT_DIR "myrms-zugangsdaten.txt"

# ==========================================================================
# 1. Verbindungsdaten abfragen
# ==========================================================================
Write-Color Yellow "[1/7] Verbindungsdaten"
$NAS_HOST = Read-Host "  NAS IP-Adresse oder Hostname"
$NAS_USER = Read-Host "  SSH-User (z.B. admin oder root)"
$NAS_PORT_INPUT = Read-Host "  SSH-Port [22]"
$NAS_PORT = if ($NAS_PORT_INPUT) { $NAS_PORT_INPUT } else { "22" }
$NAS_PATH_INPUT = Read-Host "  Installationspfad auf dem NAS [/volume1/docker/rmsclone]"
$NAS_PATH = if ($NAS_PATH_INPUT) { $NAS_PATH_INPUT } else { "/volume1/docker/rmsclone" }

Write-Host ""
Write-Color Yellow "Cloudflare Tunnel Token"
Write-Host "  (https://one.dash.cloudflare.com -> Zero Trust -> Networks -> Tunnels)"
Write-Host "  (Tunnel anklicken -> Configure -> Install connector -> Token kopieren)"
$TUNNEL_TOKEN = Read-Host "  Tunnel Token"

if (-not $TUNNEL_TOKEN) {
    Write-Color Red "  Kein Token eingegeben! Abbruch."
    exit 1
}

$SSH_ARGS = @("-p", $NAS_PORT, "$NAS_USER@$NAS_HOST")

# ==========================================================================
# 2. SSH-Verbindung testen
# ==========================================================================
Write-Host ""
Write-Color Yellow "[2/7] SSH-Verbindung testen..."
try {
    $result = ssh @SSH_ARGS "echo OK" 2>&1
    if ($LASTEXITCODE -ne 0) { throw "SSH fehlgeschlagen" }
    Write-Color Green "  OK - SSH-Verbindung erfolgreich"
} catch {
    Write-Color Red "  SSH-Verbindung fehlgeschlagen!"
    Write-Host "  Pruefe: ssh -p $NAS_PORT $NAS_USER@$NAS_HOST"
    exit 1
}

# ==========================================================================
# 3. Docker pruefen
# ==========================================================================
Write-Host ""
Write-Color Yellow "[3/7] Docker auf NAS pruefen..."
ssh @SSH_ARGS "docker --version" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Color Red "  Docker ist nicht installiert!"
    Write-Host "  Installiere Docker ueber das Synology Paket-Zentrum (Container Manager)."
    exit 1
}
Write-Color Green "  OK - Docker gefunden"

$COMPOSE_CMD = "docker compose"
ssh @SSH_ARGS "docker compose version" 2>$null
if ($LASTEXITCODE -ne 0) {
    ssh @SSH_ARGS "docker-compose --version" 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Color Red "  Docker Compose nicht gefunden!"
        exit 1
    }
    $COMPOSE_CMD = "docker-compose"
}
Write-Color Green "  OK - Docker Compose gefunden"

# ==========================================================================
# 4. Passwoerter generieren & lokal speichern
# ==========================================================================
Write-Host ""
Write-Color Yellow "[4/7] Sichere Passwoerter generieren..."

# Sichere Zufallspasswoerter mit .NET
function New-Password($length = 32) {
    $bytes = New-Object byte[] $length
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return [Convert]::ToBase64String($bytes).Substring(0, $length) -replace '[/+=]', 'x'
}

function New-HexKey($length = 64) {
    $bytes = New-Object byte[] ($length / 2)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return ($bytes | ForEach-Object { $_.ToString("x2") }) -join ''
}

$DB_PASS = New-Password
$ROOT_PASS = New-Password
$JWT_KEY = New-HexKey

# Zugangsdaten lokal speichern
$credContent = @"
==============================================================================
MyRMS - Zugangsdaten
==============================================================================
Erstellt am: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
URL:         https://$DOMAIN
NAS:         $NAS_USER@${NAS_HOST}:$NAS_PATH
==============================================================================

Datenbank:
  Host:      db (intern im Docker-Netzwerk)
  Datenbank: myrms
  User:      myrms
  Passwort:  $DB_PASS
  Root-PW:   $ROOT_PASS
  Port:      3306

JWT Secret Key:
  $JWT_KEY

Cloudflare Tunnel:
  Token:     $TUNNEL_TOKEN
  Domain:    https://$DOMAIN

Befehle (per SSH auf dem NAS):
  Status:    ssh -p $NAS_PORT $NAS_USER@$NAS_HOST "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps"
  Logs:      ssh -p $NAS_PORT $NAS_USER@$NAS_HOST "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare logs -f"
  Neustart:  ssh -p $NAS_PORT $NAS_USER@$NAS_HOST "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare restart"
  Stoppen:   ssh -p $NAS_PORT $NAS_USER@$NAS_HOST "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare down"
==============================================================================
"@

$credContent | Out-File -FilePath $CREDENTIALS_FILE -Encoding UTF8
Write-Color Green "  OK - Passwoerter generiert"
Write-Color Green "  OK - Zugangsdaten gespeichert: $CREDENTIALS_FILE"

# ==========================================================================
# 5. Dateien auf NAS uebertragen
# ==========================================================================
Write-Host ""
Write-Color Yellow "[5/7] Dateien auf NAS uebertragen..."

ssh @SSH_ARGS "mkdir -p $NAS_PATH/docker/nginx"

$SCP_ARGS = @("-P", $NAS_PORT)
$SCP_TARGET = "$NAS_USER@${NAS_HOST}:$NAS_PATH/"

# Einzeldateien
$files = @(
    "docker-compose.cloudflare.yml",
    "Dockerfile",
    "composer.json",
    "composer.lock",
    "phinx.php",
    "migrate.sh",
    "php-fpm.conf",
    "app.json"
)

foreach ($file in $files) {
    $filePath = Join-Path $SCRIPT_DIR $file
    if (Test-Path $filePath) {
        Write-Host "  Uebertrage $file..."
        scp @SCP_ARGS $filePath $SCP_TARGET
    } else {
        Write-Color Yellow "  Ueberspringe $file (nicht gefunden)"
    }
}

# Verzeichnisse
$dirs = @("src", "db", "docker", "scripts", "vendor")
foreach ($dir in $dirs) {
    $dirPath = Join-Path $SCRIPT_DIR $dir
    if (Test-Path $dirPath) {
        Write-Host "  Uebertrage $dir/..."
        scp @SCP_ARGS -r $dirPath $SCP_TARGET
    }
}

Write-Color Green "  OK - Alle Dateien uebertragen"

# ==========================================================================
# 6. .env.cloudflare auf NAS erstellen & Stack starten
# ==========================================================================
Write-Host ""
Write-Color Yellow "[6/7] Konfiguration erstellen & Stack starten..."

# .env.cloudflare auf dem NAS erstellen
$envContent = @"
CLOUDFLARE_TUNNEL_TOKEN=$TUNNEL_TOKEN
APP_DOMAIN=$DOMAIN
ROOT_URL=https://$DOMAIN
CORS_ALLOWED_ORIGIN=https://$DOMAIN
CONFIG_PROJECT_NAME=MyRMS
CONFIG_TIMEZONE=Europe/Berlin
DB_HOSTNAME=db
DB_DATABASE=myrms
DB_USERNAME=myrms
DB_PASSWORD=$DB_PASS
DB_PORT=3306
MYSQL_ROOT_PASSWORD=$ROOT_PASS
CONFIG_AUTH_JWTKey=$JWT_KEY
CONFIG_FILES_ENABLED=Enabled
LOCAL_STORAGE_PATH=/var/www/html/storage
CONFIG_EMAILS_ENABLED=Enabled
CONFIG_EMAILS_PROVIDER=SMTP
CONFIG_EMAILS_FROMEMAIL=noreply@je-soundulight
CONFIG_EMAILS_SMTP_SERVER=smtp.example.com
CONFIG_EMAILS_SMTP_PORT=587
CONFIG_TELEMETRY_NANOID=cloudflare_prod
"@

# Env-Datei per SSH schreiben
$envEscaped = $envContent -replace "'", "'\''"
ssh @SSH_ARGS "echo '$envEscaped' > $NAS_PATH/.env.cloudflare && chmod 600 $NAS_PATH/.env.cloudflare"

Write-Color Green "  OK - .env.cloudflare auf NAS erstellt"

# Zugangsdaten auf NAS speichern
$nasCredContent = @"
==============================================================================
MyRMS - Zugangsdaten
==============================================================================
URL:         https://$DOMAIN
Pfad:        $NAS_PATH

Datenbank:
  User:      myrms
  Passwort:  $DB_PASS
  Root-PW:   $ROOT_PASS

JWT Key:     $JWT_KEY

Tunnel Token: $TUNNEL_TOKEN

Befehle:
  Status:    cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps
  Logs:      cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare logs -f
  Neustart:  cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare restart
  Stoppen:   cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare down
==============================================================================
"@

$nasCredEscaped = $nasCredContent -replace "'", "'\''"
ssh @SSH_ARGS "echo '$nasCredEscaped' > $NAS_PATH/myrms-zugangsdaten.txt && chmod 600 $NAS_PATH/myrms-zugangsdaten.txt"

Write-Color Green "  OK - Zugangsdaten auf NAS gespeichert"

# Docker Images bauen
Write-Host ""
Write-Host "  Baue Docker Images (das kann einige Minuten dauern)..."
ssh @SSH_ARGS "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare build"
if ($LASTEXITCODE -ne 0) {
    Write-Color Red "  Docker Build fehlgeschlagen!"
    exit 1
}
Write-Color Green "  OK - Images gebaut"

# Stack starten
Write-Host "  Starte Stack..."
ssh @SSH_ARGS "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare down 2>/dev/null; $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare up -d"
Write-Color Green "  OK - Stack gestartet"

# ==========================================================================
# 7. Status pruefen
# ==========================================================================
Write-Host ""
Write-Color Yellow "[7/7] Status pruefen..."
Start-Sleep -Seconds 8

Write-Host ""
ssh @SSH_ARGS "cd $NAS_PATH && $COMPOSE_CMD -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps"

Write-Host ""
Write-Color Green "================================================================"
Write-Color Green "  Deployment abgeschlossen!"
Write-Color Green "================================================================"
Write-Host ""
Write-Host "  URL:             https://$DOMAIN"
Write-Host "  Zugangsdaten:    $CREDENTIALS_FILE"
Write-Host ""
Write-Color Yellow "  WICHTIG: Konfiguriere den Public Hostname in Cloudflare Zero Trust:"
Write-Host "    1. https://one.dash.cloudflare.com -> Networks -> Tunnels"
Write-Host "    2. Deinen Tunnel -> Configure -> Public Hostname -> Add"
Write-Color Cyan "    3. Subdomain: dash"
Write-Color Cyan "    4. Domain:    je-soundulight (deine Zone)"
Write-Color Cyan "    5. Type:      HTTP"
Write-Color Cyan "    6. URL:       nginx:80"
Write-Host ""
