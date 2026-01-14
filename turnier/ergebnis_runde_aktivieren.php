<?php
require_once 'config.php';
initTurnierDB();

header('Content-Type: application/json');

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    echo json_encode(['success' => false, 'message' => 'Kein aktives Turnier']);
    exit;
}

$turnierId = $aktuellesTurnier['id'];
$runde = isset($_POST['runde']) ? $_POST['runde'] : null;

if ($runde === null || $runde === '' || $runde === 'null') {
    // Deaktivieren
    deaktiviereAktiveErgebnisRunde($turnierId);
    echo json_encode(['success' => true, 'message' => 'Ergebnisrunde deaktiviert']);
} else {
    $runde = intval($runde);
    // 0 = Ergebnisse, 1-10 = Runden
    if ($runde >= 0 && $runde <= 10) {
        setzeAktiveErgebnisRunde($turnierId, $runde);
        echo json_encode(['success' => true, 'message' => 'Ergebnisrunde aktiviert']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ungültige Runde']);
    }
}

