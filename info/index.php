<?php
session_start();
require_once __DIR__ . '/../turnier/config.php';
initTurnierDB();

// Startnummer verarbeiten, falls gesendet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['startnummer'])) {
    $startnummer = trim($_POST['startnummer']);
    if (is_numeric($startnummer) && intval($startnummer) > 0) {
        $_SESSION['startnummer'] = intval($startnummer);
    }
}

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    ?><!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="60">
        <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
        <title>Turnier Info</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 1400px;
                margin: 0 auto;
            }
            h1 {
                color: #667eea;
                margin-bottom: 20px;
            }
            .message {
                padding: 15px;
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffc107;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Turnier Info</h1>
            <div class="message">
                <p>Kein aktives Turnier gefunden.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$db = getDB();
$aktiveRunde = isset($aktuellesTurnier['aktive_runde']) && $aktuellesTurnier['aktive_runde'] !== null ? intval($aktuellesTurnier['aktive_runde']) : null;

// Personenzuordnung und Tischzuordnung nur laden, wenn aktive Runde gesetzt ist
$personenZuordnung = [];
$tischZuordnungen = [];
$tischeGruppiert = [];
$spielerTisch = null;
if ($aktiveRunde !== null) {
    // Personenzuordnung für die aktive Runde abrufen
    $stmt = $db->prepare("
        SELECT tisch, spieler_id 
        FROM turnier_zuordnungen 
        WHERE turnier_id = ? AND runde = ? 
        ORDER BY spieler_id
    ");
    $stmt->execute([$aktuellesTurnier['id'], $aktiveRunde]);
    $personenZuordnung = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tischzuordnung für die aktive Runde abrufen
    $stmt = $db->prepare("
        SELECT tisch, spieler_id 
        FROM turnier_zuordnungen 
        WHERE turnier_id = ? AND runde = ? 
        ORDER BY tisch, spieler_id
    ");
    $stmt->execute([$aktuellesTurnier['id'], $aktiveRunde]);
    $tischZuordnungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gruppiere nach Tisch
    foreach ($tischZuordnungen as $zuordnung) {
        $tisch = $zuordnung['tisch'];
        if (!isset($tischeGruppiert[$tisch])) {
            $tischeGruppiert[$tisch] = [];
        }
        $tischeGruppiert[$tisch][] = $zuordnung['spieler_id'];
    }
    ksort($tischeGruppiert);

    // Tisch für die aktuelle Startnummer (Startnummer = Spieler-/Personnummer) ermitteln
    if (isset($_SESSION['startnummer']) && $_SESSION['startnummer'] !== '') {
        $startnummerFuerTisch = intval($_SESSION['startnummer']);
        foreach ($personenZuordnung as $zuordnung) {
            if (intval($zuordnung['spieler_id']) === $startnummerFuerTisch) {
                $spielerTisch = $zuordnung['tisch'];
                break;
            }
        }
    }
}

// Ergebnisse laden, falls aktiviert
$aktiveErgebnisRunde = isset($aktuellesTurnier['aktive_ergebnis_runde']) ? $aktuellesTurnier['aktive_ergebnis_runde'] : null;
$ergebnisseMitRunden = [];
$rundenErgebnisse = [];
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);

// Merken, ob bereits eine Startnummer hinterlegt ist
$hatStartnummer = isset($_SESSION['startnummer']) && $_SESSION['startnummer'] !== '';
// Startnummer der aktuellen Person (unabhängig von Tisch-/Rundenzuordnung)
$startnummerAktuell = $hatStartnummer ? intval($_SESSION['startnummer']) : null;

// Name des Spielers für die aktuelle Startnummer laden
$spielerName = null;
if ($startnummerAktuell !== null) {
    $stmt = $db->prepare("
        SELECT a.name 
        FROM turnier_registrierungen tr
        LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
        WHERE tr.turnier_id = ? AND tr.startnummer = ?
    ");
    $stmt->execute([$aktuellesTurnier['id'], $startnummerAktuell]);
    $spielerDaten = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($spielerDaten && isset($spielerDaten['name'])) {
        $spielerName = $spielerDaten['name'];
    }
}

// Ergebnis-Infos für die aktuelle Startnummer
$spielerPunkte = null;
$spielerPlatz = null;

if ($aktiveErgebnisRunde !== null) {
    if ($aktiveErgebnisRunde == 0) {
        // Gesamtergebnisse aus neuer Tabelle laden
        $ergebnisseMitRunden = getErgebnisseFuerTurnier($aktuellesTurnier['id'], $anzahlRunden);
        
        // Nach Gesamtpunkten sortieren für Platzierungsberechnung
        usort($ergebnisseMitRunden, function($a, $b) {
            $punkteA = $a['gesamtpunkte'] !== null ? intval($a['gesamtpunkte']) : -1;
            $punkteB = $b['gesamtpunkte'] !== null ? intval($b['gesamtpunkte']) : -1;
            if ($punkteA === $punkteB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return ($punkteB - $punkteA); // DESC
        });
        
        // Platzierung berechnen
        $aktuellePlatzierung = 1;
        $letztePunkte = null;
        $platzierungen = [];
        foreach ($ergebnisseMitRunden as $ergebnis) {
            $punkte = $ergebnis['gesamtpunkte'] !== null ? intval($ergebnis['gesamtpunkte']) : null;
            if ($punkte !== null) {
                if ($letztePunkte !== null && $punkte < $letztePunkte) {
                    $aktuellePlatzierung = count($platzierungen) + 1;
                }
                $platzierungen[$ergebnis['startnummer']] = $aktuellePlatzierung;
                $letztePunkte = $punkte;
            } else {
                $platzierungen[$ergebnis['startnummer']] = null;
            }
        }
        
        // Platzierungen zuweisen
        foreach ($ergebnisseMitRunden as &$ergebnis) {
            $ergebnis['platzierung'] = $platzierungen[$ergebnis['startnummer']] ?? null;
        }
        unset($ergebnis);
        
        // Ergebnis für aktuelle Startnummer (Gesamt) ermitteln
        if ($startnummerAktuell !== null) {
            foreach ($ergebnisseMitRunden as $erg) {
                if (intval($erg['startnummer']) === $startnummerAktuell) {
                    if ($erg['gesamtpunkte'] !== null) {
                        $spielerPunkte = intval($erg['gesamtpunkte']);
                    }
                    if (isset($erg['platzierung']) && $erg['platzierung'] !== null) {
                        $spielerPlatz = intval($erg['platzierung']);
                    }
                    break;
                }
            }
        }

        // Nach Platz sortieren, dann nach Startnummer
        usort($ergebnisseMitRunden, function($a, $b) {
            $platzA = isset($a['platzierung']) && $a['platzierung'] !== null ? intval($a['platzierung']) : 999999;
            $platzB = isset($b['platzierung']) && $b['platzierung'] !== null ? intval($b['platzierung']) : 999999;
            if ($platzA === $platzB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return $platzA - $platzB; // ASC (Platz 1 vor Platz 2)
        });
    } else {
        // Ergebnisse für eine spezifische Runde
        $runde = intval($aktiveErgebnisRunde);
        
        // Alle Registrierungen laden (mit name_auf_wertungsliste)
        $stmt = $db->prepare("
            SELECT tr.startnummer, a.name, a.name_auf_wertungsliste 
            FROM turnier_registrierungen tr
            LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
            WHERE tr.turnier_id = ?
        ");
        $stmt->execute([$aktuellesTurnier['id']]);
        $alleRegistrierungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ergebnisse für die Runde laden
        $alleErgebnisse = [];
        foreach ($alleRegistrierungen as $reg) {
            $ergebnis = getErgebnis($aktuellesTurnier['id'], $runde, $reg['startnummer']);
            $alleErgebnisse[] = [
                'startnummer' => $reg['startnummer'],
                'name' => $reg['name'],
                'name_auf_wertungsliste' => isset($reg['name_auf_wertungsliste']) ? intval($reg['name_auf_wertungsliste']) : 0,
                'punkte' => $ergebnis ? $ergebnis['punkte'] : null
            ];
        }
        
        // Platzierung berechnen (immer nach Punktzahl)
        usort($alleErgebnisse, function($a, $b) {
            $punkteA = $a['punkte'] !== null ? intval($a['punkte']) : -1;
            $punkteB = $b['punkte'] !== null ? intval($b['punkte']) : -1;
            if ($punkteA === $punkteB) {
                return 0;
            }
            return ($punkteA > $punkteB) ? -1 : 1;
        });
        
        $aktuellePlatzierung = 1;
        $letztePunkte = null;
        $platzierungen = [];
        foreach ($alleErgebnisse as $ergebnis) {
            $punkte = $ergebnis['punkte'] !== null ? intval($ergebnis['punkte']) : null;
            if ($punkte !== null) {
                if ($letztePunkte !== null && $punkte < $letztePunkte) {
                    $aktuellePlatzierung = count($platzierungen) + 1;
                }
                $platzierungen[$ergebnis['startnummer']] = $aktuellePlatzierung;
                $letztePunkte = $punkte;
            } else {
                $platzierungen[$ergebnis['startnummer']] = null;
            }
        }
        
        // Platzierungen zuweisen
        foreach ($alleErgebnisse as &$ergebnis) {
            $ergebnis['platzierung'] = $platzierungen[$ergebnis['startnummer']] ?? null;
        }
        unset($ergebnis);
        
        // Nach Platz sortieren, dann nach Startnummer
        usort($alleErgebnisse, function($a, $b) {
            $platzA = isset($a['platzierung']) && $a['platzierung'] !== null ? intval($a['platzierung']) : 999999;
            $platzB = isset($b['platzierung']) && $b['platzierung'] !== null ? intval($b['platzierung']) : 999999;
            if ($platzA === $platzB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return $platzA - $platzB; // ASC (Platz 1 vor Platz 2)
        });
        
        $rundenErgebnisse = $alleErgebnisse;

        // Ergebnis für aktuelle Startnummer (Einzelrunde) ermitteln
        if ($startnummerAktuell !== null) {
            foreach ($rundenErgebnisse as $erg) {
                if (intval($erg['startnummer']) === $startnummerAktuell) {
                    if ($erg['punkte'] !== null) {
                        $spielerPunkte = intval($erg['punkte']);
                    }
                    if (isset($erg['platzierung']) && $erg['platzierung'] !== null) {
                        $spielerPlatz = intval($erg['platzierung']);
                    }
                    break;
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Turnier Info<?php echo $aktiveRunde !== null ? ' - Runde ' . $aktiveRunde : ''; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin: 30px 0 15px 0;
        }
        h3 {
            color: #667eea;
            margin: 20px 0 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 25px;
        }
        th:hover {
            background: #5568d3;
        }
        th.sortable {
            padding-right: 25px;
        }
        th.sortable::after {
            content: '⇅';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            opacity: 0.5;
        }
        th.sortable.sort-asc::after {
            content: '↑';
            opacity: 0.9;
        }
        th.sortable.sort-desc::after {
            content: '↓';
            opacity: 0.9;
        }
        tr:hover {
            background: #f9f9f9;
        }
        table[border="1"] {
            border: 1px solid #000;
        }
        table[border="1"] td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info-box {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .welcome-box {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .welcome-box h2 {
            color: white;
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .startnummer-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .startnummer-form input[type="number"] {
            padding: 10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            font-size: 16px;
            width: 150px;
            background: rgba(255,255,255,0.9);
        }
        .startnummer-form input[type="number"]:focus {
            outline: none;
            border-color: white;
            background: white;
        }
        .startnummer-form button {
            padding: 10px 20px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .startnummer-form button:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .startnummer-angezeigt {
            margin-top: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
        }
        .startnummer-hervorgehoben {
            display: inline-block;
            padding: 5px 15px;
            background: white;
            color: #667eea;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            cursor: pointer;
        }
        .startnummer-hervorgehoben:hover {
            background: #f8f8f8;
        }
        .meine-punkte-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .meine-punkte-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        a.meine-punkte-btn {
            text-decoration: none;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .overlay.active {
            display: flex;
        }
        .overlay-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .overlay-content h2 {
            margin-top: 0;
            color: #667eea;
        }
        .overlay-content .form-group {
            margin-bottom: 20px;
        }
        .overlay-content label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .overlay-content input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .overlay-content input:focus {
            outline: none;
            border-color: #667eea;
        }
        .overlay-content .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .overlay-content button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .overlay-content .btn-primary {
            background: #667eea;
            color: white;
        }
        .overlay-content .btn-primary:hover {
            background: #5568d3;
        }
        .overlay-content .btn-secondary {
            background: #ccc;
            color: #333;
        }
        .overlay-content .btn-secondary:hover {
            background: #bbb;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
            display: none;
        }
        .highlighted-cell {
            background-color: #ffeb3b !important;
        }
        .highlighted-row {
            background-color: #ffeb3b !important;
        }
        .highlighted-row:hover {
            background-color: #ffd54f !important;
        }
    </style>
    <script>
        var currentSortColumn = {};
        var currentSortDirection = {};
        
        function sortTable(tableId, columnIndex, sortBy) {
            var table = document.getElementById(tableId);
            if (!table) return;
            
            // Initialisierung, falls noch nicht vorhanden
            if (currentSortColumn[tableId] === undefined) {
                currentSortColumn[tableId] = null;
                currentSortDirection[tableId] = 'asc';
            }
            
            var tbody = table.getElementsByTagName('tbody')[0];
            var rows = Array.from(tbody.getElementsByTagName('tr'));
            var thead = table.getElementsByTagName('thead')[0];
            var headerRow = thead.getElementsByTagName('tr')[0];
            
            // Richtung wechseln, wenn die gleiche Spalte erneut geklickt wird
            var sameColumn = (currentSortColumn[tableId] === columnIndex);
            if (sameColumn) {
                // Gleiche Spalte: Richtung umkehren
                currentSortDirection[tableId] = (currentSortDirection[tableId] === 'asc') ? 'desc' : 'asc';
            } else {
                // Neue Spalte: aufsteigend starten
                currentSortDirection[tableId] = 'asc';
            }
            currentSortColumn[tableId] = columnIndex;
            
            var isAscending = (currentSortDirection[tableId] === 'asc');
            
            // Alle Sortier-Icons zurücksetzen
            var allHeaders = headerRow.getElementsByTagName('th');
            for (var i = 0; i < allHeaders.length; i++) {
                allHeaders[i].classList.remove('sort-asc', 'sort-desc');
            }
            
            // Aktuelles Sortier-Icon setzen
            var currentHeader = headerRow.cells[columnIndex];
            if (currentHeader) {
                currentHeader.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
            }
            
            rows.sort(function(a, b) {
                var aValue, bValue;
                
                if (sortBy === 'startnummer') {
                    aValue = parseInt(a.cells[0].textContent.trim()) || 0;
                    bValue = parseInt(b.cells[0].textContent.trim()) || 0;
                } else if (sortBy === 'platzierung') {
                    var aText = a.cells[columnIndex].textContent.trim();
                    var bText = b.cells[columnIndex].textContent.trim();
                    aValue = aText === '-' ? 999999 : parseInt(aText.replace('.', '')) || 0;
                    bValue = bText === '-' ? 999999 : parseInt(bText.replace('.', '')) || 0;
                } else if (sortBy === 'name') {
                    aValue = a.cells[columnIndex].textContent.trim().toLowerCase();
                    bValue = b.cells[columnIndex].textContent.trim().toLowerCase();
                } else if (sortBy === 'runde') {
                    var aText = a.cells[columnIndex].textContent.trim();
                    var bText = b.cells[columnIndex].textContent.trim();
                    aValue = aText === '-' ? -1 : parseInt(aText) || -1;
                    bValue = bText === '-' ? -1 : parseInt(bText) || -1;
                }
                
                if (sortBy === 'name') {
                    // Text-Vergleich
                    if (aValue === bValue) return 0;
                    return isAscending ? (aValue > bValue ? 1 : -1) : (aValue < bValue ? 1 : -1);
                } else {
                    // Numerischer Vergleich
                    if (aValue === bValue) return 0;
                    return isAscending ? (aValue > bValue ? 1 : -1) : (aValue < bValue ? 1 : -1);
                }
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        }

        // Formular für Startnummer ein-/ausblenden
        document.addEventListener('DOMContentLoaded', function() {
            var hatStartnummer = <?php echo $hatStartnummer ? 'true' : 'false'; ?>;
            var welcomeBox = document.getElementById('welcome-box');
            var klickElement = document.getElementById('startnummer-klick-info');

            if (hatStartnummer && welcomeBox) {
                // Wenn es schon eine Startnummer gibt, welcome-box ausblenden
                welcomeBox.style.display = 'none';
            }

            if (klickElement && welcomeBox) {
                klickElement.addEventListener('click', function() {
                    // Bei Klick auf die Startnummer das Formular zum Ändern wieder anzeigen
                    welcomeBox.style.display = 'block';
                    var input = document.getElementById('startnummer');
                    if (input) {
                        input.focus();
                    }
                });
            }

            // Sekundenzähler starten
            var sekundenzaehler = document.getElementById('sekundenzaehler');
            if (sekundenzaehler) {
                var sekunden = 0;
                setInterval(function() {
                    sekunden++;
                    sekundenzaehler.textContent = sekunden + ' Sekunden';
                }, 1000);
            }
        });

        function openPunkteEingabe() {
            var startnummer = <?php echo $startnummerAktuell !== null ? $startnummerAktuell : 'null'; ?>;
            var aktiveRunde = <?php echo $aktiveRunde !== null ? $aktiveRunde : 'null'; ?>;
            
            // Punkte laden
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '../turnier/punkte_laden.php?startnummer=' + startnummer + '&runde=' + aktiveRunde, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                var punkteInput = document.getElementById('punkte-input');
                                var pinGroup = document.getElementById('pin-group');
                                var speichernBtn = document.getElementById('speichern-btn');
                                
                                if (response.vorhanden && response.punkte !== null) {
                                    // Punkte bereits vorhanden: Feld readonly, PIN ausblenden, Speichern ausblenden
                                    punkteInput.value = response.punkte;
                                    punkteInput.readOnly = true;
                                    punkteInput.style.backgroundColor = '#f0f0f0';
                                    if (pinGroup) pinGroup.style.display = 'none';
                                    if (speichernBtn) speichernBtn.style.display = 'none';
                                } else {
                                    // Keine Punkte: Normale Eingabe
                                    punkteInput.value = '';
                                    punkteInput.readOnly = false;
                                    punkteInput.style.backgroundColor = '';
                                    if (pinGroup) pinGroup.style.display = 'block';
                                    if (speichernBtn) speichernBtn.style.display = 'block';
                                }
                                
                                document.getElementById('punkte-overlay').classList.add('active');
                                if (!punkteInput.readOnly) {
                                    punkteInput.focus();
                                }
                            }
                        } catch (e) {
                            console.error('Fehler beim Laden der Punkte:', e);
                            // Bei Fehler trotzdem Overlay öffnen
                            document.getElementById('punkte-overlay').classList.add('active');
                            document.getElementById('punkte-input').focus();
                        }
                    } else {
                        // Bei Fehler trotzdem Overlay öffnen
                        document.getElementById('punkte-overlay').classList.add('active');
                        document.getElementById('punkte-input').focus();
                    }
                }
            };
            xhr.send();
        }

        function closePunkteEingabe() {
            document.getElementById('punkte-overlay').classList.remove('active');
            document.getElementById('punkte-form').reset();
            document.getElementById('error-message').style.display = 'none';
            
            // Felder zurücksetzen
            var punkteInput = document.getElementById('punkte-input');
            var pinGroup = document.getElementById('pin-group');
            var speichernBtn = document.getElementById('speichern-btn');
            
            punkteInput.readOnly = false;
            punkteInput.style.backgroundColor = '';
            if (pinGroup) pinGroup.style.display = 'block';
            if (speichernBtn) speichernBtn.style.display = 'block';
        }

        function speicherePunkte() {
            var startnummer = <?php echo $startnummerAktuell !== null ? $startnummerAktuell : 'null'; ?>;
            var aktiveRunde = <?php echo $aktiveRunde !== null ? $aktiveRunde : 'null'; ?>;
            var punkte = document.getElementById('punkte-input').value;
            var pin = document.getElementById('pin-input').value;

            if (!punkte || !pin) {
                document.getElementById('error-message').textContent = 'Bitte füllen Sie alle Felder aus.';
                document.getElementById('error-message').style.display = 'block';
                return;
            }

            var formData = new FormData();
            formData.append('startnummer', startnummer);
            formData.append('runde', aktiveRunde);
            formData.append('punkte', punkte);
            formData.append('pin', pin);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../turnier/punkte_eingeben.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert('Punkte erfolgreich gespeichert!');
                                closePunkteEingabe();
                                window.location.reload();
                            } else {
                                document.getElementById('error-message').textContent = response.message || 'Fehler beim Speichern der Punkte.';
                                document.getElementById('error-message').style.display = 'block';
                            }
                        } catch (e) {
                            document.getElementById('error-message').textContent = 'Fehler beim Verarbeiten der Antwort.';
                            document.getElementById('error-message').style.display = 'block';
                        }
                    } else {
                        document.getElementById('error-message').textContent = 'Fehler beim Speichern der Punkte.';
                        document.getElementById('error-message').style.display = 'block';
                    }
                }
            };
            xhr.send(formData);
        }

        // Overlay schließen bei Klick außerhalb
        document.addEventListener('click', function(e) {
            var overlay = document.getElementById('punkte-overlay');
            if (e.target === overlay) {
                closePunkteEingabe();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="welcome-box" id="welcome-box">
            <h2>Willkommen zum Binokel-Turnier!</h2>
            <form method="POST" action="" class="startnummer-form" id="startnummer-form-wrapper">
                <label for="startnummer" style="color: white; font-weight: bold;">Ihre Startnummer:</label>
                <input type="number" id="startnummer" name="startnummer" min="1" placeholder="Startnummer eingeben" value="<?php echo isset($_SESSION['startnummer']) ? htmlspecialchars($_SESSION['startnummer']) : ''; ?>" required>
                <button type="submit">Speichern</button>
            </form>
        </div>
        
        <h1><?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></h1>
        
        <div class="info-box">
            <p style="display: flex; justify-content: space-between; align-items: center;">
                <span><strong>Datum:</strong> <?php echo htmlspecialchars($aktuellesTurnier['datum']); ?></span>
                <span id="sekundenzaehler" style="font-weight: bold; color: #667eea; font-size: 14px;">0 Sekunden</span>
            </p>
            <p><strong>Ort:</strong> <?php echo htmlspecialchars($aktuellesTurnier['ort']); ?></p>
            <?php if (isset($_SESSION['startnummer']) && $_SESSION['startnummer'] !== ''): ?>
                <p>
                    <strong>Startnummer:</strong>
                    <span class="startnummer-hervorgehoben" id="startnummer-klick-info" title="Klicken, um die Startnummer zu ändern">
                        <?php echo htmlspecialchars($_SESSION['startnummer']); ?>
                    </span>
                </p>
                <?php if ($spielerName !== null): ?>
                    <p style="margin-top: 5px; margin-left: 20px; color: #666;">
                        <strong>Name:</strong> <?php echo htmlspecialchars($spielerName); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($aktiveRunde !== null): ?>
                <p>&nbsp;</p>
                <p style="display: flex; align-items: center; justify-content: space-between;">
                    <span><strong>Aktive Runde:</strong> <?php echo $aktiveRunde; ?></span>
                    <?php if ($startnummerAktuell !== null): ?>
                        <a href="punkte_eingabe.php" id="meine-punkte-button" class="meine-punkte-btn" style="text-decoration: none; display: inline-block;">meine Punkte</a>
                    <?php endif; ?>
                </p>
                <?php if ($spielerTisch !== null && $startnummerAktuell !== null): ?>
                    <p>Person <?php echo htmlspecialchars($startnummerAktuell); ?> - Tisch <?php echo htmlspecialchars($spielerTisch); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($aktiveErgebnisRunde !== null && $startnummerAktuell !== null): ?>
                <?php
                    $punkteText = ($spielerPunkte !== null) ? htmlspecialchars($spielerPunkte) : '-';
                    $platzText = ($spielerPlatz !== null) ? htmlspecialchars($spielerPlatz) : '-';
                ?>
                <?php if ($aktiveErgebnisRunde == 0): ?>
                    <p>&nbsp;</p>
                    <p><strong>Auswertung Gesamt:</strong></p>
                    <p>Gesamt: Punkte <?php echo $punkteText; ?> - Platz <?php echo $platzText; ?></p>
                <?php else: ?>
                    <p>&nbsp;</p>
                    <p><strong>Auswertung Runde <?php echo htmlspecialchars($aktiveErgebnisRunde); ?>:</strong></p>
                    <p>Runde <?php echo htmlspecialchars($aktiveErgebnisRunde); ?>:<br> Punkte <?php echo $punkteText; ?> - Platz <?php echo $platzText; ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($aktiveRunde !== null): ?>
            <h2>Person an Tisch - Runde: <?php echo $aktiveRunde; ?></h2>
            <table border="1">
                <?php
                $spaltenProZeile = 5;
                $spalte = 0;
                foreach ($personenZuordnung as $zuordnung):
                    if ($spalte == 0) {
                        echo '<tr>';
                    }
                    $isHighlighted = ($startnummerAktuell !== null && intval($zuordnung['spieler_id']) === $startnummerAktuell);
                    $highlightClass = $isHighlighted ? ' highlighted-cell' : '';
                    echo '<td class="' . $highlightClass . '" style="padding: 8px;"><strong>Person: ' . htmlspecialchars($zuordnung['spieler_id']) . '</strong> Tisch ' . htmlspecialchars($zuordnung['tisch']) . '</td>';
                    $spalte++;
                    if ($spalte >= $spaltenProZeile) {
                        echo '</tr>';
                        $spalte = 0;
                    }
                endforeach;
                if ($spalte > 0) {
                    echo '</tr>';
                }
                ?>
            </table>
            
            <h2>Tischpartner - Runde: <?php echo $aktiveRunde; ?></h2>
            <table border="1">
                <?php
                $tischeProZeile = 5;
                $tischZaehler = 0;
                $zeileGestartet = false;
                
                foreach ($tischeGruppiert as $tisch => $spieler):
                    if ($tischZaehler % $tischeProZeile == 0) {
                        if ($zeileGestartet) {
                            echo '</tr>';
                        }
                        echo '<tr>';
                        $zeileGestartet = true;
                    }
                    
                    $isHighlighted = false;
                    if ($startnummerAktuell !== null) {
                        foreach ($spieler as $spielerId) {
                            if (intval($spielerId) === $startnummerAktuell) {
                                $isHighlighted = true;
                                break;
                            }
                        }
                    }
                    $highlightClass = $isHighlighted ? ' highlighted-cell' : '';
                    echo '<td class="' . $highlightClass . '" style="padding: 10px; vertical-align: top;">';
                    echo '<h3 style="margin: 0 0 10px 0; color: #667eea;">Tisch ' . htmlspecialchars($tisch) . '</h3>';
                    echo '<p style="margin: 0;">Person: ';
                    
                    $spielerListe = [];
                    foreach ($spieler as $spielerId) {
                        $spielerListe[] = htmlspecialchars($spielerId);
                    }
                    echo implode(', ', $spielerListe);
                    
                    echo '</p>';
                    echo '</td>';
                    
                    $tischZaehler++;
                endforeach;
                
                // Letzte Zeile schließen
                if ($zeileGestartet) {
                    $restZellen = $tischeProZeile - ($tischZaehler % $tischeProZeile);
                    if ($restZellen < $tischeProZeile && $restZellen > 0) {
                        for ($i = 0; $i < $restZellen; $i++) {
                            echo '<td></td>';
                        }
                    }
                    echo '</tr>';
                }
                ?>
            </table>
        <?php endif; ?>
        
        <?php if ($aktiveErgebnisRunde !== null): ?>
            <?php if ($aktiveErgebnisRunde == 0): ?>
                <h2>Gesamtergebnisse</h2>
                <table border="1" id="tabelle-gesamt">
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortTable('tabelle-gesamt', 0, 'startnummer')">St.Nr</th>
                            <th class="sortable" onclick="sortTable('tabelle-gesamt', 1, 'name')">Name</th>
                            <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                                <th class="sortable" onclick="sortTable('tabelle-gesamt', <?php echo 1 + $i; ?>, 'runde')">Runde <?php echo $i; ?></th>
                            <?php endfor; ?>
                            <th>Gesamt</th>
                            <th class="sortable" onclick="sortTable('tabelle-gesamt', <?php echo 2 + $anzahlRunden + 1; ?>, 'platzierung')">Platz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ergebnisseMitRunden as $ergebnis): ?>
                            <?php $isHighlighted = ($startnummerAktuell !== null && intval($ergebnis['startnummer']) === $startnummerAktuell); ?>
                            <tr<?php echo $isHighlighted ? ' class="highlighted-row"' : ''; ?>>
                                <td><?php echo htmlspecialchars($ergebnis['startnummer']); ?></td>
                                <td><?php echo (isset($ergebnis['name_auf_wertungsliste']) && $ergebnis['name_auf_wertungsliste'] == 1) ? htmlspecialchars($ergebnis['name']) : '-'; ?></td>
                                <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                                    <td><?php echo isset($ergebnis['runde' . $i . '_punkte']) && $ergebnis['runde' . $i . '_punkte'] !== null ? htmlspecialchars($ergebnis['runde' . $i . '_punkte']) : '-'; ?></td>
                                <?php endfor; ?>
                                <td><?php echo $ergebnis['gesamtpunkte'] !== null ? htmlspecialchars($ergebnis['gesamtpunkte']) : '-'; ?></td>
                                <td><?php echo $ergebnis['platzierung'] !== null ? htmlspecialchars($ergebnis['platzierung']) . '.' : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <h2>Ergebnisse Runde <?php echo intval($aktiveErgebnisRunde); ?></h2>
                <table border="1" id="tabelle-runde">
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortTable('tabelle-runde', 0, 'startnummer')">St.Nr</th>
                            <th class="sortable" onclick="sortTable('tabelle-runde', 1, 'name')">Name</th>
                            <th class="sortable" onclick="sortTable('tabelle-runde', 2, 'runde')">Punktzahl</th>
                            <th class="sortable" onclick="sortTable('tabelle-runde', 3, 'platzierung')">Platz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rundenErgebnisse as $ergebnis): ?>
                            <?php $isHighlighted = ($startnummerAktuell !== null && intval($ergebnis['startnummer']) === $startnummerAktuell); ?>
                            <tr<?php echo $isHighlighted ? ' class="highlighted-row"' : ''; ?>>
                                <td><?php echo htmlspecialchars($ergebnis['startnummer']); ?></td>
                                <td><?php echo (isset($ergebnis['name_auf_wertungsliste']) && $ergebnis['name_auf_wertungsliste'] == 1) ? htmlspecialchars($ergebnis['name']) : '-'; ?></td>
                                <td><?php echo $ergebnis['punkte'] !== null ? htmlspecialchars($ergebnis['punkte']) : '-'; ?></td>
                                <td><?php echo $ergebnis['platzierung'] !== null ? htmlspecialchars($ergebnis['platzierung']) . '.' : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
