<?php
// Gesamtanzahl der Personen
$total_players = 12;
// Anzahl der Gruppen pro Runde
$groups_per_round = $total_players / 3;
// Array zur Speicherung der Turnierergebnisse
$turnier = array();
// Array zur Speicherung der Spielpartner jeder Person
$spielpartner = array();
$runden = 3; 

// Funktion zum Generieren der Gruppen für jede Runde
function generate_rounds($total_players, $groups_per_round) {
	GLOBAL $runden; 
	
    $players = range(1, $total_players);
    $rounds = array();
    for ($round = 0; $round < $runden; $round++) {
        shuffle($players);
        $rounds[$round] = array_chunk($players, 3);
    }
    return $rounds;
}

// Funktion zum Überprüfen, ob es doppelte Paarungen gibt
function check_duplicate_pairs($rounds) {
    $pairs = array();
    $duplicates = array();
    foreach ($rounds as $round_number => $groups) {
        foreach ($groups as $group) {
            foreach ($group as $player1) {
                foreach ($group as $player2) {
                    if ($player1 != $player2) {
                        $pair = ($player1 < $player2) ? "$player1-$player2" : "$player2-$player1";
                        if (isset($pairs[$pair])) {
                            if (!isset($duplicates[$pair])) {
                                $duplicates[$pair] = array('rounds' => array($pairs[$pair], $round_number + 1));
                            } else {
                                $duplicates[$pair]['rounds'][] = $round_number + 1;
                            }
                        } else {
                            $pairs[$pair] = $round_number + 1;
                        }
                    }
                }
            }
        }
    }
    return $duplicates;
}

$n = 1;
do {
	$min_count = 9999;

	// Generiere die Gruppen für jede Runde
	$turnier = generate_rounds($total_players, $groups_per_round);

	// Erstelle das Spielpartner-Array
	foreach ($turnier as $round_number => $groups) {
		foreach ($groups as $group) {
			foreach ($group as $player) {
				if (!isset($spielpartner[$player])) {
					$spielpartner[$player] = array();
				}
				$spielpartner[$player] = array_merge($spielpartner[$player], array_diff($group, array($player)));
			}
		}
	}

	// Überprüfe auf doppelte Paarungen
	$duplicates = check_duplicate_pairs($turnier);
	echo "Paarung berechnet: ".$n++;
	//ksort(§spielpartner);
	foreach ($spielpartner as $player => $partners) {
        $count = count(array_unique($partners));
		if ($count < $min_count) $min_count = $count;
	}
	echo "min_count: ".$min_count."<br>";
} while ($min_count < ($runden*2) and $n < 100);


?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Turnier Ergebnis</title>
</head>
<body>
    <h1>Turnier Ergebnis</h1>
    <?php for ($round = 0; $round < 3; $round++): ?>
        <h2>Runde <?php echo $round + 1; ?></h2>
        <?php foreach ($turnier[$round] as $group_number => $group): ?>
            <p>Gruppe <?php echo $group_number + 1; ?>: <?php echo implode(', ', $group); ?></p>
        <?php endforeach; ?>
    <?php endfor; ?>

    <h2>Spielpartner</h2>
    <?php foreach ($spielpartner as $player => $partners): ?>
        <p>Spieler <?php echo "$player: <b> ".count(array_unique($partners))."</b> -> ".implode(', ', array_unique($partners)); ?></p>
    <?php endforeach; ?>

    <h2>Doppelte Paarungen</h2>
    <?php if (!empty($duplicates)): ?>
        <?php foreach ($duplicates as $pair => $info): ?>
            <p>Paarung <?php echo $pair; ?> in den Runden <?php echo implode(', ', $info['rounds']); ?></p>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Keine doppelten Paarungen gefunden.</p>
    <?php endif; ?>
</body>
</html>
