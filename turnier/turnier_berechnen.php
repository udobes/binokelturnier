<?php
// turnier/config.php lädt automatisch anmeldung/config.php falls nötig
require_once 'config.php';
initTurnierDB();

$aktuellesTurnier = getAktuellesTurnier();

if (!$aktuellesTurnier) {
    header('Location: turnier_starten.php?error=no_turnier');
    exit;
}

// Parameter aus dem Turnier holen
$anzahlSpieler = intval($aktuellesTurnier['anzahl_spieler']);
$anzahlRunden = intval($aktuellesTurnier['anzahl_runden'] ?? 3);
$spielerProGruppe = intval($aktuellesTurnier['spieler_pro_runde'] ?? 3);

// Validierung
if ($anzahlSpieler < 1) {
    header('Location: turnier_starten.php?error=no_players');
    exit;
}

// Validierung: Anzahl muss durch Spieler pro Gruppe teilbar sein
if ($anzahlSpieler % $spielerProGruppe != 0) {
    header('Location: turnier_starten.php?error=invalid_group_size');
    exit;
}

// Spieler-IDs als einfache Nummern generieren (1, 2, 3, ..., anzahlSpieler)
$spielerIds = range(1, $anzahlSpieler);

// Funktion zum Mischen der Spieler
function mischeSpieler($spieler) {
    shuffle($spieler);
    return $spieler;
}

// Funktion zum Einteilen der Spieler in Gruppen
function teileSpielerEin($spieler, $anzahlGruppen) {
    $gruppen = array_chunk($spieler, count($spieler) / $anzahlGruppen);
    return $gruppen;
}

// Funktion zum Erstellen des Turnierplans
function erstelleTurnierplan($spielerIds, $anzahlRunden, $spielerProGruppe) {
    $spieler = $spielerIds;
    $turnier = [];

    for ($runde = 1; $runde <= $anzahlRunden; $runde++) {
        $spieler = mischeSpieler($spieler);
        $anzahlGruppen = count($spieler) / $spielerProGruppe;
        $gruppen = teileSpielerEin($spieler, $anzahlGruppen);
        $turnier[$runde] = $gruppen;
    }

    return $turnier;
}

// Funktion zum Finden der Spielpartner
function findeSpielpartner($turnier) {
    $spielpartner = [];

    foreach ($turnier as $runde => $gruppen) {
        foreach ($gruppen as $gruppe) {
            foreach ($gruppe as $spieler1) {
                foreach ($gruppe as $spieler2) {
                    if ($spieler1 != $spieler2) {
                        if (!isset($spielpartner[$spieler1])) {
                            $spielpartner[$spieler1] = [];
                        }
                        $spielpartner[$spieler1][] = $spieler2;
                    }
                }
            }
        }
    }

    // Entferne doppelte Einträge und sortiere die Spielpartner
    foreach ($spielpartner as &$partner) {
        $partner = array_unique($partner);
        sort($partner);
    }

    return $spielpartner;
}

// Optimale Verteilung berechnen
$n = 0;
$bestTurnier = null;
$bestSpielpartner = null;
$bestMinCount = 0;
$bestDcount = 0;

do {
    $min_count = 9999;
    
    // Turnierplan erstellen
    $turnier = erstelleTurnierplan($spielerIds, $anzahlRunden, $spielerProGruppe);
    
    // Spielpartner finden
    $spielpartner = findeSpielpartner($turnier);
    
    $n += 1;
    ksort($spielpartner);
    $scount = 0;
    foreach ($spielpartner as $spieler => $partner) {
        $count = count($partner);
        $scount += $count;
        if ($count < $min_count) $min_count = $count;
    }
    $dcount = $scount / $anzahlSpieler;
    
    // Bessere Lösung speichern
    if ($min_count > $bestMinCount || ($min_count == $bestMinCount && $dcount > $bestDcount)) {
        $bestTurnier = $turnier;
        $bestSpielpartner = $spielpartner;
        $bestMinCount = $min_count;
        $bestDcount = $dcount;
    }
    
} while (($min_count < ($anzahlRunden * 2) || $dcount < 6) && $n < 10000);

// Beste Lösung verwenden
$turnier = $bestTurnier;
$spielpartner = $bestSpielpartner;

// Alte Zuordnungen für dieses Turnier löschen
$db = getDB();
$stmt = $db->prepare("DELETE FROM turnier_zuordnungen WHERE turnier_id = ?");
$stmt->execute([$aktuellesTurnier['id']]);

// Tischzuordnungen in Datenbank speichern
$stmt = $db->prepare("INSERT INTO turnier_zuordnungen (turnier_id, runde, tisch, spieler_id) VALUES (?, ?, ?, ?)");
foreach ($turnier as $runde => $gruppen) {
    $tisch = 1;
    foreach ($gruppen as $gruppe) {
        foreach ($gruppe as $spielerId) {
            $stmt->execute([$aktuellesTurnier['id'], $runde, $tisch, $spielerId]);
        }
        $tisch++;
    }
}

// Weiterleitung mit Erfolgsmeldung
header('Location: turnier_starten.php?success=1');
exit;

