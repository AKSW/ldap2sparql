<?php
/*
 * Created on Aug 14, 2006
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */


function mail_convert($value) {
	$pos = strpos($value, "mailto:");
	return substr($value, $pos +7); 
}

function tele_convert($value) {
	$pos = strpos($value, "tel:");
	return substr($value, $pos + 4); 
}


?>
