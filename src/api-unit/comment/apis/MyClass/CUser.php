<?php
// ================================
/*
 */
namespace MyClass;

use DJApi;

class CUser
{

  /** 验证签名
   * 数据来源：api请求
   * 本函数，在整个请求过程中只会被执行一次，再次执行时，将返回上次的结果
   * @return json
   */
  public static function verify()
  {
    $old_json = \DJApi\Configs::get('verify-user-json');
    if ($old_json) {
      return $old_json;
    }

    $query = $_REQUEST;
    $tokenid = $query['tokenid'];
    $timestamp = $query['timestamp'];
    $sign = $query['sign'];

    $json = \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid' => $tokenid,
      'timestamp' => $timestamp,
      'sign' => $sign,
    ]);

    \DJApi\Configs::set('verify-user-json', $json);

    /** 记录客户端特征数据 */
    if (\DJApi\API::isOk($json)) {
      \DJApi\API::debug(['记录客户端特征数据'=>'独立模块，未记录']);
    }

    return $json;
  }

  /** 用签名换取 uid
   * 数据来源：api请求
   * @return bool
   */
  public static function sign2uid()
  {
    return self::verify()['datas']['uid'];
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function checkSign()
  {
    return \DJApi\API::isOk(self::verify());
  }

}
