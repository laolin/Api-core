<?php
/**
 * 使用记录模块
 * 1. 记录各模块、分类情况下，各项功能的使用数量及基本信息
 * 2. 提供使用情况查询
 */
namespace API_UserBind;
use DJApi;

class Steeobj{
  static $tableWX = 'api_tbl_user_wx';
  static $tableObj = 'api_tbl_stee_user';

  /**
   * 获取单人/多人的 openid
   */
  private static function getUids($type, $objid) {
    $db = DJApi\DB::db();
    $uid = $db->select(self::$tableObj, 'uid', ["{$type}_can_admin[~]"=>$objid]);
    return $uid; //[$uid, $db->getShow()];
  }


  /**
   * 获取单人/多人的 openid
   */
  public static function adminid($query) {
    $type = $query['type'];
    $objid = $query['id'];
    return DJApi\API::OK(['uid' => self::getUids($type, $objid)]);
  }

  /**
   * 获取多组多人的 openid
   */
  public static function adminidgroups($query) {
    $objidGroups = $query['group'];
    $R = [];
    foreach($objidGroups as $groupName => $group){
      $type  = $group['type' ];
      $objid = $group['objid'];
      $R[$groupName] = self::getUids($type, $objid);
    }
    return DJApi\API::OK(['R'=>$R, 'objidGroups'=>$objidGroups]);
  }

}
