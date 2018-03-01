<?php
namespace NApiUnit;

class class_user{
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
   * 接口： user/verify_token
   * 根据票据和签名，进行用户登录，获取uid
   * @request uid: 可选
   * @request tokenid
   * @request timestamp: 5分钟之内
   * @request sign: 签名 = md5($api.$call.$uid.$token.$timestamp) 或 md5($api.$call.$token.$timestamp)
   *
   * @return uid, 由于用户签名时，必须用到token, 所以，不再返回
   */
  public static function verify_token($request){
    return CUser::verifyRequest($request);
  }

  /**
   * 接口： user/create_token
   * 根据uid, 生成票据并返回。【仅限本服务器，或白名单】
   * @request uid
   *
   * @return token
   * @return tokenid
   * @return timestamp
   */
  public static function create_token($request){
    // 只允许同域名或白名单调用
    $checkWhite = CWhiteHost::check();
    if(!\DJApi\API::isOk($checkWhite)){
      return $checkWhite;
    }
    return CUser::create_token($request->query['uid']);
  }



}
