<?php
	// Erroranzeige einschalten
	if (10) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
		ini_set('display_startup_errors',1);
		ini_set('display_errors',1);
	}

	session_start();

	include_once("debugvar.php");


	$b = "<br>\n";
	$perspartner = array();
	$partner     = $partner0 = array();

	function turnier_zufall3($turnier) {
		GLOBAL $b, $perspartner, $partner, $partner0;
		
		$besetzung = array();
		$vergeben = array();   // Runde 1-3 berechnen
		$runden = $turnier['runden'];
		$anzpersonen = $turnier['personen'];
		$pns_partner = $partner = $pp = $pns = $tischgruppe = array();
		for ($p=1; $p<=$anzpersonen; $p++) $perspartner[$p] = array();
		
		for ($r=1; $r<=$runden;$r++) {
			for ($p=0; $p<$anzpersonen; $p++) $personen[] = $p+1;
			//echo __LINE__.debugvar($personen,'$personen');
			for ($t=1;$t<=$turnier['tische'];$t++) {
				$pns = $pns_partner = array(); 
				for ($pn=1;$pn<=3;$pn++) {	// Personen am Tisch
					do {
						$pos = random_int(0,count($personen)-1);
						//echo "Personpos: $pos Person: ".$personen[$pos]."$b";
						$person = $personen[$pos];

					} while ( partner_pruefen($person) );

					$personen = pick_person($pos,$personen);
					$tischgruppe[] = $person;
					$besetzung[$person] = $t;

					echo str_repeat('-',90)."<h2>$r - $t - Person: $person </h2>";
/*					echo "array pp:".debugvar($pp,'pp').
						" array tischgruppe: ".debugvar($tischgruppe,'tischgruppe').
						" array perspartner-persp: ".debugvar($perspartner[$persp],'perspartner-persp').
						" array perspartner-person: ".debugvar($perspartner[$person],'perspartner-person').
						" perspartner: ".debugvar($perspartner,'perspartner');
*/
				}
				// Partner sammeln
				$perspartner[$tischgruppe[0]][] = $tischgruppe[1];
				$perspartner[$tischgruppe[0]][] = $tischgruppe[2];
				$perspartner[$tischgruppe[1]][] = $tischgruppe[0];
				$perspartner[$tischgruppe[1]][] = $tischgruppe[2];
				$perspartner[$tischgruppe[2]][] = $tischgruppe[0];
				$perspartner[$tischgruppe[2]][] = $tischgruppe[1];
				$tischgruppe = array();
				$pp = array();
			}
			$turnier[$r] = $besetzung;
		}
		ksort($besetzung);
		echo debugvar($besetzung,'$besetzung');
		ksort($perspartner);
		echo debugvar($perspartner,'$persparter');
		echo debugvar($turnier,'$turnier');
		exit;
	}	
	
	function partner_pruefen($person) {
		GLOBAL $turnier, $perspartner, $r;
		$partner = $perspartner[$person];
		echo "Person $person ".debugvar($partner);
		echo "Besetzung ".debugvar($turnier[$r][$person]);
	}
	
	function pick_person($pos, $personen) {
		GLOBAL $b;
		
		$personen_ = array();
		foreach ($personen as $p => $pers) if ($p <> $pos) $personen_[] = $personen[$p];
		//echo "\n Anzahl personen: ".count($personen)." - ".count($personen_)."$b";
		//echo debugvar($personen_);
		return $personen_;
	}
	
	function turnier_zufall2($turnier) {
		GLOBAL $perspartner, $partner, $partner0;
		
		$besetzung = array();
		$vergeben = array();   // Runde 1-3 berechnen
		$runden = $turnier['runden'];
		$anzpersonen = $turnier['personen'];
		$pns_partner = $partner = $pns = array();
			
		for ($r=1; $r<=$runden;$r++) {
			//echo "Berechne Runde $r <br>\n";
			// Für die einzelnen Tische
			for ($p=1; $p<=$anzpersonen; $p++) $personen[] = $p;

			for ($t=1;$t<=$turnier['tische'];$t++) {
				//Personen aus der Personenliste aussuchen
				$pns = $pns_partner = array(); 
				for ($pn=1;$pn<=3;$pn++) {	// Personen am Tisch

					do {
						$pos = random_int(1,count($personen)+1);
						$person = $personen[$pos];

					} while ( in_array( $person, $perspartner[$person]));
					
					//Person aus Array nehmen -------------------------------------------------------
					$personen_ = array();
					foreach ($personen as $p => $pers) if ($p <> $pos) $personen_[] = $personen[$p];
					//echo "\n Anzahl personen: ".count($personen_)." - ".count($personen)." \n";
					$personen = $personen_;
					// ------------------------------------------------------------------------------
					
					$besetzung[$person] = $t;

					$persp[] = $person;
					//
					echo "\n R:$r - T:$t - i:$pn - P:$person  ";
					//foreach ($partner0[$person] as $p0) echo " $p0, ";
					//if ($found) echo " - true "; else echo " - ... ";
					echo "<br>";
					
				} // $pn Personen am Tisch

				$perspartner[$persp[0]][] = $persp[1];
				$perspartner[$persp[0]][] = $persp[2];
				$perspartner[$persp[1]][] = $persp[0];
				$perspartner[$persp[1]][] = $persp[2];
				$perspartner[$persp[2]][] = $persp[0];
				$perspartner[$persp[2]][] = $persp[1];
				 
			} // $t Tische

			asort($besetzung);
			$turnier[$r] = $besetzung;
			//echo __LINE__.print_r($besetzung);
			$alttisch =1;
			foreach($besetzung as $pers => $tisch) {
				if($alttisch != $tisch) {
					$perspartner[$persp[0]][] = $persp[1];
					$perspartner[$persp[0]][] = $persp[2];
					$perspartner[$persp[1]][] = $persp[0];
					$perspartner[$persp[1]][] = $persp[2];
					$perspartner[$persp[2]][] = $persp[0];
					$perspartner[$persp[2]][] = $persp[1];
					$alttisch = $tisch;
				}
				$persp[] = $pers;
			}
			
			//echo __LINE__."  turnier "; print_r($turnier);
			besetzung_erzeugen($turnier);
			//echo __LINE__."  partner 1: "; print_r($partner);
			ksort($partner0);
			//
			echo __LINE__."  partner0: "; print_r($partner0);
			
			$besetzung = array();
			$vergeben  = array();
			$persp     = array();
			//echo __LINE__."  perspartner - "; print_r($perspartner);
			echo str_repeat('-',90)."\n";
		} // $r - Runden

		return $turnier;
	}

	
	function turnier_zufall($turnier) {
		GLOBAL $perspartner, $partner, $partner0;
		
		$besetzung = array();
		$vergeben = array();   // Runde 1-3 berechnen
		$runden = $turnier['runden'];
		$persp = array();
		$anzpersonen = $turnier['personen'];
		for ($p=1; $p<=$anzpersonen; $p++) $partner0[$p] = array();
		for ($i=0;$i<=date("ds");$i++) $r = random_int(1,$anzpersonen); 
		
	
		for ($r=1; $r<=$runden;$r++) {
			//echo "Berechne Runde $r <br>\n";
			// Für die einzelnen Tische
			for ($t=1;$t<=$turnier['tische'];$t++) {
				//Personen per Zufall zuordnen
				$pns = $pns_partner = array(); $found = false;
				for ($pn=1;$pn<=3;$pn++) {	// Personen am Tisch
					$n = 0; 
					do {
						$person = random_int(1,$anzpersonen);
						//echo ".";
						//
						echo "\n $pn - prüfe Person $person<br>";
						$found = false; 
						if ($pn > 1 ) {
							foreach ($pns as $pnss) {
								if ($pn >1 and in_array($person, $partner0[$pnss]) ) {
									$found = true; 
									break;
								}
							}
						}
						if ( $n++ > $anzpersonen*10 ) { echo "Notausgang $person \n"; break; }
								
					} while (in_array($person,$vergeben) or $found);

					$vergeben[]         = $person;
					$besetzung[$person] = $t;

					echo "\n Person: $person - ";
					$pns[] = $person;
					$pns_partner = array_merge($pns, $partner0[$person]);
					//
					echo "\n R:$r - T:$t - i:$pn - P:$person  ";
					foreach ($partner0[$person] as $p0) echo " $p0, ";
					if ($found) echo " - true "; else echo " - ... ";
					echo "<br>";
					
				} // $p Personen
			} // $t Tische
			asort($besetzung);
			$turnier[$r] = $besetzung;
			//echo __LINE__.print_r($besetzung);
			$alttisch =1;
			foreach($besetzung as $pers => $tisch) {
				if($alttisch != $tisch) {
					$perspartner[$persp[0]][] = $persp[1];
					$perspartner[$persp[0]][] = $persp[2];
					$perspartner[$persp[1]][] = $persp[0];
					$perspartner[$persp[1]][] = $persp[2];
					$perspartner[$persp[2]][] = $persp[0];
					$perspartner[$persp[2]][] = $persp[1];
					$alttisch = $tisch;
				}
				$persp[] = $pers;
			}
			
			//echo __LINE__."  turnier "; print_r($turnier);
			besetzung_erzeugen($turnier);
			//echo __LINE__."  partner 1: "; print_r($partner);
			ksort($partner0);
			//echo __LINE__."  partner 0: "; print_r($partner0);
			
			$besetzung = array();
			$vergeben  = array();
			$persp     = array();
			//echo __LINE__."  perspartner - "; print_r($perspartner);
			echo str_repeat('-',90)."\n";
		} // $r - Runden

		return $turnier;
	}

	function besetzung_erzeugen($turnier) {
		GLOBAL $partner, $partner0;
		$partner   = $partner0 = array();

		// Besetzung der Tische erzeugen
		$besetzung = array();
		$p = array();
		$n = 0;
		$alttisch = 1;
		foreach ($turnier as $r => $ar) {
			if ($r < 1 or $r > 3) continue;
			//echo __LINE__."  partner $r: "; print_r($turnier);
			$besetzung = $turnier[$r];
			//echo __LINE__."  besetzung 1: "; print_r($besetzung);
			asort($besetzung);
			//echo "Runde $r Besetzung: "; print_r($besetzung);
			foreach ($besetzung as $pers => $tisch) {
					if ($alttisch != $tisch) {
						partner_zuordnen($partner, $ps, $alttisch, $r);
						partner0_zuordnen($partner0, $ps);
						$ps = array();
						$alttisch = $tisch;
						$n=0;
					}
					$ps[$n] = $pers;
					$n++;
			}
		}
		partner_zuordnen($partner, $ps, $tisch, $r);
		partner0_zuordnen($partner0, $ps);
		return;
	}

	function partner_zuordnen($partner, $ps, $t, $r) {
		GLOBAL $partner;
		//echo "Personen: $ps[0] $ps[1] $ps[2] t:$t r:$r \n";
		$partner[$ps[0]][] = array($ps[1],$t,$r);  
		$partner[$ps[0]][] = array($ps[2],$t,$r);  
		$partner[$ps[1]][] = array($ps[0],$t,$r);  
		$partner[$ps[1]][] = array($ps[2],$t,$r);  
		$partner[$ps[2]][] = array($ps[0],$t,$r);  
		$partner[$ps[2]][] = array($ps[1],$t,$r);  
		return;
	}
	function partner0_zuordnen($partner0, $ps) {
		GLOBAL $partner0;
		//echo "Personen: $ps[0] $ps[1] $ps[2]\n";
		$partner0[$ps[0]][] = $ps[1];  
		$partner0[$ps[0]][] = $ps[2];  
		$partner0[$ps[1]][] = $ps[0];  
		$partner0[$ps[1]][] = $ps[2];  
		$partner0[$ps[2]][] = $ps[0];  
		$partner0[$ps[2]][] = $ps[1];  
		return;
	}
	
	function doppelte_finden($partner) {
		GLOBAL $anzpersonen,$partner0;
		$doppelt = array();
	/*	foreach ($partner as $pers => $part) {
			$n = 0;
			foreach($part as $rnd => $pa) {
				foreach($part as $pb) {
					if ($pa[0] == $pb[0]) {
						$n++;
						$doppelt[$pers] = $part;
					}
				}
			}
			if ($n>1) {
				echo "Doppelter Partner bei Person $pers <br>\n";
				//$doppelt[$pers] = $partner[$pers];
			}
			$n = 0;
		}
	*/
		for ($p=1; $p<=$anzpersonen; $p++) {
				foreach($partner0[$p] as $p0) {
					$doppelt[$p][$p0]++;
				}
		}
		return $doppelt;
	}
	
	function partner_tauschen($turnier,$runde,$person) {
		$tauschpos = random_int(1,$turnier['personen']);
		echo "Runde $runde - Person $person - Tisch ".$turnier[$runde][$person]." <br>\n";
		echo "Runde $runde - Person $tauschpos - Tisch ".$turnier[$runde][$tauschpos]." <br>\n";
		
		$tisch = $turnier[$runde][$person];
		$turnier[$runde][$person] = $turnier[$runde][$tauschpos];
		$turnier[$runde][$tauschpos] = $tisch;
		echo "Runde $runde - Person $person - Tauschtisch ".$turnier[$runde][$person]." $tauschpos<br>\n\n";
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
	
	function params_laden($name) {
		$params = array();
		if (file_exists("turniere//".$name.".prm")){
			$fp = fopen("turniere//$name.prm","r");
			$params = unserialize(fgets($fp));
			fclose($fp);
		}
		return $params;
	}
	

	function besetzung_erstellen($turnier, $rnd) {
		foreach($turnier as $runde => $tische) {
			if ($runde == $rnd) {
				foreach ($tische as $tisch => $belegung) {
					for ($i=0; $i<3; $i++) {
						$person = $turnier[$runde][$tisch][$i];
						$besetzung[$person] = $tisch+1; 
					}
				}
			}
		}
		ksort($besetzung);
		//echo debugvar($besetzung,'$besetzung');
		return $besetzung;
	}

	function turniere_einlesen($path) {
		// Direktory auslesen
		$d = dir($path);
		//echo "Handle: " . $d->handle . "\n";
		//echo "Pfad: " . $d->path . "\n";
		while (false !== ($file = $d->read())) {
		   if(strcmp(substr($file,0,1),".")) {
				//echo $file."\n<br>";
				if (substr($file,-3) == 'trn') {
					$files[] = substr($file,0,-4);
			   }
		   }
		}
		$d->close();
		arsort($files);
		//echo array_key_first($files);
		//echo debugvar($files);
		return $files;
	}
	
	function turniere_auswaehlen($files,$k) {
		
		echo "<form name='turnierwahl'  method='post' action=''>\n";
		echo '<select name="filekey" id="filekey" onChange="this.form.submit();">\n';
		foreach ($files as $key => $file) {
			if ($k == $key) $sel = " selected "; else $sel = "";
			$params  = params_laden($files[$key]);

			echo "<option value=$key $sel>
				$file"." - ".
				$params['turnierdatum']." - ".
				$params['teilnehmer']." - ".
				$params['ort'].
				"</option>\n";
		}
		echo "</select><p>\n";
		echo '<input type="submit" name="Submitbutton" value="Submitbutton"/>';
		echo "</form>\n";
	}
	
$HTML_head ='<!DOCTYPE html> 
<html>
<head>
	<meta charset="ISO-8859-1">
	<title>Binokel Heroldstatt</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="shortcut icon" href="AKS-Logo.ico">
	<link rel="stylesheet" href="binokelturnier.css" />

	<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">   
	<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" />
	<script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
	<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

</head>

<body>';
