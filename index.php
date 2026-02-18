<?php
// URL-Parameter aus der ursprünglichen Anfrage übernehmen
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$redirectUrl = 'anmeldung/index.php';

// Falls bereits Parameter vorhanden sind, diese übernehmen
if (!empty($queryString)) {
    $redirectUrl .= '?' . $queryString;
} else {
    // Falls keine Parameter vorhanden, aktives Turnier aus DB holen
    require_once __DIR__ . '/turnier/config.php';
    initTurnierDB();
    $aktuellesTurnier = getAktuellesTurnier();
    if ($aktuellesTurnier && isset($aktuellesTurnier['id'])) {
        $redirectUrl .= '?turnier=' . $aktuellesTurnier['id'];
    }
    // Falls kein aktives Turnier gefunden, ohne Parameter weiterleiten
    // (anmeldung/index.php wird dann selbst das aktive Turnier suchen)
}

header('Location: ' . $redirectUrl);
exit;   
?>
