<?php
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();
if (!$aktuellesTurnier) {
    header('Location: turnier_starten.php?error=kein_turnier');
    exit;
}

// Alle Runden deaktivieren
deaktiviereAlleRunden($aktuellesTurnier['id']);

// Zurück zur Turnier-Startseite
header('Location: turnier_starten.php?success=deaktiviert');
exit;
?>

