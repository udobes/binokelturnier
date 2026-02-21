<?php
// Output-Buffering deaktivieren für Export
if (ob_get_level()) {
    ob_end_clean();
}

// Excel-Export - MUSS ganz am Anfang stehen, vor allen anderen Ausgaben!
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Datenbankverbindung
    require_once __DIR__ . '/../anmeldung/config.php';
    require_once __DIR__ . '/config.php';
    initTurnierDB();
    initDB();
    $db = getDB();
    
    // Ausgewähltes Turnier aus Datenbank holen
    $selectedTurnierId = null;
    try {
        $stmt = $db->prepare("SELECT wert FROM config WHERE schluessel = ?");
        $stmt->execute(['default_turnier_id']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['wert'])) {
            $selectedTurnierId = intval($result['wert']);
        }
    } catch (PDOException $e) {
        // Fehler ignorieren
    }
    
    // Nur Anmeldungen für das ausgewählte Turnier laden
    if ($selectedTurnierId) {
        $stmt = $db->prepare("SELECT * FROM anmeldungen WHERE turnier_id = ? ORDER BY anmeldedatum DESC");
        $stmt->execute([$selectedTurnierId]);
    } else {
        // Falls kein Turnier ausgewählt, keine Anmeldungen anzeigen
        $stmt = $db->prepare("SELECT * FROM anmeldungen WHERE 1=0");
        $stmt->execute();
    }
    $anmeldungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV-Daten im Speicher sammeln
    $csvContent = '';
    
    // UTF-8 BOM für Excel
    $csvContent .= "\xEF\xBB\xBF";
    
    // Header-Zeile
    $csvContent .= "ID;Name;E-Mail;Mobilnummer;Alter;Name auf Wertungsliste;Anmeldedatum;E-Mail gesendet;E-Mail gelesen\n";
    
    // Daten-Zeilen
    foreach ($anmeldungen as $anmeldung) {
        $nameAufWertungsliste = (isset($anmeldung['name_auf_wertungsliste']) && $anmeldung['name_auf_wertungsliste'] == 1) ? 'Ja' : 'Nein';
        $csvContent .= sprintf(
            "%s;%s;%s;%s;%s;%s;%s;%s;%s\n",
            $anmeldung['id'],
            str_replace(';', ',', $anmeldung['name']),
            str_replace(';', ',', $anmeldung['email']),
            str_replace(';', ',', $anmeldung['mobilnummer'] ?? ''),
            $anmeldung['alter'] ?? '',
            $nameAufWertungsliste,
            $anmeldung['anmeldedatum'] ?? '',
            $anmeldung['email_gesendet'] ?? '',
            $anmeldung['email_gelesen'] ?? ''
        );
    }
    
    // Content-Length berechnen
    $contentLength = strlen($csvContent);
    
    // Alle Output-Buffer leeren
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // HTTP Status Code setzen
    http_response_code(200);
    
    // Header setzen - MUSS vor jeder Ausgabe sein!
    // Wichtig: Keine Leerzeichen oder Ausgaben vor den Headern!
    header('Content-Type: application/octet-stream; charset=UTF-8');
    header('Content-Disposition: attachment; filename=binokel-anmeldungen.csv');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . $contentLength);
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    
    // CSV-Daten ausgeben
    echo $csvContent;
    flush();
    exit;
}

// Datenbankverbindung
require_once __DIR__ . '/../anmeldung/config.php';
require_once __DIR__ . '/config.php';
initTurnierDB();
$db = getDB();

// Datenbank initialisieren (für Konfigurationstabelle)
initDB();

// Turnierauswahl verarbeiten
if (isset($_POST['action']) && $_POST['action'] === 'set_turnier') {
    $turnierId = intval($_POST['turnier_id'] ?? 0);
    if ($turnierId > 0) {
        // In Datenbank speichern
        $stmt = $db->prepare("INSERT OR REPLACE INTO config (schluessel, wert) VALUES (?, ?)");
        $stmt->execute(['default_turnier_id', $turnierId]);
        $success = "Turnier wurde ausgewählt und gespeichert.";
    } else {
        // Auswahl entfernen
        $stmt = $db->prepare("DELETE FROM config WHERE schluessel = ?");
        $stmt->execute(['default_turnier_id']);
        $success = "Turnierauswahl wurde entfernt.";
    }
}

// Ausgewähltes Turnier aus Datenbank holen
$selectedTurnierId = null;
try {
    $stmt = $db->prepare("SELECT wert FROM config WHERE schluessel = ?");
    $stmt->execute(['default_turnier_id']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['wert'])) {
        $selectedTurnierId = intval($result['wert']);
    }
} catch (PDOException $e) {
    // Fehler ignorieren, falls Tabelle noch nicht existiert
}

$selectedTurnier = null;
if ($selectedTurnierId) {
    $selectedTurnier = getTurnierById($selectedTurnierId);
}

// Alle Turniere laden
$alleTurniere = getAllTurniere();

// Aktionen verarbeiten
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobilnummer = trim($_POST['mobilnummer'] ?? '');
        
        if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $db->prepare("UPDATE anmeldungen SET name = ?, email = ?, mobilnummer = ? WHERE id = ?");
            $stmt->execute([$name, $email, $mobilnummer ?: null, $id]);
            $success = "Eintrag wurde aktualisiert.";
        } else {
            $error = "Ungültige Daten!";
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            // Transaktion starten, damit alle Änderungen zusammengehören
            $db->beginTransaction();

            // 1) Evtl. vorhandene Verknüpfungen in turnier_registrierungen löschen
            $stmt = $db->prepare("DELETE FROM turnier_registrierungen WHERE anmeldung_id = ?");
            $stmt->execute([$id]);

            // 2) Anmeldung nicht physisch löschen (wegen Foreign Keys),
            //    sondern anonymisieren und aus dem Turnier lösen
            $stmt = $db->prepare("
                UPDATE anmeldungen
                SET 
                    name = '[gelöscht]',
                    email = 'geloescht-' || id || '@example.invalid',
                    mobilnummer = NULL,
                    \"alter\" = NULL,
                    name_auf_wertungsliste = 0,
                    turnier_id = NULL
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $db->commit();
            $success = "Eintrag wurde gelöscht (Daten anonymisiert und aus dem Turnier entfernt).";
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Fehlermeldung auch im Backend anzeigen, um die Ursache schneller zu finden
            $error = "Eintrag konnte nicht gelöscht/anonmysiert werden. Technische Details: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            error_log("Fehler beim Löschen/Anonymisieren der Anmeldung ID {$id}: " . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'resend_email') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("SELECT * FROM anmeldungen WHERE id = ?");
        $stmt->execute([$id]);
        $anmeldung = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($anmeldung) {
            // Output-Buffer leeren für sofortige Antwort
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Timeout erhöhen für E-Mail-Versand
            $oldTimeout = ini_get('max_execution_time');
            set_time_limit(120); // 2 Minuten
            
            // E-Mail-Versendung mit Performance-Messung
            $startTime = microtime(true);
            error_log("=== START E-Mail-Versand für ID " . $id . " ===");
            error_log("An: " . $anmeldung['email'] . " | Name: " . $anmeldung['name']);
            
            // Turnierdaten für E-Mail holen
            $turnier = null;
            if (!empty($anmeldung['turnier_id'])) {
                $turnier = getTurnierById($anmeldung['turnier_id']);
            }
            
            // E-Mail-Versendung starten (mit Timeout-Schutz)
            $result = false;
            try {
                // Flush vor mail() Aufruf
                if (function_exists('fastcgi_finish_request')) {
                    // FastCGI: Antwort sofort senden, dann E-Mail versenden
                    $result = @sendConfirmationEmail($anmeldung['name'], $anmeldung['email'], $id, $turnier);
                } else {
                    // Standard: E-Mail versenden
                    $result = @sendConfirmationEmail($anmeldung['name'], $anmeldung['email'], $id, $turnier);
                }
            } catch (Exception $e) {
                error_log("EXCEPTION beim E-Mail-Versand: " . $e->getMessage());
                $result = false;
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            // Timeout zurücksetzen
            if ($oldTimeout !== false) {
                set_time_limit($oldTimeout);
            }
            
            if ($result) {
                // email_gesendet aktualisieren
                $updateStmt = $db->prepare("UPDATE anmeldungen SET email_gesendet = ? WHERE id = ?");
                $updateStmt->execute([date('Y-m-d H:i:s'), $id]);
                $success = "E-Mail wurde erfolgreich gesendet. (Dauer: " . $duration . "s)";
                error_log("=== ERFOLG: E-Mail gesendet für ID " . $id . " in " . $duration . " Sekunden ===");
            } else {
                $error = "E-Mail konnte nicht gesendet werden. (Dauer: " . $duration . "s) Bitte prüfen Sie die Logs.";
                error_log("=== FEHLER: E-Mail konnte nicht gesendet werden für ID " . $id . " (Dauer: " . $duration . "s) ===");
            }
        } else {
            $error = "Eintrag nicht gefunden.";
        }
    }
}

// Anmeldungen laden - nur für das ausgewählte Turnier
if ($selectedTurnierId) {
    $stmt = $db->prepare("SELECT * FROM anmeldungen WHERE turnier_id = ? ORDER BY anmeldedatum DESC");
    $stmt->execute([$selectedTurnierId]);
    $anmeldungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Für jede Anmeldung prüfen, ob sie bereits in turnier_registrierungen registriert ist
    foreach ($anmeldungen as &$anmeldung) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM turnier_registrierungen WHERE anmeldung_id = ?");
        $stmt->execute([$anmeldung['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $anmeldung['is_registered'] = ($result['count'] > 0);
    }
    unset($anmeldung); // Referenz löschen
} else {
    // Falls kein Turnier ausgewählt, keine Anmeldungen anzeigen
    $anmeldungen = [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Anmeldungen verwalten</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
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
        .edit-form {
            display: none;
            background: #f9f9f9;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .edit-form.active {
            display: block;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            padding: 8px 15px;
            margin: 5px 5px 5px 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-save {
            background: #007bff;
            color: white;
        }
        .btn-save:hover {
            background: #0056b3;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            background: #5a6268;
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
        }
        .icon-btn:hover {
            transform: scale(1.1);
        }
        td:last-child {
            white-space: nowrap;
        }
        .icon-btn svg {
            width: 18px;
            height: 18px;
            fill: white;
        }
        .icon-btn-edit {
            background: #28a745;
        }
        .icon-btn-edit:hover {
            background: #218838;
        }
        .icon-btn-delete {
            background: #dc3545;
        }
        .icon-btn-delete:hover {
            background: #c82333;
        }
        .icon-btn-resend {
            background: #17a2b8;
        }
        .icon-btn-resend:hover {
            background: #138496;
        }
        .btn-export {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-export:hover {
            background: #218838;
        }
        .btn-print {
            background: #17a2b8;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            margin-top: 20px;
            margin-left: 10px;
            display: inline-block;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #138496;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .export-container {
            margin: 20px 0;
            text-align: center;
        }
        .export-container-top {
            margin-bottom: 20px;
        }
        .status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-sent {
            background: #d4edda;
            color: #155724;
        }
        .status-read {
            background: #cce5ff;
            color: #004085;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .name-column {
            min-width: 180px;
            width: 180px;
        }
        .email-column {
            min-width: 200px;
            width: 200px;
        }
        #teilnehmerliste-tabelle {
            width: 100%;
            table-layout: auto;
        }
        #teilnehmerliste-tabelle td.name-column {
            min-width: 180px;
            width: 180px;
        }
        #teilnehmerliste-tabelle td.email-column {
            min-width: 200px;
            width: 200px;
        }
        .print-area {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Anmeldungen verwalten</h1>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <form method="POST" id="turnier-auswahl-form" style="display: flex; align-items: center; gap: 10px;">
                <input type="hidden" name="action" value="set_turnier">
                <label for="turnier_id" style="font-weight: bold;">Turnier für Anmeldungen:</label>
                <select name="turnier_id" id="turnier_id" onchange="document.getElementById('turnier-auswahl-form').submit();" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="0">-- Kein Turnier ausgewählt --</option>
                    <?php foreach ($alleTurniere as $turnier): ?>
                        <option value="<?php echo $turnier['id']; ?>" <?php echo ($selectedTurnierId == $turnier['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($turnier['id'] . ' - ' . $turnier['titel'] . ' (' . $turnier['datum'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($selectedTurnier): ?>
                <div class="ausgewaehltes-turnier-block" style="margin-top: 15px; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 class="ausgewaehltes-turnier-toggle" style="margin-top: 0; margin-bottom: 0; padding: 10px 0; color: #28a745; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 8px;" title="Ein- oder ausklappen">
                        <span class="ausgewaehltes-turnier-icon" style="display: inline-block; transition: transform 0.2s;">▶</span>
                        <span>✓ Ausgewähltes Turnier</span>
                    </h3>
                    <div class="ausgewaehltes-turnier-inhalt" style="display: none; margin-top: 15px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 8px; font-weight: bold; width: 150px; vertical-align: top;">ID:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['id']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Datum:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['datum']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Titel:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['titel']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Veranstalter:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['veranstalter']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Ort:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['ort']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Einlasszeit:</td>
                            <td style="padding: 8px;"><?php echo !empty($selectedTurnier['einlasszeit']) ? htmlspecialchars($selectedTurnier['einlasszeit']) : '<em style="color: #999;">nicht angegeben</em>'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Startzeit:</td>
                            <td style="padding: 8px;"><?php echo !empty($selectedTurnier['startzeit']) ? htmlspecialchars($selectedTurnier['startzeit']) : '<em style="color: #999;">nicht angegeben</em>'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Google Maps:</td>
                            <td style="padding: 8px;">
                                <?php if (!empty($selectedTurnier['googlemaps_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($selectedTurnier['googlemaps_link']); ?>" target="_blank"><?php echo htmlspecialchars($selectedTurnier['googlemaps_link']); ?></a>
                                <?php else: ?>
                                    <em style="color: #999;">nicht angegeben</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Anzahl Spieler:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['anzahl_spieler'] ?? '0'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Anzahl Runden:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['anzahl_runden'] ?? '3'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Spieler pro Runde:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($selectedTurnier['spieler_pro_runde'] ?? '3'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Status:</td>
                            <td style="padding: 8px;">
                                <?php if ($selectedTurnier['aktiv'] == 1): ?>
                                    <span style="padding: 4px 8px; background: #d4edda; color: #155724; border-radius: 3px; font-size: 12px; font-weight: bold;">Aktiv</span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; background: #f8d7da; color: #721c24; border-radius: 3px; font-size: 12px; font-weight: bold;">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold; vertical-align: top;">Anmeldelink:</td>
                            <td style="padding: 8px;">
                                <a href="index.php?turnier=<?php echo $selectedTurnier['id']; ?>" target="_blank">anmeldung/index.php?turnier=<?php echo $selectedTurnier['id']; ?></a>
                            </td>
                        </tr>
                    </table>
                    </div>
                </div>
            <?php else: ?>
                <p style="margin-top: 10px; color: #dc3545; font-weight: bold;">
                    ⚠ Kein Turnier ausgewählt. Anmeldungen werden keinem Turnier zugeordnet.
                </p>
            <?php endif; ?>
        </div>
        
        <p><strong>Anzahl Anmeldungen:</strong> <?php echo count($anmeldungen); ?>
        <?php if (!$selectedTurnierId): ?>
            <span style="color: #dc3545; font-weight: normal;">(Kein Turnier ausgewählt - bitte wählen Sie ein Turnier aus, um Anmeldungen zu sehen)</span>
        <?php endif; ?>
        </p>
        
        <?php if ($selectedTurnierId): ?>
            <div class="export-container export-container-top">
                <a href="?export=excel" class="btn-export">Export in Excel</a>
                <button onclick="druckeTeilnehmerliste()" class="btn-print">Drucken</button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($anmeldungen) && $selectedTurnierId): ?>
            <p style="margin-top: 20px; color: #666; font-style: italic;">Keine Anmeldungen für dieses Turnier vorhanden.</p>
        <?php elseif (empty($anmeldungen) && !$selectedTurnierId): ?>
            <p style="margin-top: 20px; color: #dc3545; font-weight: bold;">Bitte wählen Sie ein Turnier aus, um die Anmeldungen zu sehen.</p>
        <?php else: ?>
        <div class="print-area">
            <?php if ($selectedTurnier): ?>
                <div style="margin-bottom: 20px; page-break-after: avoid;">
                    <h2 style="margin-bottom: 10px;"><?php echo htmlspecialchars($selectedTurnier['titel']); ?></h2>
                    <p style="margin: 5px 0;"><strong>Datum:</strong> <?php echo htmlspecialchars($selectedTurnier['datum']); ?></p>
                    <p style="margin: 5px 0;"><strong>Ort:</strong> <?php echo htmlspecialchars($selectedTurnier['ort']); ?></p>
                    <p style="margin: 5px 0;"><strong>Anzahl Anmeldungen:</strong> <?php echo count($anmeldungen); ?></p>
                    <p style="margin: 5px 0;"><strong>Druckdatum:</strong> <?php echo date('d.m.Y H:i'); ?></p>
                </div>
            <?php endif; ?>
        <table id="teilnehmerliste-tabelle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th class="name-column">Name</th>
                    <th class="email-column">E-Mail</th>
                    <th>Mobilnummer</th>
                    <th>Alter</th>
                    <th>Name auf Wertungsliste</th>
                    <th>Anmeldedatum</th>
                    <th>E-Mail gesendet</th>
                    <th>E-Mail gelesen</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anmeldungen as $anmeldung): ?>
                    <tr id="row-<?php echo $anmeldung['id']; ?>">
                        <td><?php echo htmlspecialchars($anmeldung['id']); ?></td>
                        <td class="name-column name-<?php echo $anmeldung['id']; ?>"><?php echo htmlspecialchars($anmeldung['name']); ?></td>
                        <td class="email-column email-<?php echo $anmeldung['id']; ?>"><?php echo htmlspecialchars($anmeldung['email']); ?></td>
                        <td><?php echo htmlspecialchars($anmeldung['mobilnummer'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($anmeldung['alter'] ?? '-'); ?></td>
                        <td><?php echo (isset($anmeldung['name_auf_wertungsliste']) && $anmeldung['name_auf_wertungsliste'] == 1) ? 'Ja' : 'Nein'; ?></td>
                        <td><?php echo htmlspecialchars($anmeldung['anmeldedatum'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($anmeldung['email_gesendet'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($anmeldung['email_gelesen'] ?? '-'); ?></td>
                        <td>
                            <?php
                            if (!empty($anmeldung['email_gelesen'])) {
                                echo '<span class="status status-read">Gelesen</span>';
                            } elseif (!empty($anmeldung['email_gesendet'])) {
                                echo '<span class="status status-sent">Gesendet</span>';
                            } else {
                                echo '<span class="status status-pending">Ausstehend</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <button class="icon-btn icon-btn-edit" onclick="editRow(<?php echo $anmeldung['id']; ?>)" title="Bearbeiten">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                </svg>
                            </button>
                            <button class="icon-btn icon-btn-resend" onclick="resendEmail(<?php echo $anmeldung['id']; ?>)" title="Mail erneut senden">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                            </button>
                            <?php if (!isset($anmeldung['is_registered']) || !$anmeldung['is_registered']): ?>
                            <button class="icon-btn icon-btn-delete" onclick="deleteRow(<?php echo $anmeldung['id']; ?>)" title="Löschen">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr id="edit-<?php echo $anmeldung['id']; ?>" class="edit-form">
                        <td colspan="11">
                            <form method="POST" onsubmit="return confirm('Änderungen speichern?');">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo $anmeldung['id']; ?>">
                                <input type="text" name="name" value="<?php echo htmlspecialchars($anmeldung['name']); ?>" placeholder="Name" required>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($anmeldung['email']); ?>" placeholder="E-Mail" required>
                                <input type="tel" name="mobilnummer" value="<?php echo htmlspecialchars($anmeldung['mobilnummer'] ?? ''); ?>" placeholder="Mobilnummer (optional)">
                                <button type="submit" class="btn-save">Speichern</button>
                                <button type="button" class="btn-cancel" onclick="cancelEdit(<?php echo $anmeldung['id']; ?>)">Abbrechen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        
        <?php if ($selectedTurnierId): ?>
            <div class="export-container no-print">
                <a href="?export=excel" class="btn-export">Export in Excel</a>
                <button onclick="druckeTeilnehmerliste()" class="btn-print">Drucken</button>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggle = document.querySelector('.ausgewaehltes-turnier-toggle');
            var inhalt = document.querySelector('.ausgewaehltes-turnier-inhalt');
            var icon = document.querySelector('.ausgewaehltes-turnier-icon');
            if (toggle && inhalt && icon) {
                toggle.addEventListener('click', function() {
                    var isOpen = inhalt.style.display !== 'none';
                    inhalt.style.display = isOpen ? 'none' : 'block';
                    icon.style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
                    icon.textContent = isOpen ? '▶' : '▼';
                });
            }
        });
        
        function editRow(id) {
            document.getElementById('edit-' + id).classList.add('active');
        }
        
        function cancelEdit(id) {
            document.getElementById('edit-' + id).classList.remove('active');
        }
        
        function deleteRow(id) {
            if (confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                                 '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resendEmail(id) {
            if (confirm('Möchten Sie die E-Mail wirklich erneut senden?')) {
                console.log('=== Starte E-Mail-Versand für ID: ' + id + ' ===');
                console.log('Zeitstempel: ' + new Date().toISOString());
                
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="resend_email">' +
                                 '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                
                console.log('Formular erstellt, starte Submit...');
                var submitStart = Date.now();
                form.submit();
                
                // Hinweis in der Konsole (wird nach Reload sichtbar)
                console.log('E-Mail-Versand gestartet. Bitte prüfen Sie die Server-Logs für Details.');
            }
        }
        
        function druckeTeilnehmerliste() {
            // Erstelle eine druckoptimierte Version
            var printWindow = window.open('', '_blank');
            var tabelle = document.getElementById('teilnehmerliste-tabelle');
            var turnierInfo = '';
            
            // Turnier-Informationen aus dem print-area Bereich holen
            var printArea = document.querySelector('.print-area');
            if (printArea) {
                var turnierHeader = printArea.querySelector('div');
                if (turnierHeader) {
                    turnierInfo = turnierHeader.outerHTML;
                }
            }
            
            // Tabellen-Header ohne Aktionen-Spalte
            var headerRow = tabelle.querySelector('thead tr');
            var headerCells = headerRow.querySelectorAll('th');
            var headerHTML = '<thead><tr>';
            for (var i = 0; i < headerCells.length - 1; i++) { // -1 um Aktionen-Spalte auszuschließen
                headerHTML += '<th>' + headerCells[i].textContent + '</th>';
            }
            headerHTML += '</tr></thead>';
            
            // Tabellen-Zeilen ohne Aktionen-Spalte
            var bodyRows = tabelle.querySelectorAll('tbody tr:not(.edit-form)');
            var bodyHTML = '<tbody>';
            bodyRows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                if (cells.length > 0 && !row.classList.contains('edit-form')) {
                    bodyHTML += '<tr>';
                    for (var i = 0; i < cells.length - 1; i++) { // -1 um Aktionen-Spalte auszuschließen
                        bodyHTML += '<td>' + cells[i].textContent + '</td>';
                    }
                    bodyHTML += '</tr>';
                }
            });
            bodyHTML += '</tbody>';
            
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<meta charset="UTF-8">');
            printWindow.document.write('<title>Teilnehmerliste</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h2 { margin-bottom: 10px; }');
            printWindow.document.write('p { margin: 5px 0; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
            printWindow.document.write('th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }');
            printWindow.document.write('th { background: #667eea; color: white; font-weight: bold; }');
            printWindow.document.write('tr:nth-child(even) { background: #f9f9f9; }');
            printWindow.document.write('@media print { @page { margin: 1cm; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(turnierInfo);
            printWindow.document.write('<table>');
            printWindow.document.write(headerHTML);
            printWindow.document.write(bodyHTML);
            printWindow.document.write('</table>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Warte kurz, dann drucke
            setTimeout(function() {
                printWindow.print();
            }, 250);
        }
    </script>
</body>
</html>

