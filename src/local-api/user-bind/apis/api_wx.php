<?php
// ================================
/*
*/
namespace DJApi\UserBind;

use DJApi\DB;
use DJApi\API;
use DJApi\Request\Request;

class class_wx{
  static $tableName = 'api_tbl_user_wx';

  /**
   * 获取单人的 openid
   */
  public static function openid($request) {
    $uid = $request->query['uid'];
    $db = DB::db();
    $openid = $db->get(self::$tableName, 'openid', ["uidBinded"=>$uid]);
    return API::OK(['openid' => $openid]);
  }

  /**
   * 获取多人的 openid
   */
  public static function openids($request) {
    $uid = $request->query['uid'];
    $db = DB::db();
    $rows = $db->select(self::$tableName, ['uidBinded', 'openid'], ["uidBinded"=>$uid]);
    return API::OK(['rows' => $rows]);
  }

  /**
   * 获取多组多人的 openid
   */
  public static function openidgroups($request) {
    $uidGroups = $request->query['uid'];
    $appid = $request->query['appid'];
    $db = DB::db();
    $R = [];
    $AND = ["uidBinded"=>0];
    if($appid) $AND['appFrom'] = $appid;
    foreach($uidGroups as $groupName => $uid){
      $AND["uidBinded"] = $uid;
      $R[$groupName] = $db->select(self::$tableName, 'openid', ["AND"=>$AND]);
      //$R[$groupName] = $db->select(self::$tableName, ['uidBinded', 'openid'], ["AND"=>$AND]);
    }
    return API::OK(['R'=>$R]);
  }

}
