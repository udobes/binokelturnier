<?php
require_once 'config.php';
initTurnierDB();

// Prüfen ob POST-Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: turnier_erfassen.php');
    exit;
}

// Daten aus dem Formular holen
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$datum = trim($_POST['datum'] ?? '');
$titel = trim($_POST['titel'] ?? '');
$veranstalter = trim($_POST['veranstalter'] ?? '');
$ort = trim($_POST['ort'] ?? '');
$einlasszeit = trim($_POST['einlasszeit'] ?? '');
$startzeit = trim($_POST['startzeit'] ?? '');
$googlemaps_link = trim($_POST['googlemaps_link'] ?? '');
$anzahl_spieler = isset($_POST['anzahl_spieler']) ? intval($_POST['anzahl_spieler']) : 0;
$anzahl_runden = isset($_POST['anzahl_runden']) ? intval($_POST['anzahl_runden']) : 3;
$spieler_pro_runde = isset($_POST['spieler_pro_runde']) ? intval($_POST['spieler_pro_runde']) : 3;

// Validierung
if (empty($datum) || empty($titel) || empty($veranstalter) || empty($ort) || $anzahl_spieler < 1 || $anzahl_runden < 1 || $spieler_pro_runde < 2) {
    if ($id > 0) {
        header('Location: turnier_erfassen.php?edit=' . $id . '&error=empty');
    } else {
        header('Location: turnier_erfassen.php?new=1&error=empty');
    }
    exit;
}

// Prüfung: Anzahl Spieler muss durch Spieler pro Runde teilbar sein
if ($anzahl_spieler % $spieler_pro_runde != 0) {
    if ($id > 0) {
        header('Location: turnier_erfassen.php?edit=' . $id . '&error=not_divisible&anzahl=' . $anzahl_spieler . '&pro_runde=' . $spieler_pro_runde);
    } else {
        header('Location: turnier_erfassen.php?new=1&error=not_divisible&anzahl=' . $anzahl_spieler . '&pro_runde=' . $spieler_pro_runde);
    }
    exit;
}

try {
    $db = getDB();
    
    if ($id > 0) {
        // Bestehendes Turnier laden, um alte Spieleranzahl zu vergleichen
        $stmt = $db->prepare("SELECT anzahl_spieler FROM turnier WHERE id = ?");
        $stmt->execute([$id]);
        $altesTurnier = $stmt->fetch(PDO::FETCH_ASSOC);
        $alteAnzahlSpieler = $altesTurnier ? intval($altesTurnier['anzahl_spieler']) : null;

        // Turnier aktualisieren
        $stmt = $db->prepare("UPDATE turnier SET datum = ?, titel = ?, veranstalter = ?, ort = ?, einlasszeit = ?, startzeit = ?, googlemaps_link = ?, anzahl_spieler = ?, anzahl_runden = ?, spieler_pro_runde = ? WHERE id = ?");
        $stmt->execute([$datum, $titel, $veranstalter, $ort, $einlasszeit ?: null, $startzeit ?: null, $googlemaps_link ?: null, $anzahl_spieler, $anzahl_runden, $spieler_pro_runde, $id]);
        
        // Wenn sich die Anzahl der Spieler geändert hat, Tischzuordnungen für dieses Turnier löschen
        if ($alteAnzahlSpieler !== null && $alteAnzahlSpieler !== $anzahl_spieler) {
            $stmt = $db->prepare("DELETE FROM turnier_zuordnungen WHERE turnier_id = ?");
            $stmt->execute([$id]);
        }
        
        header('Location: turnier_erfassen.php?success=1');
    } else {
        // Alle bisherigen Turniere als inaktiv markieren
        $db->exec("UPDATE turnier SET aktiv = 0");
        
        // Neues Turnier speichern
        $stmt = $db->prepare("INSERT INTO turnier (datum, titel, veranstalter, ort, einlasszeit, startzeit, googlemaps_link, anzahl_spieler, anzahl_runden, spieler_pro_runde, aktiv) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$datum, $titel, $veranstalter, $ort, $einlasszeit ?: null, $startzeit ?: null, $googlemaps_link ?: null, $anzahl_spieler, $anzahl_runden, $spieler_pro_runde]);
        
        header('Location: turnier_erfassen.php?success=1');
    }
    exit;
    
} catch (PDOException $e) {
    error_log("Fehler beim Speichern des Turniers: " . $e->getMessage());
    if ($id > 0) {
        header('Location: turnier_erfassen.php?edit=' . $id . '&error=database');
    } else {
        header('Location: turnier_erfassen.php?new=1&error=database');
    }
    exit;
}