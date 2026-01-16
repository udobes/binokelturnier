<?php
// Datenbankkonfiguration
define('DB_PATH', __DIR__ . '/../data/binokel_turnier.db');

// Datenbankverbindung herstellen
function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Timeout für Busy-Waiting erhöhen (Standard: 60 Sekunden)
        $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
        // WAL-Mode aktivieren für bessere Concurrency
        $db->exec("PRAGMA journal_mode=WAL");
        // Synchronisation reduzieren für bessere Performance (bei WAL-Mode sicher)
        $db->exec("PRAGMA synchronous=NORMAL");
        // Foreign Keys aktivieren
        $db->exec("PRAGMA foreign_keys=ON");
        return $db;
    } catch (PDOException $e) {
        die("Datenbankfehler: " . $e->getMessage());
    }
}

// Datenbank initialisieren (Tabelle erstellen, falls nicht vorhanden)
function initDB() {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS anmeldungen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        anmeldedatum TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        tracking_code TEXT,
        email_gesendet TEXT,
        email_gelesen TEXT
    )");
    
    // Spalten hinzufügen, falls sie noch nicht existieren (für bestehende Datenbanken)
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN tracking_code TEXT");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN email_gesendet TEXT");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN email_gelesen TEXT");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN mobilnummer TEXT");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN turnier_id INTEGER DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN \"alter\" INTEGER");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE anmeldungen ADD COLUMN name_auf_wertungsliste INTEGER DEFAULT 0");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    
    // Konfigurationstabelle für Admin-Einstellungen erstellen
    $db->exec("CREATE TABLE IF NOT EXISTS config (
        schluessel TEXT PRIMARY KEY,
        wert TEXT
    )");
}

// E-Mail-Konfiguration
define('ADMIN_EMAIL', 'udo.besenreuther@aks-heroldstatt.de');
// FROM_EMAIL sollte eine gültige E-Mail-Adresse sein, die auf dem Server existiert
// Falls noreply nicht funktioniert, verwende die Admin-E-Mail
define('FROM_EMAIL', 'udo.besenreuther@aks-heroldstatt.de');
define('FROM_NAME', 'Binokelturnier Heroldstatt');

// E-Mail-Texte
// Betreffzeilen
$EMAIL_SUBJECT_CONFIRMATION = "Anmeldungsbestätigung - 2. Binokelturnier Heroldstatt";
$EMAIL_SUBJECT_ADMIN = "Neue Anmeldung - 2. Binokelturnier Heroldstatt";

// E-Mail-Template für Bestätigungsmail an Teilnehmer (HTML)
// Platzhalter: {NAME}, {REGISTRIERNUMMER}, {TITEL}, {DATUM}, {ORT}, {GOOGLEMAPS_LINK}, {STARTZEIT}, {EINLASSZEIT}, {TRACKING_PIXEL}
$EMAIL_TEMPLATE_CONFIRMATION = "
<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #667eea; margin-top: 0;'>Anmeldungsbestätigung</h2>
        <p>Hallo {NAME},</p>
        <p>vielen Dank für Ihre Anmeldung zum <strong>{TITEL}</strong>.</p>
        <p>Ihre Anmeldung wurde erfolgreich registriert.</p>
        <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <p><strong>Ihre Registriernummer:</strong> <span style='font-size: 18px; font-weight: bold; color: #667eea;'>{REGISTRIERNUMMER}</span></p>
        

            <p><strong>Veranstaltungsdetails:</strong></p>
            <p>Datum: {DATUM}<br>
            Ort: {ORT}<br>
            {GOOGLEMAPS_LINK}
            Beginn: {STARTZEIT}<br>
            Einlass: {EINLASSZEIT}</p>
        </div>
        <p><strong>Für eine schnelle Anmeldung am Spielabend, halten Sie bitte Ihre Registriernummer bereit.</strong></p>
        <p>Weitere Informationen erhalten Sie in Kürze.</p>
        <p>Mit freundlichen Grüßen<br>
        Das Organisationsteam</p>
    </div>
    {TRACKING_PIXEL}
</body>
</html>
";

// E-Mail-Template für Bestätigungsmail an Teilnehmer (Text-Version)
// Platzhalter: {NAME}, {REGISTRIERNUMMER}, {TITEL}, {DATUM}, {ORT}, {GOOGLEMAPS_LINK}, {STARTZEIT}, {EINLASSZEIT}
$EMAIL_TEMPLATE_CONFIRMATION_TEXT = "
Anmeldungsbestätigung

Hallo {NAME},

vielen Dank für Ihre Anmeldung zum {TITEL}.

Ihre Anmeldung wurde erfolgreich registriert.

Ihre Registriernummer: {REGISTRIERNUMMER}

Veranstaltungsdetails:
Datum: {DATUM}
Ort: {ORT}
{GOOGLEMAPS_LINK_TEXT}
Beginn: {STARTZEIT}
Einlass: {EINLASSZEIT}

Für eine schnelle Anmeldung am Spielabend, halten Sie bitte Ihre Registriernummer bereit.

Weitere Informationen erhalten Sie in Kürze.

Mit freundlichen Grüßen
Das Organisationsteam
";

// E-Mail-Template für Info-Mail an Administrator
// Platzhalter: {NAME}, {EMAIL}, {MOBILNUMMER}, {ANMELDEDATUM}, {REGISTRIERNUMMER}
$EMAIL_TEMPLATE_ADMIN = "
<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #667eea;'>Neue Anmeldung</h2>
        <p>Es hat sich eine neue Person für das Binokelturnier angemeldet:</p>
        <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <p><strong>Registriernummer:</strong> {REGISTRIERNUMMER}<br>
            <strong>Name:</strong> {NAME}<br>
            <strong>E-Mail:</strong> {EMAIL}<br>
            <strong>Mobilfunknummer:</strong> {MOBILNUMMER}<br>
            <strong>Anmeldedatum:</strong> {ANMELDEDATUM}</p>
        </div>
    </div>
</body>
</html>
";

// E-Mail senden mit Multipart (HTML + Text) und optionalem Anhang
// $attachment: Array mit ['path' => 'pfad/zur/datei', 'name' => 'dateiname.odt', 'mime' => 'application/vnd.oasis.opendocument.text']
function sendEmail($to, $subject, $htmlMessage, $textMessage = null, $headers = null, $attachment = null) {
    $debugStart = microtime(true);
    $debugSteps = [];
    
    if (empty($to) || empty($subject) || empty($htmlMessage)) {
        error_log("DEBUG sendEmail: Leere Parameter - to: " . ($to ?: 'leer') . ", subject: " . ($subject ?: 'leer'));
        return false;
    }
    
    // E-Mail-Adresse validieren
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("DEBUG sendEmail: Ungültige E-Mail-Adresse: " . $to);
        return false;
    }
    
    $debugSteps['validation'] = round((microtime(true) - $debugStart) * 1000, 2);
    
    // Text-Version erstellen, falls nicht vorhanden
    if ($textMessage === null) {
        // Einfache Text-Version aus HTML erstellen
        $textMessage = strip_tags($htmlMessage);
        $textMessage = html_entity_decode($textMessage, ENT_QUOTES, 'UTF-8');
        $textMessage = preg_replace('/\s+/', ' ', $textMessage);
        $textMessage = trim($textMessage);
    }
    
    // Eindeutige Boundaries für Multipart
    $boundary = md5(uniqid(time()));
    $mixedBoundary = null;
    
    // Prüfen ob Anhang vorhanden und lesbar
    $hasAttachment = false;
    $fileContent = false;
    $mimeType = 'application/octet-stream';
    $fileName = '';
    
    $attachmentStart = microtime(true);
    if ($attachment !== null && is_array($attachment) && isset($attachment['path']) && file_exists($attachment['path'])) {
        // Dateigröße prüfen (max 10MB)
        $fileSize = filesize($attachment['path']);
        $debugSteps['file_size_check'] = round((microtime(true) - $attachmentStart) * 1000, 2);
        
        if ($fileSize > 10 * 1024 * 1024) {
            error_log("WARNUNG: Anhang zu groß (" . round($fileSize / 1024 / 1024, 2) . " MB), wird übersprungen");
        } else {
            // Datei versuchen zu lesen
            $readStart = microtime(true);
            $fileContent = @file_get_contents($attachment['path']);
            $debugSteps['file_read'] = round((microtime(true) - $readStart) * 1000, 2);
            
            if ($fileContent !== false) {
                $hasAttachment = true;
                $mixedBoundary = md5(uniqid(time()) . 'mixed');
                
                // MIME-Type bestimmen
                $mimeType = $attachment['mime'] ?? 'application/octet-stream';
                if (empty($attachment['mime'])) {
                    $fileExtension = strtolower(pathinfo($attachment['path'], PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'odt' => 'application/vnd.oasis.opendocument.text',
                        'pdf' => 'application/pdf',
                        'doc' => 'application/msword',
                        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];
                    $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
                }
                
                // Dateiname
                $fileName = $attachment['name'] ?? basename($attachment['path']);
                error_log("DEBUG sendEmail: Anhang wird hinzugefügt: " . $fileName . " (" . $mimeType . ", Größe: " . round($fileSize / 1024, 2) . " KB)");
            } else {
                error_log("FEHLER: Anhang konnte nicht gelesen werden: " . $attachment['path']);
            }
        }
    }
    $debugSteps['attachment_processing'] = round((microtime(true) - $attachmentStart) * 1000, 2);
    
    // Nachricht erstellen
    $messageStart = microtime(true);
    $message = '';
    
    if ($hasAttachment) {
        // Mit Anhang: multipart/mixed mit multipart/alternative innen
        $message .= "--" . $mixedBoundary . "\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n\r\n";
    }
    
    // Text-Teil
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textMessage . "\r\n\r\n";
    
    // HTML-Teil
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--" . $boundary . "--";
    
    // Anhang hinzufügen, falls vorhanden und lesbar
    $encodeStart = microtime(true);
    if ($hasAttachment && $fileContent !== false) {
        $message .= "\r\n--" . $mixedBoundary . "\r\n";
        
        // RFC 2047-konforme Kodierung des Dateinamens
        $encodedFileName = $fileName;
        if (preg_match('/[^\x20-\x7E]/', $fileName)) {
            $encodedFileName = '=?UTF-8?B?' . base64_encode($fileName) . '?=';
        }
        
        // Base64-Kodierung optimiert (chunk_split für bessere Performance)
        error_log("DEBUG sendEmail: Starte Base64-Kodierung für " . round(strlen($fileContent) / 1024, 2) . " KB");
        $base64Start = microtime(true);
        
        // Direkte Base64-Kodierung (schneller als chunk_split auf großen Dateien)
        $fileContentEncoded = base64_encode($fileContent);
        // Dann in Chunks aufteilen
        $fileContentEncoded = chunk_split($fileContentEncoded, 76, "\r\n");
        
        $debugSteps['base64_encode'] = round((microtime(true) - $base64Start) * 1000, 2);
        error_log("DEBUG sendEmail: Base64-Kodierung abgeschlossen in " . $debugSteps['base64_encode'] . " ms");
        
        $message .= "Content-Type: " . $mimeType . "; name=\"" . $encodedFileName . "\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"" . $encodedFileName . "\"\r\n\r\n";
        $message .= $fileContentEncoded;
        $message .= "\r\n--" . $mixedBoundary . "--";
    }
    $debugSteps['message_encoding'] = round((microtime(true) - $encodeStart) * 1000, 2);
    $debugSteps['message_building'] = round((microtime(true) - $messageStart) * 1000, 2);
    
    if ($headers === null) {
        $headers = array();
        $headers[] = "MIME-Version: 1.0";
        if ($hasAttachment) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"" . $mixedBoundary . "\"";
        } else {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"";
        }
        $headers[] = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";
        $headers[] = "Reply-To: " . ADMIN_EMAIL;
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "X-Priority: 3";
        $headers[] = "Importance: Normal";
        $headers[] = "Return-Path: " . FROM_EMAIL;
        // Anti-Spam Header
        $headers[] = "List-Unsubscribe: <mailto:" . ADMIN_EMAIL . "?subject=Unsubscribe>";
        $headers[] = "Precedence: bulk";
        $headers[] = "X-Auto-Response-Suppress: All";
        // Zusätzliche Header für bessere Zustellung
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . md5(uniqid(time())) . "@" . $_SERVER['HTTP_HOST'] . ">";
        $headers = implode("\r\n", $headers);
    }
    
    // E-Mail versenden - zusätzlicher Parameter für Return-Path
    $mailStart = microtime(true);
    $messageSize = strlen($message);
    error_log("DEBUG sendEmail: Sende E-Mail an " . $to . " (Nachrichtengröße: " . round($messageSize / 1024, 2) . " KB)");
    
    // Timeout erhöhen für große E-Mails (aber nicht zu hoch, um 60 Sekunden zu vermeiden)
    $oldTimeout = ini_get('max_execution_time');
    if ($messageSize > 1024 * 1024) { // Größer als 1MB
        set_time_limit(90); // 90 Sekunden für große E-Mails (reduziert von 300)
        error_log("DEBUG sendEmail: Timeout erhöht auf 90 Sekunden für große E-Mail");
    } else {
        set_time_limit(60); // 60 Sekunden Standard
    }
    
    // Memory-Limit erhöhen für große E-Mails
    $oldMemoryLimit = ini_get('memory_limit');
    if ($messageSize > 2 * 1024 * 1024) { // Größer als 2MB
        ini_set('memory_limit', '256M');
        error_log("DEBUG sendEmail: Memory-Limit erhöht für große E-Mail");
    }
    
    // E-Mail-Versand mit Timeout-Schutz
    $additionalParams = "-f" . FROM_EMAIL;
    
    // Versuche E-Mail zu senden - mit explizitem Timeout
    $result = false;
    try {
        // Flush output buffer vor mail() Aufruf
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        // Mail-Versand mit Timeout-Überwachung
        error_log("DEBUG sendEmail: Starte mail() Aufruf...");
        $mailCallStart = microtime(true);
        $result = @mail($to, $subject, $message, $headers, $additionalParams);
        $mailCallDuration = round((microtime(true) - $mailCallStart) * 1000, 2);
        error_log("DEBUG sendEmail: mail() Aufruf abgeschlossen in " . $mailCallDuration . " ms");
        
        if ($mailCallDuration > 50000) { // Mehr als 50 Sekunden
            error_log("WARNUNG: mail() Aufruf dauerte sehr lange: " . $mailCallDuration . " ms - möglicherweise Mail-Server-Problem");
        }
    } catch (Exception $e) {
        error_log("FEHLER beim mail() Aufruf: " . $e->getMessage());
        $result = false;
    }
    
    $debugSteps['mail_send'] = round((microtime(true) - $mailStart) * 1000, 2);
    $totalTime = round((microtime(true) - $debugStart) * 1000, 2);
    
    // Timeout und Memory zurücksetzen
    if ($oldTimeout !== false) {
        set_time_limit($oldTimeout);
    }
    if ($oldMemoryLimit !== false) {
        ini_set('memory_limit', $oldMemoryLimit);
    }
    
    // Debug-Ausgabe
    $debugInfo = "Gesamtzeit: " . $totalTime . " ms";
    foreach ($debugSteps as $step => $time) {
        $debugInfo .= " | " . $step . ": " . $time . " ms";
    }
    error_log("DEBUG sendEmail: " . $debugInfo . " | Ergebnis: " . ($result ? 'ERFOLG' : 'FEHLER'));
    
    // Console-Ausgabe für Browser (falls verfügbar) - nur wenn Output-Buffer aktiv
    // Wird entfernt, da es JavaScript-Fehler verursachen kann
    
    return $result;
}

// Tracking-Code generieren
function generateTrackingCode($id) {
    return md5($id . time() . uniqid());
}

// Bestätigungsmail an Teilnehmer senden
function sendConfirmationEmail($name, $email, $anmeldungId = null, $turnier = null) {
    global $EMAIL_SUBJECT_CONFIRMATION, $EMAIL_TEMPLATE_CONFIRMATION, $EMAIL_TEMPLATE_CONFIRMATION_TEXT;
    
    // Validierung der Parameter
    if (empty($name) || empty($email)) {
        return false;
    }
    
    // Validierung der E-Mail-Adresse
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Prüfen ob Variablen gesetzt sind
    if (!isset($EMAIL_SUBJECT_CONFIRMATION) || !isset($EMAIL_TEMPLATE_CONFIRMATION)) {
        return false;
    }
    
    $subject = $EMAIL_SUBJECT_CONFIRMATION;
    $registriernummer = $anmeldungId !== null ? (string)$anmeldungId : '';
    
    // Turnierdaten vorbereiten
    $titel = $turnier ? ($turnier['titel'] ?? '2. Binokelturnier Heroldstatt') : '2. Binokelturnier Heroldstatt';
    $datum = $turnier ? ($turnier['datum'] ?? '09.05.2026') : '09.05.2026';
    $ort = $turnier ? ($turnier['ort'] ?? 'Berghalle') : 'Berghalle';
    $startzeit = $turnier ? ($turnier['startzeit'] ?? '18 Uhr') : '18 Uhr';
    $einlasszeit = $turnier ? ($turnier['einlasszeit'] ?? '17 Uhr') : '17 Uhr';
    $googlemapsLink = $turnier ? ($turnier['googlemaps_link'] ?? '') : 'https://maps.app.goo.gl/WW1vLiSJfzJ5VBrt8';
    
    // Google Maps Link formatieren
    $googlemapsLinkHtml = '';
    $googlemapsLinkText = '';
    if (!empty($googlemapsLink)) {
        $googlemapsLinkHtml = '<a href="' . htmlspecialchars($googlemapsLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($googlemapsLink, ENT_QUOTES, 'UTF-8') . '</a><br>';
        $googlemapsLinkText = $googlemapsLink . "\n";
    }
    
    $htmlMessage = str_replace('{NAME}', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $EMAIL_TEMPLATE_CONFIRMATION);
    $htmlMessage = str_replace('{REGISTRIERNUMMER}', htmlspecialchars($registriernummer, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{TITEL}', htmlspecialchars($titel, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{DATUM}', htmlspecialchars($datum, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{ORT}', htmlspecialchars($ort, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{GOOGLEMAPS_LINK}', $googlemapsLinkHtml, $htmlMessage);
    $htmlMessage = str_replace('{STARTZEIT}', htmlspecialchars($startzeit, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{EINLASSZEIT}', htmlspecialchars($einlasszeit, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    
    $textMessage = str_replace('{NAME}', $name, $EMAIL_TEMPLATE_CONFIRMATION_TEXT);
    $textMessage = str_replace('{REGISTRIERNUMMER}', $registriernummer, $textMessage);
    $textMessage = str_replace('{TITEL}', $titel, $textMessage);
    $textMessage = str_replace('{DATUM}', $datum, $textMessage);
    $textMessage = str_replace('{ORT}', $ort, $textMessage);
    $textMessage = str_replace('{GOOGLEMAPS_LINK_TEXT}', $googlemapsLinkText, $textMessage);
    $textMessage = str_replace('{STARTZEIT}', $startzeit, $textMessage);
    $textMessage = str_replace('{EINLASSZEIT}', $einlasszeit, $textMessage);
    
    // Tracking-Pixel vorbereiten, falls Anmeldung-ID vorhanden
    $trackingPixel = '';
    $trackingCode = null;
    if ($anmeldungId !== null) {
        $db = getDB();
        // Tracking-Code generieren (wird erst gespeichert, wenn E-Mail erfolgreich versendet wurde)
        $trackingCode = generateTrackingCode($anmeldungId);
        
        // Tracking-Pixel URL erstellen
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/anmeldung');
        if ($scriptPath === '/' || $scriptPath === '\\') {
            $scriptPath = '/anmeldung';
        }
        $trackingUrl = $protocol . "://" . $host . $scriptPath . "/track.php?code=" . urlencode($trackingCode);
        
        // Unsichtbares Tracking-Pixel
        $trackingPixel = '<img src="' . htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" style="display:none;" alt="" />';
    }
    
    $htmlMessage = str_replace('{TRACKING_PIXEL}', $trackingPixel, $htmlMessage);
    
    // Anhang hinzufügen (Spielregeln)
    // Versuche verschiedene mögliche Dateinamen
    $possiblePaths = [
        __DIR__ . '/Binokel-Tunier_Spielregeln_AKS-Heroldstatt.pdf',
        __DIR__ . '/Binokel-Tunier_Spielregeln_AKS-Herodstatt.pdf',
        __DIR__ . '/Binokel-Tunier_Spielregeln_AKS-Heroldstatt.odt'
    ];
    
    $attachmentPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $attachmentPath = $path;
            break;
        }
    }
    
    $attachment = null;
    if ($attachmentPath !== null) {
        $fileName = basename($attachmentPath);
        $fileExtension = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'odt' => 'application/vnd.oasis.opendocument.text'
        ];
        $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
        
        $attachment = [
            'path' => $attachmentPath,
            'name' => $fileName,
            'mime' => $mimeType
        ];
        
        error_log("Anhang gefunden: " . $attachmentPath . " (Größe: " . filesize($attachmentPath) . " Bytes)");
    } else {
        error_log("WARNUNG: Anhang-Datei nicht gefunden. Gesuchte Pfade: " . implode(', ', $possiblePaths));
    }
    
    // E-Mail versenden (mit HTML und Text-Version und Anhang)
    $result = sendEmail($email, $subject, $htmlMessage, $textMessage, null, $attachment);
    
    // Tracking-Code und email_gesendet NUR speichern, wenn E-Mail erfolgreich versendet wurde
    if ($result && $anmeldungId !== null && $trackingCode !== null) {
        $db = getDB();
        $emailGesendet = date('Y-m-d H:i:s');
        
        try {
            $stmt = $db->prepare("UPDATE anmeldungen SET tracking_code = ?, email_gesendet = ? WHERE id = ?");
            $stmt->execute([$trackingCode, $emailGesendet, $anmeldungId]);
        } catch (PDOException $e) {
            // Fehler ignorieren, Tracking ist optional
            error_log("Fehler beim Speichern von tracking_code und email_gesendet: " . $e->getMessage());
        }
    }
    
    return $result;
}

// Info-Mail an Administrator senden
function sendAdminNotification($name, $email, $anmeldungId = null, $mobilnummer = null) {
    global $EMAIL_SUBJECT_ADMIN, $EMAIL_TEMPLATE_ADMIN;
    if (!isset($EMAIL_SUBJECT_ADMIN) || !isset($EMAIL_TEMPLATE_ADMIN)) {
        return false;
    }
    $subject = $EMAIL_SUBJECT_ADMIN;
    $anmeldedatum = date('d.m.Y H:i:s');
    $registriernummer = $anmeldungId !== null ? (string)$anmeldungId : '';
    $mobilnummerDisplay = !empty($mobilnummer) ? htmlspecialchars($mobilnummer, ENT_QUOTES, 'UTF-8') : '(nicht angegeben)';
    
    $htmlMessage = $EMAIL_TEMPLATE_ADMIN;
    $htmlMessage = str_replace('{REGISTRIERNUMMER}', htmlspecialchars($registriernummer, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{NAME}', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{EMAIL}', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    $htmlMessage = str_replace('{MOBILNUMMER}', $mobilnummerDisplay, $htmlMessage);
    $htmlMessage = str_replace('{ANMELDEDATUM}', htmlspecialchars($anmeldedatum, ENT_QUOTES, 'UTF-8'), $htmlMessage);
    
    // Text-Version erstellen
    $textMessage = "Neue Anmeldung\n\n";
    $textMessage .= "Es hat sich eine neue Person für das Binokelturnier angemeldet:\n\n";
    $textMessage .= "Registriernummer: " . $registriernummer . "\n";
    $textMessage .= "Name: " . $name . "\n";
    $textMessage .= "E-Mail: " . $email . "\n";
    $textMessage .= "Mobilfunknummer: " . (!empty($mobilnummer) ? $mobilnummer : '(nicht angegeben)') . "\n";
    $textMessage .= "Anmeldedatum: " . $anmeldedatum . "\n";
    
    return sendEmail(ADMIN_EMAIL, $subject, $htmlMessage, $textMessage);
}
