<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
$gewaehlteRunde = isset($_GET['runde']) ? intval($_GET['runde']) : (isset($_POST['runde']) ? intval($_POST['runde']) : 1);
$anzahlRunden = $aktuellesTurnier ? intval($aktuellesTurnier['anzahl_runden'] ?? 3) : 3;
$sortierung = isset($_GET['sort']) ? $_GET['sort'] : 'punktzahl';

// Validierung der gewählten Runde
if ($gewaehlteRunde < 1 || $gewaehlteRunde > $anzahlRunden) {
    $gewaehlteRunde = 1;
}

$db = getDB();
if (!in_array($sortierung, ['startnummer', 'punktzahl', 'eingabe'])) {
    $sortierung = 'punktzahl';
}


// Verarbeitung der Eingabe
$successMessage = null;
$errorMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'punkte_speichern') {
    $startnummer = intval($_POST['startnummer'] ?? 0);
    $runde = intval($_POST['runde'] ?? 0);
    $punkte = isset($_POST['punkte']) && $_POST['punkte'] !== '' ? intval($_POST['punkte']) : null;
    
    if ($startnummer > 0 && $runde >= 1 && $runde <= $anzahlRunden) {
        // Prüfen, ob Teilnehmer existiert (nur ID prüfen)
        $stmt = $db->prepare("SELECT id FROM turnier_registrierungen WHERE turnier_id = ? AND startnummer = ?");
        $stmt->execute([$aktuellesTurnier['id'], $startnummer]);
        $registrierung = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registrierung) {
            // Ergebnis in neue Tabelle speichern
            try {
                speichereErgebnis($aktuellesTurnier['id'], $runde, $startnummer, $punkte);
                
                // Weiterleitung mit beibehaltenen Parametern
                $sortParam = isset($_POST['sort']) ? $_POST['sort'] : $sortierung;
                header('Location: spielrunde.php?runde=' . $runde . '&sort=' . $sortParam . '&success=1');
                exit;
            } catch (Exception $e) {
                error_log("Fehler beim Speichern: " . $e->getMessage());
                $errorMessage = "Fehler beim Speichern der Punkte: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Teilnehmer nicht gefunden!";
        }
    }
}

// Erfolgsmeldung aus URL-Parameter
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $successMessage = "Punkte erfolgreich gespeichert!";
}

// Daten für die Runden-Tabelle laden
$rundenErgebnisse = [];
if ($aktuellesTurnier) {
    // Alle Registrierungen laden (mit JOIN zu anmeldungen für Name)
    $stmt = $db->prepare("
        SELECT tr.startnummer, a.name 
        FROM turnier_registrierungen tr
        LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
        WHERE tr.turnier_id = ?
    ");
    $stmt->execute([$aktuellesTurnier['id']]);
    $alleRegistrierungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ergebnisse für die gewählte Runde laden
    $alleErgebnisse = [];
    foreach ($alleRegistrierungen as $reg) {
        $ergebnis = getErgebnis($aktuellesTurnier['id'], $gewaehlteRunde, $reg['startnummer']);
        $alleErgebnisse[] = [
            'startnummer' => $reg['startnummer'],
            'name' => $reg['name'],
            'punkte' => $ergebnis ? $ergebnis['punkte'] : null,
            'eingetragen_am' => $ergebnis ? $ergebnis['geaendert_am'] : null
        ];
    }
    
    // Platzierung berechnen (immer nach Punktzahl, unabhängig von Sortierung)
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
    
    // Nach gewählter Sortierung sortieren
    if ($sortierung === 'punktzahl') {
        usort($alleErgebnisse, function($a, $b) {
            $punkteA = $a['punkte'] !== null ? intval($a['punkte']) : -1;
            $punkteB = $b['punkte'] !== null ? intval($b['punkte']) : -1;
            if ($punkteA === $punkteB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return ($punkteB - $punkteA); // DESC
        });
    } elseif ($sortierung === 'eingabe') {
        usort($alleErgebnisse, function($a, $b) {
            $zeitA = $a['eingetragen_am'] ? strtotime($a['eingetragen_am']) : 0;
            $zeitB = $b['eingetragen_am'] ? strtotime($b['eingetragen_am']) : 0;
            if ($zeitA === $zeitB) {
                return $a['startnummer'] - $b['startnummer'];
            }
            return ($zeitB - $zeitA); // DESC (neueste zuerst)
        });
    } else {
        // Nach Startnummer sortieren
        usort($alleErgebnisse, function($a, $b) {
            return $a['startnummer'] - $b['startnummer'];
        });
    }
    
    $rundenErgebnisse = $alleErgebnisse;
    
    // Platzierungen zuweisen
    foreach ($rundenErgebnisse as &$ergebnis) {
        $ergebnis['platzierung'] = $platzierungen[$ergebnis['startnummer']] ?? null;
    }
    unset($ergebnis);
    
    // Anzahl der eingetragenen Ergebnisse für diese Runde
    $anzahlEingetragen = 0;
    foreach ($rundenErgebnisse as $ergebnis) {
        if ($ergebnis['punkte'] !== null) {
            $anzahlEingetragen++;
        }
    }
    
    // Gesamtanzahl der Spieler
    $stmt = $db->prepare("SELECT COUNT(*) as anzahl FROM turnier_registrierungen WHERE turnier_id = ?");
    $stmt->execute([$aktuellesTurnier['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $anzahlSpieler = $result['anzahl'] ?? 0;
    
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Spielrunde</title>
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
        .runde-auswahl {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .runde-auswahl label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
        }
        .runde-auswahl select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            background: white;
        }
        .eingabe-bereich {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .eingabe-bereich h2 {
            margin-top: 0;
            color: #667eea;
        }
        .eingabe-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .form-group input.bestaetigt {
            background-color: #ffcccc;
        }
        .name-anzeige {
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            min-height: 42px;
            display: flex;
            align-items: center;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background: #5568d3;
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
        .tabelle-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
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
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
    <script>
        function rundeWechseln() {
            var runde = document.getElementById('runde-select').value;
            window.location.href = 'spielrunde.php?runde=' + runde;
        }
        
        function sortierungRundeWechseln() {
            var sort = document.getElementById('sort-runde-select').value;
            var runde = document.getElementById('runde-select').value;
            window.location.href = 'spielrunde.php?runde=' + runde + '&sort=' + sort;
        }
        
        function druckeTabelle(runde) {
            var tabelleElement = document.getElementById('tabelle-runde-' + runde);
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
            printWindow.document.write('<title>Ergebnis-Tabelle Runde ' + runde + '</title>');
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
            printWindow.document.write('<h2>Ergebnisse Runde ' + runde + '</h2>');
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
        
        function ladeTeilnehmer() {
            var startnummer = document.getElementById('startnummer').value;
            if (!startnummer || startnummer < 1) {
                document.getElementById('name-anzeige').textContent = '';
                document.getElementById('punkte').value = '';
                document.getElementById('punkte').classList.remove('bestaetigt');
                return;
            }
            
            var runde = document.getElementById('runde-select').value;
            if (runde === 'ergebnisse') {
                return;
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'spielrunde_lade_teilnehmer.php?startnummer=' + startnummer + '&runde=' + runde, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.success) {
                                document.getElementById('name-anzeige').textContent = data.name;
                                if (data.punkte !== null && data.punkte !== undefined) {
                                    document.getElementById('punkte').value = data.punkte;
                                    document.getElementById('punkte').classList.add('bestaetigt');
                                } else {
                                    document.getElementById('punkte').value = '';
                                    document.getElementById('punkte').classList.remove('bestaetigt');
                                }
                            } else {
                                document.getElementById('name-anzeige').textContent = data.error || 'Teilnehmer nicht gefunden';
                                document.getElementById('punkte').value = '';
                                document.getElementById('punkte').classList.remove('bestaetigt');
                            }
                        } catch (e) {
                            document.getElementById('name-anzeige').textContent = 'Fehler beim Laden';
                        }
                    }
                }
            };
            xhr.send();
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Spielrunde</h1>
        
        <?php if ($successMessage): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        
        <?php if (!$aktuellesTurnier): ?>
            <div class="info" style="background: #f8d7da; border-left-color: #dc3545;">
                <p style="color: #721c24;">Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.</p>
            </div>
        <?php else: ?>
            <!-- Rundenauswahl am Anfang -->
            <div class="runde-auswahl">
                <label for="runde-select">Runde auswählen:</label>
                <select id="runde-select" name="runde" onchange="rundeWechseln()">
                    <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($gewaehlteRunde == $i) ? 'selected' : ''; ?>>
                            Runde <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if ($aktuellesTurnier): ?>
                <!-- Eingabebereich -->
                <div class="eingabe-bereich">
                    <h2>Punkteeingabe für Runde <?php echo $gewaehlteRunde; ?></h2>
                    <form method="POST" action="spielrunde.php">
                        <input type="hidden" name="action" value="punkte_speichern">
                        <input type="hidden" name="runde" value="<?php echo $gewaehlteRunde; ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortierung); ?>">
                        <div class="eingabe-form">
                            <div class="form-group">
                                <label for="startnummer">Spielernummer:</label>
                                <input type="number" id="startnummer" name="startnummer" min="1" onchange="ladeTeilnehmer()" onkeyup="ladeTeilnehmer()" required>
                            </div>
                            <div class="form-group">
                                <label>Name:</label>
                                <div id="name-anzeige" class="name-anzeige"></div>
                            </div>
                            <div class="form-group">
                                <label for="punkte">Gesamtpunktzahl Runde <?php echo $gewaehlteRunde; ?>:</label>
                                <input type="number" id="punkte" name="punkte" min="0" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn">Speichern</button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Tabelle mit Ergebnissen der Runde -->
                    <div style="margin-top: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0; color: #667eea;">Ergebnisse Runde <?php echo $gewaehlteRunde; ?></h3>
                            <div class="sortierung">
                                <label for="sort-runde-select">Sortierung:</label>
                                <select id="sort-runde-select" onchange="sortierungRundeWechseln()">
                                    <option value="punktzahl" <?php echo $sortierung === 'punktzahl' ? 'selected' : ''; ?>>Punktzahl</option>
                                    <option value="startnummer" <?php echo $sortierung === 'startnummer' ? 'selected' : ''; ?>>Startnummer</option>
                                    <option value="eingabe" <?php echo $sortierung === 'eingabe' ? 'selected' : ''; ?>>Reihenfolge der Eingabe</option>
                                </select>
                            </div>
                        </div>
                        <div class="tabelle-info">
                            <div>
                                <strong>Anzahl Spieler im Turnier: <?php echo $anzahlSpieler; ?> / Anzahl eingetragener Ergebnisse: <?php echo $anzahlEingetragen; ?></strong>
                            </div>
                            <button type="button" class="btn-print" onclick="druckeTabelle('runde', <?php echo $gewaehlteRunde; ?>)">Drucken</button>
                        </div>
                        <table id="tabelle-runde-<?php echo $gewaehlteRunde; ?>">
                            <thead>
                                <tr>
                                    <th>Startnummer</th>
                                    <th>Name</th>
                                    <th>Punktzahl</th>
                                    <th>Platzierung</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rundenErgebnisse)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #999;">Noch keine Ergebnisse für diese Runde</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rundenErgebnisse as $ergebnis): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ergebnis['startnummer']); ?></td>
                                            <td><?php echo htmlspecialchars($ergebnis['name']); ?></td>
                                            <td><?php echo $ergebnis['punkte'] !== null ? htmlspecialchars($ergebnis['punkte']) : '-'; ?></td>
                                            <td><?php echo $ergebnis['platzierung'] !== null ? htmlspecialchars($ergebnis['platzierung']) . '.' : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
