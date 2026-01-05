<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
$success = isset($_GET['success']) ? $_GET['success'] : false;
$error = null;

// Prüfung der Turnierparameter beim Laden der Seite
$parameterOk = true;
$parameterFehler = [];
if ($aktuellesTurnier) {
    $anzahlTeilnehmer = intval($aktuellesTurnier['anzahl_spieler']);
    $anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
    $spielerProRunde = intval($aktuellesTurnier['spieler_pro_runde'] ?? 3);
    
    // Prüfung: Anzahl Teilnehmer muss > 0 sein
    if ($anzahlTeilnehmer < 1) {
        $parameterOk = false;
        $parameterFehler[] = "Die Anzahl der Teilnehmer muss mindestens 1 sein.";
    }
    
    // Prüfung: Anzahl Runden muss > 0 sein
    if ($anzahlRunden < 1) {
        $parameterOk = false;
        $parameterFehler[] = "Die Anzahl der Runden muss mindestens 1 sein.";
    }
    
    // Prüfung: Spieler pro Runde muss >= 2 sein
    if ($spielerProRunde < 2) {
        $parameterOk = false;
        $parameterFehler[] = "Die Anzahl Spieler pro Runde muss mindestens 2 sein.";
    }
    
    // Prüfung: Anzahl Teilnehmer muss durch Spieler pro Runde teilbar sein
    if ($anzahlTeilnehmer > 0 && $spielerProRunde > 0 && $anzahlTeilnehmer % $spielerProRunde != 0) {
        $parameterOk = false;
        $rest = $anzahlTeilnehmer % $spielerProRunde;
        $parameterFehler[] = "Die Anzahl der Teilnehmer ($anzahlTeilnehmer) muss ganzzahlig durch die Anzahl Spieler pro Runde ($spielerProRunde) teilbar sein. Rest: $rest.";
    }
}

// Prüfen ob Turnier gestartet werden soll
if (isset($_POST['start_turnier'])) {
    if (!$aktuellesTurnier) {
        $error = "Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.";
        } else {
            // Parameter aus dem Turnier holen
            $erfassteAnzahl = intval($aktuellesTurnier['anzahl_spieler']);
            $anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
            $spielerProGruppe = intval($aktuellesTurnier['spieler_pro_runde'] ?? 3);
            
            // Umfassende Validierung der Parameter
            $validierungsFehler = [];
            
            if ($erfassteAnzahl < 1) {
                $validierungsFehler[] = "Die Anzahl der Teilnehmer muss mindestens 1 sein.";
            }
            
            if ($anzahlRunden < 1) {
                $validierungsFehler[] = "Die Anzahl der Runden muss mindestens 1 sein.";
            }
            
            if ($spielerProGruppe < 2) {
                $validierungsFehler[] = "Die Anzahl Spieler pro Runde muss mindestens 2 sein.";
            }
            
            if ($erfassteAnzahl > 0 && $spielerProGruppe > 0 && $erfassteAnzahl % $spielerProGruppe != 0) {
                $rest = $erfassteAnzahl % $spielerProGruppe;
                $validierungsFehler[] = "Die Anzahl Teilnehmer ($erfassteAnzahl) muss ganzzahlig durch die Anzahl Spieler pro Runde ($spielerProGruppe) teilbar sein. Rest: $rest.";
            }
            
            if (!empty($validierungsFehler)) {
                $error = implode(" ", $validierungsFehler);
            } else {
                // Alle Parameter sind korrekt - Turnier starten
                header('Location: turnier_berechnen.php');
                exit;
            }
        }
}

// Prüfen ob bereits Zuordnungen existieren
$hatZuordnungen = false;
if ($aktuellesTurnier) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM turnier_zuordnungen WHERE turnier_id = ?");
    $stmt->execute([$aktuellesTurnier['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hatZuordnungen = ($result['count'] > 0);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Turnier starten</title>
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
            max-width: 1000px;
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
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        .info-box {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        }
        tr:hover {
            background: #f9f9f9;
        }
        .runde-container {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fafafa;
        }
        .runde-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            cursor: pointer;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .runde-header:hover {
            background: #dee2e6;
        }
        .runde-header h3 {
            margin: 0;
            color: #667eea;
        }
        .runde-toggle {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            user-select: none;
        }
        .runde-content {
            display: none;
            margin-top: 15px;
        }
        .runde-content.expanded {
            display: block;
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
            margin-bottom: 15px;
        }
        .btn-print:hover {
            background: #218838;
        }
        .no-print {
            display: none !important;
        }
        @media print {
            body {
                margin: 0;
                padding: 20px;
                background: white !important;
            }
            body > *:not(.print-content) {
                display: none !important;
            }
            .print-content {
                display: block !important;
            }
            .print-content .btn-print {
                display: none !important;
            }
            .runde-container {
                border: none !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                page-break-after: always;
            }
            .container {
                box-shadow: none !important;
                padding: 0 !important;
                background: white !important;
                border: none !important;
            }
            table {
                page-break-inside: avoid;
            }
        }
    </style>
    <script>
        function toggleRunde(rundeId) {
            var content = document.getElementById('content-' + rundeId);
            var toggle = document.getElementById('toggle-' + rundeId);
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                toggle.textContent = '▼';
            } else {
                content.classList.add('expanded');
                toggle.textContent = '▲';
            }
        }
        
        function toggleKontrolle() {
            var content = document.getElementById('content-kontrolle');
            var toggle = document.getElementById('toggle-kontrolle');
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                toggle.textContent = '▼';
            } else {
                content.classList.add('expanded');
                toggle.textContent = '▲';
            }
        }
        
        function aktiviereRunde(rundeId) {
            // AJAX-Request zum Aktivieren der Runde
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'runde_aktivieren.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        // Seite neu laden, um den aktiven Status zu aktualisieren
                        window.location.reload();
                    } else {
                        alert('Fehler beim Aktivieren der Runde');
                    }
                }
            };
            xhr.send('runde=' + rundeId);
        }
        
        function druckePersonenzuordnung(rundeId) {
            var personenElement = document.getElementById('personenzuordnung-' + rundeId);
            if (!personenElement) {
                alert('Personenzuordnung nicht gefunden');
                return;
            }
            
            // HTML-Inhalt kopieren
            var personenHTML = personenElement.innerHTML;
            
            // Button entfernen, aber Überschrift behalten
            personenHTML = personenHTML.replace(/<button[^>]*class="btn-print"[^>]*>.*?<\/button>/gi, '');
            // Entferne den flex-Container, behalte aber die Überschrift
            personenHTML = personenHTML.replace(/<div[^>]*style="[^"]*display:\s*flex[^"]*"[^>]*>/gi, '<div>');
            personenHTML = personenHTML.replace(/<\/div>\s*<table/g, '<table');
            
            // Neues Fenster öffnen
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // HTML für Druck erstellen
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<title>Personenzuordnung Runde ' + rundeId + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h3 { color: #667eea; margin-bottom: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }');
            printWindow.document.write('table[border="1"] { border: 1px solid #000; }');
            printWindow.document.write('table[border="1"] td { padding: 8px; border: 1px solid #ddd; }');
            printWindow.document.write('@media print { body { margin: 0; padding: 10px; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(personenHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Warten bis Inhalt geladen ist, dann drucken
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            };
        }
        
        function druckeTischzuordnung(rundeId) {
            var tischElement = document.getElementById('tischzuordnung-' + rundeId);
            if (!tischElement) {
                alert('Tischzuordnung nicht gefunden');
                return;
            }
            
            // HTML-Inhalt kopieren
            var tischHTML = tischElement.innerHTML;
            
            // Button entfernen, aber Überschrift behalten
            tischHTML = tischHTML.replace(/<button[^>]*class="btn-print"[^>]*>.*?<\/button>/gi, '');
            // Entferne den flex-Container, behalte aber die Überschrift
            tischHTML = tischHTML.replace(/<div[^>]*style="[^"]*display:\s*flex[^"]*"[^>]*>/gi, '<div>');
            tischHTML = tischHTML.replace(/<\/div>\s*<table/g, '<table');
            
            // Neues Fenster öffnen
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // HTML für Druck erstellen
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<title>Tischzuordnung Runde ' + rundeId + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h3 { color: #667eea; margin-bottom: 20px; }');
            printWindow.document.write('h2 { color: #667eea; margin: 0 0 10px 0; }');
            printWindow.document.write('h3 { margin: 0; font-weight: normal; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }');
            printWindow.document.write('table[border="1"] { border: 1px solid #000; }');
            printWindow.document.write('table[border="1"] td { padding: 10px; vertical-align: top; border: 1px solid #ddd; }');
            printWindow.document.write('@media print { body { margin: 0; padding: 10px; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(tischHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Warten bis Inhalt geladen ist, dann drucken
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            };
        }
        
        function druckeRunde(rundeId) {
            var rundeElement = document.getElementById('runde-' + rundeId);
            if (!rundeElement) {
                alert('Runde nicht gefunden');
                return;
            }
            
            // Content-Bereich finden und ausklappen
            var contentElement = document.getElementById('content-' + rundeId);
            if (contentElement) {
                contentElement.classList.add('expanded');
            }
            
            // Content-Bereich finden und ausklappen für den Druck
            var contentElement = document.getElementById('content-' + rundeId);
            var wasExpanded = contentElement && contentElement.classList.contains('expanded');
            if (contentElement && !wasExpanded) {
                contentElement.classList.add('expanded');
            }
            
            // HTML-Inhalt der Runde kopieren
            var rundeHTML = rundeElement.innerHTML;
            
            // Button und Toggle entfernen
            rundeHTML = rundeHTML.replace(/<button[^>]*class="btn-print"[^>]*>.*?<\/button>/gi, '');
            rundeHTML = rundeHTML.replace(/<span[^>]*class="runde-toggle"[^>]*>.*?<\/span>/gi, '');
            rundeHTML = rundeHTML.replace(/class="runde-header"[^>]*>/gi, '>');
            // Content-Bereich sichtbar machen
            rundeHTML = rundeHTML.replace(/class="runde-content[^"]*"/gi, 'style="display: block;"');
            
            // Neues Fenster öffnen
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // HTML für Druck erstellen
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<title>Tischzuordnung Runde ' + rundeId + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h3 { color: #667eea; margin-bottom: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }');
            printWindow.document.write('th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }');
            printWindow.document.write('th { background: #667eea; color: white; font-weight: bold; }');
            printWindow.document.write('table[border="1"] { border: 1px solid #000; }');
            printWindow.document.write('table[border="1"] td { padding: 8px; border: 1px solid #ddd; }');
            printWindow.document.write('@media print { body { margin: 0; padding: 10px; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h3>Runde ' + rundeId + '</h3>');
            printWindow.document.write(rundeHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Content-Bereich wieder auf ursprünglichen Zustand zurücksetzen
            if (contentElement && !wasExpanded) {
                setTimeout(function() {
                    contentElement.classList.remove('expanded');
                }, 100);
            }
            
            // Warten bis Inhalt geladen ist, dann drucken
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                    // Fenster nach dem Druck schließen (optional)
                    // printWindow.close();
                }, 250);
            };
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Turnier starten</h1>
        
        <?php if ($success): ?>
            <div class="message success">
                <?php if ($success === 'deaktiviert'): ?>
                    Alle Runden wurden erfolgreich deaktiviert!
                <?php else: ?>
                    Turnier wurde erfolgreich gestartet und die Tischzuordnungen wurden berechnet!
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$aktuellesTurnier): ?>
            <div class="message error">
                Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.
            </div>
        <?php else: ?>
            <div class="info-box">
                <p><strong>Aktuelles Turnier:</strong> <?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></p>
                <p><strong>Anzahl Teilnehmer:</strong> <?php echo htmlspecialchars($aktuellesTurnier['anzahl_spieler']); ?></p>
                <p><strong>Anzahl Runden:</strong> <?php echo htmlspecialchars($aktuellesTurnier['anzahl_runden'] ?? '3'); ?></p>
                <p><strong>Teilnehmer pro Runde (pro Tisch):</strong> <?php echo htmlspecialchars($aktuellesTurnier['spieler_pro_runde'] ?? '3'); ?></p>
                <?php if ($hatZuordnungen): ?>
                    <p><strong>Status:</strong> Turnier wurde bereits gestartet. Tischzuordnungen existieren.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!$parameterOk && !empty($parameterFehler)): ?>
                <div class="message error">
                    <strong>Fehler in den Turnierparametern:</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach ($parameterFehler as $fehler): ?>
                            <li><?php echo htmlspecialchars($fehler); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top: 10px;">Bitte korrigieren Sie die Parameter im Modul "Turnier erfassen".</p>
                </div>
            <?php endif; ?>
            
            <h2>Turnier starten</h2>
            
            <?php if (!$hatZuordnungen): ?>
                <?php if (!$parameterOk): ?>
                    <div class="message error">
                        <strong>Das Turnier kann nicht gestartet werden, da die Parameter nicht korrekt sind.</strong>
                        <p style="margin-top: 10px;">Bitte korrigieren Sie die Parameter im Modul "Turnier erfassen" und versuchen Sie es erneut.</p>
                    </div>
                    <button type="button" class="btn" disabled>Turnier starten und Tischzuordnungen berechnen</button>
                <?php else: ?>
                    <div class="info-box" style="background: #d4edda; border-left-color: #28a745;">
                        <p style="color: #155724;"><strong>✓ Alle Parameter sind korrekt.</strong></p>
                        <p style="color: #155724; margin-top: 5px;">Das Turnier kann gestartet werden.</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="start_turnier" value="1">
                        <button type="submit" class="btn">Turnier starten und Tischzuordnungen berechnen</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                    <p style="color: #856404;"><strong>Hinweis:</strong> Das Turnier wurde bereits gestartet. Tischzuordnungen existieren bereits.</p>
                    <p style="color: #856404; margin-top: 5px;">Sie können die Turnierdaten neu berechnen, um die Tischzuordnungen zu aktualisieren.</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <form method="POST">
                    <input type="hidden" name="start_turnier" value="1">
                    <button type="submit" class="btn" <?php echo !$parameterOk ? 'disabled' : ''; ?>>Turnierdaten neu berechnen</button>
                </form>
            </div>
            
            <?php if ($hatZuordnungen): ?>
                <div class="runde-container" style="margin-top: 30px;">
                    <div class="runde-header" onclick="toggleKontrolle()">
                        <h3>Kontrolle: Verschiedene Spielpartner pro Teilnehmer</h3>
                        <span class="runde-toggle" id="toggle-kontrolle">▼</span>
                    </div>
                    <div class="runde-content" id="content-kontrolle">
                        <?php
                        // Kontrollausgabe: Wie viele verschiedene Spieler jeder Spieler hatte
                        $db = getDB();
                        $stmt = $db->prepare("
                            SELECT 
                                z1.spieler_id,
                                COUNT(DISTINCT z2.spieler_id) as verschiedene_spieler
                            FROM turnier_zuordnungen z1
                            INNER JOIN turnier_zuordnungen z2 ON z1.runde = z2.runde AND z1.tisch = z2.tisch AND z1.turnier_id = z2.turnier_id
                            WHERE z1.turnier_id = ? AND z1.spieler_id != z2.spieler_id
                            GROUP BY z1.spieler_id
                            ORDER BY z1.spieler_id
                        ");
                        $stmt->execute([$aktuellesTurnier['id']]);
                        $spielerStatistik = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Teilnehmernummer</th>
                                    <th>Anzahl verschiedene Spielpartner</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($spielerStatistik)): ?>
                                    <tr>
                                        <td colspan="2" style="text-align: center; color: #999;">Keine Daten verfügbar</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($spielerStatistik as $stat): ?>
                                        <tr>
                                            <td><strong>Teilnehmer <?php echo htmlspecialchars($stat['spieler_id']); ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($stat['verschiedene_spieler']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;">Personenzuordnung nach Runden</h2>
                    <a href="runde_deaktivieren.php" style="color: #dc3545; text-decoration: none; padding: 8px 15px; border: 1px solid #dc3545; border-radius: 5px; transition: all 0.3s;" 
                       onmouseover="this.style.background='#dc3545'; this.style.color='white';" 
                       onmouseout="this.style.background='white'; this.style.color='#dc3545';">
                        Alle deaktivieren
                    </a>
                </div>
                <?php
                // Personenzuordnung für alle Runden anzeigen
                $anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
                
                for ($runde = 1; $runde <= $anzahlRunden; $runde++):
                    // Alle Zuordnungen für diese Runde abrufen
                    $stmt = $db->prepare("
                        SELECT tisch, spieler_id 
                        FROM turnier_zuordnungen 
                        WHERE turnier_id = ? AND runde = ? 
                        ORDER BY tisch, spieler_id
                    ");
                    $stmt->execute([$aktuellesTurnier['id'], $runde]);
                    $zuordnungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Gruppiere nach Tisch
                    $tische = [];
                    foreach ($zuordnungen as $zuordnung) {
                        $tisch = $zuordnung['tisch'];
                        if (!isset($tische[$tisch])) {
                            $tische[$tisch] = [];
                        }
                        $tische[$tisch][] = $zuordnung['spieler_id'];
                    }
                ?>
                    <div id="runde-<?php echo $runde; ?>" class="runde-container">
                        <div class="runde-header" onclick="toggleRunde(<?php echo $runde; ?>)">
                            <h3>Runde <?php echo $runde; ?></h3>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0;" onclick="event.stopPropagation();">
                                    <input type="radio" name="aktive_runde" value="<?php echo $runde; ?>" 
                                           <?php echo (isset($aktuellesTurnier['aktive_runde']) && $aktuellesTurnier['aktive_runde'] == $runde) ? 'checked' : ''; ?>
                                           onchange="aktiviereRunde(<?php echo $runde; ?>)">
                                    <span>Aktivieren</span>
                                </label>
                                <span class="runde-toggle" id="toggle-<?php echo $runde; ?>">▼</span>
                            </div>
                        </div>
                        <div class="runde-content" id="content-<?php echo $runde; ?>">
                        <table style="margin-bottom: 30px;">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Tisch</th>
                                <th>Teilnehmer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tische)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: #999;">Keine Zuordnungen für diese Runde</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tische as $tisch => $spieler): ?>
                                    <tr>
                                        <td><strong>Tisch <?php echo htmlspecialchars($tisch); ?></strong></td>
                                        <td>
                                            <?php 
                                            $spielerNummern = [];
                                            foreach ($spieler as $spielerId) {
                                                $spielerNummern[] = htmlspecialchars($spielerId);
                                            }
                                            echo 'Teilnehmer ' . implode(',', $spielerNummern);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Alternative Darstellung: Personenzuordnung wie in personzuordnung2.php -->
                    <div id="personenzuordnung-<?php echo $runde; ?>" style="margin-bottom: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0; color: #667eea; font-size: 16pt;">Personenzuordnung Runde <?php echo $runde; ?></h3>
                            <button type="button" class="btn-print" onclick="druckePersonenzuordnung(<?php echo $runde; ?>)">Drucken</button>
                        </div>
                        <table border="1" style="width: 100%; border-collapse: collapse;">
                            <?php
                            // Sortiere Zuordnungen nach Spieler-ID für die Anzeige
                            $stmt = $db->prepare("
                                SELECT tisch, spieler_id 
                                FROM turnier_zuordnungen 
                                WHERE turnier_id = ? AND runde = ? 
                                ORDER BY spieler_id
                            ");
                            $stmt->execute([$aktuellesTurnier['id'], $runde]);
                            $personenZuordnung = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
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
                    </div>
                    
                    <!-- Tischzuordnung wie in tischzuordnung.php -->
                    <div id="tischzuordnung-<?php echo $runde; ?>" style="margin-top: 30px; margin-bottom: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0; color: #667eea; font-size: 16pt;">Tischzuordnung Runde <?php echo $runde; ?></h3>
                            <button type="button" class="btn-print" onclick="druckeTischzuordnung(<?php echo $runde; ?>)">Drucken</button>
                        </div>
                        <table border="1" style="width: 100%; border-collapse: collapse;">
                            <?php
                            // Sortiere Zuordnungen nach Tisch, dann nach Spieler-ID
                            $stmt = $db->prepare("
                                SELECT tisch, spieler_id 
                                FROM turnier_zuordnungen 
                                WHERE turnier_id = ? AND runde = ? 
                                ORDER BY tisch, spieler_id
                            ");
                            $stmt->execute([$aktuellesTurnier['id'], $runde]);
                            $tischZuordnungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Gruppiere nach Tisch
                            $tischeGruppiert = [];
                            foreach ($tischZuordnungen as $zuordnung) {
                                $tisch = $zuordnung['tisch'];
                                if (!isset($tischeGruppiert[$tisch])) {
                                    $tischeGruppiert[$tisch] = [];
                                }
                                $tischeGruppiert[$tisch][] = $zuordnung['spieler_id'];
                            }
                            
                            // Sortiere Tische
                            ksort($tischeGruppiert);
                            
                            $spielerProTisch = intval($aktuellesTurnier['spieler_pro_runde'] ?? 3);
                            $tischeProZeile = 5;
                            $tischZaehler = 0;
                            $zeileGestartet = false;
                            
                            if (empty($tischeGruppiert)):
                                echo '<tr><td colspan="' . $tischeProZeile . '" style="text-align: center; padding: 20px; color: #999;">Keine Tischzuordnungen für diese Runde</td></tr>';
                            else:
                                foreach ($tischeGruppiert as $tisch => $spieler):
                                    if ($tischZaehler % $tischeProZeile == 0) {
                                        if ($zeileGestartet) {
                                            echo '</tr>';
                                        }
                                        echo '<tr>';
                                        $zeileGestartet = true;
                                    }
                                    
                                    echo '<td style="padding: 10px; vertical-align: top;">';
                                    echo '<h2 style="margin: 0 0 10px 0; color: #667eea;">Tisch ' . htmlspecialchars($tisch) . '</h2>';
                                    echo '<h3 style="margin: 0; font-weight: normal;">Person: ';
                                    
                                    $spielerListe = [];
                                    foreach ($spieler as $spielerId) {
                                        $spielerListe[] = htmlspecialchars($spielerId);
                                    }
                                    echo implode(', ', $spielerListe);
                                    
                                    echo '</h3>';
                                    echo '</td>';
                                    
                                    $tischZaehler++;
                                endforeach;
                                
                                // Letzte Zeile schließen
                                if ($zeileGestartet) {
                                    // Fülle restliche Zellen auf, wenn nötig
                                    $restZellen = $tischeProZeile - ($tischZaehler % $tischeProZeile);
                                    if ($restZellen < $tischeProZeile && $restZellen > 0) {
                                        for ($i = 0; $i < $restZellen; $i++) {
                                            echo '<td></td>';
                                        }
                                    }
                                    echo '</tr>';
                                }
                            endif;
                            ?>
                        </table>
                    </div>
                        </div>
                    </div>
                <?php endfor; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
