# PowerShell-Script zum Hochladen der geänderten Dateien per rsync über SSH
# Verwendung: .\upload_to_server.ps1
# Benötigt: WSL (Windows Subsystem for Linux) oder Git Bash mit rsync

# Farben für Ausgabe
$ErrorColor = "Red"
$SuccessColor = "Green"
$WarningColor = "Yellow"

# Verbindungsdetails (bitte anpassen)
$SERVER = "w0194168.kasserver.com"
$USERNAME = "ssh-w0194168"
$REMOTE_PATH = "/www/htdocs/w0194168/aks-heroldstatt.de/binokel"

# Lokales Verzeichnis
$LOCAL_PATH = $PSScriptRoot

Write-Host "=== rsync Upload für geänderte Dateien ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Lokales Verzeichnis: $LOCAL_PATH"
Write-Host "Remote-Server: ${USERNAME}@${SERVER}"
Write-Host "Remote-Pfad: $REMOTE_PATH"
Write-Host ""

# Prüfe ob WSL verfügbar ist
$wslAvailable = $false
if (Get-Command wsl -ErrorAction SilentlyContinue) {
    $wslAvailable = $true
    Write-Host "WSL gefunden. Verwende WSL für rsync." -ForegroundColor Green
} elseif (Get-Command bash -ErrorAction SilentlyContinue) {
    # Prüfe ob Git Bash verfügbar ist
    $bashPath = (Get-Command bash).Source
    if ($bashPath -like "*Git*") {
        Write-Host "Git Bash gefunden. Verwende Git Bash für rsync." -ForegroundColor Green
        $wslAvailable = $true
    }
}

if (-not $wslAvailable) {
    Write-Host "Warnung: WSL oder Git Bash nicht gefunden." -ForegroundColor $WarningColor
    Write-Host "Bitte installieren Sie eine der folgenden Optionen:"
    Write-Host "  1. WSL: wsl --install"
    Write-Host "  2. Git Bash: https://git-scm.com/downloads"
    Write-Host ""
    Write-Host "Alternative: Verwenden Sie das Bash-Script (upload_to_server.sh) in WSL oder Git Bash"
    Write-Host ""
    $continue = Read-Host "Trotzdem fortfahren? (j/n)"
    if ($continue -ne "j" -and $continue -ne "y" -and $continue -ne "J" -and $continue -ne "Y") {
        Write-Host "Abgebrochen."
        exit 0
    }
}

# Liste der zu synchronisierenden Dateien/Verzeichnisse
$FILES = @(
    "info/index.php",
    "anmeldung/index.php",
    "anmeldung/process.php",
    "anmeldung/config.php",
    "anmeldung/style.css",
    "turnier/registrierung.php",
    "turnier/config.php"
)

Write-Host "Folgende Dateien/Verzeichnisse werden synchronisiert:"
foreach ($file in $FILES) {
    $filePath = Join-Path $LOCAL_PATH $file
    if (Test-Path $filePath) {
        Write-Host "  [OK] $file" -ForegroundColor Green
    } else {
        Write-Host "  [WARNUNG] $file (nicht gefunden)" -ForegroundColor $WarningColor
    }
}
Write-Host ""

$continue = Read-Host "Fortfahren? (j/n)"
if ($continue -ne "j" -and $continue -ne "y" -and $continue -ne "J" -and $continue -ne "Y") {
    Write-Host "Abgebrochen."
    exit 0
}

Write-Host ""
Write-Host "Starte rsync-Synchronisation..." -ForegroundColor Cyan
Write-Host ""

# Konvertiere Windows-Pfade zu Unix-Pfaden für WSL/Git Bash
$unixLocalPath = $LOCAL_PATH -replace '\\', '/' -replace '^([A-Z]):', '/mnt/$1' -replace '^([A-Z])', '$1' -replace ':', ''
$unixLocalPath = $unixLocalPath.ToLower()

# Erstelle rsync-Befehl
$rsyncArgs = @(
    "-avzh",
    "--progress",
    "--exclude=.git",
    "--exclude=.gitignore",
    "--exclude=node_modules",
    "--exclude=*.db",
    "--exclude=*.db-journal",
    "--exclude=*.log",
    "--exclude=.DS_Store",
    "--exclude=Thumbs.db"
)

# Füge Dateien hinzu
foreach ($file in $FILES) {
    $filePath = Join-Path $unixLocalPath $file
    $rsyncArgs += $filePath
}

# Remote-Ziel
$rsyncArgs += "${USERNAME}@${SERVER}:${REMOTE_PATH}/"

# Führe rsync aus
try {
    if (Get-Command wsl -ErrorAction SilentlyContinue) {
        # Verwende WSL
        $rsyncCommand = "rsync " + ($rsyncArgs -join " ")
        $result = wsl bash -c $rsyncCommand
    } elseif (Get-Command bash -ErrorAction SilentlyContinue) {
        # Verwende Git Bash
        $rsyncCommand = "rsync " + ($rsyncArgs -join " ")
        $result = bash -c $rsyncCommand
    } else {
        Write-Host "Fehler: Weder WSL noch Git Bash gefunden." -ForegroundColor $ErrorColor
        exit 1
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "=== Upload erfolgreich abgeschlossen! ===" -ForegroundColor $SuccessColor
        Write-Host ""
        Write-Host "Synchronisierte Dateien:"
        foreach ($file in $FILES) {
            $filePath = Join-Path $LOCAL_PATH $file
            if (Test-Path $filePath) {
                Write-Host "  [OK] $file" -ForegroundColor Green
            }
        }
    } else {
        throw "rsync fehlgeschlagen mit Exit-Code: $LASTEXITCODE"
    }
} catch {
    Write-Host ""
    Write-Host "=== Upload fehlgeschlagen! ===" -ForegroundColor $ErrorColor
    Write-Host ""
    Write-Host "Mögliche Ursachen:"
    Write-Host "  - Falsches Passwort"
    Write-Host "  - Server nicht erreichbar"
    Write-Host "  - Falscher Benutzername oder Server-Adresse"
    Write-Host "  - Berechtigungsprobleme auf dem Server"
    Write-Host "  - SSH-Service nicht aktiviert auf dem Server"
    Write-Host ""
    Write-Host "Verwendeter Benutzername: $USERNAME"
    Write-Host "Verwendeter Server: $SERVER"
    Write-Host ""
    Write-Host "Tipp: Für passwortloses Login:"
    Write-Host "  ssh-keygen -t ed25519"
    Write-Host "  ssh-copy-id ${USERNAME}@${SERVER}"
    exit 1
}
