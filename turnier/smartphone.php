<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    ?><!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
        <title>Smartphone-Steuerung</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                max-width: 1000px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            }
            h1 {
                color: #667eea;
                margin-bottom: 20px;
            }
            .message {
                padding: 15px;
                border-radius: 5px;
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Smartphone-Steuerung</h1>
            <div class="message">
                Kein aktives Turnier gefunden. Bitte erst ein Turnier erfassen und aktivieren.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
$aktiveRunde = isset($aktuellesTurnier['aktive_runde']) && $aktuellesTurnier['aktive_runde'] !== null
    ? intval($aktuellesTurnier['aktive_runde'])
    : null;
$aktiveErgebnisRunde = isset($aktuellesTurnier['aktive_ergebnis_runde'])
    ? $aktuellesTurnier['aktive_ergebnis_runde']
    : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Smartphone-Steuerung</title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin: 25px 0 10px 0;
        }
        p {
            margin-bottom: 10px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 6px;
            background: #f9f9f9;
            border-left: 4px solid #667eea;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .section-header small {
            color: #666;
        }
        .runde-liste,
        .ergebnis-liste {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: pointer;
            background: #fff;
            font-size: 14px;
        }
        .badge.active {
            background: #667eea;
            color: #fff;
            border-color: #5568d3;
            font-weight: bold;
        }
        .badge:hover {
            background: #e6ebff;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .hint {
            margin-top: 8px;
            font-size: 13px;
            color: #555;
        }
    </style>
    <script>
        function setAktiveRunde(runde) {
            if (runde === null) {
                // Alle Runden deaktivieren
                window.location.href = 'runde_deaktivieren.php';
                return;
            }

            var formData = new FormData();
            formData.append('runde', runde);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'runde_aktivieren.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert('Fehler: ' + (response.error || 'Unbekannter Fehler'));
                            }
                        } catch (e) {
                            window.location.reload();
                        }
                    } else {
                        alert('Fehler beim Setzen der aktiven Runde');
                    }
                }
            };
            xhr.send(formData);
        }

        function setAktiveErgebnisRunde(runde) {
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
                                window.location.reload();
                            } else {
                                alert('Fehler: ' + (response.message || 'Unbekannter Fehler'));
                            }
                        } catch (e) {
                            window.location.reload();
                        }
                    } else {
                        alert('Fehler beim Setzen der Ergebnisanzeige');
                    }
                }
            };
            xhr.send(formData);
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Smartphone-Anzeige steuern</h1>
        <p>
            Hier legen Sie fest, <strong>welche Informationen auf der Info-/Smartphone-Seite angezeigt werden</strong>
            (`info/index.php`).
        </p>

        <div class="section">
            <div class="section-header">
                <h2>1. Anzeige der Tischzuordnung (aktive Runde)</h2>
                <small>Wirkt sich auf die Abschnitt „Personenzuordnung“ und „Tischzuordnung“ der Info-Seite aus.</small>
            </div>
            <p>
                Wählen Sie, für welche Runde die <strong>Tisch- und Personenzuordnung</strong> auf den Smartphones
                angezeigt werden soll. Nur die hier aktive Runde wird auf der Info-Seite dargestellt.
            </p>

            <div class="runde-liste">
                <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                    <button
                        type="button"
                        class="badge <?php echo ($aktiveRunde === $i) ? 'active' : ''; ?>"
                        onclick="setAktiveRunde(<?php echo $i; ?>)"
                    >
                        Runde <?php echo $i; ?><?php echo ($aktiveRunde === $i) ? ' (aktiv)' : ''; ?>
                    </button>
                <?php endfor; ?>
            </div>

            <div style="margin-top: 12px;">
                <button type="button" class="btn btn-danger" onclick="setAktiveRunde(null)">
                    Alle Runden deaktivieren
                </button>
            </div>

            <p class="hint">
                Ist keine Runde aktiv, werden auf der Info-Seite keine Tisch- und Personenzuordnungen angezeigt.
            </p>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>2. Anzeige der Ergebnisse</h2>
                <small>Steuert, ob auf der Info-Seite Rundenergebnisse oder die Gesamtauswertung sichtbar ist.</small>
            </div>
            <p>
                Wählen Sie, ob die <strong>Ergebnisse einer bestimmten Runde</strong> oder die
                <strong>Gesamtergebnisse</strong> auf den Smartphones angezeigt werden sollen.
                Mit „Deaktivieren“ blenden Sie die Ergebnisanzeige vollständig aus.
            </p>

            <div class="ergebnis-liste">
                <?php for ($i = 1; $i <= $anzahlRunden; $i++): ?>
                    <button
                        type="button"
                        class="badge <?php echo ($aktiveErgebnisRunde !== null && intval($aktiveErgebnisRunde) === $i) ? 'active' : ''; ?>"
                        onclick="setAktiveErgebnisRunde(<?php echo $i; ?>)"
                    >
                        Runde <?php echo $i; ?><?php echo ($aktiveErgebnisRunde !== null && intval($aktiveErgebnisRunde) === $i) ? ' (aktiv)' : ''; ?>
                    </button>
                <?php endfor; ?>

                <button
                    type="button"
                    class="badge <?php echo ($aktiveErgebnisRunde !== null && intval($aktiveErgebnisRunde) === 0) ? 'active' : ''; ?>"
                    onclick="setAktiveErgebnisRunde(0)"
                >
                    Gesamtergebnisse<?php echo ($aktiveErgebnisRunde !== null && intval($aktiveErgebnisRunde) === 0) ? ' (aktiv)' : ''; ?>
                </button>
            </div>

            <div style="margin-top: 12px;">
                <button
                    type="button"
                    class="btn btn-danger"
                    onclick="setAktiveErgebnisRunde('null')"
                >
                    Ergebnisanzeige deaktivieren
                </button>
            </div>

            <p class="hint">
                <strong>Hinweis:</strong> Die Ergebnisanzeige verwendet die gleichen Einstellungen wie die Auswertung.
                Die aktive Einstellung sehen Sie auch im Modul „6. Auswertung“.
            </p>
        </div>
    </div>
</body>
</html>


