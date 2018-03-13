<?php
namespace NApiUnit;

class class_wx {
  /**
   * 统一入口，只允许 https 调用
   */
  public static function API($functionName, $request){
    if(!method_exists(__CLASS__, $functionName)){
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    if(!\DJApi\API::is_https()){
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "请使用https");
    }
    return self::$functionName($request);
  }

  /**
   * 接口： wx/appid
   * 获取微信公众号appid
   * @request name: 公众号名称
   */
  public static function appid($request){
    $name = $request->query["name"];
    if(!$name)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1", [$request]);
    list($appid, $secret) = WxTokenBase::appid_appsec($name);
    if(!$appid)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2");
    return \DJApi\API::OK(['appid'=>$appid]);
  }

  /**
   * 接口： wx/code_login
   * 用code换取微信openid。
   * @request name: 公众号名称
   * @request code
   *
   * @return openid
   * @return unionid
   */
  public static function code_login($request){
    $name = $request->query["name"];
    if(!$name)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1", [$request]);
    list($appid, $secret) = WxTokenBase::appid_appsec($name);
    if(!$appid || !$secret)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2");

    //读取code 
    $code  = $request->query["code"];
    $state = $request->query["state"];
    if(! $code) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效3");
    }

    // step1: 通过code获取[access_token, openid, unionid]
    $R = CWxBase::code2unionid($code, $appid, $secret);
    if(!\DJApi\API::isOk($R)){
      return $R;
    }

    //  // 获取用户个人信息（UnionID机制）
    //  $wxUser = CWxBase::getWxUserOAuth2($R['datas'], $appid, $secret);
    //  \DJApi\API::debug(['获取用户个人信息（UnionID机制）', $wxUser]);
    //  if(!$wxUser['openid']){
    //    $wxUser = CWxBase::getWxUser($R['datas']['openid'], $appid, $secret);
    //    \DJApi\API::debug(['获取用户个人信息（公众号拉取）', $wxUser]);
    //  }
    //
    //  // 保存
    //  if($wxUser['openid'])CWxBase::saveWxInfo($wxUser, "openid");

    // 返回
    return $R;
  }

  /**
   * 接口： wx/jsapi_sign
   * 前端请求jsapi签名
   * @request name: 公众号名称
   * @request url
   *
   * @return config
   */
  public static function jsapi_sign($request){
    $name = $request->query["name"];
    list($appid, $secret) = WxTokenBase::appid_appsec($name);
    if(!$appid)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2");
    $url = $request->query["url"];
    \DJApi\API::debug(['url 未解码=', $url]);
    $url = urldecode($url);
    \DJApi\API::debug(['url 已解码=', $url]);
    $jsapiTicket = WxTokenBase::GetJsApiTicket($appid, $secret);
    $timestamp = time();
    $nonceStr = \DJApi\API::createNonceStr(16);
    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
    $signature = sha1($string);
    \DJApi\API::debug(['url=', $url]);
    return \DJApi\API::OK(["config"=>[
      "jsapiTicket" => $jsapiTicket,
      "appId"     => $appid,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "signature" => $signature
    ]]);
  }


  /**
   * 接口： wx/wx_info
   * 根据[微信openid/微信unionid]，获取微信呢称、头像等
   * @request openid/unionid: openid优先
   */
  public static function wx_info($request){
    $openid  = $request->query["openid" ];
    $unionid = $request->query["unionid"];

    return CWxBase::wx_info($openid, $unionid);
  }

}
