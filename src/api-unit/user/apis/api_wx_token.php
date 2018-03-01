<?php
namespace NApiUnit;

class class_wx_token extends WxTokenBase {
  /**
   * 统一入口，只允许同一服务器之间调用
   */
  public static function API($functionName, $request){
    if(!method_exists(__CLASS__, $functionName)){
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    if(!\DJApi\API::is_https()){
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "请使用https");
    }

    // 只允许同域名或白名单调用
    $checkWhite = CWhiteHost::check();
    if(!\DJApi\API::isOk($checkWhite)){
      return $checkWhite;
    }

    return self::$functionName($request);
  }

  /**
   * 接口： token/access_token
   * 获取 access_token
   * @request name: 公众号名称
   */
  public static function access_token($request){
    $query = $request->query;
    $name = $query["name"];
    if(!$name)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1", [$request]);
    list($appid, $secret) = WxTokenBase::appid_appsec($name);
    if(!$appid || !$secret)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2", []);
    $access_token = WxTokenBase::GetWxToken($appid, $secret);
    return \DJApi\API::OK(['access_token'=>$access_token, 'appid'=>$appid]);
  }

  /**
   * 接口： token/JsApiTicket
   * 获取 JsApiTicket
   * @request name: 公众号名称
   */
  public static function JsApiTicket($request){
    $query = $request->query;
    $name = $query["name"];
    if(!$name)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效");
    list($appid, $secret) = WxTokenBase::appid_appsec($name);
    if(!$appid || !$secret)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2");
    $ticket = WxTokenBase::GetJsApiTicket($appid, $secret);
    return \DJApi\API::OK(['ticket'=>$ticket, 'appid'=>$appid]);
  }

}
