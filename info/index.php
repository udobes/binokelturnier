<?php
require_once __DIR__ . '/../turnier/config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    ?><!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
}

// Ergebnisse laden, falls aktiviert
$aktiveErgebnisRunde = isset($aktuellesTurnier['aktive_ergebnis_runde']) ? $aktuellesTurnier['aktive_ergebnis_runde'] : null;
$ergebnisseMitRunden = [];
$rundenErgebnisse = [];
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);

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
        
        // Zurück nach Gesamtpunkten sortieren
        usort($ergebnisseMitRunden, function($a, $b) {
            $punkteA = $a['gesamtpunkte'] !== null ? intval($a['gesamtpunkte']) : -1;
            $punkteB = $b['gesamtpunkte'] !== null ? intval($b['gesamtpunkte']) : -1;
            if ($punkteA === $punkteB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return ($punkteB - $punkteA); // DESC
        });
    } else {
        // Ergebnisse für eine spezifische Runde
        $runde = intval($aktiveErgebnisRunde);
        
        // Alle Registrierungen laden
        $stmt = $db->prepare("
            SELECT tr.startnummer, a.name 
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
        
        // Nach Startnummer sortieren für Anzeige
        usort($alleErgebnisse, function($a, $b) {
            return $a['startnummer'] - $b['startnummer'];
        });
        
        $rundenErgebnisse = $alleErgebnisse;
        
        // Platzierungen zuweisen
        foreach ($rundenErgebnisse as &$ergebnis) {
            $ergebnis['platzierung'] = $platzierungen[$ergebnis['startnummer']] ?? null;
        }
        unset($ergebnis);
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
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
    </script>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></h1>
        
        <div class="info-box">
            <p><strong>Datum:</strong> <?php echo htmlspecialchars($aktuellesTurnier['datum']); ?></p>
            <p><strong>Ort:</strong> <?php echo htmlspecialchars($aktuellesTurnier['ort']); ?></p>
            <?php if ($aktiveRunde !== null): ?>
                <p><strong>Aktive Runde:</strong> <?php echo $aktiveRunde; ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($aktiveRunde !== null): ?>
            <h2>Personenzuordnung Runde <?php echo $aktiveRunde; ?></h2>
            <table border="1">
                <?php
                $spaltenProZeile = 5;
                $spalte = 0;
                foreach ($personenZuordnung as $zuordnung):
                    if ($spalte == 0) {
                        echo '<tr>';
                    }
                    echo '<td style="padding: 8px;"><strong>Person: ' . htmlspecialchars($zuordnung['spieler_id']) . '</strong> Tisch ' . htmlspecialchars($zuordnung['tisch']) . '</td>';
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
            
            <h2>Tischzuordnung Runde <?php echo $aktiveRunde; ?></h2>
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
                    
                    echo '<td style="padding: 10px; vertical-align: top;">';
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
                            <tr>
                                <td><?php echo htmlspecialchars($ergebnis['startnummer']); ?></td>
                                <td><?php echo htmlspecialchars($ergebnis['name']); ?></td>
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
                            <tr>
                                <td><?php echo htmlspecialchars($ergebnis['startnummer']); ?></td>
                                <td><?php echo htmlspecialchars($ergebnis['name']); ?></td>
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
