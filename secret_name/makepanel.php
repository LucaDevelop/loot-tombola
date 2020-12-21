<?php
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");
$gameid = $_GET['gameid'];
$estratti = [];
if(file_exists("extract_".$gameid.".txt"))
{
	$estratti = json_decode(file_get_contents("extract_".$gameid.".txt"), true);
}
header("Content-type: image/png");
$im     = imagecreate ( (count($estratti['lobbies']) >= 2 ? 500 : 250) , ceil(count($estratti['lobbies'])/2) * 230 ); //250 x 230
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $white);
$b = imagecolorallocate ( $im , 0 , 0 , 0 );
$r = imagecolorallocate ( $im , 255 , 0 , 0 );
for($i=0; $i<count($estratti['lobbies']); $i++)
{
	imagestring ( $im , 3 , 105+($i % 2 == 0 ? 0 : 250) , 4+(floor($i/2) * 230) , "Lobby ".($i+1) , $b );
	if($i%2 == 0 && count($estratti['lobbies']) >= 2)
	{
		imageline($im, 249, 0+(floor($i/2) * 230),249, 229+(floor($i/2) * 230), $b);
	}
	if(count($estratti['lobbies']) >= 2 && $i <= count($estratti['lobbies']) - (count($estratti['lobbies']) % 2 > 0 ? 2 : 1))
	{
		imageline($im, 0+($i % 2 == 0 ? 0 : 250), 229+(floor($i/2) * 230),249+($i % 2 == 0 ? 0 : 250),229+(floor($i/2) * 230), $b);
	}
	for($n=1;$n<=90;$n++)
	{
		$d = floor($n / 10);
		$u = $n - ($d*10);
		$x1 = ($u > 0 && $u < 6 ? ($u*20) : ($u >= 6 ? ($u*20)+20 : 220))+($i % 2 == 0 ? 0 : 250);
		$y1 = ($u > 0 ? ($d * 20) + 20 : (($d - 1) * 20) + 20)+(floor($i/2) * 230);
		$x2 = $x1 + 10;
		$y2 = $y1 + 10;
		$xs = ($n < 10 ? $x1 + 3 : $x1 - 1);
		$ys = $y1 - 2;
		if(!in_array($n, $estratti['lobbies'][$i]))
			imagefilledrectangle($im, $x1,$y1,$x2,$y2, $b);
		else
			imagestring ( $im , 3 , $xs , $ys , $n , $r );
	}
}

imagepng($im);
imagedestroy($im);

?>