<!DOCTYPE html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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
<style>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
</head>
<body>


<?php
include_once("startturnier.inc.php");


//echo "Turnier laden";
$turnier = turnier_laden("20250511225021_Turnier");
//echo debugvar($turnier,'$turnier');

for ($r=1; $r<=3; $r++ ) {

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