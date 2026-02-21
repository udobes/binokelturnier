<?php
require_once 'config.php';
initTurnierDB();

// Aktuelles Turnier holen für Anzeige im Kopfbereich
$aktuellesTurnier = getAktuellesTurnier();

// Alle Turniere holen
$alleTurniere = getAllTurniere();

// Erfolgsmeldung und Fehler prüfen
$success = null;
if (isset($_GET['success'])) {
    $success = 'Aktion wurde erfolgreich durchgeführt!';
}
$error = null;
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'empty') {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } elseif ($_GET['error'] == 'database') {
        $error = 'Fehler beim Speichern. Bitte versuchen Sie es erneut.';
    } elseif ($_GET['error'] == 'not_divisible') {
        $anzahl = isset($_GET['anzahl']) ? intval($_GET['anzahl']) : 0;
        $pro_runde = isset($_GET['pro_runde']) ? intval($_GET['pro_runde']) : 0;
        $error = "Die Anzahl der Spieler ($anzahl) muss ganzzahlig durch die Anzahl Spieler pro Runde ($pro_runde) teilbar sein!";
    } elseif ($_GET['error'] == 'invalid') {
        $error = 'Ungültige Anfrage.';
    }
}

// Bearbeiten-Modus prüfen
$editMode = false;
$editTurnier = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editTurnier = getTurnierById($editId);
    if ($editTurnier) {
        $editMode = true;
    }
}

// Formular anzeigen?
$showForm = isset($_GET['new']) || $editMode;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Turnier erfassen</title>
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
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
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
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
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
        input[type="text"],
        input[type="date"],
        input[type="number"],
        input[type="url"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="number"]:focus,
        input[type="url"]:focus {
            outline: none;
            border-color: #667eea;
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
        .no-turnier {
            color: #999;
            font-style: italic;
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
        .icon-btn {
            padding: 6px 10px;
            margin: 0 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, transform 0.1s;
            vertical-align: middle;
        }
        .icon-btn:hover {
            transform: scale(1.1);
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
        .icon-btn-activate {
            background: #17a2b8;
        }
        .icon-btn-activate:hover {
            background: #138496;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .status-active {
            padding: 4px 8px;
            background: #d4edda;
            color: #155724;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-inactive {
            padding: 4px 8px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="toolbar">
            <a href="?new=1" class="btn">Neues Turnier</a>
            <?php if ($showForm): ?>
                <a href="turnier_erfassen.php" class="btn btn-secondary">Abbrechen</a>
            <?php endif; ?>
        </div>

        <?php if ($showForm): ?>
            <h2><?php echo $editMode ? 'Turnier bearbeiten' : 'Neues Turnier erfassen'; ?></h2>
            
            <?php if ($success): ?>
                <div class="message success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="turnier_speichern.php" method="POST">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?php echo $editTurnier['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="datum">Datum *</label>
                    <input type="date" id="datum" name="datum" required value="<?php echo $editMode ? htmlspecialchars($editTurnier['datum']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="titel">Titel *</label>
                    <input type="text" id="titel" name="titel" required placeholder="z.B. 2. Binokelturnier Heroldstatt" value="<?php echo $editMode ? htmlspecialchars($editTurnier['titel']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="veranstalter">Veranstalter *</label>
                    <input type="text" id="veranstalter" name="veranstalter" required placeholder="z.B. AKS Heroldstatt" value="<?php echo $editMode ? htmlspecialchars($editTurnier['veranstalter']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="ort">Ort *</label>
                    <input type="text" id="ort" name="ort" required placeholder="z.B. Berghalle, 72535 Heroldstatt" value="<?php echo $editMode ? htmlspecialchars($editTurnier['ort']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="einlasszeit">Einlasszeit (optional)</label>
                    <input type="text" id="einlasszeit" name="einlasszeit" placeholder="z.B. 17 Uhr" value="<?php echo $editMode ? htmlspecialchars($editTurnier['einlasszeit'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="startzeit">Startzeit (optional)</label>
                    <input type="text" id="startzeit" name="startzeit" placeholder="z.B. 18 Uhr" value="<?php echo $editMode ? htmlspecialchars($editTurnier['startzeit'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="googlemaps_link">Google Maps Link (optional)</label>
                    <input type="url" id="googlemaps_link" name="googlemaps_link" placeholder="z.B. https://maps.app.goo.gl/WW1vLiSJfzJ5VBrt8" value="<?php echo $editMode ? htmlspecialchars($editTurnier['googlemaps_link'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="anzahl_spieler">Anzahl der Spieler *</label>
                    <input type="number" id="anzahl_spieler" name="anzahl_spieler" required min="1" placeholder="z.B. 16" value="<?php echo $editMode ? htmlspecialchars($editTurnier['anzahl_spieler'] ?? '0') : ''; ?>" onchange="pruefeTeilbarkeit()" oninput="pruefeTeilbarkeit()">
                </div>

                <div class="form-group">
                    <label for="anzahl_runden">Anzahl der Runden *</label>
                    <input type="number" id="anzahl_runden" name="anzahl_runden" required min="1" value="<?php echo $editMode ? htmlspecialchars($editTurnier['anzahl_runden'] ?? '3') : '3'; ?>">
                </div>

                <div class="form-group">
                    <label for="spieler_pro_runde">Anzahl Spieler pro Runde (pro Tisch) *</label>
                    <input type="number" id="spieler_pro_runde" name="spieler_pro_runde" required min="2" value="<?php echo $editMode ? htmlspecialchars($editTurnier['spieler_pro_runde'] ?? '3') : '3'; ?>" onchange="pruefeTeilbarkeit()" oninput="pruefeTeilbarkeit()">
                    <small id="teilbarkeits_hinweis" style="display: none; color: #dc3545; margin-top: 5px; display: block;"></small>
                </div>

                <button type="submit" class="btn" id="submitBtn"><?php echo $editMode ? 'Turnier aktualisieren' : 'Turnier speichern'; ?></button>
            </form>
            
            <script>
                function pruefeTeilbarkeit() {
                    var anzahlSpieler = parseInt(document.getElementById('anzahl_spieler').value) || 0;
                    var spielerProRunde = parseInt(document.getElementById('spieler_pro_runde').value) || 0;
                    var hinweis = document.getElementById('teilbarkeits_hinweis');
                    var submitBtn = document.getElementById('submitBtn');
                    
                    if (anzahlSpieler > 0 && spielerProRunde > 0) {
                        if (anzahlSpieler % spielerProRunde != 0) {
                            var rest = anzahlSpieler % spielerProRunde;
                            hinweis.textContent = 'Warnung: Die Anzahl der Spieler (' + anzahlSpieler + ') ist nicht ganzzahlig durch die Anzahl Spieler pro Runde (' + spielerProRunde + ') teilbar. Rest: ' + rest + '.';
                            hinweis.style.color = '#dc3545';
                            hinweis.style.display = 'block';
                            submitBtn.disabled = false; // Erlaube trotzdem das Absenden, Server validiert auch
                        } else {
                            hinweis.textContent = '✓ Die Anzahl der Spieler ist durch die Anzahl Spieler pro Runde teilbar.';
                            hinweis.style.color = '#28a745';
                            hinweis.style.display = 'block';
                            submitBtn.disabled = false;
                        }
                    } else {
                        hinweis.style.display = 'none';
                        submitBtn.disabled = false;
                    }
                }
                
                // Beim Laden der Seite prüfen, falls Werte bereits vorhanden
                window.addEventListener('DOMContentLoaded', function() {
                    pruefeTeilbarkeit();
                });
            </script>
        <?php else: ?>
            <h2>Alle Turniere</h2>
            
            <?php if ($success): ?>
                <div class="message success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($alleTurniere)): ?>
                <p class="no-turnier">Noch keine Turniere erfasst.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Datum</th>
                            <th>Titel</th>
                            <th>Veranstalter</th>
                            <th>Ort</th>
                            <th>Einlass</th>
                            <th>Start</th>
                            <th>Google Maps</th>
                            <th>Spieler</th>
                            <th>Runden</th>
                            <th>Spieler/Tisch</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alleTurniere as $turnier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($turnier['id']); ?></td>
                                <td><?php echo htmlspecialchars($turnier['datum']); ?></td>
                                <td><?php echo htmlspecialchars($turnier['titel']); ?></td>
                                <td><?php echo htmlspecialchars($turnier['veranstalter']); ?></td>
                                <td><?php echo htmlspecialchars($turnier['ort']); ?></td>
                                <td><?php echo htmlspecialchars($turnier['einlasszeit'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($turnier['startzeit'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($turnier['googlemaps_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($turnier['googlemaps_link']); ?>" target="_blank" title="<?php echo htmlspecialchars($turnier['googlemaps_link']); ?>">Link</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($turnier['anzahl_spieler'] ?? '0'); ?></td>
                                <td><?php echo htmlspecialchars($turnier['anzahl_runden'] ?? '3'); ?></td>
                                <td><?php echo htmlspecialchars($turnier['spieler_pro_runde'] ?? '3'); ?></td>
                                <td>
                                    <?php if ($turnier['aktiv'] == 1): ?>
                                        <span class="status-active">Aktiv</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($turnier['aktiv'] != 1): ?>
                                            <button class="icon-btn icon-btn-activate" onclick="activateTurnier(<?php echo $turnier['id']; ?>)" title="Aktivieren">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        <button class="icon-btn icon-btn-edit" onclick="editTurnier(<?php echo $turnier['id']; ?>)" title="Bearbeiten">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                            </svg>
                                        </button>
                                        <button class="icon-btn icon-btn-delete" onclick="deleteTurnier(<?php echo $turnier['id']; ?>)" title="Löschen">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
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

    <script>
        function editTurnier(id) {
            window.location.href = '?edit=' + id;
        }

        function deleteTurnier(id) {
            if (confirm('Möchten Sie dieses Turnier wirklich löschen?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'turnier_loeschen.php';
                form.innerHTML = '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function activateTurnier(id) {
            if (confirm('Möchten Sie dieses Turnier aktivieren? Alle anderen Turniere werden deaktiviert.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'turnier_aktivieren.php';
                form.innerHTML = '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
