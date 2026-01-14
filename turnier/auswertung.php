<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
$anzahlRunden = $aktuellesTurnier ? intval($aktuellesTurnier['anzahl_runden'] ?? 3) : 3;
$sortierung = isset($_GET['sort']) ? $_GET['sort'] : 'punktzahl';
if (!in_array($sortierung, ['startnummer', 'punktzahl'])) {
    $sortierung = 'punktzahl';
}

$aktiveErgebnisRunde = null;
if ($aktuellesTurnier) {
    $aktiveErgebnisRunde = isset($aktuellesTurnier['aktive_ergebnis_runde']) ? $aktuellesTurnier['aktive_ergebnis_runde'] : null;
}

$db = getDB();
$ergebnisseMitRunden = [];
$anzahlMitPunkten = 0;
$anzahlSpielerGesamt = 0;

if ($aktuellesTurnier) {
    // Gesamtanzahl der Spieler
    $stmt = $db->prepare("SELECT COUNT(*) as anzahl FROM turnier_registrierungen WHERE turnier_id = ?");
    $stmt->execute([$aktuellesTurnier['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $anzahlSpielerGesamt = $result['anzahl'] ?? 0;
    
    // Alle Rundendaten laden (aus neuer Tabelle)
    $ergebnisseMitRunden = getErgebnisseFuerTurnier($aktuellesTurnier['id'], $anzahlRunden);
    
    // Sortierung anwenden
    if ($sortierung === 'punktzahl') {
        usort($ergebnisseMitRunden, function($a, $b) {
            $punkteA = $a['gesamtpunkte'] !== null ? intval($a['gesamtpunkte']) : -1;
            $punkteB = $b['gesamtpunkte'] !== null ? intval($b['gesamtpunkte']) : -1;
            if ($punkteA === $punkteB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return ($punkteB - $punkteA); // DESC
        });
    } else {
        // Nach Startnummer sortieren
        usort($ergebnisseMitRunden, function($a, $b) {
            return $a['startnummer'] - $b['startnummer'];
        });
    }
    
    // Platzierung berechnen (immer nach Gesamtpunkten, unabhängig von Sortierung)
    $alleFuerPlatzierung = [];
    $stmt = $db->prepare("SELECT startnummer, gesamtpunkte FROM turnier_registrierungen WHERE turnier_id = ? ORDER BY gesamtpunkte DESC, startnummer ASC");
    $stmt->execute([$aktuellesTurnier['id']]);
    $alleFuerPlatzierung = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $aktuellePlatzierung = 1;
    $letztePunkte = null;
    $platzierungen = [];
    foreach ($alleFuerPlatzierung as $ergebnis) {
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
        if ($ergebnis['gesamtpunkte'] !== null && $ergebnis['gesamtpunkte'] !== '') {
            $anzahlMitPunkten++;
        }
    }
    unset($ergebnis);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Auswertung</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #2d5016;
            background-image: url('../img/Binokel_Hintergrund.png');
            background-size: contain;
            background-position: center top;
            background-repeat: no-repeat;
            background-attachment: fixed;
            padding: 20px;
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
        h2 {
            color: #333;
            margin: 30px 0 15px 0;
        }
        .info {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .tabelle-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .sortierung {
            margin-bottom: 15px;
        }
        .sortierung label {
            margin-right: 10px;
        }
        .sortierung select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .btn-print {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-print:hover {
            background: #218838;
        }
        .aktivierung {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .aktivierung h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .aktivierung-optionen {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .aktivierung-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aktivierung-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .aktivierung-option label {
            cursor: pointer;
            font-size: 14px;
        }
        @media print {
            body > *:not(.print-tabelle) {
                display: none !important;
            }
            .print-tabelle {
                display: block !important;
            }
            .btn-print {
                display: none !important;
            }
        }
    </style>
    <script>
        function sortierungWechseln() {
            var sort = document.getElementById('sort-select').value;
            window.location.href = 'auswertung.php?sort=' + sort;
        }
        
        function aktiviereErgebnisRunde(runde) {
            var formData = new FormData();
            formData.append('runde', runde);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'ergebnis_runde_aktivieren.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Seite neu laden, um aktualisierten Status anzuzeigen
                                window.location.reload();
                            } else {
                                alert('Fehler: ' + response.message);
                            }
                        } catch (e) {
                            alert('Fehler beim Verarbeiten der Antwort');
                        }
                    } else {
                        alert('Fehler beim Aktivieren der Ergebnisrunde');
                    }
                }
            };
            xhr.send(formData);
        }
        
        function druckeTabelle() {
            var tabelleElement = document.getElementById('tabelle-ergebnisse');
            if (!tabelleElement) {
                alert('Tabelle nicht gefunden');
                return;
            }
            
            // HTML-Inhalt der Tabelle kopieren (inkl. thead)
            var tabelleHTML = tabelleElement.outerHTML;
            
            // Neues Fenster öffnen
            var printWindow = window.open('', '_blank', 'width=1200,height=800');
            
            // HTML für Druck erstellen
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<title>Gesamtergebnisse</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h2 { color: #667eea; margin-bottom: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }');
            printWindow.document.write('th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }');
            printWindow.document.write('th { background: #667eea; color: white; font-weight: bold; }');
            printWindow.document.write('tr:hover { background: #f9f9f9; }');
            printWindow.document.write('@media print { body { margin: 0; padding: 10px; } @page { size: A4 landscape; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h2>Gesamtergebnisse</h2>');
            printWindow.document.write('<div class="print-tabelle">');
            printWindow.document.write(tabelleHTML);
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Warten bis Inhalt geladen ist, dann drucken
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            };
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Auswertung</h1>
        
        <?php if (!$aktuellesTurnier): ?>
            <div class="info" style="background: #f8d7da; border-left: 4px solid #dc3545;">
                <p style="color: #721c24;">Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.</p>
            </div>
        <?php else: ?>
            <!-- Aktivierungsbereich -->
            <div class="aktivierung">
                <h3>Anzeige auf Infoseite aktivieren</h3>
                <div class="aktivierung-optionen">
                    <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                        <div class="aktivierung-option">
                            <input type="radio" name="aktive_ergebnis_runde" 
                                   value="<?php echo $i; ?>" 
                                   id="runde-<?php echo $i; ?>"
                                   <?php echo ($aktiveErgebnisRunde !== null && $aktiveErgebnisRunde == $i) ? 'checked' : ''; ?>
                                   onchange="aktiviereErgebnisRunde(<?php echo $i; ?>)">
                            <label for="runde-<?php echo $i; ?>" onclick="document.getElementById('runde-<?php echo $i; ?>').click();">
                                Runde <?php echo $i; ?> aktivieren
                            </label>
                        </div>
                    <?php endfor; ?>
                    <div class="aktivierung-option">
                        <input type="radio" name="aktive_ergebnis_runde" 
                               value="0" 
                               id="ergebnisse"
                               <?php echo ($aktiveErgebnisRunde !== null && $aktiveErgebnisRunde == 0) ? 'checked' : ''; ?>
                               onchange="aktiviereErgebnisRunde(0)">
                        <label for="ergebnisse" onclick="document.getElementById('ergebnisse').click();">
                            Ergebnisse aktivieren
                        </label>
                    </div>
                    <?php if ($aktiveErgebnisRunde !== null): ?>
                        <div class="aktivierung-option">
                            <button type="button" onclick="aktiviereErgebnisRunde(null)" style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer;">
                                Deaktivieren
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <h2>Ergebnisse</h2>
            <div class="tabelle-info">
                <div>
                    <strong>Anzahl Spieler im Turnier: <?php echo $anzahlSpielerGesamt; ?> / Anzahl eingetragener Ergebnisse: <?php echo $anzahlMitPunkten; ?></strong>
                </div>
                <button type="button" class="btn-print" onclick="druckeTabelle()">Drucken</button>
            </div>
            <div class="sortierung">
                <label for="sort-select">Sortierung:</label>
                <select id="sort-select" onchange="sortierungWechseln()">
                    <option value="punktzahl" <?php echo $sortierung === 'punktzahl' ? 'selected' : ''; ?>>Punktzahl</option>
                    <option value="startnummer" <?php echo $sortierung === 'startnummer' ? 'selected' : ''; ?>>Startnummer</option>
                </select>
            </div>
            <table id="tabelle-ergebnisse">
                <thead>
                    <tr>
                        <th>Startnummer</th>
                        <th>Name</th>
                        <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                            <th>Runde <?php echo $i; ?></th>
                        <?php endfor; ?>
                        <th>Gesamt</th>
                        <th>Platzierung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ergebnisseMitRunden)): ?>
                        <tr>
                            <td colspan="<?php echo 2 + $anzahlRunden + 2; ?>" style="text-align: center; color: #999;">Keine Ergebnisse verfügbar</td>
                        </tr>
                    <?php else: ?>
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
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
