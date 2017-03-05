<?php

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
		$output = curl_exec($ch);
		//$info = curl_getinfo($ch);

		//DEBUG_STR("<br/><br/>output="); DEBUG_VAR($output);
		//DEBUG_STR("<br/><br/>info="); DEBUG_VAR($info);

		curl_close($ch);
		//if($output===false)return curl_error($ch);
		return $output;
	}
	static function saveFile($file, $res){
	    $fp = fopen($file,"w");
	    fwrite($fp, $res);
	    fclose($fp);
	}
    
}




?>