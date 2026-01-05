<?php
 	include_once("startturnier.inc.php");
	echo $HTML_head; 
	
	// Direktory auslesen
	$path = "./turniere";
	$d = dir($path);
	echo "Handle: " . $d->handle . "\n";
	echo "Pfad: " . $d->path . "\n";
	while (false !== ($file = $d->read())) {
	   if(strcmp(substr($file,0,1),".")) {
			echo $file."\n<br>";
			$lines = file($path."/".$file);
			print_r($lines);
			print_r(unserialize($lines[0]));
	   }
	}
	$d->close();
	exit;
?>


    <script>
        $(document).on("pagecreate", function() {
            $("#turnierdatum").datepicker({
                dateFormat: "dd.mm.yy",
                firstDay: 1,
                dayNamesMin: ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"],
                monthNames: ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"]
            });
            $('#turnierForm').submit(function(event) {
                var teilnehmer = parseInt($('#teilnehmer').val());
                var runden = parseInt($('#runden').val());
                if (runden > 1 && teilnehmer % 2 !== 0) {
                    alert('Bei mehr als einer Runde muss die Anzahl der Teilnehmer gerade sein.');
                    event.preventDefault();
                }
            });
        });
    </script>
</head>

<body>

<div data-role="page" id="mainPage">
    <div data-role="header">
        <h1>Turnieranmeldung</h1>
    </div>

    <div data-role="content">
        <form id="turnierForm" action="gemini1.php" method=POST>
            <div data-role="fieldcontain">
                <label for="turnierdatum">Turnierdatum:</label>
                <input type="text" name="turnierdatum" id="turnierdatum" value="" data-clear-btn="true" required>
            </div>

            <div data-role="fieldcontain">
                <label for="turniername">Turniername:</label>
                <input type="text" name="turniername" id="turniername" value="" data-clear-btn="true" required>
            </div>

            <div data-role="fieldcontain">
                <label for="teilnehmer">Anzahl Teilnehmer:</label>
                <input type="number" name="teilnehmer" id="teilnehmer" value="1" min="1" required>
            </div>

            <div data-role="fieldcontain">
                <label for="runden">Anzahl Runden:</label>
                <input type="number" name="runden" id="runden" value="3" min="1" required>
            </div>

            <div data-role="fieldcontain">
                <label for="ort">Ort:</label>
                <input type="text" name="ort" id="ort" value="" data-clear-btn="true" required>
            </div>

            <div data-role="fieldcontain">
                <label for="beschreibung">Beschreibung:</label>
                <textarea name="beschreibung" id="beschreibung"></textarea>
            </div>

            <button type="submit" data-theme="b">Absenden</button>
        </form>
    </div>

    <div data-role="footer">
        <h4>&copy; Meine Firma</h4>
    </div>
</div>

</body>
</html>