<?php

//27.12.2024 - Versuch von Timo

session_start();

// Personenliste
$personen = [
    "Person 1", "Person 2", "Person 3", "Person 4",
    "Person 5", "Person 6", "Person 7", "Person 8",
    "Person 9", "Person 10", "Person 11", "Person 12"
];

// Frühere Paarungen aus der Sitzung abrufen
if (!isset($_SESSION['fruehere_paarungen'])) {
    $_SESSION['fruehere_paarungen'] = [];
}
$frueherePaarungen = $_SESSION['fruehere_paarungen'];

// Funktion, um alle möglichen Paare in einer Gruppe zu erstellen
function erstellePaare($gruppe) {
    $paare = [];
    for ($i = 0; $i < count($gruppe); $i++) {
        for ($j = $i + 1; $j < count($gruppe); $j++) {
            $paare[] = [$gruppe[$i], $gruppe[$j]];
        }
    }
    return $paare;
}

// Funktion, um Gruppen zu erstellen, die frühere Paarungen vermeiden
function erstelleGruppen($personen, $frueherePaarungen) {
    shuffle($personen); // Personenliste mischen
    $gruppen = array_chunk($personen, 3); // In Gruppen zu je 3 Personen aufteilen

    // Sicherstellen, dass keine Paarungen den früheren gleichen entsprechen
    foreach ($gruppen as $gruppe) {
        $paare = erstellePaare($gruppe);
        foreach ($paare as $paar) {
            sort($paar); // Paar sortieren für konsistente Vergleiche
            if (in_array($paar, $frueherePaarungen)) {
                // Wenn eine gleiche Paarung existiert, erneut mischen
                return erstelleGruppen($personen, $frueherePaarungen);
            }
        }
    }

    return $gruppen;
}

// Drei Runden erstellen
$alleRunden = [];
for ($runde = 1; $runde <= 3; $runde++) {
    // Neue Gruppen erstellen
    $neueGruppen = erstelleGruppen($personen, $frueherePaarungen);

    // Neue Paarungen zu den früheren hinzufügen
    foreach ($neueGruppen as $gruppe) {
        $paare = erstellePaare($gruppe);
        foreach ($paare as $paar) {
            sort($paar); // Paar sortieren für konsistente Speicherung
            $frueherePaarungen[] = $paar;
        }
    }

    // Runde speichern
    $alleRunden[] = $neueGruppen;
}

// Aktualisierte Paarungen speichern
$_SESSION['fruehere_paarungen'] = $frueherePaarungen;

// Ergebnisse anzeigen
echo "<h1>Gruppen für 3 Runden:</h1>";
foreach ($alleRunden as $rundeIndex => $rundeGruppen) {
    echo "<h2>Runde " . ($rundeIndex + 1) . ":</h2>";
    foreach ($rundeGruppen as $gruppenIndex => $gruppe) {
        echo "<h3>Gruppe " . ($gruppenIndex + 1) . ":</h3>";
        echo "<ul>";
        foreach ($gruppe as $person) {
            echo "<li>$person</li>";
        }
        echo "</ul>";
    }
}
