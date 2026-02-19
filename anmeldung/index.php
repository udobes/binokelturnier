<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/../turnier/config.php';
initDB();
initTurnierDB();

// Turnier-ID aus URL-Parameter holen (akzeptiere sowohl "turnier" als auch "tunier")
$turnierId = null;
if (isset($_GET['turnier'])) {
    $turnierId = intval($_GET['turnier']);
} elseif (isset($_GET['tunier'])) {
    // Toleranz für Tippfehler "tunier" statt "turnier"
    $turnierId = intval($_GET['tunier']);
}
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
$startzeitRaw = $turnier ? ($turnier['startzeit'] ?? '18') : '18';
$einlasszeitRaw = $turnier ? ($turnier['einlasszeit'] ?? '17') : '17';

// "Uhr" hinzufügen, falls nicht bereits vorhanden
$startzeit = (strpos($startzeitRaw, 'Uhr') !== false || strpos($startzeitRaw, 'uhr') !== false) 
    ? $startzeitRaw 
    : trim($startzeitRaw) . ' Uhr';
$einlasszeit = (strpos($einlasszeitRaw, 'Uhr') !== false || strpos($einlasszeitRaw, 'uhr') !== false) 
    ? $einlasszeitRaw 
    : trim($einlasszeitRaw) . ' Uhr';

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
                // Erfolgsdaten aus der Session holen (werden von process.php gesetzt)
                $email = $_SESSION['anmeldung_success_email'] ?? '';
                $registriernummer = $_SESSION['anmeldung_success_id'] ?? '';
                // Einmalige Anzeige, danach löschen
                unset($_SESSION['anmeldung_success_email'], $_SESSION['anmeldung_success_id']);
                $email = !empty($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '';
                $registriernummer = !empty($registriernummer) ? trim(htmlspecialchars($registriernummer, ENT_QUOTES, 'UTF-8')) : '';
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
            // Vorherige Eingaben aus der Session holen (werden von process.php gesetzt)
            $formValues = $_SESSION['anmeldung_form_values'] ?? [];
            $prevName = isset($formValues['name']) ? htmlspecialchars($formValues['name'], ENT_QUOTES, 'UTF-8') : '';
            $prevEmail = isset($formValues['email']) ? htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8') : '';
            $prevMobilnummer = isset($formValues['mobilnummer']) ? htmlspecialchars($formValues['mobilnummer'], ENT_QUOTES, 'UTF-8') : '';
            $prevAlter = isset($formValues['alter']) ? htmlspecialchars($formValues['alter'], ENT_QUOTES, 'UTF-8') : '';
            $prevNameAufWertungsliste = isset($formValues['name_auf_wertungsliste']) ? htmlspecialchars($formValues['name_auf_wertungsliste'], ENT_QUOTES, 'UTF-8') : '';
            // Nach einmaliger Verwendung löschen
            unset($_SESSION['anmeldung_form_values']);
            ?>
            <form action="process.php" method="POST">
                <?php
                // CSRF-Token setzen (einfacher Schutz gegen Formular-Manipulation)
                if (empty($_SESSION['anmeldung_csrf_token'])) {
                    $_SESSION['anmeldung_csrf_token'] = bin2hex(random_bytes(32));
                }
                ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['anmeldung_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
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

                <div class="form-group">
                    <label for="alter">Alter</label>
                    <input type="text" id="alter" name="alter" value="<?php echo $prevAlter; ?>" placeholder="z.B. 35">
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="name_auf_wertungsliste" name="name_auf_wertungsliste" value="1" <?php echo ($prevNameAufWertungsliste === '0') ? '' : 'checked'; ?>>
                        <span>Mein Name darf auf den Wertungslisten genannt werden</span>
                    </label>
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="datenschutz" name="datenschutz" value="1" required>
                        <span>Ich bin mit der Speicherung meiner Daten für interne Zwecke einverstanden *</span>
                    </label>
                </div>

                <button type="submit" class="btn" id="submitBtn">Anmelden</button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.getElementById('submitBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (form && submitBtn && loadingOverlay) {
                form.addEventListener('submit', function(e) {
                    // Zeige sofort den Wartekreisel
                    loadingOverlay.classList.add('active');
                    
                    // Deaktiviere den Button, um mehrfaches Klicken zu verhindern
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Wird verarbeitet...';
                });
            }
        });
    </script>
</body>
</html>