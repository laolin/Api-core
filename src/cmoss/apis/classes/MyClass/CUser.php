<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class CUser{

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function verify($query)
  {
    $tokenid = $query['tokenid'];
    $timestamp = $query['timestamp'];
    $sign = $query['sign'];

    return \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid' => $tokenid,
      'timestamp' => $timestamp,
      'sign' => $sign,
    ]);
  }

  /** 用签名换取 uid
   * 数据来源：api请求
   * @return bool
   */
  public static function sign2uid($query)
  {
    return self::verify($query)['datas']['uid'];
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function checkSign($query)
  {
    return \DJApi\API::isOk(self::verify($query));
  }



}
