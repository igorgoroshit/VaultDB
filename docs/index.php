<?php

$page = "home";

if (isset($_GET["page"])) { $page = $_GET["page"]; }

function loadview($page) {
	$filename = $page.".php";
	if (file_exists($filename)) {
		include("__header.php");
		include($filename);
		include("__footer.php");
	} else { 
		echo '
			<div align="center">
			<table width="80%" padding="10">
			<tr style="background:#cccccc;"><td align="center" style="font-family: helvetica;"><br/><h1>404 - Page not found</h1><br/><a href="?page=home">Click here</a><br/>&nbsp;</td></tr>
			</table>
			</div>
		'; 
	}
}

loadview($page);

?>
