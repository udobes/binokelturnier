<?php
require_once 'config.php';
initTurnierDB();

header('Content-Type: application/json');

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    echo json_encode(['success' => false, 'error' => 'Kein aktives Turnier gefunden']);
    exit;
}

// Alle Runden deaktivieren
deaktiviereAlleRunden($aktuellesTurnier['id']);

echo json_encode(['success' => true, 'message' => 'Alle Runden deaktiviert']);
exit;
?>

