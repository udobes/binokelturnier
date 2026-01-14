<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    echo json_encode(['success' => false, 'error' => 'Kein aktives Turnier gefunden']);
    exit;
}

$startnummer = isset($_GET['startnummer']) ? intval($_GET['startnummer']) : 0;
$runde = isset($_GET['runde']) ? intval($_GET['runde']) : 1;

if ($startnummer < 1 || $runde < 1) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
    exit;
}

$db = getDB();

// Name aus turnier_registrierungen holen (mit JOIN zu anmeldungen)
$stmt = $db->prepare("
    SELECT a.name 
    FROM turnier_registrierungen tr
    LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
    WHERE tr.turnier_id = ? AND tr.startnummer = ?
");
$stmt->execute([$aktuellesTurnier['id'], $startnummer]);
$registrierung = $stmt->fetch(PDO::FETCH_ASSOC);

if ($registrierung) {
    // Ergebnis aus turnier_ergebnisse holen
    $ergebnis = getErgebnis($aktuellesTurnier['id'], $runde, $startnummer);
    
    echo json_encode([
        'success' => true,
        'name' => $registrierung['name'],
        'punkte' => $ergebnis && $ergebnis['punkte'] !== null ? intval($ergebnis['punkte']) : null
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Teilnehmer nicht gefunden']);
}
?>

