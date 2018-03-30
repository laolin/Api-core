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
      $db = DJApi\DB::db();
      $lastTime = time();
      $log_id = $db->get(SteeStatic::$table['token'], 'id', ['tokenid' => $tokenid]);
      if ($log_id) {
        $db->update(SteeStatic::$table['token'], ['lastTime' => $lastTime], ['id' => $log_id]);
      } else {
        $db->insert(SteeStatic::$table['token'], [
          'uid' => $json['datas']['uid'],
          'tokenid' => $tokenid,
          'ip' => $_SERVER['REMOTE_ADDR'],
          'tokenDesc' => $_SERVER['HTTP_USER_AGENT'],
          'lastTime' => $lastTime,
        ]);
      }
    }

    return $json;
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
