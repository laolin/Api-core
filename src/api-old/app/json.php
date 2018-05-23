<?php

class CJsonData{
  public $errcode = 0;
  
  public function add($arr){
    foreach($arr as $k=>$v){
      $this->$k = $v;
    }
    return $this;
  }

  public function isOK(){
    return !$this->errcode;
  }
  public function isError(){
    return $this->errcode;
  }
}
class CJson{
  public static function OK($arr){
    $R = new CJsonData();
    $R->add($arr);
    return $R;
  }

  public static function error($errcode, $errmsg, $arr=false){
    if($arr === false)$arr = [];
    $arr['errcode'] = $errcode;
    $arr['errmsg' ] = $errmsg;
    $R = new CJsonData();
    $R->add($arr);
    return $R;
  }
  public static function errorData($errmsg, $arr=false){
    return self::error(5001, $errmsg, $arr);
  }

  public static function exitError($errcode, $errmsg, $arr=false){
    echo cn_json(self::error($errcode, $errmsg, $arr));
    exit();
  }
}

function cn_json($arr){
  if(is_array($arr) && !$arr)return "[]";
  return json_encode($arr, JSON_UNESCAPED_UNICODE);
}


//  -------------  以下旧的，但还在用 -------------


function json_error($errcode, $errmsg, $arr=false){
	if(!is_array($arr))$arr = [];
	$arr['errcode'] = $errcode < 1000? 1000: $errcode;
	$arr['errmsg' ] = $errmsg;
	return json_encode($arr);
}

function json_OK($arr){
	$arr['errcode'] = 0;
	return json_encode($arr);
}

function system_error($msg){
  switch($msg){
    case "没有权限":
      return json_encode(["errcode"=>101, "errmsg"=>"没有权限"]);
    case "时间戳":
      return json_encode(["errcode"=>102, "errmsg"=>"时间戳错误"]);
    default:
      return json_encode(["errcode"=>103, "errmsg"=>$msg]);
  }
}


function json_error_arr($errcode, $errmsg, $arr=false){
	if(!is_array($arr))$arr = [];
	$arr['errcode'] = $errcode;
	$arr['errmsg' ] = $errmsg;
	return $arr;
}

function json_OK_arr($arr){
	$arr['errcode'] = 0;
	return $arr;
}


function json_error_exit($errcode, $errmsg, $arr=false){
	if($arr === false)$arr = [];
	$arr['errcode'] = $errcode;
	$arr['errmsg' ] = $errmsg;
	echo json_encode($arr);
	exit;
}


?>