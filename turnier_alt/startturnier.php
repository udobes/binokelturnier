<?php

$turnier = array();
$besetzung = array();
$vergeben = array();
$partner = $partner0 = array();

include_once("startturnier.inc.php");

$anzahltische        = 4;
$anzahlteilnehmer    = $anzahltische * 3;
$turnier['name']     = "20250517_AKS_Heroldstatt";
$turnier['datum']    = "17.05.2025";
$turnier['tische']   = $anzahltische;
$turnier['personen'] = $turnier['tische'] * 3;
$turnier['runden']   = 3;

echo "<html><body>";
echo "starte Zuordnung <br>";
echo "Tische: ".$turnier['tische']." <br>";
echo "Personen: ".$turnier['personen']." <br>";

$turnier = turnier_zufall3($turnier);

turnier_speichern($turnier['name'], $turnier);
$turnier = turnier_laden($turnier['name']);

// Turnier [Runde][Person] = Tisch
if (10) { echo __LINE__.' $turnier: ';  print_r($turnier); }

// Partner [Person] [0-5] [[Person][Tisch][Runde]]
besetzung_erzeugen($turnier);
ksort($partner0);
if (10) { echo '$partner0: '; print_r($partner0); }

$doppelt = doppelte_finden($partner);
ksort($doppelt);
echo '$doppelt: ---------------------------------------------';
print_r($doppelt);


foreach ($doppelt as $pers => $part) {
	$n = 0;
	foreach($part as $pa) {
		foreach($part as $pb) {
			if ($pa[0] == $pb[0]) {
				//echo "Runde: $pa[2] - Person: $pa[0] Tisch: ".$turnier[$pa[2]][$pa[0]];
				//partner_tauschen($turnier,$pa[2],$pa[0]);
			}
		}
		$n = 0;
	}
}

$partner = besetzung_erzeugen($turnier);
//ksort($partner);
$doppelt = doppelte_finden($partner);

