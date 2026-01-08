<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

// Test-Modus: Datei nur erstellen ohne zu drucken
$testMode = isset($_GET['test']) && $_GET['test'] == '1';

// Prüfen ob Laufzettel-Daten vorhanden
if (!isset($_GET['startnummer'])) {
    die(json_encode(['success' => false, 'message' => 'Keine Startnummer übergeben.']));
}

$startnummer = intval($_GET['startnummer']);
$aktuellesTurnier = getAktuellesTurnier();

if (!$aktuellesTurnier) {
    die(json_encode(['success' => false, 'message' => 'Kein aktives Turnier gefunden.']));
}

// Registrierung aus Datenbank holen (mit JOIN zu anmeldungen für Name, Email, Mobilnummer)
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

// Laufzettel-Daten zusammenstellen
$laufzettelDaten = [
    'startnummer' => $registrierung['startnummer'],
    'name' => $registrierung['name'] ?? '',
    'email' => $registrierung['email'] ?? '',
    'mobilnummer' => $registrierung['mobilnummer'] ?? '',
    'registrier_nummer' => $registrierung['anmeldung_id'],
    'turnier' => $aktuellesTurnier
];

// Laufzettel-Template laden
$laufzettelTemplate = file_get_contents(__DIR__ . '/laufzettel.txt');

// Daten ersetzen
$laufzettel = str_replace('2. Binokel in Heroldstatt', $laufzettelDaten['turnier']['titel'], $laufzettelTemplate);
$laufzettel = str_replace('Datum: ', 'Datum: ' . $laufzettelDaten['turnier']['datum'], $laufzettel);
$laufzettel = str_replace('Spieler:', 'Spieler: ' . $laufzettelDaten['turnier']['anzahl_spieler'], $laufzettel);
$laufzettel = str_replace('Runden:', 'Runden: ' . ($laufzettelDaten['turnier']['anzahl_runden'] ?? '3'), $laufzettel);
$laufzettel = str_replace('Spieler/Runde:', 'Spieler/Runde: ' . ($laufzettelDaten['turnier']['spieler_pro_runde'] ?? '3'), $laufzettel);
$laufzettel = str_replace('Name:', 'Name: ' . $laufzettelDaten['name'], $laufzettel);
$laufzettel = str_replace('Email:', 'Email: ' . ($laufzettelDaten['email'] ?? ''), $laufzettel);
$laufzettel = str_replace('Spieler Nr: ', 'Spieler Nr: ' . $laufzettelDaten['startnummer'], $laufzettel);

// Text auf 80 Zeichen Breite begrenzen und bereinigen
$laufzettelLines = explode("\n", $laufzettel);
$formattedLines = [];
foreach ($laufzettelLines as $line) {
    // Leerzeilen am Anfang überspringen
    if (count($formattedLines) == 0 && trim($line) == '') {
        continue;
    }
    
    // Zeile auf 80 Zeichen begrenzen
    if (strlen($line) > 80) {
        $formattedLines[] = substr($line, 0, 80);
        $remaining = substr($line, 80);
        while (strlen($remaining) > 80) {
            $formattedLines[] = substr($remaining, 0, 80);
            $remaining = substr($remaining, 80);
        }
        if (strlen($remaining) > 0) {
            $formattedLines[] = $remaining;
        }
    } else {
        $formattedLines[] = $line;
    }
}

// Leerzeilen am Ende entfernen
while (count($formattedLines) > 0 && trim(end($formattedLines)) == '') {
    array_pop($formattedLines);
}

$laufzettel = implode("\n", $formattedLines);

// Runden dynamisch anpassen
$anzahlRunden = intval($laufzettelDaten['turnier']['anzahl_runden'] ?? 3);
$laufzettelLines = explode("\n", $laufzettel);
$newLaufzettel = [];
$rundePattern = '/^Runde (\d+):/';
$inRundenBereich = false;

foreach ($laufzettelLines as $line) {
    if (preg_match($rundePattern, $line, $matches)) {
        $rundeNummer = intval($matches[1]);
        if ($rundeNummer <= $anzahlRunden) {
            $inRundenBereich = true;
            $newLaufzettel[] = $line;
        } else {
            $inRundenBereich = false;
        }
    } elseif ($inRundenBereich) {
        // Summe-Zeilen korrekt nummerieren
        if (preg_match('/Summe Runde\d+/', $line)) {
            // Finde die aktuelle Runde durch Rückwärtssuche
            $currentRunde = 1;
            for ($i = count($newLaufzettel) - 1; $i >= 0; $i--) {
                if (preg_match($rundePattern, $newLaufzettel[$i], $m)) {
                    $currentRunde = intval($m[1]);
                    break;
                }
            }
            $line = preg_replace('/Summe Runde\d+/', 'Summe Runde' . $currentRunde, $line);
        }
        $newLaufzettel[] = $line;
    } elseif (!preg_match($rundePattern, $line)) {
        $newLaufzettel[] = $line;
    }
}

$laufzettel = implode("\n", $newLaufzettel);

// Leerzeilen am Anfang entfernen, aber am Ende behalten
$laufzettel = ltrim($laufzettel);

// 10 Leerzeilen am Ende hinzufügen
$laufzettel .= str_repeat("\n", 10);

// ESC/POS-Befehl für Papierschnitt: 1D 56 01 (ESC V 01)
$laufzettel .= "\x1D\x56\x01";

// Temporäre Datei erstellen (im Projektverzeichnis für einfacheren Zugriff)
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}
$tempFile = $tempDir . '/laufzettel_' . time() . '.txt';

// Datei im binären Modus schreiben (wichtig für Form Feed)
$fp = fopen($tempFile, 'wb');
fwrite($fp, $laufzettel);
fclose($fp);

// Drucker-Name (optional, falls leer wird Standard-Drucker verwendet)
$printerName = isset($_GET['printer']) ? $_GET['printer'] : '';
$success = false;
$errorMessage = '';

// Test-Modus: Nur Datei erstellen
if ($testMode) {
    $success = true;
    $errorMessage = 'Test-Modus: Datei erstellt unter ' . $tempFile;
    error_log("Test-Modus: Datei erstellt: " . $tempFile);
    error_log("Dateigröße: " . filesize($tempFile) . " Bytes");
    error_log("Erste 200 Zeichen: " . substr($laufzettel, 0, 200));
} else {
    // Windows: Standard-Drucker verwenden
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        error_log("=== DRUCK START ===");
        error_log("Datei: " . $tempFile);
        error_log("Dateigröße: " . filesize($tempFile) . " Bytes");
        if ($printerName) {
            error_log("Drucker: " . $printerName);
        } else {
            error_log("Drucker: Standard-Drucker");
        }
        
        $startTime = microtime(true);
        
        // Windows: print Befehl verwenden (verwendet Standard-Drucker)
        if (!empty($printerName)) {
            // Spezifischen Drucker verwenden
            $command = 'print /D:"' . $printerName . '" "' . $tempFile . '"';
        } else {
            // Standard-Drucker verwenden
            $command = 'print "' . $tempFile . '"';
        }
        
        error_log("Befehl: " . $command);
        
        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        error_log("Druckbefehl ausgeführt in " . $duration . "ms");
        error_log("Return Code: " . $returnVar);
        error_log("Output: " . implode(' | ', $output));
        
        if ($returnVar === 0) {
            $success = true;
            error_log("✓ Druck erfolgreich");
        } else {
            $errorMessage = 'Fehler beim Drucken. Return Code: ' . $returnVar . '. Output: ' . implode(' ', $output);
            error_log("✗ FEHLER: " . $errorMessage);
        }
    } else {
        // Linux/Unix: lp verwenden
        if (!empty($printerName)) {
            $command = 'lp -d "' . $printerName . '" "' . $tempFile . '"';
        } else {
            $command = 'lp "' . $tempFile . '"';
        }
        exec($command . ' 2>&1', $output, $returnVar);
        $success = ($returnVar === 0);
        if (!$success) {
            $errorMessage = 'Fehler beim Drucken: ' . implode(' ', $output);
        }
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

// Antwort zurückgeben
if ($success) {
    $printerInfo = $printerName ? ' (' . $printerName . ')' : ' (Standard-Drucker)';
    echo json_encode(['success' => true, 'message' => 'Laufzettel wurde an den Drucker' . $printerInfo . ' gesendet.']);
} else {
    echo json_encode(['success' => false, 'message' => $errorMessage ?: 'Fehler beim Drucken. Bitte prüfen Sie die Server-Logs.']);
}
?>

