<?php
require_once 'config.php';
initTurnierDB();

$htpasswdPath = __DIR__ . '/../data/.htpasswd';
$message = '';
$error = '';
$istUdo = (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] === 'udo');

// htaccess-Benutzer anlegen (nur für Benutzer "udo")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'htpasswd_add') {
        if (!$istUdo) {
            $error = 'Nur der Benutzer "udo" darf neue htaccess-Benutzer anlegen.';
        } else {
            $user = isset($_POST['htpasswd_user']) ? trim($_POST['htpasswd_user']) : '';
            $pass = isset($_POST['htpasswd_pass']) ? $_POST['htpasswd_pass'] : '';
            if ($user === '' || preg_match('/[:\n\r]/', $user)) {
                $error = 'Ungültiger Benutzername (keine Doppelpunkte oder Zeilenumbrüche).';
            } elseif (strlen($pass) < 4) {
                $error = 'Das Passwort muss mindestens 4 Zeichen haben.';
            } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $line = $user . ':' . $hash . "\n";
            $content = '';
            if (is_file($htpasswdPath)) {
                $content = file_get_contents($htpasswdPath);
                $lines = explode("\n", $content);
                $lines = array_filter($lines, function($l) use ($user) {
                    return $l !== '' && strpos($l, $user . ':') !== 0;
                });
                $content = implode("\n", $lines) . (count($lines) ? "\n" : '');
            }
            $content .= $line;
            if (file_put_contents($htpasswdPath, $content) !== false) {
                $message = 'Benutzer "' . htmlspecialchars($user) . '" wurde angelegt.';
            } else {
                $error = 'Datei konnte nicht geschrieben werden (Schreibrechte prüfen).';
            }
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            max-width: 600px;
            margin: 0 auto;
        }
        h1 { color: #667eea; margin-bottom: 25px; }
        h2 { color: #333; margin: 25px 0 15px 0; font-size: 18px; }
        .message { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"],
        .form-group input[type="password"] { width: 100%; max-width: 280px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #5568d3; }
        .btn-link {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn-link:hover { background: #218838; }
        .section { margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #eee; }
        .section:last-of-type { border-bottom: none; }
        .hint { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>Admin</h1>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>1. htaccess-Benutzer anlegen</h2>
            <?php if ($istUdo): ?>
            <p class="hint">Neue Benutzer für die Turnier-Verwaltung (HTTP-Anmeldung). Bestehende Einträge mit gleichem Namen werden ersetzt.</p>
            <form method="POST">
                <input type="hidden" name="action" value="htpasswd_add">
                <div class="form-group">
                    <label for="htpasswd_user">Benutzername</label>
                    <input type="text" id="htpasswd_user" name="htpasswd_user" required maxlength="100" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="htpasswd_pass">Passwort</label>
                    <input type="password" id="htpasswd_pass" name="htpasswd_pass" required minlength="4" autocomplete="new-password">
                </div>
                <button type="submit" class="btn">Benutzer anlegen</button>
            </form>
            <?php else: ?>
            <p class="hint" style="color: #856404;">Nur der Benutzer „udo“ darf hier neue htaccess-Benutzer anlegen. Sie sind als „<?php echo htmlspecialchars($_SERVER['REMOTE_USER'] ?? '?'); ?>“ angemeldet.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>2. Vom Turnierbereich abmelden</h2>
            <p class="hint">Öffnet eine Hinweis-Seite. Zum Abmelden das Fenster schließen – dann ist beim nächsten Aufruf erneut eine Anmeldung nötig.</p>
            <?php $logoutUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/logout.php'; ?>
            <a href="<?php echo htmlspecialchars($logoutUrl); ?>" class="btn-link" target="_top">Abmelden</a>
        </div>
    </div>
</body>
</html>
