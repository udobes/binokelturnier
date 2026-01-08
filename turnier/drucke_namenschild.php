<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

// Prüfen ob Startnummer übergeben wurde
if (!isset($_GET['startnummer'])) {
    die(json_encode(['success' => false, 'message' => 'Keine Startnummer übergeben.']));
}

$startnummer = intval($_GET['startnummer']);
$aktuellesTurnier = getAktuellesTurnier();

if (!$aktuellesTurnier) {
    die(json_encode(['success' => false, 'message' => 'Kein aktives Turnier gefunden.']));
}

// Registrierung aus Datenbank holen (mit JOIN zu anmeldungen für Name)
$db = getDB();
$stmt = $db->prepare("
    SELECT 
        tr.id,
        tr.turnier_id,
        tr.anmeldung_id,
        tr.startnummer,
        a.name,
        a.email,
        a.mobilnummer
    FROM turnier_registrierungen tr
    LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
    WHERE tr.turnier_id = ? AND tr.startnummer = ?
");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registrierung) {
    die(json_encode(['success' => false, 'message' => 'Registrierung nicht gefunden.']));
}

// Namenschild-Daten zusammenstellen
$name = $registrierung['name'] ?? 'Unbekannt';
$startnummer = $registrierung['startnummer'];

// Debug-Informationen sammeln
$debugInfo = [];

// Funktion zum Konvertieren von Umlauten für ESC/POS-Drucker
// Konvertiert deutsche und internationale Sonderzeichen zu ASCII-kompatiblen Zeichen
function convertToEscPosEncoding($text) {
    // Umlaute und Sonderzeichen zu ASCII-Ersatzzeichen konvertieren
    $umlautMap = [
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'Ä' => 'Ae',
        'Ö' => 'Oe',
        'Ü' => 'Ue',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'ç' => 'c',
        'Ç' => 'C',
        'ñ' => 'n',
        'Ñ' => 'N'
    ];
    
    // Umlaute ersetzen
    $text = strtr($text, $umlautMap);
    
    // Alle nicht-ASCII-Zeichen durch ? ersetzen (falls noch vorhanden)
    // Aber zuerst UTF-8 dekodieren für bessere Kompatibilität
    if (function_exists('mb_convert_encoding')) {
        // Versuche zu ISO-8859-1 zu konvertieren, dann zu ASCII
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $text = mb_convert_encoding($text, 'ASCII', 'ISO-8859-1');
    } else {
        // Fallback: Nur ASCII-Zeichen behalten
        $text = preg_replace('/[^\x20-\x7E\n\r]/', '?', $text);
    }
    
    // Mehrfache Fragezeichen zusammenfassen
    $text = preg_replace('/\?+/', '?', $text);
    
    return $text;
}

// Funktion zum Erstellen eines einfachen Test-Musters (für Debugging)
function createTestPattern($width = 80, $height = 80, &$debugInfo = null) {
    if ($debugInfo !== null) {
        $debugInfo[] = "Erstelle Test-Muster: " . $width . "x" . $height . " Pixel";
    }
    
    // Einfaches Test-Muster: Schachbrett
    $bytesPerLine = (int)ceil($width / 8);
    $rasterData = '';
    
    for ($y = 0; $y < $height; $y++) {
        $lineData = '';
        for ($x = 0; $x < $width; $x += 8) {
            $byte = 0;
            for ($bit = 0; $bit < 8; $bit++) {
                if ($x + $bit < $width) {
                    // Schachbrett-Muster: Jeder zweite Pixel schwarz
                    $isBlack = (($x + $bit + $y) % 2 == 0);
                    if ($isBlack) {
                        $byte |= (1 << (7 - $bit));
                    }
                }
            }
            $lineData .= chr($byte);
        }
        $rasterData .= $lineData;
    }
    
    $xL = $bytesPerLine & 0xFF;
    $xH = ($bytesPerLine >> 8) & 0xFF;
    $yL = $height & 0xFF;
    $yH = ($height >> 8) & 0xFF;
    
    $escPosCommand = "\x1D\x76\x30"; // GS v 0
    $escPosCommand .= "\x00"; // Modus 0 = normale Dichte
    $escPosCommand .= chr($xL) . chr($xH);
    $escPosCommand .= chr($yL) . chr($yH);
    $escPosCommand .= $rasterData;
    
    return $escPosCommand;
}

// Funktion zum Konvertieren eines Bildes in ESC/POS Raster-Format
function imageToEscPosRaster($imagePath, $maxWidth = 200, &$debugInfo = null) {
    if ($debugInfo !== null) {
        $debugInfo[] = "imageToEscPosRaster aufgerufen mit: " . $imagePath;
    }
    
    if (!file_exists($imagePath)) {
        if ($debugInfo !== null) {
            $debugInfo[] = "FEHLER: Logo-Datei existiert nicht: " . $imagePath;
        }
        return '';
    }
    
    // Prüfe ob GD-Bibliothek verfügbar ist
    if (!function_exists('imagecreatefrompng')) {
        if ($debugInfo !== null) {
            $debugInfo[] = "FEHLER: GD-Bibliothek nicht verfügbar";
        }
        return '';
    }
    
    if ($debugInfo !== null) {
        $debugInfo[] = "GD-Bibliothek verfügbar, lade Bild...";
    }
    
    // Bild laden
    $imageInfo = @getimagesize($imagePath);
    if (!$imageInfo) {
        if ($debugInfo !== null) {
            $debugInfo[] = "FEHLER: getimagesize fehlgeschlagen für: " . $imagePath;
        }
        return '';
    }
    
    if ($debugInfo !== null) {
        $debugInfo[] = "Bild geladen: " . $imageInfo[0] . "x" . $imageInfo[1] . " Pixel, Typ: " . $imageInfo['mime'];
    }
    
    $mimeType = $imageInfo['mime'];
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Bild je nach Typ laden
    $img = null;
    switch ($mimeType) {
        case 'image/png':
            $img = @imagecreatefrompng($imagePath);
            break;
        case 'image/jpeg':
        case 'image/jpg':
            $img = @imagecreatefromjpeg($imagePath);
            break;
        case 'image/gif':
            $img = @imagecreatefromgif($imagePath);
            break;
        default:
            return '';
    }
    
    if (!$img) {
        if ($debugInfo !== null) {
            $debugInfo[] = "FEHLER: imagecreatefrompng/jpeg/gif fehlgeschlagen";
        }
        return '';
    }
    
    if ($debugInfo !== null) {
        $debugInfo[] = "Bild erfolgreich geladen in GD";
    }
    
    // Skalieren auf gewünschte Größe (300x300)
    // Wenn maxWidth = 300, dann auf 300x300 skalieren (quadratisch, nicht proportional)
    $targetWidth = $maxWidth;
    $targetHeight = $maxWidth; // Quadratisch (300x300)
    
    $newWidth = $targetWidth;
    $newHeight = $targetHeight;
    
    if ($originalWidth != $targetWidth || $originalHeight != $targetHeight) {
        $scaledImg = imagecreatetruecolor($newWidth, $newHeight);
        // Weiß als Hintergrund setzen
        $white = imagecolorallocate($scaledImg, 255, 255, 255);
        imagefill($scaledImg, 0, 0, $white);
        imagealphablending($scaledImg, true);
        imagecopyresampled($scaledImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        imagedestroy($img);
        $img = $scaledImg;
    } else {
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
    }
    
    // In Schwarz-Weiß konvertieren (falls noch nicht)
    $bwImg = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($bwImg, 255, 255, 255);
    $black = imagecolorallocate($bwImg, 0, 0, 0);
    imagefill($bwImg, 0, 0, $white);
    
    for ($y = 0; $y < $newHeight; $y++) {
        for ($x = 0; $x < $newWidth; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $alpha = ($rgb >> 24) & 0x7F;
            // Graustufen-Berechnung
            $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
            // Berücksichtige Alpha-Kanal
            if ($alpha > 0) {
                $gray = (int)(($gray * (127 - $alpha) + 255 * $alpha) / 127);
            }
            // Wenn dunkel (Schwellwert 128), schwarz setzen
            if ($gray < 128) {
                imagesetpixel($bwImg, $x, $y, $black);
            }
        }
    }
    imagedestroy($img);
    $img = $bwImg;
    
    // In 1-bit Bitmap konvertieren (Schwarz/Weiß)
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Maximale Breite für ESC/POS begrenzen (typisch 384 Pixel bei 58mm Papier)
    if ($width > 384) {
        $scale = 384 / $width;
        $newWidth = 384;
        $newHeight = (int)($height * $scale);
        $resizedImg = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($resizedImg, 255, 255, 255);
        imagefill($resizedImg, 0, 0, $white);
        imagecopyresampled($resizedImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img);
        $img = $resizedImg;
        $width = $newWidth;
        $height = $newHeight;
    }
    
    // ESC/POS Raster-Grafik Daten generieren
    // GS v 0 - Drucke Raster-Grafik
    // Format: GS v 0 [xL xH] [yL yH] [d1...dk]
    // ESC/POS erwartet zeilenweise Daten
    // WICHTIG: Bei Epson-Druckern ist das MSB (Most Significant Bit) links, LSB rechts
    
    $bytesPerLine = (int)ceil($width / 8); // Bytes pro Zeile
    $rasterData = '';
    
    // Zeilenweise verarbeiten
    for ($y = 0; $y < $height; $y++) {
        $lineData = '';
        // Jede Zeile in 8-Bit-Blöcke aufteilen
        for ($x = 0; $x < $width; $x += 8) {
            $byte = 0;
            // 8 Pixel pro Byte verarbeiten (von links nach rechts)
            for ($bit = 0; $bit < 8; $bit++) {
                if ($x + $bit < $width) {
                    $rgb = imagecolorat($img, $x + $bit, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Graustufen-Berechnung
                    $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
                    // Wenn dunkel (Schwellwert 128), Bit setzen
                    // MSB (Most Significant Bit) ist links, daher (7 - bit)
                    // Bit 7 = links, Bit 0 = rechts
                    if ($gray < 128) {
                        $byte |= (1 << (7 - $bit));
                    }
                }
            }
            $lineData .= chr($byte);
        }
        $rasterData .= $lineData;
    }
    
    imagedestroy($img);
    
    // ESC/POS-Befehle für Raster-Grafik
    // Format: GS v 0 [xL xH] [yL yH] [d1...dk]
    // xL, xH: Breite in Bytes (Little Endian)
    // yL, yH: Höhe in Pixeln (Little Endian)
    
    $xL = $bytesPerLine & 0xFF;
    $xH = ($bytesPerLine >> 8) & 0xFF;
    $yL = $height & 0xFF;
    $yH = ($height >> 8) & 0xFF;
    
    if ($debugInfo !== null) {
        $debugInfo[] = "Logo konvertiert: " . $width . "x" . $height . " Pixel";
        $debugInfo[] = "Bytes pro Zeile: " . $bytesPerLine;
        $debugInfo[] = "Raster-Daten: " . strlen($rasterData) . " Bytes";
        $debugInfo[] = "Erwartete Bytes: " . ($bytesPerLine * $height);
    }
    
    // GS v 0 - Drucke Raster-Grafik
    // Format: GS v 0 m xL xH yL yH d1...dk
    // m: Modus (0 = normale Dichte, 1 = doppelte Breite, 2 = doppelte Höhe, 3 = doppelte Breite und Höhe)
    // xL, xH: Breite in Bytes (Little Endian, 16-bit)
    // yL, yH: Höhe in Pixeln (Little Endian, 16-bit)
    // d1...dk: Raster-Daten
    
    $escPosCommand = "\x1D\x76\x30"; // GS v 0
    $escPosCommand .= "\x00"; // Modus 0 = normale Dichte
    $escPosCommand .= chr($xL) . chr($xH); // Breite in Bytes (Little Endian)
    $escPosCommand .= chr($yL) . chr($yH); // Höhe in Pixeln (Little Endian)
    $escPosCommand .= $rasterData;
    
    if ($debugInfo !== null) {
        $debugInfo[] = "ESC/POS-Befehl erstellt: " . strlen($escPosCommand) . " Bytes gesamt";
    }
    
    return $escPosCommand;
}

// ESC/POS-Befehle für Epson TM-M30 erstellen
// Layout: Logo rechts oben, Startnummer oben links (groß), Name unten mittig, Gesamthöhe 50mm
// WICHTIG: Keine ESC d Befehle verwenden, da diese Papierschnitt auslösen können

$namenschild = "";

// Initialisierung
$namenschild .= "\x1B\x40"; // ESC @ - Initialisierung

// ===== TURNIERTITEL MITTIG OBEN =====
// Turniertitel aus dem aktuellen Turnier holen
$turniertitel = $aktuellesTurnier['titel'] ?? 'Binokelturnier';
$debugInfo[] = "Turniertitel: " . $turniertitel;

// Turniertitel für ESC/POS konvertieren
$turniertitelEscPos = convertToEscPosEncoding("2. Binokelturnier \n Heroldstatt");
$debugInfo[] = "Turniertitel (ESC/POS): " . $turniertitelEscPos;

// Turniertitel mittig und groß drucken
$namenschild .= "\x1B\x61\x01"; // ESC a 1 - zentriert
$namenschild .= "\x1D\x21\x11"; // GS ! - Zeichengröße: normal breit, doppelt hoch
$namenschild .= "\x1B\x45\x01"; // ESC E 1 - Fettdruck ein
$namenschild .= $turniertitelEscPos . "\n";
$namenschild .= "\x1B\x45\x00"; // ESC E 0 - Fettdruck aus
$namenschild .= "\x1D\x21\x00"; // GS ! - Zeichengröße: normal
$namenschild .= "\x1B\x61\x00"; // ESC a 0 - links (zurücksetzen)

// Leerzeile zwischen Turniertitel und Startnummer
$namenschild .= "\n";

// ===== STARTNUMMER UNTER DEM TURNIERTITEL (MITTIG) =====
// Textausrichtung zentriert
$namenschild .= "\x1B\x61\x01"; // ESC a 1 - zentriert

// Große Startnummer (2x breit, 2x hoch)
$namenschild .= "\x1D\x21\x44"; // GS ! - Zeichengröße: 2x breit, 2x hoch
$namenschild .= "\x1B\x45\x01"; // ESC E 1 - Fettdruck ein
$namenschild .= $startnummer . "\n";
$namenschild .= "\x1B\x45\x00"; // ESC E 0 - Fettdruck aus

// Zeichengröße zurücksetzen auf normal
$namenschild .= "\x1D\x21\x00"; // GS ! - Zeichengröße: normal
$namenschild .= "\x1B\x61\x00"; // ESC a 0 - links (zurücksetzen)

// 1 Leerzeile zwischen Startnummer und Name
$namenschild .= "\n";

// ===== NAME UNTEN MITTIG =====
// Name für ESC/POS konvertieren
$nameEscPos = convertToEscPosEncoding($name);
$debugInfo[] = "Name (Original): " . $name;
$debugInfo[] = "Name (ESC/POS): " . $nameEscPos;

// Textausrichtung zentriert
$namenschild .= "\x1B\x61\x01"; // ESC a 1 - zentriert

// Name in normaler Größe, fett
$namenschild .= "\x1D\x21\x11"; // GS ! - Zeichengröße: normal breit, doppelt hoch
$namenschild .= "\x1B\x45\x01"; // ESC E 1 - Fettdruck ein
$namenschild .= $nameEscPos . "\n";
$namenschild .= "\x1B\x45\x00"; // ESC E 0 - Fettdruck aus

// 2 Leerzeilen vor dem Papierschnitt
$namenschild .= "\n\n\n\n";

// Zeichengröße zurücksetzen
$namenschild .= "\x1D\x21\x00"; // GS ! - Zeichengröße: normal

// Papierschnitt NUR am Ende - als allerletzter Befehl
$namenschild .= "\x1D\x56\x01"; // ESC V 01 - Papierschnitt

// Temporäre Datei erstellen
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}
$tempFile = $tempDir . '/namenschild_' . time() . '.txt';

// Datei im binären Modus schreiben (wichtig für ESC/POS-Befehle)
$fp = fopen($tempFile, 'wb');
fwrite($fp, $namenschild);
fclose($fp);

// Drucker-IP-Adresse (Netzwerkdrucker)
$printerIP = isset($_GET['printer_ip']) ? $_GET['printer_ip'] : '192.168.188.97';
$printerPort = isset($_GET['printer_port']) ? intval($_GET['printer_port']) : 9100; // Standard RAW-Port
$success = false;
$errorMessage = '';

$debugInfo[] = "=== NAMENSSCHILD DRUCK START ===";
$debugInfo[] = "Datei: " . $tempFile;
$debugInfo[] = "Dateigröße: " . filesize($tempFile) . " Bytes";
$debugInfo[] = "Drucker-IP: " . $printerIP;
$debugInfo[] = "Drucker-Port: " . $printerPort;

$startTime = microtime(true);

// Direkter Netzwerkdruck über TCP/IP (Port 9100 - RAW printing)
// Verwende fsockopen oder socket für direkten Netzwerkzugriff
$socket = @fsockopen($printerIP, $printerPort, $errno, $errstr, 5);

if (!$socket) {
    $errorMessage = "Verbindung zum Drucker fehlgeschlagen: $errstr ($errno)";
    $debugInfo[] = "✗ FEHLER: " . $errorMessage;
    
    // Fallback: Versuche mit copy Befehl (falls Netzwerkdrucker als Share eingerichtet)
    $fallbackCommand = 'copy /B "' . $tempFile . '" "\\\\' . $printerIP . '\\"';
    $debugInfo[] = "Fallback: Versuche mit copy Befehl: " . $fallbackCommand;
    
    $output = [];
    $returnVar = 0;
    exec($fallbackCommand . ' 2>&1', $output, $returnVar);
    
    if ($returnVar === 0) {
        $success = true;
        $debugInfo[] = "✓ Namenschild-Druck erfolgreich (Fallback-Methode)";
    } else {
        $errorMessage .= " | Fallback fehlgeschlagen: " . implode(' ', $output);
        $debugInfo[] = "✗ FEHLER: Fallback fehlgeschlagen";
    }
} else {
    // Dateiinhalt lesen und direkt an Drucker senden
    $fileContent = file_get_contents($tempFile);
    $bytesWritten = fwrite($socket, $fileContent);
    fclose($socket);
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    $debugInfo[] = "Druckbefehl ausgeführt in " . $duration . "ms";
    $debugInfo[] = "Bytes gesendet: " . $bytesWritten . " von " . strlen($fileContent);
    
    if ($bytesWritten === strlen($fileContent)) {
        $success = true;
        $debugInfo[] = "✓ Namenschild-Druck erfolgreich";
    } else {
        $errorMessage = "Nicht alle Bytes wurden gesendet. Gesendet: $bytesWritten, Erwartet: " . strlen($fileContent);
        $debugInfo[] = "✗ FEHLER: " . $errorMessage;
    }
}

// Temporäre Datei nach kurzer Zeit löschen (async)
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows: Datei nach 5 Sekunden löschen
    exec('timeout /t 5 /nobreak >nul 2>&1 & del "' . $tempFile . '"');
} else {
    // Linux: Datei nach 5 Sekunden löschen
    exec('sleep 5 && rm "' . $tempFile . '" &');
}

// Antwort zurückgeben (mit Debug-Informationen)
if ($success) {
    // Erfolgsmeldung entfernt - stille Bestätigung
    echo json_encode([
        'success' => true, 
        'debug' => $debugInfo
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => false, 
        'message' => $errorMessage ?: 'Fehler beim Drucken. Bitte prüfen Sie die Server-Logs.',
        'debug' => $debugInfo
    ], JSON_PRETTY_PRINT);
}
?>

