<?php
session_start();
require_once __DIR__ . '/../turnier/config.php';
initTurnierDB();

// Prüfen ob Startnummer vorhanden
if (!isset($_SESSION['startnummer']) || $_SESSION['startnummer'] === '') {
    header('Location: index.php');
    exit;
}

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    header('Location: index.php');
    exit;
}

$startnummer = intval($_SESSION['startnummer']);
$aktiveRunde = isset($aktuellesTurnier['aktive_runde']) && $aktuellesTurnier['aktive_runde'] !== null 
    ? intval($aktuellesTurnier['aktive_runde']) 
    : null;

if ($aktiveRunde === null) {
    header('Location: index.php');
    exit;
}

// Prüfen ob Spieler gesperrt ist
$db = getDB();
$stmt = $db->prepare("SELECT gesperrt FROM turnier_registrierungen WHERE turnier_id = ? AND startnummer = ?");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);
$istGesperrt = ($registrierung && isset($registrierung['gesperrt']) && intval($registrierung['gesperrt']) === 1);

// Spielername laden
$db = getDB();
$stmt = $db->prepare("
    SELECT a.name 
    FROM turnier_registrierungen tr
    LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
    WHERE tr.turnier_id = ? AND tr.startnummer = ?
");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$spielerDaten = $stmt->fetch(PDO::FETCH_ASSOC);
$spielerName = $spielerDaten['name'] ?? '';

// Prüfen ob bereits Punkte vorhanden sind
$ergebnis = getErgebnis($aktuellesTurnier['id'], $aktiveRunde, $startnummer);
$punkteVorhanden = ($ergebnis && $ergebnis['punkte'] !== null);
$vorhandenePunkte = $punkteVorhanden ? intval($ergebnis['punkte']) : null;

// Falls keine Punkte in DB vorhanden, aber in Session (z.B. nach falscher PIN), diese verwenden
$punkteAusSession = null;
if (!$punkteVorhanden && isset($_SESSION['punkte_eingabe']) && $_SESSION['punkte_eingabe'] !== '') {
    $punkteAusSession = intval($_SESSION['punkte_eingabe']);
}

// Erfolgsmeldung aus Session
$successMessage = null;
if (isset($_SESSION['punkte_success'])) {
    $successMessage = $_SESSION['punkte_success'];
    unset($_SESSION['punkte_success']);
}

// Fehlermeldung aus Session
$errorMessage = null;
if (isset($_SESSION['punkte_error'])) {
    $errorMessage = $_SESSION['punkte_error'];
    unset($_SESSION['punkte_error']);
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Punkte eingeben - <?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></title>
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
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        h2 {
            color: #333;
            margin: 20px 0 10px 0;
            font-size: 18px;
        }
        .info-box {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group input[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #ccc;
            color: #333;
        }
        .btn-secondary:hover {
            background: #bbb;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
        .success-message {
            color: #155724;
            margin-top: 10px;
            padding: 10px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Zurück zur Info-Seite</a>
        
        <h1>Punkte eingeben</h1>
        
        <div class="info-box">
            <p><strong>Turnier:</strong> <?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></p>
            <p><strong>Startnummer:</strong> <?php echo htmlspecialchars($startnummer); ?></p>
            <?php if ($spielerName): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($spielerName); ?></p>
            <?php endif; ?>
            <p><strong>Runde:</strong> <?php echo htmlspecialchars($aktiveRunde); ?></p>
            <?php if ($istGesperrt): ?>
                <p style="color: #dc3545; font-weight: bold; margin-top: 10px;">⚠ Sie sind gesperrt und können keine Punkte mehr eingeben.</p>
            <?php endif; ?>
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="punkte_speichern.php">
            <input type="hidden" name="startnummer" value="<?php echo htmlspecialchars($startnummer); ?>">
            <input type="hidden" name="runde" value="<?php echo htmlspecialchars($aktiveRunde); ?>">
            
            <div class="form-group">
                <label for="punkte">Punkte für Runde <?php echo htmlspecialchars($aktiveRunde); ?>:</label>
                <input 
                    type="number" 
                    id="punkte" 
                    name="punkte" 
                    min="0" 
                    value="<?php 
                        if ($vorhandenePunkte !== null) {
                            echo htmlspecialchars($vorhandenePunkte);
                        } elseif ($punkteAusSession !== null) {
                            echo htmlspecialchars($punkteAusSession);
                        }
                    ?>"
                    <?php echo ($punkteVorhanden || $istGesperrt) ? 'readonly' : 'required autofocus'; ?>
                >
                <?php if ($punkteVorhanden): ?>
                    <small style="color: #666; display: block; margin-top: 5px;">Punkte wurden bereits eingegeben.</small>
                <?php elseif ($istGesperrt): ?>
                    <small style="color: #dc3545; display: block; margin-top: 5px;">Sie sind gesperrt und können keine Punkte eingeben.</small>
                <?php endif; ?>
            </div>
            
            <?php if (!$punkteVorhanden && !$istGesperrt): ?>
                <div class="form-group">
                    <label for="pin">PIN (vom Laufzettel):</label>
                    <input 
                        type="tel" 
                        id="pin" 
                        name="pin" 
                        maxlength="4" 
                        pattern="[0-9]{4}" 
                        inputmode="numeric" 
                        required
                    >
                </div>
            <?php endif; ?>
            
            <div class="button-group">
                <a href="index.php" class="btn-secondary" style="text-decoration: none; display: inline-block; text-align: center;">Zurück</a>
                <?php if (!$punkteVorhanden && !$istGesperrt): ?>
                    <button type="submit" class="btn-primary">Speichern</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>

