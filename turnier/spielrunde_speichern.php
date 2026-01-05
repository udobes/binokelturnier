<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Kein aktives Turnier gefunden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['startnummer']) && isset($_POST['runde']) && isset($_POST['punkte'])) {
    $startnummer = intval($_POST['startnummer']);
    $runde = intval($_POST['runde']);
    $punkte = isset($_POST['punkte']) && $_POST['punkte'] !== '' ? intval($_POST['punkte']) : null;
    
    $anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
    if ($runde < 1 || $runde > $anzahlRunden) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Rundennummer']);
        exit;
    }
    
    $db = getDB();
    
    // Registrierung finden
    $stmt = $db->prepare("SELECT * FROM turnier_registrierungen WHERE turnier_id = ? AND startnummer = ?");
    $stmt->execute([$aktuellesTurnier['id'], $startnummer]);
    $registrierung = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registrierung) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Teilnehmer nicht gefunden']);
        exit;
    }
    
    // Punktzahl für die Runde speichern (in neue Tabelle)
    try {
        speichereErgebnis($aktuellesTurnier['id'], $runde, $startnummer, $punkte);
        
        // Gesamtpunkte aus turnier_registrierungen holen (wurde bereits in speichereErgebnis berechnet)
        $stmt = $db->prepare("SELECT gesamtpunkte FROM turnier_registrierungen WHERE turnier_id = ? AND startnummer = ?");
        $stmt->execute([$aktuellesTurnier['id'], $startnummer]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $gesamtpunkte = $result['gesamtpunkte'] ?? 0;
        
        echo json_encode(['success' => true, 'gesamtpunkte' => $gesamtpunkte]);
    } catch (Exception $e) {
        error_log("Fehler beim Speichern: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
}
?>

