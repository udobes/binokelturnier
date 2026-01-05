<?php
/**
 * Dieses Skript erstellt eine Einteilung für 12 Spieler in 3 Runden.
 * Jede Runde hat 4 Gruppen à 3 Spieler. Dabei werden nach Möglichkeit
 * wiederholte Paarungen vermieden.
 */

// -------------------------
// EINSTELLUNGEN
// -------------------------
$anzahlPersonen = 12;  // Gesamtzahl der Personen
$anzahlRunden   = 3;   // Wie viele Runden gespielt werden
$maxVersucheProRunde = 5000; // Maximale Anzahl Zufallsversuche pro Runde


// -------------------------
// HILFSFUNKTIONEN
// -------------------------

/**
 * Erzeugt alle 2er-Paaren eines Dreier-Arrays (z. B. [1,2,3] => (1,2), (1,3), (2,3)).
 *
 * @param array $gruppe  Array mit genau 3 Spielern.
 * @return array Array von Paaren (jeweils ein 2-elementiges Array), 
 *               wobei immer die kleinere Spielernummer vorne steht (Sortierung).
 */
function erstellePaarungenAusGruppe(array $gruppe): array
{
    sort($gruppe);
    return [
        [$gruppe[0], $gruppe[1]],
        [$gruppe[0], $gruppe[2]],
        [$gruppe[1], $gruppe[2]],
    ];
}

/**
 * Prüft, ob eine Dreiergruppe Paarungen enthält, die bereits in $benutztePaarungen vorkommen.
 *
 * @param array $gruppe           Array mit genau 3 Spielern (z.B. [1,2,3]).
 * @param array $benutztePaarungen Array (assoziativ oder einfach) der schon verwendeten 2er-Paare,
 *                                z.B. im Format ['1-2'=>true, '1-3'=>true, ...]
 * @return bool true, wenn es Kollisionen (schon benutze Paarungen) gibt, sonst false.
 */
function hatKonfliktMitBenutztenPaaren(array $gruppe, array $benutztePaarungen): bool
{
    $paaren = erstellePaarungenAusGruppe($gruppe);
    foreach ($paaren as $paar) {
        [$a, $b] = $paar; 
        $key = "{$a}-{$b}";
        if (isset($benutztePaarungen[$key])) {
            // Diese Paarung wurde schon verwendet
            return true;
        }
    }
    return false;
}

/**
 * Fügt alle Paarungen einer Dreiergruppe in das Array $benutztePaarungen ein.
 *
 * @param array $gruppe            Array mit genau 3 Spielern.
 * @param array &$benutztePaarungen Referenz auf das Array der benutzten Paarungen.
 */
function fuegePaarungenHinzu(array $gruppe, array &$benutztePaarungen): void
{
    $paaren = erstellePaarungenAusGruppe($gruppe);
    foreach ($paaren as $paar) {
        sort($paar);
        $key = "{$paar[0]}-{$paar[1]}";
        $benutztePaarungen[$key] = true;
    }
}

/**
 * Erzeugt eine zufällige (!) Aufteilung der Spieler in 4 Gruppen á 3 Spieler,
 * die keine Konflikte mit den bereits benutzten Paarungen aufweist.
 *
 * @param int   $anzahlPersonen
 * @param array $benutztePaarungen
 * @param int   $maxVersuche
 * @return array|false  Array der Form [ [p1,p2,p3], [p4,p5,p6], ... ] oder false, wenn erfolglos.
 */
function erzeugeRundenGruppierungOhneKonflikte(int $anzahlPersonen, array $benutztePaarungen, int $maxVersuche = 1000)
{
    $spieler = range(1, $anzahlPersonen);

    for ($versuch = 1; $versuch <= $maxVersuche; $versuch++) {
        shuffle($spieler);
        
        // In 4 Gruppen à 3 Spieler aufteilen:
        $gruppen = array_chunk($spieler, 3);

        // Prüfen, ob wir wirklich 4 Gruppen (zu 3 Personen) bekommen haben
        if (count($gruppen) != 4) {
            // Das sollte bei 12 Spielern eigentlich nie passieren,
            // es sei denn, $anzahlPersonen ist nicht durch 3 teilbar
            continue;
        }
        
        // Prüfen, ob keine der 4 Gruppen mit benutzten Paarungen kollidiert
        $konfliktGefunden = false;
        foreach ($gruppen as $gruppe) {
            if (hatKonfliktMitBenutztenPaaren($gruppe, $benutztePaarungen)) {
                $konfliktGefunden = true;
                break;
            }
        }
        
        if (!$konfliktGefunden) {
            // Wir haben eine konfliktfreie Gruppierung gefunden
            return $gruppen;
        }
    }
    
    // Wenn wir hier ankommen, haben wir nach $maxVersuche Durchläufen keine konfliktfreie
    // Gruppierung finden können.
    return false;
}

/**
 * Baut aus dem Turnier-Array ein Partner-Array auf, in dem zu jeder Person
 * aufgelistet wird, mit wem sie (in allen Runden) zusammenspielte.
 *
 * @param array $turnier Array der Form: 
 *                       $turnier[rundeIndex][gruppenIndex] = [spieler1, spieler2, spieler3]
 * @return array $partnerArray, z.B.:
 *         $partner[1] = [2,3,5,7,...]
 */
function bauePartnerArray(array $turnier): array
{
    $partner = [];
    
    // Alle Runden durchgehen
    foreach ($turnier as $runde => $gruppenDerRunde) {
        foreach ($gruppenDerRunde as $gruppe) {
            // Für jede Gruppe die Partner befüllen
            for ($i = 0; $i < count($gruppe); $i++) {
                $spieler_i = $gruppe[$i];
                // Falls noch nicht existent, initialisiere leeres Array
                if (!isset($partner[$spieler_i])) {
                    $partner[$spieler_i] = [];
                }
                // Die zwei anderen Spieler in der Gruppe hinzufügen
                for ($j = 0; $j < count($gruppe); $j++) {
                    if ($i == $j) continue;
                    $spieler_j = $gruppe[$j];
                    // Duplikate vermeiden
                    if (!in_array($spieler_j, $partner[$spieler_i])) {
                        $partner[$spieler_i][] = $spieler_j;
                    }
                }
            }
        }
    }
    
    // Optional sortieren, damit es "ordentlicher" aussieht
    foreach ($partner as $spieler => $partnerListe) {
        sort($partnerListe);
        $partner[$spieler] = $partnerListe;
    }
    
    return $partner;
}

/**
 * Gibt das Turnier in HTML-Form aus.
 *
 * @param array $turnier
 */
function zeigeTurnierInHTML(array $turnier): void
{
    echo "<h2>Turnier-Einteilung</h2>\n";
    foreach ($turnier as $rundenIndex => $gruppenDerRunde) {
        echo "<h3>Runde ".($rundenIndex)." </h3>\n";
        echo "<ul>\n";
        foreach ($gruppenDerRunde as $gruppenIndex => $gruppe) {
            echo "<li>Gruppe ".($gruppenIndex+1).": Spieler ";
            echo implode(", ", $gruppe);
            echo "</li>\n";
        }
        echo "</ul>\n";
    }
}

/**
 * Gibt das Partner-Array in HTML-Form aus.
 *
 * @param array $partner
 */
function zeigePartnerInHTML(array $partner): void
{
    echo "<h2>Partner-Übersicht</h2>\n";
    echo "<ul>\n";
    ksort($partner); // nach Spielernummer sortieren
    foreach ($partner as $spieler => $partnerListe) {
        echo "<li>Spieler $spieler spielte mit: ".implode(", ", $partnerListe)."</li>\n";
    }
    echo "</ul>\n";
}

/**
 * Prüft, ob es doppelte Paarungen gibt und gibt diese in HTML aus.
 *
 * @param array $turnier
 */
function checkDoppeltePaarungen(array $turnier): void
{
    // Wir durchforsten alle Runden, sammeln alle Paarungen in einem Array und prüfen auf Mehrfach-Vorkommen
    $allePaarungen = [];  // key => Anzahl Vorkommen
    
    foreach ($turnier as $rundeIndex => $gruppenDerRunde) {
        foreach ($gruppenDerRunde as $gruppe) {
            $paaren = erstellePaarungenAusGruppe($gruppe);
            foreach ($paaren as $paar) {
                sort($paar);
                $key = "{$paar[0]}-{$paar[1]}";
                if (!isset($allePaarungen[$key])) {
                    $allePaarungen[$key] = 0;
                }
                $allePaarungen[$key]++;
            }
        }
    }
    
    // Jetzt schauen wir, welche Paarungen mehr als 1-mal vorkommen
    $doppeltePaarungen = [];
    foreach ($allePaarungen as $key => $anzahl) {
        if ($anzahl > 1) {
            $doppeltePaarungen[$key] = $anzahl;
        }
    }
    
    echo "<h2>Prüfung auf doppelte Paarungen</h2>\n";
    if (empty($doppeltePaarungen)) {
        echo "<p style='color: green;'>Es wurden keine mehrfach vorkommenden Paarungen gefunden. Prima!</p>\n";
    } else {
        echo "<p style='color: red;'>Folgende Paarungen kamen mehrfach vor:</p>\n";
        echo "<ul>\n";
        foreach ($doppeltePaarungen as $pairKey => $count) {
            echo "<li>$pairKey wurde $count-mal verwendet</li>\n";
        }
        echo "</ul>\n";
    }
}

// -------------------------
// HAUPTLOGIK
// -------------------------

// Hier speichern wir die Aufteilung aller Runden:
//   $turnier[1] = [ [p1,p2,p3], [p4,p5,p6], [p7,p8,p9], [p10,p11,p12] ]
//   $turnier[2] = [...]
//   ...
$turnier = [];

// Hier sammeln wir alle bereits verwendeten Paarungen, um Wiederholungen möglichst zu vermeiden.
// Schlüssel sind Strings wie '1-5' (kleinere Nummer zuerst).
$benutztePaarungen = [];

// Wir erstellen nun für jede Runde eine (weitgehend) konfliktfreie Gruppierung
for ($runde = 1; $runde <= $anzahlRunden; $runde++) {
    $gruppierung = erzeugeRundenGruppierungOhneKonflikte(
        $anzahlPersonen, 
        $benutztePaarungen, 
        $maxVersucheProRunde
    );
    
    if ($gruppierung === false) {
        // Falls keine konfliktfreie Gruppierung gefunden wurde
        echo "<p style='color: red;'>Für Runde $runde konnte keine passende Gruppierung gefunden werden!</p>\n";
        // Ggf. könnte man hier abbrechen oder doch eine nehmen, die minimal Konflikte hat
        // Wir brechen jetzt mal ab.
        break;
    } else {
        // Gruppierung übernehmen
        $turnier[$runde] = $gruppierung;
        
        // Paarungen vermerken
        foreach ($gruppierung as $gruppe) {
            fuegePaarungenHinzu($gruppe, $benutztePaarungen);
        }
    }
}

// Partner-Array aufbauen
$partner = bauePartnerArray($turnier);

// -------------------------
// AUSGABE
// -------------------------
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kartenspiel-Turnier</title>
</head>
<body>
<?php
// Turnier in HTML ausgeben
zeigeTurnierInHTML($turnier);

// Partner-Übersicht ausgeben
zeigePartnerInHTML($partner);

// Doppelte Paarungen überprüfen
checkDoppeltePaarungen($turnier);
?>
</body>
</html>