<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    http_response_code(400);
    echo json_encode(['error' => 'Kein aktives Turnier gefunden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['runde'])) {
    $runde = intval($_POST['runde']);
    
    // Prüfen, ob die Runde gültig ist
    $anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
    if ($runde < 1 || $runde > $anzahlRunden) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Rundennummer']);
        exit;
    }
    
    // Aktive Runde setzen
    setzeAktiveRunde($aktuellesTurnier['id'], $runde);
    
    echo json_encode(['success' => true, 'runde' => $runde]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Anfrage']);
}
?>

