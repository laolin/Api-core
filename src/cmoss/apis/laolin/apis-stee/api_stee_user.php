<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
 
*/

require_once dirname( __FILE__ ) . '/class.stee_user.php';

class class_stee_user {
    
    
  public static function main( $para1,$para2) {
    $res=API::data(['time'=>time().' - stee_user is ready.']);
    return $res;
  }
 
//=========================================================
   
   //=====【C---】==【Create】==============
   /**
   *  API:
   *    /steel_user/applyAdmins
   */
  public static function apply_fac_admin( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    //$uid = $verify_token['datas']['uid'];
    $userid=intval(API::INP('userid'));
    $facid=intval(API::INP('facid'));
    return stee_user::apply_fac_admin($userid,$facid);
  }  
  public static function apply_admin( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];

    //未关注的，不允许申请
    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid'=>$uid]);
    if(\DJApi\API::isOk($wxInfoJson)){
      $wx = $wxInfoJson['datas']['list'][0];
      $subscribe = 0 + $wx['subscribe'];
    }
    if(!$subscribe){
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '请关注后再申请管理员');
    }



    return self::_apply_admin($uid, '申请管理员', 'apply_admin');
  }
  public static function restore_admin( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    return self::_apply_admin(API::INP('userid'), '恢复管理员', 'apply_admin');
  }
  public static function remove_admin( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    return self::_apply_admin(API::INP('userid'), '移除管理员', 'remove_admin');
  }
  protected static function _apply_admin($userid, $ac, $fn='apply_admin') {
    $type   =API::INP('type');
    $facid  =intval(API::INP('facid'));
    // 先操作一下
    $json = stee_user::$fn($type, $userid, $facid);
    // 记录操作
    $record = \MyClass\SteeData::recordItem($userid, [
      'k1' => $ac,
      'k2' => $userid,
      'v1' => $type,
      'v2' => $facid,
      'json' => $json['errcode']==0 ? '' : '-1' // -1 表示操作失败
    ]);
    \DJApi\API::debug($record, 'record');
    return $json;
  }



  //=====【-R--】==【Restrive】==============
   /**
   *  API:
   *    /steel_user/me
   *  获得自己的权限
   */
  public static function me( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];
    $r=stee_user::_get_user($uid);
    return API::data($r);
  }
   /**
   *  API:
   *    /steel_user/get_user_rights
   *  获得 用户权限
   */
  public static function get_user_rights( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];
    $userid=intval(API::INP('userid'));//查询对象
    
    $user=stee_user::_get_user($uid );
    if(!($user['is_admin'] & 0x10000)) {
      return API::msg(202001,'not sysadmin '.$user['is_admin']);
    }
    
    return stee_user::get_user_rights($userid);
  }
  
  
   /**
   *  API:
   *    /steel_user/get_admins
   *  获得 所有的管理员
   */
  public static function get_admins( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];
    $user=stee_user::_get_user($uid );
    if(!($user['is_admin']& 0x10000)) {
      return API::msg(202001,"not sysadmin");
    }
    
    $r=stee_user::get_admins();
    
    return ($r);
  }
  
   /**
   *  API:
   *    /steel_user/get_admin_of_fac
   *  获得一个fac的管理员
   */
  public static function get_admin_of_fac( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    //$uid = $verify_token['datas']['uid'];
    $facid=intval(API::INP('facid'));
    return stee_user::get_admin_of_fac($facid);
  }  

  public static function get_admin_of_obj( ) {
    $verify_token = \MyClass\CUser::verify($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    //$uid = $verify_token['datas']['uid'];
    $type=API::INP('type');
    $facid=intval(API::INP('facid'));
    return stee_user::get_admin_of_obj($type,$facid);
  }  

  
}
