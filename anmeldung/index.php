<?php
require_once 'config.php';
require_once __DIR__ . '/../turnier/config.php';
initDB();
initTurnierDB();

// Turnier-ID aus URL-Parameter holen
$turnierId = isset($_GET['turnier']) ? intval($_GET['turnier']) : null;
$turnier = null;

if ($turnierId) {
    $turnier = getTurnierById($turnierId);
}

// Falls kein Turnier über Parameter, versuche Standard-Turnier aus DB zu holen
if (!$turnier) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT wert FROM config WHERE schluessel = ?");
        $stmt->execute(['default_turnier_id']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['wert'])) {
            $turnierId = intval($result['wert']);
            $turnier = getTurnierById($turnierId);
        }
    } catch (PDOException $e) {
        // Fehler ignorieren
    }
}

// Falls immer noch kein Turnier, versuche aktives Turnier zu holen
if (!$turnier) {
    $turnier = getAktuellesTurnier();
    if ($turnier) {
        $turnierId = $turnier['id'];
    }
}

// Standardwerte, falls kein Turnier gefunden
$titel = $turnier ? $turnier['titel'] : '2. Binokelturnier Heroldstatt';
$datum = $turnier ? $turnier['datum'] : '09.05.2026';
$ort = $turnier ? $turnier['ort'] : 'Berghalle';
$startzeit = $turnier ? ($turnier['startzeit'] ?? '18 Uhr') : '18 Uhr';
$einlasszeit = $turnier ? ($turnier['einlasszeit'] ?? '17 Uhr') : '17 Uhr';
$subtitle = $turnier ? 
    ($datum . ' in ' . $ort . ', Beginn ' . $startzeit . ' (Einlass ' . $einlasszeit . ')') :
    ('09.05.2026 in der Berghalle, Beginn 18 Uhr (Einlass 17 Uhr)');

// Prüfen, ob das Turnierdatum überschritten ist
$anmeldungMoeglich = true;
if ($turnier && !empty($turnier['datum'])) {
    // Datum parsen (kann im Format YYYY-MM-DD oder DD.MM.YYYY sein)
    $turnierDatumStr = $turnier['datum'];
    $turnierDatum = null;
    
    // Versuche zuerst YYYY-MM-DD Format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $turnierDatumStr)) {
        $turnierDatum = DateTime::createFromFormat('Y-m-d', $turnierDatumStr);
    }
    // Versuche DD.MM.YYYY Format
    elseif (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $turnierDatumStr)) {
        $turnierDatum = DateTime::createFromFormat('d.m.Y', $turnierDatumStr);
    }
    
    if ($turnierDatum) {
        $turnierDatum->setTime(23, 59, 59); // Ende des Tages
        $heute = new DateTime();
        $heute->setTime(0, 0, 0); // Anfang des Tages
        
        if ($turnierDatum < $heute) {
            $anmeldungMoeglich = false;
        }
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Anmeldung - <?php echo htmlspecialchars($titel); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($titel); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars($subtitle); ?></p>

        <?php if (!$anmeldungMoeglich): ?>
            <div class="message error" style="text-align: center; padding: 20px; font-size: 18px; font-weight: bold; margin-top: 20px;">
                Keine Anmeldung mehr möglich
            </div>
        <?php elseif (isset($_GET['success'])): ?>
            <div class="message success">
                <?php
                // URL-Parameter direkt aus $_GET lesen
                // Prüfe auch auf "amp;email" und "amp;id" falls & zu &amp; encodiert wurde
                $emailRaw = $_GET['email'] ?? $_GET['amp;email'] ?? '';
                $registriernummerRaw = $_GET['id'] ?? $_GET['amp;id'] ?? '';
                
                // Falls Parameter mit "amp;" gefunden wurden, parse die Query-String manuell
                if (empty($emailRaw) && empty($registriernummerRaw) && isset($_SERVER['QUERY_STRING'])) {
                    // Ersetze &amp; durch & und parse dann
                    $queryString = str_replace('&amp;', '&', $_SERVER['QUERY_STRING']);
                    $queryString = str_replace('amp;', '', $queryString); // Falls "amp;" ohne & vorkommt
                    parse_str($queryString, $parsedParams);
                    $emailRaw = $parsedParams['email'] ?? '';
                    $registriernummerRaw = $parsedParams['id'] ?? '';
                }
                
                // Falls immer noch leer, versuche direkt aus den falsch geparsten Parametern
                if (empty($emailRaw) && isset($_GET['amp;email'])) {
                    $emailRaw = $_GET['amp;email'];
                }
                if (empty($registriernummerRaw) && isset($_GET['amp;id'])) {
                    $registriernummerRaw = $_GET['amp;id'];
                }
                
                $email = !empty($emailRaw) ? htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8') : '';
                $registriernummer = !empty($registriernummerRaw) ? trim(htmlspecialchars($registriernummerRaw, ENT_QUOTES, 'UTF-8')) : '';
                
                // Debug: Konsolen-Ausgabe für URL-Parameter
                echo '<script>console.log("=== Registriernummer Debug ===");';
                echo 'console.log("Alle GET-Parameter (roh):", ' . json_encode($_GET) . ');';
                echo 'console.log("Query String:", ' . json_encode($_SERVER['QUERY_STRING'] ?? '') . ');';
                echo 'console.log("URL Parameter id (roh):", ' . json_encode($registriernummerRaw) . ');';
                echo 'console.log("URL Parameter email (roh):", ' . json_encode($emailRaw) . ');';
                echo 'console.log("Registriernummer (verarbeitet):", ' . json_encode($registriernummer) . ');';
                echo 'console.log("E-Mail (verarbeitet):", ' . json_encode($email) . ');';
                
                // Falls ID nicht in URL, versuche aus Datenbank zu holen (falls E-Mail vorhanden)
                if (empty($registriernummer) && !empty($email)) {
                    echo 'console.log("Registriernummer leer, versuche aus DB zu holen...");';
                    require_once 'config.php';
                    try {
                        $db = getDB();
                        $stmt = $db->prepare("SELECT id FROM anmeldungen WHERE email = ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$email]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && isset($result['id'])) {
                            $registriernummer = (string)$result['id'];
                            echo 'console.log("Registriernummer aus DB geholt:", ' . json_encode($registriernummer) . ');';
                        } else {
                            echo 'console.log("Keine Registriernummer in DB gefunden");';
                        }
                    } catch (Exception $e) {
                        echo 'console.error("Fehler beim Holen aus DB:", ' . json_encode($e->getMessage()) . ');';
                    }
                }
                echo 'console.log("Finale Registriernummer:", ' . json_encode($registriernummer) . ');';
                echo 'console.log("===========================");</script>';
                ?>
                <p><strong>Vielen Dank für die Anmeldung.</strong></p>
                <?php if (!empty($registriernummer)): ?>
                    <p class="registrierung-info"><strong>Ihre Registriernummer:</strong> <span class="registriernummer"><?php echo $registriernummer; ?></span></p>
                    <p><strong>Für eine schnelle Anmeldung am Spielabend, halten Sie bitte Ihre Registriernummer bereit.</strong></p>
                <?php endif; ?>
                <?php if ($email): ?>
                    <p class="email-info">Es wird ein Mail an <strong><?php echo $email; ?></strong> mit der Bestätigung der Anmeldung und weiteren Informationen gesendet.</p>
                <?php else: ?>
                    <p class="email-info">Es wird ein Mail mit der Bestätigung der Anmeldung und weiteren Informationen gesendet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $anmeldungMoeglich): ?>
            <div class="message error">
                <?php
                $error = $_GET['error'];
                if ($error == 'empty') {
                    echo 'Bitte füllen Sie alle Felder aus.';
                } elseif ($error == 'email') {
                    echo 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
                } elseif ($error == 'datenschutz') {
                    echo 'Bitte bestätigen Sie die Speicherung Ihrer Daten.';
                } elseif ($error == 'datum_ueberschritten') {
                    echo 'Keine Anmeldung mehr möglich.';
                } else {
                    echo 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($anmeldungMoeglich && !isset($_GET['success'])): ?>
            <?php
            // Vorherige Eingaben aus GET-Parametern holen (falls vorhanden)
            $prevName = isset($_GET['name']) ? htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8') : '';
            $prevEmail = isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : '';
            $prevMobilnummer = isset($_GET['mobilnummer']) ? htmlspecialchars($_GET['mobilnummer'], ENT_QUOTES, 'UTF-8') : '';
            ?>
            <form action="process.php" method="POST">
                <input type="hidden" name="turnier_id" value="<?php echo $turnierId ? $turnierId : ''; ?>">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo $prevName; ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">E-Mail-Adresse *</label>
                    <input type="email" id="email" name="email" value="<?php echo $prevEmail; ?>" required>
                </div>

                <div class="form-group">
                    <label for="mobilnummer">Mobilfunknummer (optional)</label>
                    <input type="tel" id="mobilnummer" name="mobilnummer" value="<?php echo $prevMobilnummer; ?>" placeholder="z.B. 0171 12345678">
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="datenschutz" name="datenschutz" value="1" required>
                        <span>Ich bin mit der Speicherung meiner Daten für interne Zwecke einverstanden *</span>
                    </label>
                </div>

                <button type="submit" class="btn">Anmelden</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>