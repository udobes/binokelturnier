#!/bin/bash
# Upload-Script für geänderte Dateien per rsync über SSH
# Verwendung: ./upload_to_server.sh
# Ermittelt automatisch geänderte Dateien (via Git oder Dateisystem)

# Farben für Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verbindungsdetails (bitte anpassen)
SERVER="w0194168.kasserver.com"
USERNAME="ssh-w0194168"
REMOTE_PATH="/www/htdocs/w0194168/aks-heroldstatt.de/binokel"

# Lokales Verzeichnis (aktuelles Verzeichnis)
LOCAL_PATH="$(cd "$(dirname "$0")" && pwd)"

echo "=== rsync Upload für geänderte Dateien ==="
echo ""
echo "Lokales Verzeichnis: $LOCAL_PATH"
echo "Remote-Server: $USERNAME@$SERVER"
echo "Remote-Pfad: $REMOTE_PATH"
echo ""

# Prüfe ob rsync installiert ist
if ! command -v rsync &> /dev/null; then
    echo -e "${RED}Fehler: rsync ist nicht installiert.${NC}"
    echo "Bitte installieren Sie rsync:"
    echo "  Ubuntu/Debian: sudo apt-get install rsync"
    echo "  macOS: brew install rsync"
    echo "  Windows: Verwenden Sie WSL oder Git Bash"
    exit 1
fi

# Prüfe ob SSH-Key vorhanden ist (optional, aber empfohlen)
if [ ! -f "$HOME/.ssh/id_rsa" ] && [ ! -f "$HOME/.ssh/id_ed25519" ]; then
    echo -e "${YELLOW}Warnung: Kein SSH-Key gefunden.${NC}"
    echo "Sie werden nach dem Passwort gefragt."
    echo "Für passwortloses Login erstellen Sie einen SSH-Key:"
    echo "  ssh-keygen -t ed25519 -C 'your_email@example.com'"
    echo "  ssh-copy-id $USERNAME@$SERVER"
    echo ""
    read -p "Fortfahren? (j/n) [j]: " CONFIRM
    CONFIRM="${CONFIRM:-j}"  # Standardwert ist "j" wenn leer
    if [[ ! "$CONFIRM" =~ ^[jJyY]$ ]]; then
        echo "Abgebrochen."
        exit 0
    fi
fi

# Exclude-Patterns definieren (muss vor rsync-Aufruf sein)
EXCLUDED_PATTERNS=(
    ".git"
    ".gitignore"
    "node_modules"
    "*.db"
    "*.db-journal"
    "*.log"
    ".DS_Store"
    "Thumbs.db"
    "upload_to_server.cmd"
    "upload_to_server.ps1"
    "upload_to_server.sh"
)

# Geänderte Dateien ermitteln mit rsync --list-only
CHANGED_FILES=()
DELETED_FILES=()

echo -e "${BLUE}Vergleiche lokale Dateien mit Server...${NC}"
echo ""

# Erstelle temporäre Datei für rsync-Ausgabe
TEMP_RSYNC_OUTPUT=$(mktemp)

# Exclude-Patterns für rsync
EXCLUDE_ARGS=()
for pattern in "${EXCLUDED_PATTERNS[@]}"; do
    EXCLUDE_ARGS+=(--exclude="$pattern")
done

# Führe rsync --list-only aus, um Unterschiede zu finden
# --dry-run zeigt an, was synchronisiert werden würde
# --itemize-changes zeigt detaillierte Informationen
rsync -avzn --itemize-changes "${EXCLUDE_ARGS[@]}" "$LOCAL_PATH/" "$USERNAME@$SERVER:$REMOTE_PATH/" > "$TEMP_RSYNC_OUTPUT" 2>&1
RSYNC_LIST_RESULT=$?

if [ $RSYNC_LIST_RESULT -ne 0 ]; then
    echo -e "${YELLOW}Warnung: rsync --list-only konnte nicht ausgeführt werden.${NC}"
    echo "Möglicherweise ist der Server nicht erreichbar oder die Authentifizierung fehlgeschlagen."
    echo ""
    echo "Versuche alternative Methode (Git)..."
    
    # Fallback: Git verwenden (falls vorhanden)
    if command -v git &> /dev/null && [ -d "$LOCAL_PATH/.git" ]; then
        cd "$LOCAL_PATH"
        while IFS= read -r line; do
            [ -z "$line" ] && continue
            filename=$(echo "$line" | sed 's/^[^ ]* *//')
            if [ -f "$filename" ]; then
                CHANGED_FILES+=("$filename")
            elif [[ "$line" =~ ^D ]]; then
                DELETED_FILES+=("$filename")
            fi
        done < <(git status --porcelain 2>/dev/null)
    fi
else
    # Parse rsync-Ausgabe
    # rsync --itemize-changes gibt Zeilen wie:
    # >f.st.... file.php  (Datei wurde geändert)
    # >f+++++++ file.php  (Neue Datei)
    # *deleting file.php  (Datei wurde gelöscht)
    # cd++++++++ dir/     (Neues Verzeichnis)
    # .d..t.... dir/      (Verzeichnis-Zeitstempel geändert)
    
    while IFS= read -r line; do
        # Ignoriere leere Zeilen
        [ -z "$line" ] && continue
        
        # Ignoriere Verzeichnis-Zeilen (beginnen mit "cd" oder ".d")
        if [[ "$line" =~ ^[cd] ]] || [[ "$line" =~ ^\.d ]]; then
            continue
        fi
        
        # Extrahiere Dateinamen
        # Format: ">f.st.... filename" oder "*deleting filename" oder ">f+++++++ filename"
        # Die ersten 11 Zeichen sind Status-Informationen, dann kommt der Dateiname
        filename=$(echo "$line" | sed -E 's/^.{11}[[:space:]]+//' | sed 's/[[:space:]]*$//')
        
        # Entferne führenden Pfad, falls vorhanden
        filename="${filename#$LOCAL_PATH/}"
        
        # Entferne führenden Slash, falls vorhanden
        filename="${filename#/}"
        
        # Ignoriere leere Dateinamen
        [ -z "$filename" ] && continue
        
        # Prüfe ob es eine gelöschte Datei ist
        if [[ "$line" =~ ^\*deleting ]]; then
            DELETED_FILES+=("$filename")
        # Prüfe ob es eine geänderte oder neue Datei ist (beginnt mit > oder <)
        elif [[ "$line" =~ ^[\>\<] ]]; then
            # Prüfe ob es eine Datei ist (nicht Verzeichnis) - Format: >f... oder <f...
            if [[ "$line" =~ ^[\>\<]f ]]; then
                # Prüfe ob Datei lokal existiert
                if [ -f "$LOCAL_PATH/$filename" ]; then
                    # Prüfe ob Datei nicht ausgeschlossen werden soll
                    excluded=false
                    for pattern in "${EXCLUDED_PATTERNS[@]}"; do
                        if [[ "$filename" == *"$pattern"* ]]; then
                            excluded=true
                            break
                        fi
                    done
                    
                    if [ "$excluded" = false ]; then
                        CHANGED_FILES+=("$filename")
                    fi
                fi
            fi
        fi
    done < "$TEMP_RSYNC_OUTPUT"
    
    # Lösche temporäre Datei
    rm -f "$TEMP_RSYNC_OUTPUT"
fi

# Dateien sind bereits gefiltert, verwende sie direkt
FILTERED_FILES=("${CHANGED_FILES[@]}")

# Zeige gefundene Dateien an
if [ ${#FILTERED_FILES[@]} -eq 0 ] && [ ${#DELETED_FILES[@]} -eq 0 ]; then
    echo -e "${YELLOW}Keine geänderten Dateien gefunden.${NC}"
    echo ""
    read -p "Trotzdem alle Dateien synchronisieren? (j/n) [j]: " SYNC_ALL
    SYNC_ALL="${SYNC_ALL:-j}"  # Standardwert ist "j" wenn leer
    if [[ ! "$SYNC_ALL" =~ ^[jJyY]$ ]]; then
        echo "Abgebrochen."
        exit 0
    fi
    echo ""
    echo "Synchronisiere alle Dateien..."
    FILTERED_FILES=("*")
else
    echo -e "${GREEN}Gefundene geänderte Dateien:${NC}"
    echo ""
    
    if [ ${#FILTERED_FILES[@]} -gt 0 ]; then
        echo -e "${BLUE}Zu synchronisierende Dateien (${#FILTERED_FILES[@]}):${NC}"
        for file in "${FILTERED_FILES[@]}"; do
            if [ -f "$LOCAL_PATH/$file" ]; then
                size=$(du -h "$LOCAL_PATH/$file" 2>/dev/null | cut -f1)
                echo -e "  ${GREEN}[+]${NC} $file ${YELLOW}($size)${NC}"
            else
                echo -e "  ${YELLOW}[?]${NC} $file (nicht gefunden)"
            fi
        done
        echo ""
    fi
    
    if [ ${#DELETED_FILES[@]} -gt 0 ]; then
        echo -e "${RED}Gelöschte Dateien (${#DELETED_FILES[@]}):${NC}"
        for file in "${DELETED_FILES[@]}"; do
            echo -e "  ${RED}[-]${NC} $file"
        done
        echo ""
        echo -e "${YELLOW}Hinweis: Gelöschte Dateien werden nicht automatisch vom Server entfernt.${NC}"
        echo ""
    fi
    
    read -p "Fortfahren mit Upload? (J/n) [J]: " CONFIRM
    CONFIRM="${CONFIRM:-j}"  # Standardwert ist "j" wenn leer
    if [[ ! "$CONFIRM" =~ ^[jJyY]$ ]]; then
        echo "Abgebrochen."
        exit 0
    fi
fi

echo ""
echo "Starte rsync-Synchronisation..."
echo ""

# rsync-Optionen:
# -a: archive mode (behält Rechte, Zeiten, etc.)
# -v: verbose (zeigt übertragene Dateien)
# -z: komprimiert während der Übertragung
# -h: human-readable Größen
# --progress: zeigt Fortschritt
# --exclude: schließt Dateien aus

# Erstelle rsync-Argumente
RSYNC_ARGS=(-avzh --progress)

# Füge Excludes hinzu
for pattern in "${EXCLUDED_PATTERNS[@]}"; do
    RSYNC_ARGS+=(--exclude="$pattern")
done

# Füge Dateien hinzu
if [ "${FILTERED_FILES[0]}" = "*" ]; then
    # Alle Dateien synchronisieren (aber mit Excludes)
    RSYNC_ARGS+=("$LOCAL_PATH/")
    # Remote-Ziel
    RSYNC_ARGS+=("$USERNAME@$SERVER:$REMOTE_PATH/")
else
    # Nur geänderte Dateien synchronisieren
    # Verwende --relative, um die Verzeichnisstruktur beizubehalten
    RSYNC_ARGS+=(--relative)
    
    # Speichere aktuelles Verzeichnis
    OLD_PWD="$PWD"
    
    # Wechsle ins lokale Verzeichnis, damit relative Pfade funktionieren
    cd "$LOCAL_PATH"
    
    # Füge Dateien mit relativen Pfaden hinzu
    for file in "${FILTERED_FILES[@]}"; do
        if [ -f "$file" ]; then
            RSYNC_ARGS+=("./$file")
        fi
    done
    
    # Remote-Ziel (ohne trailing slash, damit --relative funktioniert)
    RSYNC_ARGS+=("$USERNAME@$SERVER:$REMOTE_PATH")
    
    # Führe rsync aus (im LOCAL_PATH-Verzeichnis)
    rsync "${RSYNC_ARGS[@]}"
    RSYNC_RESULT=$?
    
    # Zurück zum ursprünglichen Verzeichnis
    cd "$OLD_PWD"
    
    # Überspringe die normale rsync-Ausführung weiter unten
    SKIP_RSYNC=true
fi

# Führe rsync aus (nur wenn nicht bereits ausgeführt)
if [ "${SKIP_RSYNC:-false}" != "true" ]; then
    rsync "${RSYNC_ARGS[@]}"
    RSYNC_RESULT=$?
fi

echo ""
if [ $RSYNC_RESULT -eq 0 ]; then
    echo -e "${GREEN}=== Upload erfolgreich abgeschlossen! ===${NC}"
    echo ""
    if [ ${#FILTERED_FILES[@]} -gt 0 ] && [ "${FILTERED_FILES[0]}" != "*" ]; then
        echo "Hochgeladene Dateien:"
        for file in "${FILTERED_FILES[@]}"; do
            if [ -f "$LOCAL_PATH/$file" ]; then
                echo -e "  ${GREEN}[OK]${NC} $file"
            fi
        done
    else
        echo "Alle Dateien wurden synchronisiert."
    fi
else
    echo -e "${RED}=== Upload fehlgeschlagen! ===${NC}"
    echo ""
    echo "Mögliche Ursachen:"
    echo "  - Falsches Passwort"
    echo "  - Server nicht erreichbar"
    echo "  - Falscher Benutzername oder Server-Adresse"
    echo "  - Berechtigungsprobleme auf dem Server"
    echo "  - SSH-Service nicht aktiviert auf dem Server"
    echo ""
    echo "Verwendeter Benutzername: $USERNAME"
    echo "Verwendeter Server: $SERVER"
    echo ""
    echo "Tipp: Für passwortloses Login:"
    echo "  ssh-keygen -t ed25519"
    echo "  ssh-copy-id $USERNAME@$SERVER"
    exit 1
fi
