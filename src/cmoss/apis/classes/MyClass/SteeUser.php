<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class SteeUser extends CUser{

  
  /** 获取指定id产能/项目[列表]
   * @param ids: 产能/项目的id，或其数组
   * @param type: steefac/steeproj ，产能/项目
   * 返回：
   * @return 数组
   */
  static function listObj($ids, $type) {
    $db = DJApi\DB::db();
    $list = $db->select(SteeStatic::$table[$type], SteeStatic::$fieldOfObj[$type],
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
    $list = $db->select(SteeStatic::$table[$type], SteeStatic::$fieldOfObj[$type],
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
    $row = $db->get(\MyClass\SteeStatic::$table['stee_user'], SteeStatic::$field['stee_user'], ['AND'=>['uid'=>$uid ,'mark'=>'']]);
    \DJApi\API::debug(['读取用户信息', "DB"=>$db->getShow()]);
    return ($row);
  }


  /** 验证签名
   * @return Json
   */
  public static function verify_token($query){
    return \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid'   => $query['tokenid'],
      'timestamp' => $query['timestamp'],
      'sign'      => $query['sign']
    ]);
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
      SteeStatic::$table['user'],
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
    $uid = $db->insert( SteeStatic::$table['user'], ['uname'=>'']);
    DJApi\API::debug(['添加用户', $db->getShow(), $uid]);
    return $uid;
  }
}
