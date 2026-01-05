<?php
 	include_once("startturnier.inc.php");
	echo $HTML_head; 
?>

<script>
$(document).ready(function(){
  $("#myInput").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#myTable tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
</script>


<?php
include_once("startturnier.inc.php");

if (isset($_REQUEST['runde'])) {
	$runde = $_REQUEST['runde'];
}


	// Direktory auslesen
	$path = "./turniere";
	$d = dir($path);
	//echo "Handle: " . $d->handle . "\n";
	//echo "Pfad: " . $d->path . "\n";
	while (false !== ($file = $d->read())) {
	   if(strcmp(substr($file,0,1),".")) {
			//echo $file."\n<br>";
			$files[] = substr($file,0,-4);
			//$lines = file($path."/".$file);
			//print_r($lines);
			//print_r(unserialize($lines[0]));
	   }
	}
	$d->close();
	arsort($files);
	//echo array_key_first($files);
	//echo debugvar($files);
	
//echo "Turnier laden";
$key = array_key_first($files);
$turnier = turnier_laden($files[$key]);
$params  = params_laden($files[$key]);
//echo debugvar($turnier,'$turnier'); exit;	
//Secho debugvar($params,'$params'); exit;

echo "Datei: ".$files[$key].$b.$b;

echo "<b>Turniername: </b>".$params['turniername'].$b;
echo "<b>Teilnehmer: </b>".$params['teilnehmer'].$b;
echo "<b>Runden / Spieler pro Tisch: </b>".$params['runden']." / ".$params['spielergruppe'].$b;
echo "<b>Ort: </b>".$params['ort'].$b;
echo "<b>Bemerkung: </b>".$params['beschreibung'].$b;

	
function anzeige_personenzuordnung($turnier, $r) {
	$besetzung = besetzung_erstellen($turnier,$r);
	ksort($besetzung);
	$alttisch = $n = $m = 0;
	//echo "<h1>".$turnier['name']." - Runde $r </h1>";
	echo "<h1>Binokel - Runde $r </h1>";
	$s = "<table border=1 width=100% height=>";
	foreach ($besetzung as $pers => $tisch) {
		if (($m+$n) == 0) $s .= "<tr>";
		$r0 = $r-1;
		$s .= "<td><strong>Person: ".$pers." </strong> Tisch ".$tisch." </td>";
		$n++;
		$m++;
		
		if ($m == 5) {
			$m = 0;
			$s .= "</tr>\n";
		}
	}
	echo "$s </table> \n";
}


anzeige_personenzuordnung($turnier, $runde);
	

?>

</body>
</html>

