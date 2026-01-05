<?php

// Erroranzeige einschalten
if (10) {
	error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	ini_set('display_startup_errors',1);
	ini_set('display_errors',1);
}


/***************************************************************
*  Copyright notice
*
*  (c) 2002 Luite van Zelst (luite@aegee.org)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * DebugVar for PHP / Typo3 Development.
 *
 * @author	Luite van Zelst <luite@aegee.org>
 * @link	http://www.xinix.dnsalias.net/fileadmin/t3dev/debugvar.php.txt
 *
 * @access	public
 * @version	1.0
 *
 * @param	mixed	$var	The variable you want to debug. It may be one of these: object, array, boolean, int, float, string
 * @param	string	$name	Name of the variable you are debugging. Usefull to distinguish different debugvar() calls.
 * @param	int		$level	The number of recursive levels to debug. With nested arrays/objects it's the safest thing
 * @internal 				Don't use the recursive param yourself - you'll end up with incomplete tables!
 * @return	string			Returns ready debug output in html-format. Uses nested tables, unfortunately.
 */

function debugvar($var, $name = '', $level = 3, $recursive = false) {
	$style[0] = 'font-size:10px;font-family:arial;border-collapse:collapse;padding:0px;';
	$style[1] = 'border-width:1px;border-style:solid;border-color:black;border-right-style:dotted;';
	$style[2] = 'border-width:1px;border-style:solid;border-color:black;border-right-style:dotted;border-left-style:dotted;';
	$style[3] = 'border-width:1px;border-style:solid;border-color:black;border-left-style:dotted;';
	$line = ''; $len = '';
	if (@is_null($var)) {
		$type = 'Mixed';
		$var = 'NULL';
		$style[3] .= 'color:red;font-style:italic;';
	} else if(@is_array($var)) {
		$type = 'Array';
		$len = '&nbsp;('. sizeof($var) .')';
		if($level > -1) {
			$multiple = true;
			//while(list($key, $val) = each($var)) {
			foreach( $var as $key => $val) {
				$line .= debugvar($val, $key, $level - 1, true);
			}
			$var = sprintf("<table style=\"%s ;background-color:#FFFFFF \">\n%s\n</table >\n",
				$style[0],
				$line
			);
		} else {
			$var = 'Array not debugged. Set higher "level" if you want to debug this.';
			$style[3] .= 'color:red;font-style:italic;';
		}
		$style[1] .= 'color:grey;font-face:bold;';
		$style[2] .= 'color:grey;font-face:bold;';
		$style[3].= 'padding:0px;';
	} else if(@is_object($var)) {
		$type = @get_class($var) ; //. '&nbsp;(extends&nbsp;' . @get_parent_class($var) . ')&nbsp;';
		$style[1] .= 'color:purple;';
		$style[3] .= 'color:purple;';
		if($level > -1) {
			$multiple = true;
			$vars = (array) @get_object_vars($var);
			while(list($key, $val) = each($vars)) {
				$line .= debugvar($val, $key, $level -1, true);
			}
			$methods = (array) @get_class_methods($var);
			while(list($key, $val) = each($methods)) {
				$line .= sprintf("<tr ><td style=\"%s\">Method</td ><td colspan=\"2\" style=\"%s\">%s</td ></tr >",
					$style[1],
					$style[3],
					$val . '&nbsp;(&nbsp;)'
				);
			}
			$var = sprintf("<table style=\"%s ;background-color:#FFFFFF \">\n%s\n</table >\n",
				$style[0],
				$line
			);
			$len = '&nbsp;('. sizeof($vars) . '&nbsp;+&nbsp;' . sizeof($methods) .')';
		} else {
			$var = 'Object not debugged. Set higher "level" if you want to debug this.';
			$style[3] .= 'color:red;font-style:italic;';
		}
		$style[3].= 'padding:0px;';
	} else if(@is_bool($var)) {
		$type = 'Boolean';
		$style[1] .= 'color:cyan;';
		$style[2] .= 'color:cyan;';
	} else if(@is_float($var)) {
		$type = 'Float';
		$style[1] .= 'color:cyan;';
		$style[2] .= 'color:cyan;';
	} else if(@is_int($var)) {
		$type = 'Integer';
		$style[1] .= 'color:green;';
		$style[2] .= 'color:green;';
	} else if(@is_string($var)) {
		$type = 'String';
		$style[1] .= 'color:darkgrey;';
		$style[2] .= 'color:darkgrey;';
		$var = @htmlspecialchars($var);
		$len = '&nbsp;('.strlen($var).')';
		if($var == '') $var = '&nbsp;';
	} else {
		$type = 'Unknown!';
		$style[1] .= 'color:red;';
		$style[2] .= 'color:red;';
		$var = @htmlspecialchars($var);
	}
	if(! $recursive) {
		if($name == '') {
			$name = '(no name given)';
			$style[2] .= 'font-style:italic;';
		}
		$style[2] .= 'color:red;';

		if($multiple) {
			$html = "<table style=\"%s ;background-color:#FFFFFF \">\n<tr >\n<td width=\"0\" style=\"%s\">%s</td ></tr ><tr >\n<td style=\"%s\">%s</td>\n</tr >\n<tr >\n <td colspan=\"2\" style=\"%s\">%s</td>\n</tr >\n</table >\n";
		} else {
			$html = "<table style=\"%s ;background-color:#FFFFFF \">\n<tr >\n<td style=\"%s\">%s</td>\n<td style=\"%s\">%s</td ><td style=\"%s\">%s</td >\n</tr >\n</table>\n";
		}
		return sprintf($html, $style[0],
			$style[1], $type . $len,
			$style[2], $name,
			$style[3], $var
		);
	} else {
		return 	sprintf("<tr >\n<td style=\"%s\">\n%s\n</td >\n<td style=\"%s\">%s</td >\n<td style=\"%s\">\n%s\n</td ></tr >",
					$style[1],
					$type . $len,
					$style[2],
					$name,
					$style[3],
					$var
				);
	}
}

?>
