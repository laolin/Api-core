<?php
// ================================
/*
 */
namespace RequestByApiShell;

class class_user {
  public static $guestCall = [
    'login',
    'wx_code_login',
  ];
  /**
   * 统一入口，要求登录
   */
  public static function API($call, $request) {
    if (!method_exists(__CLASS__, $call)) {
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$call]);
    }
    if (in_array($call, self::$guestCall)) {
      return self::$call($request);
    } else {
      $verify = \MyClass\CUser::verify($request->query);
      if (!\DJApi\API::isOk($verify)) {
        return $verify;
      }
      $uid = $verify['datas']['uid'];
      return self::$call($request, $uid);

    }
  }

  /**
   * 接口： user/login
   * 根据用户名密码签名，进行用户登录，获取登录信息
   * @request uid/nick:
   * @request timestamp: 5分钟之内
   * @request sign: 签名 = md5($uid.$timestamp)
   *
   * @return {id, [uid,] tokenid, token}
   */
  public static function login($request) {
    return \MyClass\CUser::login($request->query);
  }

  /**
   * 接口： user/wx_code_login
   * 根据微信 code 登录，获取登录信息
   * @request code:
   * @request name: 公众号名称
   *
   * @return {id, [uid,] tokenid, token}
   */
  public static function wx_code_login($request) {
    return \MyClass\CUser::wx_code_login($request->query);
  }
  /**
   * 接口： user/info
   * 读取自己的微信等信息
   *
   * @return {me}
   */
  public static function info($request, $uid) {
    return \MyClass\CUser::info($uid);
  }

  /**
   * 接口： user/save_info
   * 保存自己的个人信息
   *
   * @return {me}
   */
  public static function save_info($request, $uid) {
    return \MyClass\CUser::save_info($uid, $request->query['attr']);
  }

  /**
   * 接口： user/myRights
   * 读取自己的权限
   *
   * @return {list}
   */
  public static function myRights($request, $uid) {
    return \MyClass\CUser::getUserRight($uid);
  }
}
