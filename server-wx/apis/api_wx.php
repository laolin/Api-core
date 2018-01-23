<?php

require_once("classes/user.class.php");
require_once("classes/wx.class.php");

use DJApi\API;
use DJApi\Configs;
use DJApi\DB;

class class_wx{

  /**
   * 统一入口，只允许同一服务器之间调用
   */
  public static function API($functionName, $para1, $para2){
    if(!method_exists(__CLASS__, $functionName)){
      return API::error(API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    if($_SERVER['HTTP_ORIGIN'] != "http://pgy" && $_SERVER['HTTP_REFERER'] != "http://pgy" && $_SERVER['SERVER_ADDR'] && $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']) return API::error(103, "非法调用", [$_SERVER['SERVER_ADDR'], $_SERVER]);
    return self::$functionName();
  }

  /**
   * 微信公众号的access_token /wx/access_token
   *
   * 请求参数
   *   无
   *
   * 返回：
   *   access_token: 微信公众号 access_token
   */
  public static function access_token(){
    $query = Configs::get('query-d');
    $name = $query["name"];
    if(!$name){
      $query = $_REQUEST;
      $name = $query["name"];
    }
    if(!$name)return API::error(1001, "参数无效");
    $WX_APPID_APPSEC = DJApi\Configs::get('WX_APPID_APPSEC');
    if(!$WX_APPID_APPSEC[$name])return API::error(1002, "参数无效");
    $appid = $WX_APPID_APPSEC[$name]['WX_APPID'];
    $secret = $WX_APPID_APPSEC[$name]['WX_APPSEC'];
    return API::OK(['access_token'=>WX::GetWxToken($appid, $secret)]);
  }



  /**
   * 微信 code 登录 /wx/code_login
   *
   * 请求参数
   *   @request code: 微信code
   *
   * 返回：
   *   userId
   *   token: 票据，64位16进制串
   *   tokenExtendSecond: 票据有效时间，7200(秒)
   */
  public static function code_login(){
    $db = DB::db();
    $query = Configs::get('query-d');
    $code = $query["code"];
    if(!$code){
      $query = $_REQUEST;
      $code = $query["code"];
    }
    if(!$code)return API::error(101, "无code");

    $wx_user_info = WX::getUserinfoAndAutoSave($code);

    if(!isset($wx_user_info['openid'])){
      return API::error(102, cn_json($wx_user_info));
    }
    $openid = $wx_user_info['openid'];
    $unionid = $wx_user_info['unionid'];

    $bind = $db->get(mymedoo::table('z_user_bind'), "*", ['openid'=>$openid]);

    // 只绑定过 unionid？
    if(!$bind && $unionid){
      $bind = $db->get(mymedoo::table('z_user_bind'), "*", ["unionid"=>$unionid]);
      if($bind){
        //把 openid 也绑定一下
        $db->update(mymedoo::table('z_user_bind'), ["openid"=>$openid], ["id"=>$bind["id"]]);
      }
    }
    // 已绑定的，确认一下绑定 unionid （如果重新绑定第三方的，也更新）
    if($bind && $unionid && (!$bind['unionid'] || $unionid!=$bind['unionid'])){
      //把 unionid 也绑定一下
      $db->update(mymedoo::table('z_user_bind'), ["unionid"=>$openid], ["id"=>$bind["id"]]);
    }

    // 已绑定，就去获取用户信息
    if($bind){
      $me = $db->get(mymedoo::table('z_user'), ['id', 'password'], ["id"=>$bind["userid"]]);
    }

    // 无用户信息，就是全新用户
    if(!$me){
      // 是全新的用户（原来绑定错误的，也不管了）
      $parentid = $query["state"] + 0;
      if($parentid < 200001) $parentid = 200001;
      $me = ["parentid"=>$parentid, "password"=>strtoupper(md5("pgy" . PASSWORD_APPEND))];//默认为空密码
      $me["id"] = $db->insert(mymedoo::table('z_user'), $me);
    }

    // 未绑定的，要绑定
    if(!$bind){
      $bind = ["openid"=>$openid, "unionid"=>$unionid, "userid"=>$me["id"], "appid"=>Configs::get("WX_APPID")];
      $bind["id"] = $db->insert(mymedoo::table('z_user_bind'), $bind);
    }

    return API::OK(['userId'=>$me['id'], 'token'=>CUserToken::newToken($me['id'], 3600*24*365)]);// 票据有效期 1 年
  }


  /**
   * 请求微信JSAPI接口参数  /wx/get_jsapi_param
   *
   * 请求参数
   *   @request url: 发出请求的页面url
   *
   * 返回：
   *   config: 微信JSAPI接口参数
   */
  public static function get_jsapi_param(){
    $jsapiTicket = WX::GetJsApiTicket();
    $query = Configs::get('query-d');
    $url = $query["url"];
    if(!$url){
      $query = $_REQUEST;
      $url = $query["url"];
    }
    $timestamp = time();
    $nonceStr = self::createNoncestr(16);
    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
    $signature = sha1($string);
    return API::datas(["config"=>[
        "jsapiTicket" => $jsapiTicket,
        "appId"     => APPID,
        "nonceStr"  => $nonceStr,
        "timestamp" => $timestamp,
        "signature" => $signature
      ]
    ]);
  }
	/**
   * 作用：产生随机字符串，长 $length 位
   */
	protected function createNoncestr( $length = 32 ){
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {
			$str .= $chars[mt_rand(0,35)]; //这样希望提高效率，但因调用次数少，作用估计不大。
		}
		return $str;
	}

}
