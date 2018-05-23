<?php
include "phpqrcode.php";

	$level = isset($_GET['level']) ? $_GET['level']+0 : 1;
	$size = isset($_GET['size']) ? $_GET['size']+0 : 9; 
	$margin = isset($_GET['margin']) ? $_GET['margin']+0 : 4; 
	$str = isset($_GET['str']) ? $_GET['str'] : false; 
	

	QRcode::png($_GET['s'], false, min(3, max(0, $level)), $size , $margin, false, $str);

?>