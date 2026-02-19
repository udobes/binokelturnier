<?php
session_start();
require_once __DIR__ . '/../turnier/config.php';
initTurnierDB();

// Prüfen ob Startnummer vorhanden
if (!isset($_SESSION['startnummer']) || $_SESSION['startnummer'] === '') {
    $_SESSION['punkte_error'] = 'Keine Startnummer gefunden.';
    header('Location: punkte_eingabe.php');
    exit;
}

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    $_SESSION['punkte_error'] = 'Kein aktives Turnier gefunden.';
    header('Location: index.php');
    exit;
}

// Prüfen ob Spieler gesperrt ist
$db = getDB();
$stmt = $db->prepare("SELECT gesperrt FROM turnier_registrierungen WHERE turnier_id = ? AND startnummer = ?");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);
$istGesperrt = ($registrierung && isset($registrierung['gesperrt']) && intval($registrierung['gesperrt']) === 1);

if ($istGesperrt) {
    $_SESSION['punkte_error'] = 'Sie sind gesperrt und können keine Punkte eingeben.';
    header('Location: punkte_eingabe.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: punkte_eingabe.php');
    exit;
}

// Parameter prüfen
if (!isset($_POST['startnummer']) || !isset($_POST['runde']) || !isset($_POST['punkte'])) {
    $_SESSION['punkte_error'] = 'Alle Felder müssen ausgefüllt sein.';
    header('Location: punkte_eingabe.php');
    exit;
}

$startnummer = intval($_POST['startnummer']);
$runde = intval($_POST['runde']);
$punkte = isset($_POST['punkte']) && $_POST['punkte'] !== '' ? intval($_POST['punkte']) : null;
$pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';

// Runde validieren
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
if ($runde < 1 || $runde > $anzahlRunden) {
    $_SESSION['punkte_error'] = 'Ungültige Rundennummer.';
    header('Location: punkte_eingabe.php');
    exit;
}

// Prüfen ob bereits Punkte vorhanden sind
$ergebnis = getErgebnis($aktuellesTurnier['id'], $runde, $startnummer);
$punkteVorhanden = ($ergebnis && $ergebnis['punkte'] !== null);

if ($punkteVorhanden) {
    // Bereits vorhanden, keine PIN-Prüfung nötig, aber auch kein Speichern möglich
    $_SESSION['punkte_error'] = 'Punkte wurden bereits eingegeben und können nicht geändert werden.';
    header('Location: punkte_eingabe.php');
    exit;
}

// PIN validieren (nur wenn noch keine Punkte vorhanden)
if (empty($pin)) {
    $_SESSION['punkte_error'] = 'PIN ist erforderlich.';
    header('Location: punkte_eingabe.php');
    exit;
}

$db = getDB();

// Registrierung mit PIN prüfen
$stmt = $db->prepare("
    SELECT id, pin 
    FROM turnier_registrierungen 
    WHERE turnier_id = ? AND startnummer = ?
");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registrierung) {
    $_SESSION['punkte_error'] = 'Teilnehmer nicht gefunden.';
    header('Location: punkte_eingabe.php');
    exit;
}

// PIN validieren
if (empty($registrierung['pin']) || $registrierung['pin'] !== $pin) {
    // Punkte in Session speichern, damit sie erhalten bleiben
    $_SESSION['punkte_eingabe'] = $punkte;
    $_SESSION['punkte_error'] = 'Ungültige PIN. Bitte prüfen Sie die PIN auf Ihrem Laufzettel.';
    header('Location: punkte_eingabe.php');
    exit;
}

// Punktzahl speichern
try {
    speichereErgebnis($aktuellesTurnier['id'], $runde, $startnummer, $punkte);
    
    // Eingabe aus Session löschen, da erfolgreich gespeichert
    unset($_SESSION['punkte_eingabe']);
    
    $_SESSION['punkte_success'] = 'Punkte erfolgreich gespeichert!';
    header('Location: punkte_eingabe.php');
    exit;
} catch (Exception $e) {
    error_log("Fehler beim Speichern der Punkte: " . $e->getMessage());
    // Punkte in Session speichern, damit sie erhalten bleiben
    $_SESSION['punkte_eingabe'] = $punkte;
    $_SESSION['punkte_error'] = 'Fehler beim Speichern der Punkte.';
    header('Location: punkte_eingabe.php');
    exit;
}
?>

