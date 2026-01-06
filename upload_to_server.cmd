@echo off
REM Batch-Skript zum Hochladen der geänderten Dateien per SFTP

REM Benötigt: SFTP-Client (z.B. OpenSSH) oder PuTTY plink

echo === SFTP Upload für geänderte Dateien ===
echo.

REM Verbindungsdetails (fest codiert)
set SERVER=w0194168.kasserver.com
set USERNAME=ssh-w0194168
set REMOTE_PATH=/w0194168/aks-heroldstatt.de/binokel

REM Lokales Verzeichnis
set LOCAL_PATH=d:\www\aks-heroldstatt.de\binokel

REM Temporäres SFTP-Skript erstellen
set SFTP_SCRIPT=%TEMP%\upload_%RANDOM%.sftp
set PS_SCRIPT=%TEMP%\upload_%RANDOM%.ps1

echo Folgende Dateien werden hochgeladen:
echo   - info\index.php
echo   - anmeldung\index.php
echo   - anmeldung\style.css
echo   - turnier\registrierung.php
echo.
REM set /p CONFIRM=Fortfahren? (j/n): 
set CONFIRM=j
if /i not "%CONFIRM%"=="j" if /i not "%CONFIRM%"=="y" (
    echo Abgebrochen.
    exit /b
)

REM Passwort abfragen
set /p PASSWORD=Passwort eingeben: 

REM SFTP-Befehle in Datei schreiben
(
echo cd %REMOTE_PATH%
echo put "%LOCAL_PATH%\info\index.php" "info/index.php"
echo put "%LOCAL_PATH%\anmeldung\index.php" "anmeldung/index.php"
echo put "%LOCAL_PATH%\anmeldung\style.css" "anmeldung/style.css"
echo put "%LOCAL_PATH%\turnier\registrierung.php" "turnier/registrierung.php"
echo quit
) > "%SFTP_SCRIPT%"

REM Prüfe ob Dateien existieren
if not exist "%LOCAL_PATH%\info\index.php" (
    echo WARNUNG: Datei nicht gefunden: info\index.php
)
if not exist "%LOCAL_PATH%\anmeldung\index.php" (
    echo WARNUNG: Datei nicht gefunden: anmeldung\index.php
)
if not exist "%LOCAL_PATH%\anmeldung\style.css" (
    echo WARNUNG: Datei nicht gefunden: anmeldung\style.css
)
if not exist "%LOCAL_PATH%\turnier\registrierung.php" (
    echo WARNUNG: Datei nicht gefunden: turnier\registrierung.php
)

echo.
echo Verbinde mit %USERNAME%@%SERVER% ...
echo.

REM Prüfe ob plink (PuTTY) verfügbar ist - unterstützt Passwort-Übergabe
where plink >nul 2>&1
if not errorlevel 1 (
    REM Verwende plink mit Passwort und Host-Key-Akzeptanz
    plink -batch -hostkey * -pw %PASSWORD% -sftp %USERNAME%@%SERVER% -m "%SFTP_SCRIPT%"
    set UPLOAD_RESULT=%ERRORLEVEL%
) else (
    REM Verwende PowerShell für Passwort-Übergabe an SFTP
    REM Erstelle temporäres PowerShell-Skript
    (
    echo $pass = '%PASSWORD%'
    echo $script = '%SFTP_SCRIPT%'
    echo $server = '%USERNAME%@%SERVER%'
    echo.
    echo $psi = New-Object System.Diagnostics.ProcessStartInfo
    echo $psi.FileName = 'sftp'
    echo $psi.Arguments = "-o StrictHostKeyChecking=no -b `"$script`" $server"
    echo $psi.UseShellExecute = $false
    echo $psi.RedirectStandardInput = $true
    echo $psi.RedirectStandardOutput = $true
    echo $psi.RedirectStandardError = $true
    echo $psi.CreateNoWindow = $true
    echo.
    echo $proc = New-Object System.Diagnostics.Process
    echo $proc.StartInfo = $psi
    echo $proc.Start^(^) ^| Out-Null
    echo Start-Sleep -Milliseconds 500
    echo $proc.StandardInput.WriteLine($pass^)
    echo $proc.StandardInput.Close^(^)
    echo $output = $proc.StandardOutput.ReadToEnd^(^)
    echo $errorOutput = $proc.StandardError.ReadToEnd^(^)
    echo $proc.WaitForExit^(^)
    echo.
    echo if ($proc.ExitCode -ne 0^) {
    echo     Write-Host $errorOutput
    echo     exit $proc.ExitCode
    echo }
    ) > "%PS_SCRIPT%"
    
    REM PowerShell-Skript ausführen
    powershell -ExecutionPolicy Bypass -NoProfile -File "%PS_SCRIPT%"
    set UPLOAD_RESULT=%ERRORLEVEL%
    
    REM Temporäres PowerShell-Skript löschen
    if exist "%PS_SCRIPT%" del "%PS_SCRIPT%"
)

if %UPLOAD_RESULT% neq 0 (
    echo.
    echo Upload fehlgeschlagen!
    if exist "%SFTP_SCRIPT%" del "%SFTP_SCRIPT%"
    pause
    exit /b 1
) else (
    echo.
    echo Upload erfolgreich abgeschlossen!
)

REM Temporäre Skripte löschen
if exist "%SFTP_SCRIPT%" del "%SFTP_SCRIPT%"
if exist "%PS_SCRIPT%" del "%PS_SCRIPT%"

echo.
pause
