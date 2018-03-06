<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class CUser extends SteeStatic{
  static $field = [
    'stee_user' => ['id','uid','name','is_admin','update_at','fac_can_admin','steefac_can_admin','steeproj_can_admin','rights','score']
  ];
  public function __construct($uid){
    $db = DJApi\DB::db();
    $this->data = $db->get(
      self::$table['stee_user'],
      self::$field['stee_user'],
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
class SteeUser extends CUser{

  
  /** 获取指定id产能/项目[列表]
   * @param ids: 产能/项目的id，或其数组
   * @param type: steefac/steeproj ，产能/项目
   * 返回：
   * @return 数组
   */
  static function listObj($ids, $type) {
    $db = DJApi\DB::db();
    $list = $db->select(self::$table[$type], self::$fieldOfObj[$type],
      ['and' => [
        'id' => $ids,
        'or' => ['mark'=>null, 'mark#'=>'']
      ]]
    );
    DJApi\API::debug($db->getShow(), "DB-$type");
    return $list;
  }
  
  /** 获取用户管理的产能/项目列表
   * @param user: 用户，一个数组，包含[]字段
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return 数组
   */
  static function listAdminObj($user, $type) {
    $db = DJApi\DB::db();
    $list = $db->select(self::$table[$type], self::$fieldOfObj[$type],
      ['and' => [
        'id' => $userid,
        'or' => ['mark'=>null, 'mark#'=>'']
      ]]
    );
    DJApi\API::debug($db->getShow(), "DB-$type");
    return $list;
  }


  /** 读取 uid 的用户详情
   * @return 一行数据
   */
  public static function readSteeUser($uid){
    $db = \DJApi\DB::db();
    $row = $db->get(\MyClass\SteeStatic::$table['stee_user'], self::$field['stee_user'], ['AND'=>['uid'=>$uid ,'mark'=>'']]);
    \DJApi\API::debug(['读取用户信息', "DB"=>$db->getShow()]);
    return ($row);
  }


  /** 用签名换取 uid
   * 数据来源：api请求
   * @return bool
   */
  public static function sign2uid($query){
    $tokenid   = $query['tokenid'];
    $timestamp = $query['timestamp'];
    $sign      = $query['sign'];
    $verify = \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid'   => $tokenid,
      'timestamp' => $timestamp,
      'sign'      => $sign
    ]);
    return $verify['datas']['uid'];
  }

  /** 获取多个用户信息列表
   * @param user: 用户，一个数组，包含[]字段
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return 数组
   */
  static function get_users($ids) {
    $db = DJApi\DB::db();
    $list = $db->select(
      self::$table['user'],
      ['uid', 'uname'],
      ['uid' => $ids ]
    );
    if(!$list){
      DJApi\API::debug(['get_users()', $db->getShow(), $list]);
      return \DJApi\API::error(2001, '读取失败');
    }
    return \DJApi\API::OK(["list"=>$list]);
  }


  /** 获取多个用户信息列表
   * @param user: 用户，一个数组，包含[]字段
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return uid
   */
  static function create_user($ids) {
    $db = DJApi\DB::db();
    $uid = $db->insert( self::$table['user'], ['uname'=>'']);
    DJApi\API::debug(['添加用户', $db->getShow(), $uid]);
    return $uid;
  }
}
