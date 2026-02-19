<?php
session_start();
require_once 'config.php';
initTurnierDB();

// Falls anmeldung/config.php noch nicht geladen wurde
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

header('Content-Type: application/json; charset=utf-8');

// Prüfen ob Startnummer übergeben wurde
if (!isset($_GET['startnummer'])) {
    die(json_encode(['success' => false, 'message' => 'Keine Startnummer übergeben.']));
}

$startnummer = intval($_GET['startnummer']);
$aktuellesTurnier = getAktuellesTurnier();

if (!$aktuellesTurnier) {
    die(json_encode(['success' => false, 'message' => 'Kein aktives Turnier gefunden.']));
}

// Registrierung aus Datenbank holen (mit JOIN zu anmeldungen für Name, Email, Mobilnummer)
$db = getDB();
$stmt = $db->prepare("
    SELECT 
        tr.id,
        tr.turnier_id,
        tr.anmeldung_id,
        tr.startnummer,
        tr.pin,
        a.name,
        a.email,
        a.mobilnummer
    FROM turnier_registrierungen tr
    LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
    WHERE tr.turnier_id = ? AND tr.startnummer = ?
");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registrierung) {
    die(json_encode(['success' => false, 'message' => 'Registrierung nicht gefunden.']));
}

// Laufzettel-Daten zusammenstellen
$laufzettelDaten = [
    'startnummer' => $registrierung['startnummer'],
    'name' => $registrierung['name'] ?? '',
    'email' => $registrierung['email'] ?? '',
    'mobilnummer' => $registrierung['mobilnummer'] ?? '',
    'registrier_nummer' => $registrierung['anmeldung_id'],
    'pin' => $registrierung['pin'] ?? '',
    'turnier' => $aktuellesTurnier
];

// HTML-Laufzettel erstellen
$anzahlRunden = intval($laufzettelDaten['turnier']['anzahl_runden'] ?? 3);
$logoPath = '../img/AKS-Logo_210.png';

// HTML-Laufzettel zusammenstellen
$laufzettelHTML = '<div class="laufzettel-html">';
$laufzettelHTML .= '<div class="laufzettel-logo">';
$laufzettelHTML .= '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="AKS Logo" class="logo-img">';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-header">';
$laufzettelHTML .= '<h1 class="laufzettel-title">' . htmlspecialchars($laufzettelDaten['turnier']['titel'], ENT_QUOTES, 'UTF-8') . '</h1>';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-info">';
$laufzettelHTML .= '<div class="info-row"><span class="info-label">Datum:</span> <span class="info-value">' . htmlspecialchars($laufzettelDaten['turnier']['datum'], ENT_QUOTES, 'UTF-8') . '</span></div>';
$laufzettelHTML .= '</div>';
$laufzettelHTML .= '<div class="laufzettel-spieler">';
$laufzettelHTML .= '<div class="info-row"><span class="info-label">Spieler Nr:</span> <span class="info-value info-value-large">' . htmlspecialchars($laufzettelDaten['startnummer'], ENT_QUOTES, 'UTF-8') . '</span></div>';
$laufzettelHTML .= '<div class="info-row"><span class="info-label info-label-small">Name:</span> <span class="info-value info-value-small">' . htmlspecialchars($laufzettelDaten['name'], ENT_QUOTES, 'UTF-8') . '</span></div>';
if (!empty($laufzettelDaten['email'])) {
    $laufzettelHTML .= '<div class="info-row"><span class="info-label info-label-small">Email:</span> <span class="info-value info-value-small">' . htmlspecialchars($laufzettelDaten['email'], ENT_QUOTES, 'UTF-8') . '</span></div>';
}
$laufzettelHTML .= '</div>';

// Gesamt Summe zuerst
$laufzettelHTML .= '<div class="laufzettel-summe-spacer"></div>';
$laufzettelHTML .= '<div class="laufzettel-summe">';
$laufzettelHTML .= '<table class="runde-table summe-table">';
$laufzettelHTML .= '<tr><td class="runde-label summe-label">Gesamt Summe:</td><td class="runde-field summe-field"></td></tr>';
$laufzettelHTML .= '</table>';
$laufzettelHTML .= '</div>';

// Runden generieren (absteigend: Runde 3, 2, 1)
for ($runde = $anzahlRunden; $runde >= 1; $runde--) {
    $laufzettelHTML .= '<div class="laufzettel-runde">';
    $laufzettelHTML .= '<h2 class="runde-title">Runde ' . $runde . ':</h2>';
    $laufzettelHTML .= '<table class="runde-table">';
    $laufzettelHTML .= '<tr><td class="runde-label">Summe Runde ' . $runde . ':</td><td class="runde-field"></td></tr>';
    $laufzettelHTML .= '</table>';
    $laufzettelHTML .= '</div>';
}

// PIN am Ende
$laufzettelHTML .= '<div class="laufzettel-pin">';
$laufzettelHTML .= '<table class="runde-table pin-table">';
$laufzettelHTML .= '<tr><td class="runde-label pin-label">PIN:</td><td class="runde-field pin-field">' . htmlspecialchars($laufzettelDaten['pin'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
$laufzettelHTML .= '</table>';
$laufzettelHTML .= '</div>';

// Leerzeilen am Ende für Papierschnitt
$laufzettelHTML .= '<div class="laufzettel-spacer"></div>';
$laufzettelHTML .= '</div>';

// JSON zurückgeben
echo json_encode([
    'success' => true,
    'laufzettel' => $laufzettelHTML,
    'startnummer' => $laufzettelDaten['startnummer']
]);
?>

