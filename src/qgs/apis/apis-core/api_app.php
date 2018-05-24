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

    $json = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_code_to_token_uid", ['name' => $name, 'code' => $code]);
    \DJApi\API::debug(['wx_code_to_token_uid', SERVER_API_ROOT, $json]);

    return $json;
  }

  /**
   * 接口： app/jsapi_sign
   * 前端请求jsapi签名
   *
   * @request name: 公众号名称
   * @request url
   *
   * @return config: 签名参数
   */
  public static function jsapi_sign($request) {
    $name = $request->query['name'];
    $url = $request->query['url'];

    $json = \DJApi\API::post(SERVER_API_ROOT, "user/wx/jsapi_sign", ['name' => $name, 'url' => $url]);
    \DJApi\API::debug(['jsapi_ticket', $json]);

    return $json;
  }

  /**
   * 接口： app/verify_token
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

  /**
   * 接口： app/me
   * 根据票据和签名，进行用户登录，用户信息
   * @request tokenid
   * @request timestamp: 5分钟之内
   * @request sign: 签名 = md5($api.$call.$uid.$token.$timestamp) 或 md5($token.$timestamp)
   *
   * @return uid, 由于用户签名时，必须用到token, 所以，不再返回
   */
  public static function me($request) {
    $uid = \MyClass\CUser::sign2uid($request->query);
    \DJApi\API::debug(['sign2uid()', $uid, $request->query]);
    if (!$uid) {
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '未登录');
    }

    $data = ['uid' => $uid];

    // 微信信息
    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid' => $uid, 'bindtype' => 'wx-unionid']);
    \DJApi\API::debug(['读取微信信息', $uid, $wxInfoJson]);
    if (\DJApi\API::isOk($wxInfoJson)) {
      $data['wx'] = $wxInfoJson['datas']['list'][0];
    }

    return \DJApi\API::OK($data);
  }

  /**
   * 接口： app/readDisk
   * 读取字典
   * @request param
   *
   * @return list
   */
  public static function readDisk($request) {
    return \MyClass\CDick::readDisk($request->query);
  }

  /**
   * 接口： app/createAssert
   * 新增资产id
   * @request k1,k2,k3...
   *
   * @return assert_id
   */
  public static function createAssertId($request) {
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];
    if (!\MyClass\CUser::hasRight($uid, '新增资产id')) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '没有权限');
    }
    return \MyClass\CAssert::createAssertId($uid, $request->query);
  }

  /**
   * 接口： app/translateQrCode
   * 翻译二维码
   * @request code: 单一码，或数组
   *
   * 无需权限
   *
   * @return {k1,k2,k3...}
   * @return {list}
   */
  public static function translateQrCode($request) {
    $code = $request->query['code'];
    $json = \MyClass\CAssert::readQrCode($code);
    if (!\DJApi\API::isOk($json)) {
      return $json;
    }
    \DJApi\API::debug(['读取资源', $json]);
    $list = $json['datas']['list'];
    if (!is_array($list)) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '参数错误, 或资源不存在');
    }
    // 翻译产品
    $list = \MyClass\CDick::translateProduct($list);
    // 读取图片
    $list = \MyClass\CAssert::readImgs($list);
    if (!is_array($list) || !$list[0]) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '参数错误, 或资源不存在');
    }

    if (is_array($code)) {
      return \DJApi\API::OK(['list' => $list]);
    }
    return \DJApi\API::OK($list[0]);
  }

  /**
   * 接口： app/listQrCode
   * 翻译多个二维码
   * @request code
   *
   * 无需权限
   *
   * @return {k1,k2,k3...}
   */
  public static function listQrCode($request) {
    $json = \MyClass\CAssert::readQrCode($request->query['code']);
    if (!\DJApi\API::isOk($json)) {
      return $json;
    }
    $list = $json['datas']['list'];
    if (!is_array($list) || ！count($list)) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '参数错误, 或资源不存在');
    }
    return $json;
  }

  /**
   * 接口： app/getWxInfo
   * 获取微信信息
   * @param uid: 用户id，可为数组或单个用户id
   * 返回：
   * @return 微信信息数组
   */
  public static function getWxInfo($request) {
    $uid = $request->query['uid'];
    \DJApi\API::debug(['参数 uid = ', $uid]);

    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid' => $uid]);
    \DJApi\API::debug(['从独立服务器获取微信信息', $wxInfoJson]);
    if (!\DJApi\API::isOk($wxInfoJson)) {
      return $wxInfoJson;
    }
    $wxInfo = $wxInfoJson['datas']['list'];

    // 整理
    $list = [];
    foreach ($wxInfo as $k => $row) {
      $list[] = [
        'headimgurl' => $row['headimgurl'],
        'nickname' => $row['nickname'],
        'uid' => $row['uid'],
      ];
    }
    return \DJApi\API::OK(['list' => $list]);
  }

}
