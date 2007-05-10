<?php
/*
 * Created on Apr 15, 2006
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
include("inc/Backend.php");

if ($_POST['inifile']) {
	$back = new Backend(true, $_POST['inifile']);
	$back->search($HTTP_POST_VARS);
	print "<pre>";
	print_r($ldifString);
	print "</pre>";
}
else {
?>
<form method="post" action="">
	<table>
		<tr>
			<td>Querystring:</td>
			<td><input type="text" name="filter" value="(objectclass=*)" size="100" maxlength="200"/> </td>  
		</tr>
		<tr>
			<td>Base:</td>
			<td><input type="text" name="base" value="dc=shelldomain" size="40" maxlength="100"/> </td>  
		</tr>
		<tr>
			<td>Attributes:</td>
			<td><input type="text" name="attrs" value="all" size="40" maxlength="100"/> </td>  
		</tr>
		<tr>
			<td>Scope(0,1,2 - DN,OneLevel,Subtree) :</td>
			<td><input type="text" name="scope" value="2" size="40" maxlength="40"/> </td>  
		</tr>
		<tr>
			<td>ini-file:</td>
			<td><input type="text" name="inifile" value="backend_ex_biz.ini" size="40" maxlength="40"/> </td>  
		</tr>
		<tr>
			<td colspan = "2">
				<input type="submit" value="Absenden"/>
				<input type="reset" name="reset" value="Reset"/>
			</td>  
		</tr>
	</table>
</form>

<?php
}
#phpinfo();
?>