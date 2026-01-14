<?php
 	include_once("startturnier.inc.php");
	echo $HTML_head; 
?>

<div data-role="tabs" id="tabs">
<div data-role="navbar">
	<ul>
		<li><a href="#one" data-ajax="false">Start Turnier</a></li>
		<li><a href="#two" data-ajax="false">Ablauf</a></li>
		<li><a href="ajax-content-ignore.html" data-ajax="false">Ergebnisse</a></li>
	</ul>
</div>
<div id="one" class="ui-body-d ui-content">
	<h1>Turnier Grunddaten</h1>
</div>
<div id="two">
	<ul data-role="listview" data-inset="true">
		<li><a href="turniereingabe.php">Zuordnungen erstellen</a></li>
		<li><a href="personzuordnung2.php?runde=1">Runde 1</a></li>
		<li><a href="personzuordnung2.php?runde=2">Runde 2</a></li>
		<li><a href="personzuordnung2.php?runde=3">Runde 3</a></li>
		<li><a href="#">Auswertung</a></li>
	</ul>
</div>
</div>



