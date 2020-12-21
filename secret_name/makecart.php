<?php
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");
putenv('GDFONTPATH=' . realpath('.'));
$tblid = $_GET['tblid'];
$userid = $_GET['userid'];
$idx = $_GET['idx'];
//error_log($tblid."-".$userid."-".$idx);
$estratti = [];
if(file_exists("extract_".$tblid.".txt"))
{
	$estratti = json_decode(file_get_contents("extract_".$tblid.".txt"));
}
if(file_exists("cart_".$tblid."_".$userid.".txt"))
{
	$cartobj = json_decode(file_get_contents("cart_".$tblid."_".$userid.".txt"), true);
	$rows = $cartobj['cartelle'][$idx];
	//error_log(print_r($rows, true));
	header("Content-type: image/png");
	$im     = imagecreate ( 490 , 190 );
	$white = imagecolorallocate($im, 255, 255, 255);
	imagefill($im, 0, 0, $white);
	$b = imagecolorallocate ( $im , 0 , 0 , 0 );
	$r = imagecolorallocate ( $im , 255 , 0 , 0 );
	
	imagesetthickness ($im, 2 );
	//LINEE ORRIZZONTALI
	for($i=1; $i<=2; $i++)
	{
		imageline($im, 20, (50*$i)+20, 470, (50*$i)+20, $b);
	}
	//LINEE VERTICALI
	for($i=1; $i<=8; $i++)
	{
		imageline($im, (50*$i)+20, 20, (50*$i)+20, 170, $b);
	}
	for($i=0;$i<count($rows);$i++)
	{
		for($y=0;$y<count($rows[$i]);$y++)
		{
			$g = ($rows[$i][$y] == 90 ? 8 : floor($rows[$i][$y]/10));
			$xs = ($g * 50) + 20 + ($rows[$i][$y] >= 10 ? 10 : 15);
			$ys = ($i * 50) + 20 + 33 + $i;
			imagettftext( $im , 20 , 0, $xs , $ys , (in_array($rows[$i][$y], $estratti) ? $r : $b), "arialbd.ttf", $rows[$i][$y] );
		}
	}
	
	imagepng($im);
	imagedestroy($im);
}
?>