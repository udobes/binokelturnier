<?php
 	include_once("startturnier.inc.php");
	//echo debugvar($_REQUEST,'$_REQUEST');
	
	echo $HTML_head; 

	
$path = "./turniere";
$files = turniere_einlesen($path);

//echo "Turnier laden";
if (isset($_REQUEST['filekey'])) {
	$key = $_REQUEST['filekey'];
} else {	
	$key = array_key_first($files);
}

turniere_auswaehlen($files,$key);
	
$turnier = turnier_laden($files[$key]);
$params  = params_laden($files[$key]);
echo debugvar($params,'$params');

echo "Datei: ".$files[$key].$b.$b;

echo "<b>Turniername: </b>".$params['turniername'].$b;
echo "<b>Teilnehmer: </b>".$params['teilnehmer'].$b;
echo "<b>Runden / Spieler pro Tisch: </b>".$params['runden']." / ".$params['spielergruppe'].$b;
echo "<b>Ort: </b>".$params['ort'].$b;
echo "<b>Bemerkung: </b>".$params['beschreibung'].$b;

function tische_anzeigen($turnier,$r) {
	
	$besetzung = besetzung_erstellen($turnier,$r);
	
	asort($besetzung);
	//echo debugvar($besetzung);

	$alttisch = $n = $m = 0;
	$s = "<h1>Runde $r</h1>";
	$s .= "<table border=1 width=100% height=>";
	foreach ($besetzung as $pers => $tisch) {
		//echo "Person: $pers - Tish: $tisch <br>\n";
		if ($alttisch != $tisch) {
			$alttisch = $tisch;
			if (($m+$n) == 0) $s .= "<tr>";
			$s .= "<td><h2>Tisch $tisch </h2><h3>Person: ";
		}

		$s .= "$pers, ";
		$n++;
		
		if ($n == 3) {
			$n = 0;
			$s = substr($s,0,-2);
			$s .= "</h3></td>\n";
			$m++;
		}
		if ($m == 5) {
			$m = 0;
			$s .= "</tr>\n";
		}
	}
	echo "$s </table>\n";
}




if (isset($_REQUEST['runde'])) {
	tische_anzeigen($turnier, $_REQUEST['runde']);
} else {
	for ($r=1; $r<=3; $r++ ) {
		tische_anzeigen($turnier,$r);
	}
}
