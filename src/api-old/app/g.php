<?php
define("APIDOMAIN","http://gs.lateder.com/API");

class G{
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃            常用函数                                                  ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function V($arr, $keys){
    $p = &$arr;
    foreach($keys as $k){
      if(!isset($p[$k]))return "";
      $p = &$p[$k];
    }
    return $p;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃            二维码                                                    ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function QR($text){
    return APIDOMAIN ."/QRcode?s=" .urlencode(text) ;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃           微信网址转义、随机串                                       ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function my_url($url, $subpath="/qgs"){
    return WX_DOMAIN ."$subpath/index.htm$hash";
  }
  static function my_wx_url($url, $subpath="/qgs"){
    if($url[0] == "#")return WX_DOMAIN ."$subpath/wx?hash=%2523". G::urlencode(substr($url,1));
    return WX_DOMAIN ."$subpath/wx?hash=%2523". G::urlencode($url);
  }
  static function urlencode($url){
     $from = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
     $to = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
     return str_replace($from, $to, $url);
  }
  //作用：产生随机字符串，不长于 $length 位
  static function createNoncestr( $length = 32 ){
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
    $str ="";
    for ( $i = 0; $i < $length; $i++ )  {  
      $str .= $chars[mt_rand(0,35)]; //这样希望提高效率，但因调用次数少，作用估计不大。
    }  
    return $str;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             后台网页请求                                                     ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }

  static function post($url, $param) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    //$info = curl_getinfo($ch);

    //DEBUG_STR("<br/><br/>output="); DEBUG_VAR($output);
    //error_log("info=" . cn_json($info) . "\n\n" . curl_error($ch));

    curl_close($ch);
    //if($output===false)return curl_error($ch);
    return $output;
  }
  static function saveFile($file, $res){
      $fp = fopen($file,"w");
      fwrite($fp, $res);
      fclose($fp);
  }
    
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             时间函数                                                         ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function my_time(){  return time(); }//+8*3600;
  static function s_today($n=0){ return date( "Y-m-d",self::my_time()+3600*24*$n );}
  static function s_month1stday($n=0){ if($n==0) return date( "Y-m-01",self::my_time()); return date( "Y-m-d", self::str2date(self::s_month1stday())+3600*24*$n );} 
  static function s_now($n=0){ return date("Y-m-d H:i:s",self::my_time()+$n);}
  static function good_date($d,$dmin='2000-01-01'){return self::str2date($d) >= self::str2date($dmin);}
  static function str2date($str){
    $str=trim($str);
    $str=str_replace(" " , "-" , $str);
    $str=str_replace(":" , "-" , $str);
    $str=str_replace("/" , "-" , $str);
    $str=str_replace("\\" , "-" , $str);
    $str=str_replace("." , "-" , $str);
    list($a, $b, $c, $d, $e, $f) = explode("-", $str);
    if(!$d)$d = 0;
    if(!$e)$e = 0;
    if(!$f)$f = 0;
    if(!$a || !$b)return 0;
    if(!$c){
      if($a>0 && $a<=12 && $b>0 && $b<32)//月-日
        return mktime(0,0,0,$a, $b);
      return mktime(0,0,0,$b,1,$a);//年-月
    }
    return mktime($d, $e, $f, $b,$c,$a);//年-月-日
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             记录                                                             ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  public static function db_log($text){
    $db = new medoo();
    $db->insert("log_debug", ["rq"=>self::s_now(), "text"=>$text]);
  }
  
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             图形函数                                                         ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  public static function  make_mini_jpg(&$fn, $size){
    $img0 = @imagecreatefromjpeg($fn);//获取图像
    if(!$img0)return;
    $w0 = imagesx($img0);
    $h0 = imagesy($img0);

    if($w0 > $h0){ 
      $xy = $h0; $x0 = ($w0 - $h0) / 2; $y0 = 0;
    }
    else{ 
      $xy = $w0; $x0 = 0; $y0 = ($h0 - $w0) / 2;
    }
    $mini_jpg = imagecreatetruecolor($size, $size);
    imagecopyresampled($mini_jpg, $img0, 0,0,$x0,$y0, $size,$size, $xy,$xy);
    imagejpeg($mini_jpg, "$fn.$size.jpg");//保存新图像
  }
}




?>