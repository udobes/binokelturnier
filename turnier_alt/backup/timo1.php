<?php
/**
 * Beispiel-Programm zur Einteilung von 12 Spielern in 3 Runden
 * mit möglichst wenigen wiederkehrenden Paarungen.
 */

// --- 1. Schritt: Parameter festlegen ---
$anzahlSpieler = 12; 
$anzahlRunden  = 3;
$gruppenGroesse = 3;    // Jede Gruppe soll 3 Spieler haben.
$anzahlGruppenProRunde = $anzahlSpieler / $gruppenGroesse;  // 12 / 3 = 4

// --- 2. Schritt: Gruppen pro Runde erstellen ---
//   Wir versuchen, die Spieler so zu mischen, dass es wenige Überschneidungen gibt.
//   Hier ein vereinfachter Ansatz: 
//   - Für jede Runde mischen wir das Array aller Spieler zufällig
//   - Verteilen die Spieler in Gruppen zu je 3
//   - Prüfen, ob die neu entstandenen Gruppen zu viele Doppelpaare erzeugen
//   - Bei zu vielen Überschneidungen mischen wir erneut

$turnier = array();   // $turnier[runde][gruppenIndex] = array(spieler1, spieler2, spieler3)
$alleSpieler = range(1, $anzahlSpieler);

// Diese Funktion extrahiert alle Paare aus einem Runden-Array (z.B. [ [1,2,3], [4,5,6], ... ])
function extrahierePaareAusRunde($gruppenEinerRunde) {
    $paare = array();
    foreach ($gruppenEinerRunde as $gruppe) {
        // Gruppe könnte z.B. [1,2,3] sein
        sort($gruppe); // vorsortieren, damit das Paar [1,2] identisch ist zu [2,1]
        for ($i = 0; $i < count($gruppe); $i++) {
            for ($j = $i+1; $j < count($gruppe); $j++) {
                $paare[] = array($gruppe[$i], $gruppe[$j]);
            }
        }
    }
    return $paare;
}

// Diese Funktion liefert alle Paare, die in den bisher eingeteilten Runden vorkommen
function alleBisherigenPaare($turnier) {
    $result = array();
    foreach ($turnier as $runde => $gruppen) {
        $paare = extrahierePaareAusRunde($gruppen);
        foreach ($paare as $p) {
            // Sortieren, um [2,1] und [1,2] als identisch zu behandeln
            sort($p);
            $result[] = $p;
        }
    }
    return $result;
}

// Hier generieren wir Gruppen für alle Runden
for ($r = 1; $r <= $anzahlRunden; $r++) {
    do {
        // zufällige Anordnung aller Spieler:
        shuffle($alleSpieler);

        // Gruppen zusammenstellen
        $tempGruppen = array();
        for ($i = 0; $i < $anzahlSpieler; $i += $gruppenGroesse) {
            $gruppe = array_slice($alleSpieler, $i, $gruppenGroesse);
            $tempGruppen[] = $gruppe;
        }

        // Prüfen, wie viele Paare doppelt wären, wenn wir diese Gruppen verwenden
        $bisherigePaare = alleBisherigenPaare($turnier);
        $neuePaare      = extrahierePaareAusRunde($tempGruppen);

        $anzahlKonflikte = 0;
        foreach ($neuePaare as $np) {
            sort($np);
            if (in_array($np, $bisherigePaare)) {
                $anzahlKonflikte++;
            }
        }

        // Wenn die Konflikte zu hoch sind, mischen wir neu
        // Je nach Toleranz kann man hier verschieden strenge Schwellenwerte wählen
        $toleranz = 0; // maximale Anzahl an überlappenden Paaren, die wir zulassen wollen
        if ($anzahlKonflikte <= $toleranz) {
            // Gruppen übernehmen
            $turnier[$r] = $tempGruppen;
			
            break;
        }
        // sonst wird die Schleife wiederholt (do-while)
    } while (true);
}

// --- 3. Schritt: Partner-Array erstellen ---
//   $partner[spieler] = array(alle Mitspieler)
$partner = array();
for ($i = 1; $i <= $anzahlSpieler; $i++) {
    $partner[$i] = array();
}

foreach ($turnier as $runde => $gruppen) {
    foreach ($gruppen as $gruppe) {
        // z.B. $gruppe = [1,2,3]
        foreach ($gruppe as $sp) {
            // alle anderen dieser Gruppe als Partner eintragen
            foreach ($gruppe as $sp2) {
                if ($sp != $sp2 && !in_array($sp2, $partner[$sp])) {
                    $partner[$sp][] = $sp2;
                }
            }
        }
    }
}

// --- 4. Schritt: HTML-Ausgabe des Turniers ---
echo "<h1>Turnier-Einteilung</h1>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Runde</th><th>Gruppen</th></tr>";
foreach ($turnier as $runde => $gruppen) {
    echo "<tr>";
    echo "<td><strong>Runde $runde</strong></td>";
    echo "<td>";
    // Alle Gruppen nacheinander ausgeben
    foreach ($gruppen as $index => $gruppe) {
        echo "Gruppe ".($index+1).": [".implode(", ", $gruppe)."]<br>";
    }
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Partner-Liste in HTML ausgeben
echo "<h2>Partner-Übersicht</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Spieler</th><th>Partner</th></tr>";
foreach ($partner as $spieler => $partnerListe) {
    echo "<tr>";
    echo "<td>Spieler $spieler</td>";
    echo "<td>".implode(", ", $partnerListe)."</td>";
    echo "</tr>";
}
echo "</table>";

// --- 5. Schritt: Funktion zum Prüfen, ob es doppelte Paarungen in den Runden gibt ---
function pruefeDoppeltePaarungen($turnier) {
    // Wir bauen uns ein assoziatives Array [ '1-2' => Anzahl, '1-3' => Anzahl, ... ]
    // Sortiert nach aufsteigender Spieler-ID in der Paarung
    $paarCount = array();

    foreach ($turnier as $runde => $gruppen) {
        foreach ($gruppen as $gruppe) {
            sort($gruppe);
            for ($i = 0; $i < count($gruppe); $i++) {
                for ($j = $i+1; $j < count($gruppe); $j++) {
                    $p1 = $gruppe[$i];
                    $p2 = $gruppe[$j];
                    $key = "$p1-$p2"; 
                    if (!isset($paarCount[$key])) {
                        $paarCount[$key] = 0;
                    }
                    $paarCount[$key]++;
                }
            }
        }
    }

    // Jetzt schauen wir, welche Paarungen öfter als 1x vorkommen
    $doppelte = array();
    foreach ($paarCount as $paar => $anzahl) {
        if ($anzahl > 1) {
            $doppelte[$paar] = $anzahl;
        }
    }

    return $doppelte;
}

// Aufruf der Prüf-Funktion
$doppeltePaare = pruefeDoppeltePaarungen($turnier);

// Ausgabe der doppelten Paarungen
echo "<h2>Prüfung auf doppelte Paarungen</h2>";
if (empty($doppeltePaare)) {
    echo "<p>Es wurden keine doppelten Paarungen gefunden. (n: $n)</p>";
	exit;
} else {
    echo "<p>Folgende Paarungen kamen mehrfach vor: (n: $n)</p>";
    echo "<ul>";
    foreach ($doppeltePaare as $paar => $anzahl) {
        echo "<li>Paarung $paar kam $anzahl-mal vor.</li>";
    }
    echo "</ul>";
}


?>