#!/usr/bin/php5
<?php
/**
 * Shell script for starting of search and returning results to the STDOUT
 *
 * @package backend
 * 
 */
 include("inc/Backend.php");
 
 # create the logfile handle
$loghandle = fopen ("log/shellback.log", "a")
	or die("Can not open Logfile!\n");
$log2 = fopen ("log/mylog.log", "a")
	or die("Can not open Logfile!\n");

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
		fwrite($log2, $request[$tmpa[0]]."\n");	
	}
} while ($line != "");

$back = new Backend(false, $argv[1]);
$ldif = $back->search($request);
fwrite($log2, $ldif);
#echo $ldif;

#echo "dn: dc=ttt,ou=ldap2sparql,dc=localdomain\n".
#	"dc: ttt\n".
#	"objectClass: domain\n\n";

?>
