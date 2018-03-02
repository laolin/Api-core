<?php
// ================================
/*
*/
namespace RequestByApiShell;

class class_app {

  /**
   * 接口： app/wx_code_to_token_uid
   * 用code换取用户登录票据和uid
   *
   * @request name: 公众号名称
   * @request code
   *
   * @return uid
   * @return token
   * @return tokenid
   * @return timestamp
   */
  public static function wx_code_login($request) {
    $code = $request->query['code'];
    $name = $request->query['name'];

    $json = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_code_to_token_uid", ['name'=>$name, 'code'=>$code]);
    \DJApi\API::debug(['wx_code_to_token_uid', $json]);

    return $json;
  }

  /**
   * 接口： user/verify_token
   * 根据票据和签名，进行用户登录，获取uid
   * @request uid: 可选
   * @request tokenid
   * @request timestamp: 5分钟之内
   * @request sign: 签名 = md5($api.$call.$uid.$token.$timestamp) 或 md5($token.$timestamp)
   *
   * @return uid, 由于用户签名时，必须用到token, 所以，不再返回
   */
  public static function verify_token($request) {
    return \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", $request->query);
  }


}
