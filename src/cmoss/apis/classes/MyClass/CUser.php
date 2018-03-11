<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class CUser{
  public function __construct($uid){
    $db = DJApi\DB::db();
    $this->data = $db->get(
      SteeStatic::$table['stee_user'],
      SteeStatic::$field['stee_user'],
      ['and'=>['uid'=>$uid ,'mark'=>'']]
    );
  }

  /** 自己管理的产能/项目
   * @param type: steefac/steeproj
   * @return 数组 [id1, id2 ...]
   */
  public function adminObjIds($type){
    $str = $this->data[$type . '_can_admin'];
    return explode(',', $str);
  }

  /** 已登录的用户 */
  static $me;
  public static function me(){
    if(!self::$me){
      $loginRight = self::checkSign($_REQUEST);
      if($loginRight){
        self::$me = new \MyClass\CUser($_REQUEST['uid']);
      }
    }
    return self::$me;
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function checkSign($query){

    $tokenid   = $query['tokenid'];
    $timestamp = $query['timestamp'];
    $sign      = $query['sign'];

    $verify = \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid'   => $tokenid,
      'timestamp' => $timestamp,
      'sign'      => $sign
    ]);
    return \DJApi\API::isOk($verify);
  }
}
