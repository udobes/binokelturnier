<?php

include_once("debugvar.php");

// Gesamtzahl der Spieler
$anzahlSpieler = 120;
// Anzahl der Runden
$anzahlRunden = 3;
// Anzahl der Spieler pro Gruppe
$spielerProGruppe = 3;

//turnierdatum=10.05.2025&turniername=test&teilnehmer=120&runden=3&spielergruppe=3&ort=Heroldstatt&beschreibung="Hier kommt ein Test für die Parameter"
if (isset($_REQUEST['turnierdatum']))  $turnierdatum = $_REQUEST['turnierdatum'];
if (isset($_REQUEST['turniername']))   $turniername = $_REQUEST['turniername'];
if (isset($_REQUEST['teilnehmer']))    $anzahlSpieler = $_REQUEST['teilnehmer'];
if (isset($_REQUEST['runden']))        $anzahlRunden = $_REQUEST['runden'];
if (isset($_REQUEST['spielergruppe'])) $spielerProGruppe = $_REQUEST['spielergruppe'];
if (isset($_REQUEST['ort']))           $ort = $_REQUEST['ort'];
if (isset($_REQUEST['beschreibung']))  $beschreibung = $_REQUEST['beschreibung'];


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
function erstelleTurnierplan($anzahlSpieler, $anzahlRunden, $spielerProGruppe) {
  $spieler = range(1, $anzahlSpieler);
  $turnier = [];

  for ($runde = 1; $runde <= $anzahlRunden; $runde++) {
    $spieler = mischeSpieler($spieler);
    $anzahlGruppen = $anzahlSpieler / $spielerProGruppe;
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

function turnier_speichern($name,$turnier) {
	$fp = fopen("turniere/".$name.".trn", "w");
	fputs($fp,serialize($turnier));
	fclose($fp);
}
function turnier_laden($name) {
	$fp = fopen("turniere/$name.trn","r");
	$turnier = unserialize(fgets($fp));
	fclose($fp);
	return $turnier;
}



$n = 0;
do {
	$min_count = 9999;
	
	// Turnierplan erstellen
	$turnier = erstelleTurnierplan($anzahlSpieler, $anzahlRunden, $spielerProGruppe);

	// Spielpartner finden
	$spielpartner = findeSpielpartner($turnier);

	$n += 1;
	//echo "Paarung berechnet: ".$n;
	ksort($spielpartner);
	$scount = 0;
	foreach ($spielpartner as $spieler => $partner) {
        $count = count($partner);
		$scount += $count;
		if ($count < $min_count) $min_count = $count;
	}
	$dcount = $scount/$anzahlSpieler;
	//echo " - min_count: ".$min_count." - ".$dcount."<br>";
} while ($min_count < ($anzahlRunden*2) and $dcount < 6 and $n < 100000);

echo "Paarungen berechnet: ".$n." - min_count: ".$min_count." - ".$dcount."<br>";
turnier_speichern(date("YmdHis")."_Turnier",$turnier);

	
// HTML-Ausgabe des Turnierplans
echo "<h2>Turnierplan</h2>";
echo "Anzahl Spieler: $anzahlSpieler <br>";
echo "Anzahl Runden: $anzahlRunden <br>";
echo "Anzahl Spieler in Gruppe: $spielerProGruppe <br>";
echo "<table border='1'>";
foreach ($turnier as $runde => $gruppen) {
  echo "<tr><th colspan='" . count($gruppen) . "'>Runde: $runde</th></tr>";
  echo "<tr>";
  foreach ($gruppen as $gruppe) {
    echo "<td>" . implode(", ", $gruppe) . "</td>";
  }
  echo "</tr>";
}
echo "</table>";

// HTML-Ausgabe der Spielpartner
echo "<h2>Spielpartner</h2>";
echo "<ul>";
foreach ($spielpartner as $spieler => $partner) {
  echo "<li>Spieler $spieler: " . implode(", ", $partner) . " - <b>".count($partner)."</b></li>";
}
echo "</ul>";

//echo debugvar($turnier,'$turnier'	);
?>