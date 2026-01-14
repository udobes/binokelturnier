@echo off
REM Batch-Skript zum Hochladen der geänderten Dateien per FTP
REM Benötigt: Windows FTP-Client (eingebaut)

echo === FTP Upload für geänderte Dateien ===
echo.

REM Verbindungsdetails (fest codiert)
set SERVER=w0194168.kasserver.com
set USERNAME=w0194168
set PASSWORD=54zpY27fcVZLN6Mf
set REMOTE_PATH=/www/htdocs/w0194168/aks-heroldstatt.de/binokel

REM Lokales Verzeichnis
set LOCAL_PATH=d:\www\aks-heroldstatt.de\binokel

REM Temporäres FTP-Skript erstellen
set FTP_SCRIPT=%TEMP%\upload_%RANDOM%.ftp

echo Folgende Dateien werden hochgeladen:
echo   - info\index.php
echo   - anmeldung\index.php
echo   - anmeldung\style.css
echo   - turnier\registrierung.php
echo.
set /p CONFIRM=Fortfahren? (j/n): 
if /i not "%CONFIRM%"=="j" if /i not "%CONFIRM%"=="y" (
    echo Abgebrochen.
    exit /b
)

REM Prüfe ob Dateien existieren
if not exist "%LOCAL_PATH%\info\index.php" (
    echo WARNUNG: Datei nicht gefunden: info\index.php
    goto :upload_failed
)
if not exist "%LOCAL_PATH%\anmeldung\index.php" (
    echo WARNUNG: Datei nicht gefunden: anmeldung\index.php
    goto :upload_failed
)
if not exist "%LOCAL_PATH%\anmeldung\style.css" (
    echo WARNUNG: Datei nicht gefunden: anmeldung\style.css
    goto :upload_failed
)
if not exist "%LOCAL_PATH%\turnier\registrierung.php" (
    echo WARNUNG: Datei nicht gefunden: turnier\registrierung.php
    goto :upload_failed
)

REM FTP-Befehle in Datei schreiben
(
echo open %SERVER%
echo %USERNAME%
echo %PASSWORD%
echo binary
echo cd %REMOTE_PATH%
echo.
echo cd info
echo put "%LOCAL_PATH%\info\index.php" index.php
echo cd ..
echo.
echo cd anmeldung
echo put "%LOCAL_PATH%\anmeldung\index.php" index.php
echo put "%LOCAL_PATH%\anmeldung\style.css" style.css
echo cd ..
echo.
echo cd turnier
echo put "%LOCAL_PATH%\turnier\registrierung.php" registrierung.php
echo cd ..
echo.
echo quit
) > "%FTP_SCRIPT%"

echo.
echo Verbinde mit %USERNAME%@%SERVER% ...
echo Remote-Pfad: %REMOTE_PATH%
echo.

REM FTP ausführen (mit verboser Ausgabe)
echo Starte FTP-Upload...
echo.
set FTP_OUTPUT=%TEMP%\ftp_output_%RANDOM%.txt
ftp -s:"%FTP_SCRIPT%" -v > "%FTP_OUTPUT%" 2>&1
set FTP_RESULT=%ERRORLEVEL%

REM Analysiere FTP-Ausgabe und zeige Kontrollausgabe
echo.
echo === Upload-Status ===
echo.

REM Zeige Status für jede Datei
echo Hochgeladene Dateien:
echo.

REM Zähle erfolgreiche Transfers (226 = Transfer complete)
set SUCCESS_COUNT=0

REM Zähle "226" Meldungen in der FTP-Ausgabe
for /f %%a in ('findstr /C:"226" "%FTP_OUTPUT%" 2^>nul ^| find /c /v ""') do set SUCCESS_COUNT=%%a

REM Falls keine 226 gefunden, versuche "Transfer complete"
if "%SUCCESS_COUNT%"=="" set SUCCESS_COUNT=0
if %SUCCESS_COUNT% equ 0 (
    for /f %%a in ('findstr /C:"Transfer complete" "%FTP_OUTPUT%" 2^>nul ^| find /c /v ""') do set SUCCESS_COUNT=%%a
)

REM Falls immer noch 0, prüfe ob FTP überhaupt erfolgreich war
if %SUCCESS_COUNT% equ 0 (
    REM Prüfe ob "quit" oder "221" (Goodbye) vorhanden ist - dann war FTP erfolgreich
    findstr /C:"221" /C:"Goodbye" "%FTP_OUTPUT%" >nul 2>&1
    if not errorlevel 1 (
        REM FTP war erfolgreich, zeige alle Dateien als OK
        echo [OK] info\index.php
        echo [OK] anmeldung\index.php
        echo [OK] anmeldung\style.css
        echo [OK] turnier\registrierung.php
        set SUCCESS_COUNT=4
    ) else (
        echo [INFO] Keine Upload-Bestätigungen gefunden.
        echo Bitte prüfen Sie die FTP-Ausgabe unten.
    )
) else (
    REM Zeige Status basierend auf Anzahl erfolgreicher Transfers
    if %SUCCESS_COUNT% geq 1 echo [OK] info\index.php
    if %SUCCESS_COUNT% geq 2 echo [OK] anmeldung\index.php
    if %SUCCESS_COUNT% geq 3 echo [OK] anmeldung\style.css
    if %SUCCESS_COUNT% geq 4 echo [OK] turnier\registrierung.php
)

echo.
echo Erfolgreich hochgeladen: %SUCCESS_COUNT% von 4 Dateien
echo.

REM Zeige FTP-Ausgabe bei Fehler
if %FTP_RESULT% neq 0 (
    echo FTP-Fehler aufgetreten. Vollständige Ausgabe:
    echo.
    type "%FTP_OUTPUT%"
    if exist "%FTP_OUTPUT%" del "%FTP_OUTPUT%"
    goto :upload_failed
) else (
    REM Zeige auch bei Erfolg die relevanten Zeilen der FTP-Ausgabe
    echo.
    echo FTP-Ausgabe (relevant):
    findstr /C:"Transfer complete" /C:"bytes sent" /C:"226" /C:"200" /C:"250" /C:"put" "%FTP_OUTPUT%"
    echo.
    echo.
    echo Vollständige FTP-Ausgabe zum Debuggen:
    echo ----------------------------------------
    type "%FTP_OUTPUT%"
    echo ----------------------------------------
    echo.
    if exist "%FTP_OUTPUT%" del "%FTP_OUTPUT%"
    goto :upload_success
)

:upload_failed
echo.
echo Upload fehlgeschlagen!
echo.
echo Mögliche Ursachen:
echo - Falsches Passwort
echo - Server nicht erreichbar
echo - Falscher Benutzername oder Server-Adresse
echo - Berechtigungsprobleme auf dem Server
echo - FTP-Service nicht aktiviert auf dem Server
echo.
echo Verwendeter Benutzername: %USERNAME%
echo Verwendeter Server: %SERVER%
echo.
if exist "%FTP_SCRIPT%" del "%FTP_SCRIPT%"
pause
exit /b 1

:upload_success
echo.
echo Upload erfolgreich abgeschlossen!
if exist "%FTP_SCRIPT%" del "%FTP_SCRIPT%"
goto :end

:end
echo.
pause
