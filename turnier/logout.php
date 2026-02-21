<?php
/**
 * Abmeldeseite vom Turnierbereich (HTTP Basic Auth).
 * Ein echtes Abmelden ist nur durch Schließen des Fensters möglich –
 * ein Link zurück würde die gespeicherten Zugangsdaten nutzen.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abgemeldet</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
        .box { background: white; padding: 30px; border-radius: 8px; max-width: 420px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 15px; font-size: 20px; }
        p { color: #666; margin-bottom: 20px; line-height: 1.5; }
        .small { font-size: 12px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Abmeldeseite</h1>
        <p><strong>Zum Abmelden:</strong> Schließen Sie dieses Browser-Fenster oder den Tab.</p>
        <p>Erst dann sind Sie abgemeldet. Beim nächsten Aufruf der Turnierverwaltung (neues Fenster oder neue Adresse) werden Sie erneut nach Benutzername und Passwort gefragt.</p>
        <p class="small">Es gibt bewusst keinen Link „Zurück“ – der würde Sie ohne erneute Anmeldung wieder einloggen.</p>
    </div>
</body>
</html>
