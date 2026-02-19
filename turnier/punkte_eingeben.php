<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Nur POST-Anfragen erlaubt.']);
    exit;
}

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    echo json_encode(['success' => false, 'message' => 'Kein aktives Turnier gefunden.']);
    exit;
}

// Parameter prüfen
if (!isset($_POST['startnummer']) || !isset($_POST['runde']) || !isset($_POST['punkte']) || !isset($_POST['pin'])) {
    echo json_encode(['success' => false, 'message' => 'Alle Felder müssen ausgefüllt sein.']);
    exit;
}

$startnummer = intval($_POST['startnummer']);
$runde = intval($_POST['runde']);
$punkte = isset($_POST['punkte']) && $_POST['punkte'] !== '' ? intval($_POST['punkte']) : null;
$pin = trim($_POST['pin']);

// Runde validieren
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
if ($runde < 1 || $runde > $anzahlRunden) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rundennummer.']);
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
    echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht gefunden.']);
    exit;
}

// PIN validieren
if (empty($registrierung['pin']) || $registrierung['pin'] !== $pin) {
    echo json_encode(['success' => false, 'message' => 'Ungültige PIN. Bitte prüfen Sie die PIN auf Ihrem Laufzettel.']);
    exit;
}

// Punktzahl speichern
try {
    speichereErgebnis($aktuellesTurnier['id'], $runde, $startnummer, $punkte);
    
    echo json_encode(['success' => true, 'message' => 'Punkte erfolgreich gespeichert.']);
} catch (Exception $e) {
    error_log("Fehler beim Speichern der Punkte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Punkte.']);
}
?>

