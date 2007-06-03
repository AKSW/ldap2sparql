#!/usr/bin/php5
<?php
/**
 * Shell script for starting of search and returning results to the STDOUT
 *
 * @package backend
 * 
 */

// ini file to use
$ini = "default.ini";

define('REAL_BASE', str_replace('\\', '/', dirname(__FILE__)) . '/');
 include("inc/Backend.php");
 
 # create the logfile handle
$loghandle = fopen (REAL_BASE.'log/shellback.log', 'a')
	or die("Can not open Logfile!\n");
$GLOBALS['loghandle'] = $loghandle;

fwrite($loghandle, "STARTING\n");

# parse stdin to an array and write it to the logfile
$request = array();
do {
	$line = trim(fgets(STDIN));
	# old school log
	fwrite($loghandle, "$line\n");
	if ($line != "")
	{
		$tmpa = split(": ", $line);
		$request[$tmpa[0]] = $tmpa[1];
		fwrite($loghandle, $request[$tmpa[0]]."\n");	
	}
} while ($line != "");

$back = new Backend(false, $ini);
$ldif = $back->search($request);
fwrite($loghandle, $ldif);
echo $ldif;

#echo "dn: dc=ttt,ou=ldap2sparql,dc=localdomain\n".
#	"dc: ttt\n".
#	"objectClass: domain\n\n";
fclose($loghandle);

?>