<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Nur GET-Anfragen erlaubt.']);
    exit;
}

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    echo json_encode(['success' => false, 'message' => 'Kein aktives Turnier gefunden.']);
    exit;
}

// Parameter prüfen
if (!isset($_GET['startnummer']) || !isset($_GET['runde'])) {
    echo json_encode(['success' => false, 'message' => 'Startnummer und Runde müssen angegeben sein.']);
    exit;
}

$startnummer = intval($_GET['startnummer']);
$runde = intval($_GET['runde']);

// Runde validieren
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
if ($runde < 1 || $runde > $anzahlRunden) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rundennummer.']);
    exit;
}

// Ergebnis laden
$ergebnis = getErgebnis($aktuellesTurnier['id'], $runde, $startnummer);

if ($ergebnis && $ergebnis['punkte'] !== null) {
    echo json_encode([
        'success' => true,
        'punkte' => intval($ergebnis['punkte']),
        'vorhanden' => true
    ]);
} else {
    echo json_encode([
        'success' => true,
        'punkte' => null,
        'vorhanden' => false
    ]);
}
?>


