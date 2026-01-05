<?php
// Gesamtanzahl der Personen
$total_players = 12;
// Anzahl der Gruppen pro Runde
$groups_per_round = $total_players / 3;
// Array zur Speicherung der Turnierergebnisse
$turnier = array();
// Array zur Speicherung der Spielpartner jeder Person
$spielpartner = array();

// Funktion zum Generieren der Gruppen für jede Runde
function generate_rounds($total_players, $groups_per_round) {
    $players = range(1, $total_players);
    $rounds = array();
    for ($round = 0; $round < 3; $round++) {
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
                            $duplicates[] = array('round' => $round_number + 1, 'pair' => $pair);
                        } else {
                            $pairs[$pair] = true;
                        }
                    }
                }
            }
        }
    }
    return $duplicates;
}

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
        <p>Spieler <?php echo $player; ?>: <?php echo implode(', ', array_unique($partners)); ?></p>
    <?php endforeach; ?>

    <h2>Doppelte Paarungen</h2>
    <?php if (!empty($duplicates)): ?>
        <?php foreach ($duplicates as $duplicate): ?>
            <p>Runde <?php echo $duplicate['round']; ?>: Paarung <?php echo $duplicate['pair']; ?></p>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Keine doppelten Paarungen gefunden.</p>
    <?php endif; ?>
</body>
</html>
