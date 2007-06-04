<!DOCTYPE html 
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
 	<link rel="stylesheet" type="text/css" href="http://aksw.org/themes/aksw2007/../default/css/default.css.php" />
	<link media="screen,projection" rel="stylesheet" type="text/css" href="http://aksw.org/themes/aksw2007/layout/aksw.css" />	
	<link media="print" rel="stylesheet" type="text/css" href="http://aksw.org/themes/aksw2007/layout/print.css" />
<style type="text/css">
<!--
input {
        width: 20em;
        background-color: #f9f9f9;
        border: 1px dotted #666666;
        font-size: 100%;
        padding: 0.2em 0em 0.2em 0.2em;
}
.submit {
        float: right;
        width: 10em;
        font-size: 100%;
        font-weight: bold;
        background-color: #f9f9f9;
        border: 1px solid #666666;
        padding: 0em 0.5em 0em 0.5em;
}
}
-->
</style>
</head>
<body><div id="content">
<h1>LDAP 2 SPARQL Query Demo</h1>
<p class="examples">Examples:
<a href="?filter=%28sn%3DDietzold%29">std</a>,
<a href="?filter=%28%26%28sn%3DAuer%29%28mail%3D*%29%29">and</a>,
<a href="?filter=%28%26%28%7C%28sn%3DDietzold%29%28sn%3DLehmann%29%29%28mail%3D*%29%29">and/or</a>
</p>
<form method="post" action="">
	<table>
		<tr>
			<td>Querystring:</td>
			<td><input type="text" name="filter" value="<?php if (isset($_REQUEST["filter"])) echo  $_REQUEST["filter"]; else echo "(objectclass=*)";?>"  maxlength="200"/> </td>  
		</tr>
		<tr>
			<td>Base:</td>
			<td><input type="text" name="base" value="<?php if (isset($_REQUEST["base"])) echo  $_REQUEST["base"]; else echo "dc=shelldomain";?>"  maxlength="100"/> </td>  
		</tr>
		<tr>
			<td>Attributes:</td>
			<td><input type="text" name="attrs" value="<?php if (isset($_REQUEST["attrs"])) echo  $_REQUEST["attrs"]; else echo "all";?>" maxlength="100"/> </td>  
		</tr>
		<tr>
			<td>Scope(0,1,2) :</td>
			<td><input type="text" name="scope" value="<?php if (isset($_REQUEST["scope"])) echo  $_REQUEST["scope"]; else echo "2";?>"  maxlength="40"/> </td>  
		</tr>
		<tr>
			<td>ini-file:</td>
			<td><input type="text" name="inifile" value="<?php if (isset($_REQUEST["inifile"])) echo  $_REQUEST["inifile"]; else echo "default.ini";?>" maxlength="40"/> </td>  
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><input class="submit" type="submit" value="Submit"/></td>
		</tr>
	</table>
</form>

<?php
define('REAL_BASE', str_replace('\\', '/', dirname(__FILE__)) . '/');
include("inc/Backend.php");

if ($_POST['inifile']) {
	$GLOBALS['wwwoutput'] = TRUE;
	$back = new Backend(false, $_POST['inifile']);
	$back->search($HTTP_POST_VARS);
	print "<pre>";
	print_r($ldifString);
	print "</pre>";
}
else {
?>

<?php
}
#phpinfo();
?>
</div>
</body>
</html>
