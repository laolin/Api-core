<?php
namespace NApiUnit;

/**
 * 基础类
 */
class WxTokenBase {

  /**
   * 获取 Token
   */
  static function GetWxToken($appid, $secret){
    $db = CDbBase::db('wx_token_db');
    $t_now = time();

    $row = $db->get(CDbBase::table('g_settings'), ["v1", "v2"], ["AND"=>[ "k1"=>'access_token', "k2"=>$appid]]);
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局Token仍然有效
    }

    //获取新的 全局Token:
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential"
           . "&appid=" . $appid . "&secret=" . $secret;
    $r = \DJApi\API::httpGet($url);

    $dick_token = json_decode($r);
    if(isset($dick_token->errcode)) return "";
    //得到了：
    $token = $dick_token->access_token;
    if(strlen($token)<10)return "";
    //写入数据库，以后可以再用：
    if ($row){
      $db->update(CDbBase::table('g_settings'), ["v1"=>$token,"v2"=>$t_now], ["AND"=>["k1"=>'access_token', "k2"=>$appid]]);
    }else{
      $db->insert(CDbBase::table('g_settings'), ["v1"=>$token,"v2"=>$t_now,"k1"=>'access_token', "k2"=>$appid]);
    }
    return $token;
  }
  static function GetJsApiTicket($appid, $secret){
    $db = CDbBase::db('wx_token_db');
    $t_now = time();

    $row = $db->get(CDbBase::table('g_settings'), ["v1", "v2"], ["AND"=>[ "k1"=>'jsapi_ticket', "k2"=>$appid]]);
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局ticket仍然有效
    }

    //获取新的 全局ticket:
    $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".self::GetWxToken($appid, $secret);
    $res = json_decode(\DJApi\API::httpGet($url));
    if(!isset($res->errcode) || $res->errcode != 0) return "";
    $ticket = $res->ticket;
    if (!$ticket) return "";

    //写入数据库，以后可以再用：
    if ($row){
      $db->update(CDbBase::table('g_settings'), array("v1"=>$ticket,"v2"=>$t_now), ["AND"=>["k1"=>'jsapi_ticket', "k2"=>$appid]]);
    }else{
      $db->insert(CDbBase::table('g_settings'), ["v1"=>$ticket,"v2"=>$t_now,"k1"=>'jsapi_ticket', "k2"=>$appid]);
    }
    return $ticket;
  }


  static function appid_appsec($name){
    $WX_APPID_APPSEC = \DJApi\Configs::get('WX_APPID_APPSEC')[$name];
    if(!$WX_APPID_APPSEC)$WX_APPID_APPSEC = \DJApi\Configs::get('WX_APPID_APPSEC_DEFAULT');
    if(!$WX_APPID_APPSEC){
      // 敏感数据, 调试后删除！
      \DJApi\API::debug(['获取 APPID', $name, \DJApi\Configs::get('WX_APPID_APPSEC'), \DJApi\Configs::get(['WX_APPID_APPSEC_DEFAULT'])]);
      return [];
    }
    $appid = $WX_APPID_APPSEC['WX_APPID'];
    $secret = $WX_APPID_APPSEC['WX_APPSEC'];
    return [$appid, $secret];
  }
}

