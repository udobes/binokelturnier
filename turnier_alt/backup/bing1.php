<?php
$totalPlayers = 12;
$players = range(1, $totalPlayers);
$rounds = 3;
$groupSize = 3;
$turnier = [];
$partnerMatrix = array_fill(1, $totalPlayers, []);

shuffle($players);

for ($round = 1; $round <= $rounds; $round++) {
    $roundPlayers = $players;
    for ($group = 1; $group <= $totalPlayers / $groupSize; $group++) {
        $currentGroup = [];
        while (count($currentGroup) < $groupSize) {
            $player = array_shift($roundPlayers);
            $validGroup = true;

            foreach ($currentGroup as $existingPlayer) {
                if (in_array($player, $partnerMatrix[$existingPlayer])) {
                    $validGroup = false;
                    array_push($roundPlayers, $player);
                    break;
                }
            }

            if ($validGroup) {
                $currentGroup[] = $player;
            }
        }

        foreach ($currentGroup as $player) {
            foreach ($currentGroup as $partner) {
                if ($player !== $partner) {
                    $partnerMatrix[$player][] = $partner;
                }
            }
        }

        $turnier[$round][$group] = $currentGroup;
    }

    shuffle($players); // Shuffle players for next round
}

// HTML output
echo "<h1>Turnier Einteilung</h1>";
echo "<table border='1'>";
foreach ($turnier as $round => $groups) {
    echo "<tr><th colspan='3'>Runde $round</th></tr>";
    foreach ($groups as $group => $members) {
        echo "<tr><td>Gruppe $group</td><td>" . implode(", ", $members) . "</td></tr>";
    }
}
echo "</table>";

// Partner Matrix output
echo "<h2>Spielpartner Matrix</h2>";
echo "<table border='1'>";
foreach ($partnerMatrix as $player => $partners) {
    echo "<tr><td>Spieler $player</td><td>" . implode(", ", $partners) . "</td></tr>";
}
echo "</table>";
?>
