<?php
require_once 'config.php';
initTurnierDB();

// Prüfen ob POST-Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: turnier_erfassen.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM turnier WHERE id = ?");
        $stmt->execute([$id]);
        
        header('Location: turnier_erfassen.php?success=1');
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Löschen des Turniers: " . $e->getMessage());
        header('Location: turnier_erfassen.php?error=database');
        exit;
    }
} else {
    header('Location: turnier_erfassen.php?error=invalid');
    exit;
}