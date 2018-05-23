<?php
	require_once("qrcode/phpqrcode.php");

class class_qrcode{
	
	//获取一个图像资源：
	private static function image_png($intext, $level, $pixelPerPoint, $margin, $bottomStr = false){
		$enc = QRencode::factory($level, $pixelPerPoint, $margin);
		try {
			ob_start();
			$tab = $enc->encode($intext);
			$err = ob_get_contents();
			ob_end_clean();

			$maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab) + 2*$margin));
			$pixelPerPoint = min(max(1, $pixelPerPoint), $maxSize);
			return QRimage::get_image($tab, $pixelPerPoint, $margin, $bottomStr);
		}
		catch (Exception $e) {
			return false;
		}
	}
	//画一个方形、圆角描边的LOGO
	private static function image_logo(&$image, &$logo, $x, $y, $size, $margin){
		
    ImageCopyResized($image, $logo, $x, $y, 0, 0, $size, $size, imagesx($logo), imagesy($logo));
    
    $white = 0x00ffffff;//imagecolorallocate($image, 255, 0, 0);
    $shad  = 0xcccccccc;//imagecolorallocate($image, 255, 0, 0);
		// 画一个黑色的圆
		for($i=1; $i<=$margin; $i++){
			$D = ($margin+$i)*2;
			imagearc($image, $x +             $margin, $y + $margin,             $D, $D, 180, 270, $i==$margin?$shad:$white);
			imagearc($image, $x +             $margin, $y + $size - 1 - $margin, $D, $D,  90, 180, $i==$margin?$shad:$white);
			imagearc($image, $x + $size - 1 - $margin, $y + $size - 1 - $margin, $D, $D,   0,  90, $i==$margin?$shad:$white);
			imagearc($image, $x + $size - 1 - $margin, $y + $margin,             $D, $D, 270, 360, $i==$margin?$shad:$white);
		}
		imagefilledrectangle($image, $x - $margin, $y, $x - 1, $y + $size,  $white);
		imagefilledrectangle($image, $x, $y - $margin, $x + $size, $y - 1,  $white);
		imagefilledrectangle($image, $x + $size, $y, $x + $size + $margin - 1, $y + $size,  $white);
		imagefilledrectangle($image, $x, $y + $size, $x + $size, $y + $size - 1 + $margin,  $white);

		//imagefilledrectangle($image, $x - $margin, $y, $x - $margin, $y + $size,  $shad);
		//imagefilledrectangle($image, $x, $y - $margin, $x + $size, $y - $margin,  $shad);
		for($i=1; $i<=$margin; $i++){
			$color = ((239 + (int)(16 * $i / $margin))<<24) | 0x000000;
			imageline($image, $x + $size + $margin - 1 + $i, $y, $x + $size + $margin - 1 + $i, $y + $size,  $color);
			imageline($image, $x, $y + $size + $margin - 1 + $i, $x + $size, $y + $size + $margin - 1 + $i,  $color);
		}
  }
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 添加公众号logo
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function add_logo(&$image){
		$fn = $_SERVER['DOCUMENT_ROOT'] . "/images/logo-128.png";
		$logo = @imagecreatefrompng($fn);//获取图像
		
    $w = imagesx($image);
    $x = $w *42/100;
    $size = $w *16/100;
    $margin = $size * 8 / 100;
		self::image_logo($image, $logo, $x, $x, $size, $margin);
  }
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 基本二维码
	 * xxx.com/qrcode/png (GET)
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function save_png($fn, $intext, $bottomStr=false, $level=1, $pixelPerPoint=9, $margin=4){
		$image = self::image_png($intext, $level, $pixelPerPoint, $margin, $bottomStr);
		self::add_logo($image);
		imagepng($image, $fn);
  }
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 基本二维码 + 本站 LOGO
	 * xxx.com/qrcode/png (GET)
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function png(&$get, &$post, &$db){
		$query = &$get;
		if(!isset($query['s']))return;
		$intext = trim($query['s']); if(!$intext)return;
		$level = isset($query['level']) ? $query['level']+0 : 1;
		$pixelPerPoint = isset($query['size']) ? $query['size']+0 : 10; 
		$margin = isset($query['margin']) ? $query['margin']+0 : 4; 
		$str = isset($query['str']) ? $query['str'] : false; 
		
		$image = self::image_png($intext, $level, $pixelPerPoint, $margin, $str);
		self::add_logo($image);
		
    Header("Content-type: image/png");
    ImagePng($image);
    return "";
	}
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 基本二维码
	 * xxx.com/qrcode/png (GET)
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function base(&$get, &$post, &$db){
		$query = &$get;
		if(!isset($query['s']))return;
		$intext = $query['s'];
		$level = isset($query['level']) ? $query['level']+0 : 1;
		$pixelPerPoint = isset($query['size']) ? $query['size']+0 : 10; 
		$margin = isset($query['margin']) ? $query['margin']+0 : 4; 
		$str = isset($query['str']) ? $query['str'] : false; 
		
		$image = self::image_png($intext, $level, $pixelPerPoint, $margin, $str);
		
    Header("Content-type: image/png");
    ImagePng($image);
    return "";
	}


}



?>