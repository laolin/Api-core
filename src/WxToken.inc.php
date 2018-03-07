<?php
//require_once("medoo.php");

class WxToken{
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃              获取 Token                                                      ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function GetToken($db_wx = false){
    $dbParams = [
      "database_type" => "mysql", //add by linjp because my medoo version(0.9.8) has no default value of database_type
      "server"        =>"rdsptusejuwmv3etsdahk.mysql.rds.aliyuncs.com",
      "username"      =>"gs",
      "password"      =>"gsgs0101",
      "database_name" =>"qgs_modules"
    ];
    $tableName = "g_settings";
    $appid  = "wx93301b9f5ddf5c8f";
    $secret = "c6d792289693dc6d432a79e2c13612e3";
    
    
    if($db_wx===false)$db_wx = new medoo($dbParams);
    $row = $db_wx->get($tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'access_token', "k2"=>$appid]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局Token仍然有效
    }

    //获取新的 全局Token:
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential"
           . "&appid=" . $appid . "&secret=" . $secret;
    $r = self::httpGet($url);
    
    $dick_token = json_decode($r);
    if(isset($dick_token->errcode)) return "";
    //得到了：
    $token = $dick_token->access_token;
    if(strlen($token)<10)return "";
    //写入数据库，以后可以再用：
    if ($row){
      $db_wx->update($tableName, ["v1"=>$token,"v2"=>$t_now], ["AND"=>["k1"=>'access_token', "k2"=>$appid]]);
    }else{
      $db_wx->insert($tableName, ["v1"=>$token,"v2"=>$t_now,"k1"=>'access_token', "k2"=>$appid]);
    }
    return $token;
  }
  static function GetJsApiTicket($db_wx = false){
    $dbParams = [
      "database_type" => "mysql", //add by linjp because my medoo version(0.9.8) has no default value of database_type
      "server"        =>"rdsptusejuwmv3etsdahk.mysql.rds.aliyuncs.com",
      "username"      =>"gs",
      "password"      =>"gsgs0101",
      "database_name" =>"qgs_modules"
    ];
    $tableName = "g_settings";
    $appid  = "wx93301b9f5ddf5c8f";
    $secret = "c6d792289693dc6d432a79e2c13612e3";
    
    
    if($db_wx===false)$db_wx = new medoo($dbParams);
    $row = $db_wx->get($tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'jsapi_ticket', "k2"=>$appid]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局ticket仍然有效
    }

    //获取新的 全局ticket:
    $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".self::GetToken($db_wx);
    $res = json_decode(self::httpGet($url));
    if(!isset($res->errcode) || $res->errcode != 0) return "";
    $ticket = $res->ticket;
    if (!$ticket) return "";

    //写入数据库，以后可以再用：
    if ($row){
      $db_wx->update($tableName, array("v1"=>$ticket,"v2"=>$t_now), ["AND"=>["k1"=>'jsapi_ticket', "k2"=>$appid]]);
    }else{
      $db_wx->insert($tableName, ["v1"=>$ticket,"v2"=>$t_now,"k1"=>'jsapi_ticket', "k2"=>$appid]);
    }
    return $ticket;
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
}
  




?>