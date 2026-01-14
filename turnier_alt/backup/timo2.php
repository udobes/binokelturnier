<?php


// -------------------------
// EINSTELLUNGEN
// -------------------------
$anzahlPersonen = 12;    // z.B. 12 Spieler
$anzahlRunden   = 3;     // Anzahl Runden
$gruppengroesse  = 3;    // pro Gruppe 3 Spieler
$gruppenProRunde = $anzahlPersonen/$gruppengroesse;    // 4 Gruppen


// -------------------------
// SCHRITT 1: ALLE PARTITIONEN (OHNE REIHENFOLGE) ERZEUGEN
// -------------------------------------------------------
/**
 * Erzeugt alle möglichen Zerlegungen einer Spielermenge in 4 disjunkte Gruppen zu je 3 Spielern.
 *
 * @param array $spieler  Liste aller Spieler (z.B. [1,2,3,...,12])
 * @return array  Ein Array aller Partitionen, wobei jede Partition so aussieht:
 *                [
 *                  [a1, a2, a3],   // Gruppe 1
 *                  [b1, b2, b3],   // Gruppe 2
 *                  [c1, c2, c3],   // Gruppe 3
 *                  [d1, d2, d3],   // Gruppe 4
 *                ]
 *                Alle Spieler sind exakt verteilt, Reihenfolge der Gruppen spielt keine Rolle,
 *                ebenso die Reihenfolge in einer Gruppe nicht. Wir geben aber trotzdem eine
 *                feste Reihenfolge zurück (lexikographisch), um deterministisch vorzugehen.
 */
function erstelleAllePartitionen4x3(array $spieler): array {	

	GLOBAL $gruppenProRunde;
	
    // Wir erzeugen zunächst alle Kombinationen aus 3 Spielern für Gruppe1, 
    // dann rekursiv Gruppe2 aus den übrig gebliebenen usw.
    // Das ist klassisches "partition backtracking".
    
    $allePartitionen = [];
    
    // Kleine Hilfsfunktion, um rekursiv Gruppen zu bilden
    function backtrackGruppen(array $restSpieler, int $nochZuBildendeGruppen, array $aktuell, array &$ergebnis) {
        if ($nochZuBildendeGruppen === 0) {
            // Keine Gruppen mehr zu bilden => $aktuell enthält 4 Gruppen
            // Alle Spieler sollten dann verteilt sein
            // (theoretisch müsste restSpieler hier leer sein, 
            //  wir verlassen uns aber auf 12 = 4*3).
            $ergebnis[] = $aktuell;
            return;
        }
        // Gruppengröße ist 3
        $gruppengroesse = 3;
        
        // Alle Kombinationen von 3 Spielern aus $restSpieler erzeugen
        // und für jede Kombi rekursiv weitermachen
        $kombis = kombination3($restSpieler);
        foreach ($kombis as $k) {
            // $k ist z.B. [1,2,4]
            // Nimm die verbleibenden Spieler, die nicht in $k sind
            $restNachDieserGruppe = array_diff($restSpieler, $k);
            // Neue Gruppe an $aktuell anhängen
            $aktNeu = $aktuell;
            $aktNeu[] = array_values($k); // array_values, um Indizes "schön" zu haben
            
            // Eine Gruppe weniger zu bilden
            backtrackGruppen(array_values($restNachDieserGruppe), $nochZuBildendeGruppen-1, $aktNeu, $ergebnis);
        }
    }
    
    // Da wir 4 Gruppen (zu je 3 Spielern) bilden wollen:
    backtrackGruppen($spieler, $gruppenProRunde, [], $allePartitionen);

    // Nun haben wir eine Menge an Partitionen, aber darin sind ggf. Duplikate,
    // weil die Reihenfolge der 4 Gruppen egal ist. In unserem rekursiven Vorgehen
    // werden wir jede Sortierung einmal erwischen.  
    // Darum normalisieren wir jede Partition:
    //   1) Sortiere jede Gruppe aufsteigend (innerhalb der Gruppe).
    //   2) Sortiere die 4 Gruppen lexikographisch.
    //   3) Dann nutzen wir (string) als "Signatur" und eliminieren Dubletten.
    
    $uniqueMap = [];
    
    foreach ($allePartitionen as $part) {
        // Schritt 1: Jede Gruppe sortieren
        foreach ($part as &$gruppe) {
            sort($gruppe);
        }
        unset($gruppe);
        // Schritt 2: 4 Gruppen sortieren
        usort($part, function($a, $b) {
            return $a <=> $b; // lexikographisch
        });
        // Schöne Signatur
        $sig = json_encode($part);
        $uniqueMap[$sig] = $part;
    }
    
    // Nur eindeutige Partitionen
    $eindeutigePartitionen = array_values($uniqueMap);
    
    // Abschließend sortieren wir das Gesamtarray nochmal lexikographisch,
    // damit die Lösung wirklich deterministisch ist.
    usort($eindeutigePartitionen, function($p1, $p2) {
        return json_encode($p1) <=> json_encode($p2);
    });
    
    return $eindeutigePartitionen;
}

/**
 * Erzeugt alle Kombinationen (ohne Reihenfolge) von 3 Elementen aus $menge.
 * Liefert Array von Arrays, z.B. kombination3([1,2,3,4]) => [[1,2,3],[1,2,4],[1,3,4],[2,3,4]]
 *
 * @param array $menge
 * @return array
 */
function kombination3(array $menge): array
{
    $result = [];
    $n = count($menge);
    if ($n < 3) return $result;
    
    // Zur Vereinfachung wandeln wir $menge in numerisches Array um
    $menge = array_values($menge);
    
    for ($i=0; $i<$n-2; $i++) {
        for ($j=$i+1; $j<$n-1; $j++) {
            for ($k=$j+1; $k<$n; $k++) {
                $result[] = [$menge[$i], $menge[$j], $menge[$k]];
            }
        }
    }
    return $result;
}

// -------------------------
// SCHRITT 2: BACKTRACKING ÜBER RUNDEN
// -----------------------------------
/**
 * Liefert ENTWEDER ein fertiges $turnier-Array (3 Runden, je 4 Gruppen) zurück
 * ODER false, falls keine Lösung gefunden wird.
 *
 * @param int   $runde       Aktuelle Runden-Nummer (1..$anzahlRunden)
 * @param int   $anzRunden   Zielanzahl Runden (z.B. 3)
 * @param array $allePartitions   Alle möglichen Partitionen (von 12 Spielern in 4x3)
 * @param array $bereitsVerwendetePaarungen  assoziatives Array 'kleinereID-größereID'=>true
 * @param array $turnierSoFar    Teil-Lösung: z.B. [ 1 => [...], 2=>[...], ... ]
 * @return array|false
 */
function rundenBacktracking(
    int $runde,
    int $anzRunden,
    array $allePartitions,
    array &$bereitsVerwendetePaarungen,
    array $turnierSoFar
) {
    // Abbruchbedingung: Haben wir alle Runden gefüllt?
    if ($runde > $anzRunden) {
        // Dann ist $turnierSoFar eine komplette Lösung
        return $turnierSoFar;
    }
    
    // Wir versuchen nun, eine der möglichen Partitionen für diese Runde zu nehmen
    // und prüfen, ob diese Partition konfliktfrei zu $bereitsVerwendetePaarungen ist.
    foreach ($allePartitions as $part) {
        if (partitionKonflikt($part, $bereitsVerwendetePaarungen)) {
            // Konflikt => ignorieren
            continue;
        }
        
        // KEIN Konflikt => wir verwenden diese Partition
        // => Paarungen aus dieser Partition in $bereitsVerwendetePaarungen eintragen
        $altePaarungen = []; // zur Wiederherstellung bei Backtracking
        fuegePartitionZuPaarungenHinzu($part, $bereitsVerwendetePaarungen, $altePaarungen);
        
        // Turnier so far updaten
        $turnierSoFar[$runde] = $part;
        
        // Rekursiver Aufruf: Nächste Runde
        $loesung = rundenBacktracking(
            $runde + 1,
            $anzRunden,
            $allePartitions,
            $bereitsVerwendetePaarungen,
            $turnierSoFar
        );
        if ($loesung !== false) {
            // Wir haben eine vollständige Lösung gefunden
            return $loesung;
        }
        
        // Backtracking: entfernen wir die Paarungen dieser Partition
        entfernePartitionAusPaarungen($part, $bereitsVerwendetePaarungen, $altePaarungen);
        // TurnierSoFar zurücksetzen:
        unset($turnierSoFar[$runde]);
    }
    
    // Keine Partition hat funktioniert
    return false;
}

/**
 * Prüft, ob eine Partition (4 Gruppen á 3 Spieler) Konflikte mit bereits verwendeten Paaren hat.
 *
 * @param array $partition [ [x1,x2,x3], [y1,y2,y3], [z1,z2,z3], [w1,w2,w3] ]
 * @param array $benutzePaarungen  key='a-b'
 * @return bool  true, wenn KONFLIKT (=mind. eine Paarung schon verwendet), sonst false
 */
function partitionKonflikt(array $partition, array $benutzePaarungen): bool
{
    foreach ($partition as $gruppe) {
        // Alle 2er-Paare in dieser Gruppe bilden
        $pairs = erstelleDreierPaare($gruppe);
        foreach ($pairs as $p) {
            sort($p);
            $key = $p[0].'-'.$p[1];
            if (isset($benutzePaarungen[$key])) {
                // Bereits benutzt => Konflikt
                return true;
            }
        }
    }
    return false;
}

/**
 * Aus einer Dreiergruppe (z.B. [1,2,3]) alle 2er-Paaren bilden => [[1,2],[1,3],[2,3]]
 *
 * @param array $gruppe
 * @return array
 */
function erstelleDreierPaare(array $gruppe): array
{
    // Einfachste Variante
    return [
        [$gruppe[0], $gruppe[1]],
        [$gruppe[0], $gruppe[2]],
        [$gruppe[1], $gruppe[2]],
    ];
}

/**
 * Schreibt alle Paare aus partition in $benutztePaarungen.
 * Hängt gleichzeitig die neu eingetragenen Paarungen an $altePaarungen an,
 * damit man sie im Backtracking-Fall wieder entfernen kann.
 *
 * @param array $partition
 * @param array &$benutztePaarungen
 * @param array &$altePaarungen
 */
function fuegePartitionZuPaarungenHinzu(array $partition, array &$benutztePaarungen, array &$altePaarungen)
{
    foreach ($partition as $gruppe) {
        $pairs = erstelleDreierPaare($gruppe);
        foreach ($pairs as $p) {
            sort($p);
            $key = $p[0].'-'.$p[1];
            $benutztePaarungen[$key] = true;
            // Merken, dass wir diesen Eintrag erzeugt haben
            $altePaarungen[] = $key;
        }
    }
}

/**
 * Entfernt alle in $altePaarungen gemerkten Paare wieder aus $benutztePaarungen.
 *
 * @param array $partition
 * @param array &$benutztePaarungen
 * @param array $altePaarungen
 */
function entfernePartitionAusPaarungen(array $partition, array &$benutztePaarungen, array $altePaarungen)
{
    // Einfach alle Keys wieder löschen
    foreach ($altePaarungen as $key) {
        unset($benutztePaarungen[$key]);
    }
}


// -------------------------
// SCHRITT 3: GEFUNDENES TURNIER AUSWERTEN
// ----------------------------------------
/**
 * Baut aus dem Turnier-Array ein Partner-Array: 
 *  $partner[Spieler] = [Liste aller Mitspieler über alle Runden]
 *
 * @param array $turnier assoziatives Array:
 *      [
 *         1 => [ [a1,a2,a3], [b1,b2,b3], [c1,c2,c3], [d1,d2,d3] ],
 *         2 => [...],
 *         3 => [...],
 *      ]
 * @return array
 */
function bauePartnerArray(array $turnier): array
{
    $partner = [];
    foreach ($turnier as $runde => $gruppenDerRunde) {
        foreach ($gruppenDerRunde as $gruppe) {
            // Für jede Gruppe die gegenseitigen Partner eintragen
            for ($i=0; $i<3; $i++) {
                $s1 = $gruppe[$i];
                if (!isset($partner[$s1])) {
                    $partner[$s1] = [];
                }
                for ($j=0; $j<3; $j++) {
                    if ($i==$j) continue;
                    $s2 = $gruppe[$j];
                    if (!in_array($s2, $partner[$s1])) {
                        $partner[$s1][] = $s2;
                    }
                }
            }
        }
    }
    // sortieren
    foreach ($partner as &$pList) {
        sort($pList);
    }
    unset($pList);
    
    ksort($partner);
    return $partner;
}

/**
 * Gibt das Turnier in HTML aus.
 *
 * @param array $turnier
 */
function zeigeTurnierInHTML(array $turnier): void
{
    echo "<h2>Turnier-Einteilung (deterministische Lösung)</h2>\n";
    for ($r=1; $r<=count($turnier); $r++) {
        echo "<h3>Runde $r</h3>\n";
        echo "<ul>\n";
        // $turnier[$r] enthält die 4 Gruppen
        $gruppen = $turnier[$r];
        foreach ($gruppen as $idx => $grp) {
            echo "<li>Gruppe ".($idx+1).": ".implode(", ", $grp)."</li>\n";
        }
        echo "</ul>\n";
    }
}

/**
 * Gibt das Partner-Array in HTML aus.
 *
 * @param array $partner
 */
function zeigePartnerInHTML(array $partner): void
{
    echo "<h2>Partner-Übersicht</h2>\n";
    echo "<ul>\n";
    foreach ($partner as $spieler => $liste) {
        echo "<li>Spieler $spieler spielte mit: ".implode(", ", $liste)." - Anz: ".count($liste)."</li>\n";
    }
    echo "</ul>\n";
}

/**
 * Findet und zeigt alle Paarungen, die mehr als einmal vorkommen.
 *
 * @param array $turnier
 */
function checkDoppeltePaarungen(array $turnier): void
{
    $countPaarungen = [];
    foreach ($turnier as $runde => $gruppenDerRunde) {
        foreach ($gruppenDerRunde as $g) {
            $pairs = erstelleDreierPaare($g);
            foreach ($pairs as $p) {
                sort($p);
                $key = $p[0].'-'.$p[1];
                if (!isset($countPaarungen[$key])) {
                    $countPaarungen[$key] = 0;
                }
                $countPaarungen[$key]++;
            }
        }
    }
    // Wer hat count > 1?
    $duplicates = [];
    foreach ($countPaarungen as $key => $cnt) {
        if ($cnt > 1) {
            $duplicates[$key] = $cnt;
        }
    }
    
    echo "<h2>Prüfung auf doppelte Paarungen</h2>\n";
    if (empty($duplicates)) {
        echo "<p style='color:green'>Keine doppelten Paarungen gefunden – perfekt!</p>";
    } else {
        echo "<p style='color:red'>Folgende Paarungen kamen mehrfach vor:</p>\n";
        echo "<ul>\n";
        foreach ($duplicates as $key => $anz) {
            echo "<li>$key: $anz-mal</li>\n";
        }
        echo "</ul>\n";
    }
}


// -------------------------
// HAUPTPROGRAMM
// -------------------------
$spielerListe = range(1, $anzahlPersonen);

// 1) Erzeuge alle Partitionen (4x3)
$allePartitionen = erstelleAllePartitionen4x3($spielerListe);

// 2) Backtracking über 3 Runden
$benutztePaarungen = [];
$loesung = rundenBacktracking(
    1, 
    $anzahlRunden, 
    $allePartitionen, 
    $benutztePaarungen,
    []
);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kartenspiel-Turnier (Backtracking-Lösung)</title>
</head>
<body>
<?php
if ($loesung === false) {
    echo "<h1>Leider keine Lösung gefunden!</h1>\n";
} else {
    // Turnier anzeigen
    zeigeTurnierInHTML($loesung);

    // Partnerarray
    $partner = bauePartnerArray($loesung);
    zeigePartnerInHTML($partner);

    // Doppelte Paarungen checken
    checkDoppeltePaarungen($loesung);
}
?>
</body>
</html>