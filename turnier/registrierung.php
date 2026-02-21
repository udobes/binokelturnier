<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

$aktuellesTurnier = getAktuellesTurnier();
$success = isset($_GET['success']) ? true : false;
$error = null;
$successMessage = null;
$anmeldungDaten = null;
$registrierNummerEingabe = '';
$laufzettelDaten = null;
$showLaufzettel = isset($_GET['show_laufzettel']) ? true : false;

// Laufzettel-Daten aus Session holen
if ($showLaufzettel && isset($_SESSION['laufzettel_daten'])) {
    $laufzettelDaten = $_SESSION['laufzettel_daten'];
    unset($_SESSION['laufzettel_daten']); // Nach Anzeige löschen
}

// Bestätigung für Turnierwechsel verarbeiten
if (isset($_POST['uebernehme_anderes_turnier'])) {
    $registrierNummer = isset($_POST['registrier_nummer']) ? intval($_POST['registrier_nummer']) : 0;
    if ($registrierNummer > 0 && $aktuellesTurnier) {
        $db = getDB();
        // Turnierzuordnung aktualisieren
        $stmt = $db->prepare("UPDATE anmeldungen SET turnier_id = ? WHERE id = ?");
        $stmt->execute([$aktuellesTurnier['id'], $registrierNummer]);
        
        // Daten neu laden
        $stmt = $db->prepare("SELECT id, name, email, mobilnummer, \"alter\", name_auf_wertungsliste, turnier_id FROM anmeldungen WHERE id = ?");
        $stmt->execute([$registrierNummer]);
        $anmeldungDaten = $stmt->fetch(PDO::FETCH_ASSOC);
        $registrierNummerEingabe = $registrierNummer;
        
        // Session-Variable löschen
        unset($_SESSION['pending_turnier_wechsel']);
    }
}

// Registriernummer eingeben und Daten laden
if (isset($_POST['lade_daten'])) {
    $registrierNummer = isset($_POST['registrier_nummer']) ? intval($_POST['registrier_nummer']) : 0;
    
    if ($registrierNummer < 1) {
        $error = "Bitte geben Sie eine gültige Registriernummer ein.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, mobilnummer, \"alter\", name_auf_wertungsliste, turnier_id FROM anmeldungen WHERE id = ?");
        $stmt->execute([$registrierNummer]);
        $anmeldungDaten = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$anmeldungDaten) {
            $error = "Registriernummer nicht gefunden.";
        } else {
            // Prüfen, ob Teilnehmer einem anderen Turnier zugeordnet ist
            if (!empty($anmeldungDaten['turnier_id']) && $aktuellesTurnier && $anmeldungDaten['turnier_id'] != $aktuellesTurnier['id']) {
                // Teilnehmer ist einem anderen Turnier zugeordnet
                $_SESSION['pending_turnier_wechsel'] = [
                    'registrier_nummer' => $registrierNummer,
                    'anmeldung_daten' => $anmeldungDaten,
                    'aktuelles_turnier_id' => $aktuellesTurnier['id']
                ];
                // Daten trotzdem anzeigen, aber mit Bestätigungsmeldung
                $registrierNummerEingabe = $registrierNummer;
            } else {
                $registrierNummerEingabe = $registrierNummer;
            }
        }
    }
}

// Registrierung über Registriernummer
if (isset($_POST['registrierung_nummer'])) {
    if (!$aktuellesTurnier) {
        $error = "Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.";
    } else {
        $registrierNummer = isset($_POST['registrier_nummer']) ? intval($_POST['registrier_nummer']) : 0;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobilnummer = trim($_POST['mobilnummer'] ?? '');
        $alter = isset($_POST['alter']) && !empty($_POST['alter']) ? intval($_POST['alter']) : null;
        $nameAufWertungsliste = isset($_POST['name_auf_wertungsliste']) && $_POST['name_auf_wertungsliste'] == '1' ? 1 : 0;
        
        if ($registrierNummer < 1) {
            $error = "Bitte geben Sie eine gültige Registriernummer ein.";
        } elseif (empty($name)) {
            $error = "Bitte geben Sie einen Namen ein.";
        } else {
            // Email validieren, falls angegeben
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
            } else {
                $result = registriereDurchNummerMitDaten($aktuellesTurnier['id'], $registrierNummer, $name, !empty($email) ? $email : null, !empty($mobilnummer) ? $mobilnummer : null, $alter, $nameAufWertungsliste);
                if ($result['success']) {
                    // Turnierzuordnung in anmeldungen aktualisieren, falls noch nicht geschehen
                    $db = getDB();
                    $stmt = $db->prepare("UPDATE anmeldungen SET turnier_id = ? WHERE id = ?");
                    $stmt->execute([$aktuellesTurnier['id'], $registrierNummer]);
                    
                    // Session-Variable löschen
                    unset($_SESSION['pending_turnier_wechsel']);
                    
                    // Erfolgsmeldung vorbereiten
                    $successMessage = isset($result['updated']) && $result['updated'] 
                        ? "Daten wurden erfolgreich aktualisiert." 
                        : "Registrierung erfolgreich abgeschlossen.";
                    
                    // Daten für Laufzettel-Overlay vorbereiten
                    // PIN aus Datenbank holen
                    $db = getDB();
                    $stmt = $db->prepare("SELECT pin FROM turnier_registrierungen WHERE turnier_id = ? AND anmeldung_id = ? AND startnummer = ?");
                    $stmt->execute([$aktuellesTurnier['id'], $registrierNummer, $result['startnummer']]);
                    $pinData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $laufzettelDaten = [
                        'startnummer' => $result['startnummer'],
                        'name' => $name,
                        'email' => $email,
                        'mobilnummer' => $mobilnummer,
                        'registrier_nummer' => $registrierNummer,
                        'pin' => $pinData['pin'] ?? '',
                        'turnier' => $aktuellesTurnier
                    ];
                    $_SESSION['laufzettel_daten'] = $laufzettelDaten;
                    $_SESSION['success_message'] = $successMessage;
                    header('Location: registrierung.php?show_laufzettel=1');
                    exit;
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

// Neue Person registrieren
if (isset($_POST['registrierung_neu'])) {
    if (!$aktuellesTurnier) {
        $error = "Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobilnummer = trim($_POST['mobilnummer'] ?? '');
        $alter = isset($_POST['alter']) && !empty($_POST['alter']) ? intval($_POST['alter']) : null;
        $nameAufWertungsliste = isset($_POST['name_auf_wertungsliste']) && $_POST['name_auf_wertungsliste'] == '1' ? 1 : 0;
        
        if (empty($name)) {
            $error = "Bitte geben Sie einen Namen ein.";
        } else {
            // Email validieren, falls angegeben
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
            } else {
                $result = registriereNeuePerson($aktuellesTurnier['id'], $name, !empty($email) ? $email : null, !empty($mobilnummer) ? $mobilnummer : null, $alter, $nameAufWertungsliste);
                if ($result['success']) {
                    // Erfolgsmeldung mit Registriernummer vorbereiten
                    $registrierNummer = $result['anmeldung_id'] ?? null;
                    $successMessage = "Registrierung erfolgreich abgeschlossen.";
                    if ($registrierNummer) {
                        $successMessage .= " Registriernummer: " . $registrierNummer;
                    }
                    
                    // Daten für Laufzettel-Overlay vorbereiten
                    // PIN aus Datenbank holen
                    $db = getDB();
                    $stmt = $db->prepare("SELECT pin FROM turnier_registrierungen WHERE turnier_id = ? AND anmeldung_id = ? AND startnummer = ?");
                    $stmt->execute([$aktuellesTurnier['id'], $registrierNummer, $result['startnummer']]);
                    $pinData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $laufzettelDaten = [
                        'startnummer' => $result['startnummer'],
                        'name' => $name,
                        'email' => $email,
                        'mobilnummer' => $mobilnummer,
                        'registrier_nummer' => $registrierNummer,
                        'pin' => $pinData['pin'] ?? '',
                        'turnier' => $aktuellesTurnier
                    ];
                    $_SESSION['laufzettel_daten'] = $laufzettelDaten;
                    $_SESSION['success_message'] = $successMessage;
                    header('Location: registrierung.php?show_laufzettel=1');
                    exit;
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

// Erfolgsmeldung aus URL holen
if (isset($_GET['message'])) {
    $successMessage = urldecode($_GET['message']);
}

// Erfolgsmeldung aus Session holen (falls vorhanden)
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Alle Registrierungen für aktuelles Turnier holen
$registrierungen = [];
if ($aktuellesTurnier) {
    $registrierungen = getTurnierRegistrierungen($aktuellesTurnier['id']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Registrierung</title>
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .checkbox-group {
            margin-bottom: 20px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
            width: auto;
            max-width: none;
        }
        input[type="text"],
        input[type="number"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
        }
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
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
        .registration-box {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 2px solid #667eea;
        }
        .registration-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .registration-container .registration-box {
            flex: 1;
            margin-bottom: 0;
        }
        @media (max-width: 768px) {
            .registration-container {
                flex-direction: column;
            }
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
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
        .small-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .overlay.active {
            display: flex;
        }
        .laufzettel-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .laufzettel-content {
            background: white;
            padding: 20px 20px 20px 0;
            border: 1px solid #ddd;
            max-width: 64mm;
            width: 64mm;
            margin: 0 auto;
        }
        .laufzettel-html {
            width: 100%;
            max-width: 64mm;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            color: #000;
        }
        .laufzettel-logo {
            text-align: center;
            margin-bottom: 15px;
        }
        .laufzettel-logo .logo-img {
            max-width: 100%;
            height: auto;
            max-height: 40mm;
        }
        .laufzettel-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .laufzettel-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            color: #000;
        }
        .laufzettel-info,
        .laufzettel-spieler {
            margin-bottom: 15px;
            padding: 10px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 10pt;
        }
        .info-label {
            font-weight: bold;
            margin-right: 5px;
        }
        .info-label-small {
            font-weight: normal;
            font-size: 8pt;
        }
        .info-value {
            flex: 1;
            text-align: right;
        }
        .info-value-large {
            font-size: 14pt;
            font-weight: bold;
        }
        .info-value-small {
            font-size: 8pt;
            font-weight: normal;
        }
        .laufzettel-runde {
            margin-bottom: 15px;
            padding: 10px 0;
            border-top: 1px dashed #ccc;
        }
        .runde-title {
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 10px 0;
            color: #000;
        }
        .runde-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            table-layout: fixed;
        }
        .runde-table td {
            padding: 8px 5px;
            vertical-align: bottom;
        }
        .runde-label {
            font-weight: normal;
            font-size: 10pt;
            padding-right: 10px;
            width: 70%;
        }
        .runde-field {
            border-bottom: 1px solid #000;
            width: 30%;
            min-height: 18px;
        }
        .summe-table {
            margin-top: 0;
        }
        .summe-label {
            font-weight: bold;
            font-size: 12pt;
        }
        .summe-field {
            min-height: 20px;
        }
        .laufzettel-summe-spacer {
            height: 2em;
        }
        .laufzettel-summe {
            margin-top: 0;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        .laufzettel-spacer {
            height: 0;
            margin-top: 3em;
        }
        .laufzettel-actions {
            margin-top: 20px;
            text-align: center;
        }
        .icon-btn {
            padding: 8px 12px;
            margin: 0 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, transform 0.1s;
            vertical-align: middle;
            white-space: nowrap;
            gap: 8px;
        }
        .icon-btn:hover {
            transform: scale(1.05);
        }
        .icon-btn svg {
            width: 20px;
            height: 20px;
        }
        .icon-btn-print {
            background: #28a745;
            color: white;
            padding: 6px 10px;
            font-weight: 500;
        }
        .icon-btn-print:hover {
            background: #218838;
        }
        .icon-btn-print:disabled {
            opacity: 0.5;
            cursor: wait !important;
        }
        .icon-btn-namenschild {
            background: #17a2b8;
            color: white;
            padding: 6px 10px;
            font-weight: 500;
        }
        .icon-btn-namenschild:hover {
            background: #138496;
        }
        .icon-btn-container {
            display: flex;
            gap: 5px;
            align-items: center;
            white-space: nowrap;
        }
        .btn-print {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-close {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            /* Alles ausblenden */
            body {
                margin: 0;
                padding: 0;
            }
            body > *:not(#laufzettelOverlay) {
                display: none !important;
            }
            /* Overlay immer anzeigen beim Drucken */
            #laufzettelOverlay {
                display: flex !important;
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: auto !important;
                background: white !important;
                z-index: 99999 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            .laufzettel-container {
                width: 64mm !important;
                max-width: 64mm !important;
                height: auto !important;
                min-height: auto !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 auto !important;
                background: white !important;
                display: block !important;
                overflow: visible !important;
                page-break-inside: avoid !important;
            }
            .laufzettel-content {
                max-width: 64mm !important;
                width: 64mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                border: none !important;
                overflow: visible !important;
                height: auto !important;
            }
            .laufzettel-html {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                height: auto !important;
            }
            .laufzettel-logo .logo-img {
                max-height: 25mm !important;
            }
            .laufzettel-title {
                font-size: 11pt !important;
            }
            .info-row {
                font-size: 8pt !important;
            }
            .info-value-small {
                font-size: 10pt !important;
                font-weight: normal !important;
            }
            /* Spezifischere Regeln für Name und Email im Spieler-Bereich */
            .laufzettel-spieler .info-row {
                font-size: 8pt !important;
            }
            .laufzettel-spieler .info-row .info-value-small,
            .laufzettel-spieler .info-value-small,
            .laufzettel-spieler span.info-value-small {
                font-size: 12pt !important;
                font-weight: normal !important;
            }
            .laufzettel-spieler .info-label-small {
                font-size: 9pt !important;
            }
            .laufzettel-runde {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: visible !important;
            }
            .runde-label {
                font-size: 8pt !important;
            }
            .runde-field {
                min-height: 16px !important;
            }
            .laufzettel-summe {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: visible !important;
            }
            .summe-label {
                font-size: 10pt !important;
            }
            .summe-field {
                min-height: 18px !important;
            }
            .laufzettel-spacer {
                height: 0 !important;
                margin-top: 3em !important;
            }
            .laufzettel-actions {
                display: none !important;
            }
            @page {
                size: 64mm auto;
                margin: 0;
            }
        }
    </style>
    <script>
        var currentLaufzettelStartnummer = 0;
        
        function druckeLaufzettel(startnummer) {
            if (!startnummer || startnummer <= 0) {
                // Fallback für Overlay-Druck
                startnummer = <?php echo $laufzettelDaten ? htmlspecialchars($laufzettelDaten['startnummer'], ENT_QUOTES) : '0'; ?>;
            }
            
            if (!startnummer || startnummer <= 0) {
                alert('Keine gültige Startnummer gefunden.');
                return;
            }
            
            // Laufzettel-Daten laden
            fetch('lade_laufzettel.php?startnummer=' + startnummer, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Laufzettel-Inhalt setzen (HTML)
                    document.getElementById('laufzettelContent').innerHTML = data.laufzettel;
                    currentLaufzettelStartnummer = data.startnummer;
                    // Overlay anzeigen
                    document.getElementById('laufzettelOverlay').classList.add('active');
                } else {
                    alert('Fehler beim Laden des Laufzettels: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                alert('Fehler beim Laden des Laufzettels.');
            });
        }
        
        function druckeLaufzettelAus() {
            // Browser-Druckdialog öffnen
            window.print();
        }
        
        function druckeNamenschild(startnummer) {
            if (!startnummer || startnummer <= 0) {
                alert('Keine gültige Startnummer gefunden.');
                return;
            }
            
            // Namenschild drucken
            fetch('drucke_namenschild.php?startnummer=' + startnummer, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                // Debug-Informationen in der Konsole ausgeben
                if (data.debug && Array.isArray(data.debug)) {
                    console.log('=== DEBUG-Informationen Namenschild-Druck ===');
                    data.debug.forEach(function(debugLine) {
                        console.log(debugLine);
                    });
                    console.log('=== Ende Debug-Informationen ===');
                }
                
                return data;
            })
            .then(data => {
                if (!data.success) {
                    alert('Fehler beim Drucken: ' + (data.message || 'Unbekannter Fehler'));
                }
                // Erfolgsmeldung entfernt - keine Meldung bei erfolgreichem Druck
            })
            .catch(error => {
                console.error('Fehler:', error);
                alert('Fehler beim Drucken des Namenschilds.');
            });
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Registrierung</h1>
        
        <?php if ($successMessage): ?>
            <div class="message success">
                <?php echo htmlspecialchars($successMessage); ?>
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
            <div class="registration-container">
                <div class="registration-box">
                    <h2>Registrierung über Registriernummer</h2>
                <?php if (!$anmeldungDaten): ?>
                    <form method="POST">
                        <input type="hidden" name="lade_daten" value="1">
                        <div class="form-group">
                            <label for="registrier_nummer">Registriernummer *</label>
                            <input type="number" id="registrier_nummer" name="registrier_nummer" min="1" required placeholder="z.B. 42" value="<?php echo htmlspecialchars($registrierNummerEingabe); ?>">
                            <div class="small-text">Geben Sie die Registriernummer aus der Anmeldungsbestätigung ein.</div>
                        </div>
                        <button type="submit" class="btn">Daten laden</button>
                    </form>
                <?php else: ?>
                    <?php 
                    // Prüfen, ob Teilnehmer einem anderen Turnier zugeordnet ist
                    $anderesTurnier = false;
                    if (isset($_SESSION['pending_turnier_wechsel']) && $_SESSION['pending_turnier_wechsel']['registrier_nummer'] == $anmeldungDaten['id']) {
                        $anderesTurnier = true;
                        $pendingWechsel = $_SESSION['pending_turnier_wechsel'];
                    } elseif (!empty($anmeldungDaten['turnier_id']) && $aktuellesTurnier && $anmeldungDaten['turnier_id'] != $aktuellesTurnier['id']) {
                        $anderesTurnier = true;
                    }
                    ?>
                    
                    <?php if ($anderesTurnier): ?>
                        <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <p style="margin: 0 0 15px 0; font-weight: bold; color: #856404;">
                                ⚠ Teilnehmer ist einem anderen Turnier zugeordnet, soll dieser Teilnehmer übernommen werden?
                            </p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="uebernehme_anderes_turnier" value="1">
                                <input type="hidden" name="registrier_nummer" value="<?php echo htmlspecialchars($anmeldungDaten['id']); ?>">
                                <button type="submit" class="btn" style="background: #28a745; margin-right: 10px;">Ja, übernehmen</button>
                            </form>
                            <button type="button" class="btn" style="background: #dc3545;" onclick="window.location.href='registrierung.php'">Abbrechen</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="registrierung_nummer" value="1">
                        <input type="hidden" name="registrier_nummer" value="<?php echo htmlspecialchars($anmeldungDaten['id']); ?>">
                        <div class="form-group">
                            <label for="registrier_nummer_display">Registriernummer</label>
                            <input type="text" id="registrier_nummer_display" value="<?php echo htmlspecialchars($anmeldungDaten['id']); ?>" disabled style="background: #f0f0f0;">
                        </div>
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($anmeldungDaten['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">E-Mail-Adresse</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($anmeldungDaten['email'] ?? ''); ?>">
                            <div class="small-text">Optional - kann korrigiert oder ergänzt werden</div>
                        </div>
                        <div class="form-group">
                            <label for="mobilnummer">Mobilnummer</label>
                            <input type="tel" id="mobilnummer" name="mobilnummer" value="<?php echo htmlspecialchars($anmeldungDaten['mobilnummer'] ?? ''); ?>" placeholder="z.B. 0171 1234567">
                            <div class="small-text">Optional - kann ergänzt werden</div>
                        </div>
                        <div class="form-group">
                            <label for="alter">Alter</label>
                            <input type="text" id="alter" name="alter" value="<?php echo htmlspecialchars($anmeldungDaten['alter'] ?? ''); ?>" placeholder="z.B. 35">
                        </div>
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="name_auf_wertungsliste" name="name_auf_wertungsliste" value="1" <?php echo (!isset($anmeldungDaten['name_auf_wertungsliste']) || $anmeldungDaten['name_auf_wertungsliste'] != 0) ? 'checked' : ''; ?>>
                                <span>Mein Name darf auf den Wertungslisten genannt werden</span>
                            </label>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn" <?php echo $anderesTurnier ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Registrieren</button>
                            <button type="button" class="btn" style="background: #6c757d;" onclick="window.location.href='registrierung.php'">Abbrechen</button>
                        </div>
                    </form>
                <?php endif; ?>
                </div>
                
                <div class="registration-box">
                    <h2>Neue Person registrieren</h2>
                <form method="POST">
                    <input type="hidden" name="registrierung_neu" value="1">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required placeholder="z.B. Max Mustermann">
                    </div>
                    <div class="form-group">
                        <label for="email">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" placeholder="z.B. max@example.com">
                        <div class="small-text">Optional</div>
                    </div>
                    <div class="form-group">
                        <label for="mobilnummer">Mobilnummer</label>
                        <input type="tel" id="mobilnummer" name="mobilnummer" placeholder="z.B. 0171 1234567">
                        <div class="small-text">Optional</div>
                    </div>
                    <div class="form-group">
                        <label for="alter">Alter</label>
                        <input type="text" id="alter" name="alter" placeholder="z.B. 35">
                    </div>
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="name_auf_wertungsliste_neu" name="name_auf_wertungsliste" value="1" checked="checked">
                            <span>Mein Name darf auf den Wertungslisten genannt werden</span>
                        </label>
                    </div>
                    <button type="submit" class="btn">Registrieren</button>
                </form>
                </div>
            </div>
            
            <h2>Registrierte Teilnehmer</h2>
            <?php if (empty($registrierungen)): ?>
                <p style="color: #999;">Noch keine Teilnehmer registriert.</p>
            <?php else: ?>
                <table id="registrierte-teilnehmer">
                    <thead>
                        <tr>
                            <th data-sort-key="startnummer" class="sortable">Startnummer</th>
                            <th data-sort-key="anmeldung_id" class="sortable">Registriernummer</th>
                            <th data-sort-key="name" class="sortable">Name</th>
                            <th data-sort-key="email" class="sortable">E-Mail</th>
                            <th data-sort-key="mobilnummer" class="sortable">Mobilnummer</th>
                            <th data-sort-key="name_auf_wertungsliste" class="sortable">Name in Wertungsliste</th>
                            <th data-sort-key="registriert_am" class="sortable">Registriert am</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrierungen as $reg): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($reg['startnummer']); ?></strong></td>
                                <td><?php echo htmlspecialchars($reg['anmeldung_id'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($reg['name']); ?></td>
                                <td><?php echo htmlspecialchars($reg['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($reg['mobilnummer'] ?? '-'); ?></td>
                                <td><?php echo (isset($reg['name_auf_wertungsliste']) && $reg['name_auf_wertungsliste'] == 1) ? 'Ja' : 'Nein'; ?></td>
                                <td><?php echo htmlspecialchars($reg['registriert_am'] ?? '-'); ?></td>
                                <td>
                                    <div class="icon-btn-container">
                                        <button class="icon-btn icon-btn-print" onclick="druckeLaufzettel(<?php echo htmlspecialchars($reg['startnummer']); ?>)" title="Laufzettel drucken">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                                            </svg>
                                        </button>
                                        <button class="icon-btn icon-btn-namenschild" onclick="druckeNamenschild(<?php echo htmlspecialchars($reg['startnummer']); ?>)" title="Namenschild drucken">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="overlay <?php echo ($showLaufzettel && $laufzettelDaten) ? 'active' : ''; ?>" id="laufzettelOverlay">
        <div class="laufzettel-container">
            <div class="laufzettel-actions" style="margin-bottom: 20px;">
                <button class="btn-print" onclick="druckeLaufzettelAus()">Drucken</button>
                <button class="btn-close" onclick="document.getElementById('laufzettelOverlay').classList.remove('active')">Schließen</button>
            </div>
            <div class="laufzettel-content" id="laufzettelContent">
<?php if ($showLaufzettel && $laufzettelDaten): ?>
<?php
// HTML-Laufzettel erstellen
$anzahlRunden = intval($laufzettelDaten['turnier']['anzahl_runden'] ?? 3);
$logoPath = '../img/AKS-Logo_210.png';

// HTML-Laufzettel zusammenstellen
$laufzettelHTML = '<div class="laufzettel-html">';
$laufzettelHTML .= '<div class="laufzettel-logo">';
$laufzettelHTML .= '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="AKS Logo" class="logo-img">';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-header">';
$laufzettelHTML .= '<h1 class="laufzettel-title">' . htmlspecialchars($laufzettelDaten['turnier']['titel'], ENT_QUOTES, 'UTF-8') . '</h1>';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-info">';
$laufzettelHTML .= '<div class="info-row"><span class="info-label">Datum:</span> <span class="info-value">' . htmlspecialchars($laufzettelDaten['turnier']['datum'], ENT_QUOTES, 'UTF-8') . '</span></div>';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-spieler">';
$laufzettelHTML .= '<div class="info-row"><span class="info-label">Spieler Nr:</span> <span class="info-value info-value-large">' . htmlspecialchars($laufzettelDaten['startnummer'], ENT_QUOTES, 'UTF-8') . '</span></div>';
if (!empty($laufzettelDaten['registrier_nummer'])) {
    $laufzettelHTML .= '<div class="info-row"><span class="info-label info-label-small">Registriernummer:</span> <span class="info-value info-value-small">' . htmlspecialchars($laufzettelDaten['registrier_nummer'], ENT_QUOTES, 'UTF-8') . '</span></div>';
}
$laufzettelHTML .= '<div class="info-row"><span class="info-label info-label-small">Name:</span> <span class="info-value info-value-small">' . htmlspecialchars($laufzettelDaten['name'], ENT_QUOTES, 'UTF-8') . '</span></div>';
if (!empty($laufzettelDaten['email'])) {
    $laufzettelHTML .= '<div class="info-row"><span class="info-label info-label-small">Email:</span> <span class="info-value info-value-small">' . htmlspecialchars($laufzettelDaten['email'], ENT_QUOTES, 'UTF-8') . '</span></div>';
}
$laufzettelHTML .= '</div>';

// Gesamt Summe zuerst
$laufzettelHTML .= '<div class="laufzettel-summe-spacer"></div>';
$laufzettelHTML .= '<div class="laufzettel-summe">';
$laufzettelHTML .= '<table class="runde-table summe-table">';
$laufzettelHTML .= '<tr><td class="runde-label summe-label">Gesamt Summe:</td><td class="runde-field summe-field"></td></tr>';
$laufzettelHTML .= '</table>';
$laufzettelHTML .= '</div>';

// Runden generieren (absteigend: Runde 3, 2, 1)
for ($runde = $anzahlRunden; $runde >= 1; $runde--) {
    $laufzettelHTML .= '<div class="laufzettel-runde">';
    $laufzettelHTML .= '<h2 class="runde-title">Runde ' . $runde . ':</h2>';
    $laufzettelHTML .= '<table class="runde-table">';
    $laufzettelHTML .= '<tr><td class="runde-label">Summe Runde ' . $runde . ':</td><td class="runde-field"></td></tr>';
    $laufzettelHTML .= '</table>';
    $laufzettelHTML .= '</div>';
}

// PIN am Ende
$laufzettelHTML .= '<div class="laufzettel-pin">';
$laufzettelHTML .= '<table class="runde-table pin-table">';
$laufzettelHTML .= '<tr><td class="runde-label pin-label">PIN:</td><td class="runde-field pin-field">' . htmlspecialchars($laufzettelDaten['pin'] ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr>';
$laufzettelHTML .= '</table>';
$laufzettelHTML .= '</div>';

// Leerzeilen am Ende für Papierschnitt
$laufzettelHTML .= '<div class="laufzettel-spacer"></div>';
$laufzettelHTML .= '</div>';

echo $laufzettelHTML;
?>
<?php endif; ?>
            </div>
            <div class="laufzettel-actions" style="margin-top: 20px;">
                <button class="btn-print" onclick="druckeLaufzettelAus()">Drucken</button>
                <button class="btn-close" onclick="document.getElementById('laufzettelOverlay').classList.remove('active')">Schließen</button>
            </div>
        </div>
    </div>
    
    <script>
    (function () {
        const table = document.getElementById('registrierte-teilnehmer');
        if (!table) return;
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        if (!thead || !tbody) return;

        const STORAGE_KEY = 'turnier_registrierung_sort';

        function parseDate(value) {
            if (!value || value === '-') return NaN;
            // Expect formats like "YYYY-MM-DD HH:MM:SS"
            const normalized = value.replace(' ', 'T');
            const t = Date.parse(normalized);
            return isNaN(t) ? Date.parse(value) : t;
        }

        function getCellValue(tr, idx) {
            const cell = tr.children[idx];
            return (cell ? cell.textContent : '').trim();
        }

        function sortRowsBy(columnIndex, key, direction) {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const dirFactor = direction === 'desc' ? -1 : 1;
            rows.sort((a, b) => {
                const va = getCellValue(a, columnIndex);
                const vb = getCellValue(b, columnIndex);

                if (key === 'startnummer' || key === 'anmeldung_id') {
                    const na = parseFloat(va.replace(',', '.')) || 0;
                    const nb = parseFloat(vb.replace(',', '.')) || 0;
                    return (na - nb) * dirFactor;
                }
                if (key === 'registriert_am') {
                    const da = parseDate(va);
                    const db = parseDate(vb);
                    // Newest first when desc
                    if (isNaN(da) && isNaN(db)) return 0;
                    if (isNaN(da)) return -1 * dirFactor;
                    if (isNaN(db)) return 1 * dirFactor;
                    return (da - db) * dirFactor;
                }
                if (key === 'name_auf_wertungsliste') {
                    // Sortiere "Ja" vor "Nein"
                    const aVal = va.toLowerCase() === 'ja' ? 1 : 0;
                    const bVal = vb.toLowerCase() === 'ja' ? 1 : 0;
                    return (aVal - bVal) * dirFactor;
                }
                // String compare for others
                return va.localeCompare(vb, 'de', { sensitivity: 'base' }) * dirFactor;
            });
            // Re-append rows in new order
            rows.forEach(row => tbody.appendChild(row));
        }

        function clearIndicators(exceptTh) {
            thead.querySelectorAll('th.sortable').forEach(th => {
                if (th !== exceptTh) {
                    th.removeAttribute('data-sort-dir');
                    const label = th.getAttribute('data-label');
                    if (label) th.textContent = label;
                }
            });
        }

        function applySorting(key, direction) {
            // Find the column index
            let columnIndex = -1;
            let targetTh = null;
            thead.querySelectorAll('th.sortable').forEach((th, index) => {
                if (th.getAttribute('data-sort-key') === key) {
                    columnIndex = index;
                    targetTh = th;
                }
            });

            if (columnIndex === -1 || !targetTh) return;

            // Apply sorting
            sortRowsBy(columnIndex, key, direction);

            // Update indicators
            clearIndicators(targetTh);
            targetTh.setAttribute('data-sort-dir', direction);
            const label = targetTh.getAttribute('data-label') || '';
            const arrow = direction === 'asc' ? ' ▲' : ' ▼';
            targetTh.textContent = label + arrow;

            // Save to localStorage
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ key: key, direction: direction }));
            } catch (e) {
                // Ignore localStorage errors
            }
        }

        function loadSavedSorting() {
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const sort = JSON.parse(saved);
                    if (sort.key && sort.direction) {
                        applySorting(sort.key, sort.direction);
                    }
                }
            } catch (e) {
                // Ignore errors
            }
        }

        // Set up click handlers
        thead.querySelectorAll('th.sortable').forEach((th, index) => {
            // Store original label
            if (!th.getAttribute('data-label')) th.setAttribute('data-label', th.textContent.trim());

            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const key = th.getAttribute('data-sort-key');
                if (!key) return;

                // Determine direction: toggle, but default for 'registriert_am' is 'desc', otherwise 'asc'
                let dir = th.getAttribute('data-sort-dir');
                if (!dir) {
                    dir = key === 'registriert_am' ? 'desc' : 'asc';
                } else {
                    dir = dir === 'asc' ? 'desc' : 'asc';
                }

                // Apply sorting (this will also save to localStorage)
                applySorting(key, dir);
            });
        });

        // Load saved sorting on page load
        loadSavedSorting();
    })();
    </script>
</body>
</html>

