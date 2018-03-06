<?php
namespace NApiUnit;

class class_bind{
  /**
   * 统一入口，只允许 https 调用
   */
  public static function API($functionName, $request){
    if(!method_exists(__CLASS__, $functionName)){
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }

    // 只允许 https 调用
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
   * 接口： bind/bind
   * 绑定用户[微信openid/微信unionid/手机号]
   * @request uid
   * @request bindtype: 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   * @request value: 绑定的值
   * @request param1: 绑定子类型1, 可选
   * @request param2: 绑定子类型2, 可选
   *
   * @return 是否成功
   */
  public static function bind($request){
    return CBind::bind($request->query);
  }

  /**
   * 接口： bind/get_bind
   * 获取指定uid绑定情况，同时包含[微信openid/微信unionid/手机号]
   * @request uid
   *
   * @return binds: 数组
   */
  public static function get_bind($request){
    return CBind::get_bind($request->query);
  }

  /**
   * 接口： bind/get_uid
   * 根据[微信openid/微信unionid/手机号], 获取用户uid
   * @request bindtype: 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   * @request value: 绑定的值
   * @request param1: 绑定子类型1, 可选
   * @request param2: 绑定子类型2, 可选
   *
   * @return uid
   */
  public static function get_uid($request){
    return CBind::get_uid($request->query);
  }

}
