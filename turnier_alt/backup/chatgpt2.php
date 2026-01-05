<?php

function generatePairings($players) {
    $n = count($players);
    if ($n % 3 != 0) {
        echo "The number of players must be divisible by 3.";
        return;
    }

    // Initialize an array to store pairings for each round
    $pairings = [];

    // Generate round robin pairing
    $rounds = 3;
    $playerCount = $n;
    for ($round = 0; $round < $rounds; $round++) {
        $roundPairings = [];
        
        // Rotate the players for this round
        $players = rotatePlayers($players);

        // Pair players into groups of 3
        for ($i = 0; $i < $playerCount; $i += 3) {
            $roundPairings[] = [$players[$i], $players[$i + 1], $players[$i + 2]];
        }

        // Store pairings for this round
        $pairings[] = $roundPairings;
    }
	

    return $pairings;
}

// Helper function to rotate the players array
function rotatePlayers($players) {
    $firstPlayer = array_shift($players);
    array_push($players, $firstPlayer);
    return $players;
}

// Function to display pairings for each round
function displayPairings($pairings) {
    $roundCount = 1;
    foreach ($pairings as $roundPairings) {
        echo "Round $roundCount:\n";
        foreach ($roundPairings as $pairing) {
            echo "Table: (" . implode(", ", $pairing) . ")\n";
        }
        echo "\n";
        $roundCount++;
    }
}

// Test with 9 players
$players = [1, 2, 3, 4, 5, 6, 7, 8, 9];
$pairings = generatePairings($players);
displayPairings($pairings);

?>