<?php
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");
putenv('GDFONTPATH=' . realpath('.'));
$tblid = $_GET['tblid'];
$userid = $_GET['userid'];
$page = $_GET['page'];
$estrobj = [];
if(file_exists("extract_".$tblid.".txt"))
{
	$estrobj = json_decode(file_get_contents("extract_".$tblid.".txt"), true);
}
if(file_exists("cart_".$tblid."_".$userid.".txt"))
{
	$cartobj = json_decode(file_get_contents("cart_".$tblid."_".$userid.".txt"), true);
	$estratti = [];
	$lobby = -1;
	if(isset($cartobj['lobby']))
	{
		$lobby = $cartobj['lobby'];
		$estratti = $estrobj['lobbies'][$lobby];
	}
    header("Content-type: image/png");
    $cartelle = $cartobj['cartelle'];
    $altezza_cartella = 190;
    $im     = imagecreate ( 490 , $altezza_cartella*($page == 1 ? (count($cartelle) < 5 ? count($cartelle) : 5) : count($cartelle) - 5));
	$white = imagecolorallocate($im, 255, 255, 255);
	imagefill($im, 0, 0, $white);
	$b = imagecolorallocate ( $im , 0 , 0 , 0 );
    $r = imagecolorallocate ( $im , 255 , 0 , 0 );
	imagesetthickness ($im, 2 );
	$startcart = ($page == 1 ? 0 : 5);
	$contcart = ($page == 1 ? (count($cartelle) < 5 ? count($cartelle) : 5) : count($cartelle));
    for($c = $startcart; $c < $contcart; $c++)
    {
        //LINEE ORRIZZONTALI
	    for($i=1; $i<=2; $i++)
	    {
	    	imageline($im, 20, (50*$i)+20+($altezza_cartella*($c >= 5 ? $c - 5 : $c)), 470, (50*$i)+20+($altezza_cartella*($c >= 5 ? $c - 5 : $c)), $b);
	    }
	    //LINEE VERTICALI
	    for($i=1; $i<=8; $i++)
	    {
	    	imageline($im, (50*$i)+20, 20+($altezza_cartella*($c >= 5 ? $c - 5 : $c)), (50*$i)+20, 20+($altezza_cartella*($c >= 5 ? $c - 5 : $c))+150, $b);
        }
        $rows = $cartelle[$c];
        for($i=0;$i<count($rows);$i++)
	    {
	    	for($y=0;$y<count($rows[$i]);$y++)
	    	{
	    		$g = ($rows[$i][$y] == 90 ? 8 : floor($rows[$i][$y]/10));
	    		$xs = ($g * 50) + 20 + ($rows[$i][$y] >= 10 ? 10 : 15);
	    		$ys = ($i * 50) + 20 + 33 + $i + ($altezza_cartella*($c >= 5 ? $c - 5 : $c));
	    		imagettftext( $im , 20 , 0, $xs , $ys , (in_array($rows[$i][$y], $estratti) ? $r : $b), "arialbd.ttf", $rows[$i][$y] );
	    	}
	    }
    }
    imagepng($im);
	imagedestroy($im);
}
?>