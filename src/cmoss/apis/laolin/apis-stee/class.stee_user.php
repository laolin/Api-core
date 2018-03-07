<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
 除了 table_name 函数，其他函数首字命名规则：
  以下划线开头的，返回普通PHP对象
  以非下划线开头的，返回 API::msg对象。
 
*/
class stee_user {
//=========================================================
  //获取 数据表名
  static function table_name( $item='stee_user' ) {
    $prefix=api_g("api-table-prefix");
    return $prefix.$item;
  }
  static function _keys(   ) {
    return ['id','uid','name','is_admin','update_at','fac_can_admin','steefac_can_admin','steeproj_can_admin','rights','score'];
  }  
  static function  _check_obj_type( $type ) {
    $ok=['steefac','steeproj'];
    //注意，这里需要true,严格类型检查
    return in_array($type,$ok,true);
  }
//=================================================
//  普通用户申请增加一个工厂的管理权限
  public static function apply_fac_admin($userid,$facid) {
    $tblname=self::table_name();
    $db=API::db();
    //字段名
    $ky=self::_keys();
    
    $r=self::_get_user($userid);
    if($r) {
      $id=$r['id'];
      unset($r['id']);
      $r['is_admin'] |= 1;
      if($r['fac_can_admin']){
        $ff=explode(',',$r['fac_can_admin']);
        if(count($ff)>3){
          return API::msg(202001,"count exceed");
        }
        $r['fac_can_admin'].=','.$facid;
      }
      else $r['fac_can_admin']=$facid;
      $r2=$db->update($tblname,$r,['id'=>$id]);
    } else {
      $data=['uid'=>$userid,'is_admin'=>1,
        'fac_can_admin'=>$facid];
      $r2=$db->insert($tblname,$data);
    }
    if( !$r2 ){
      return API::msg(202001,"run sql err");
    }
    return API::data($r2);
  }
  //======1//======//======//======
  public static function apply_admin($type,$userid,$obj_id) {
    $tblname=self::table_name();
    $db=API::db();
    //字段名
    $ky=self::_keys();
    if(!self::_check_obj_type($type)){
      return API::msg(202001,"E:type:".$type);
    }
    
    $col_name=$type.'_can_admin';
    $r=self::_get_user($userid);
    if($r) {
      $id=$r['id'];
      unset($r['id']);
      $r['is_admin'] |= 1;
      if($r[$col_name]){
        $ff=explode(',',$r[$col_name]);
        if(count($ff)>3){
          return API::msg(202001,"count exceed");
        }
        $r[$col_name].=','.$obj_id;
      }
      else $r[$col_name]=$obj_id;
      $r2=$db->update($tblname,$r,['id'=>$id]);
    } else {
      $data=['uid'=>$userid,'is_admin'=>1,
        $col_name=>$obj_id];
      $r2=$db->insert($tblname,$data);
    }
    if( !$r2 ){
      return API::msg(202001,"run sql err");
    }
    return API::data($r2);
  }
  
  /**
   * 移除管理员
   */
  public static function remove_admin($type, $userid, $obj_id) {
    $tblname=self::table_name();
    $db=API::db();
    //字段名
    $ky=self::_keys();
    if(!self::_check_obj_type($type)){
      return API::msg(202001,"E:type:".$type);
    }
    // 分析数据
    $col_name = $type.'_can_admin';
    $userRow = self::_get_user($userid);
    $objIds = explode(',', $userRow[$col_name]);
    if(!$objIds || !in_array($obj_id, $objIds)) {
      return API::msg(202001, ["not exit", "DB"=>$db->log(), $userRow, $objIds]);
    }
    // 移除这个管理员
    $newObjIds = array_values(array_diff($objIds, [$obj_id]));
    $n = $db->update($tblname, [$col_name=>join(',', $newObjIds)], ['id'=>$userRow['id']]);
    return API::data(['n'=>$n]);
  }
  
  //==================================================================
  //get用户权限
  public static function get_user_rights($userid) {
    $r=self::_get_user($userid);
    if(!isset($r['rights'])) {
      return API::msg(202001,"err:UID:".$userid);
    }
    return API::data($r['rights']);
  }
  //======================
  //给用户加权限
  public static function add_user_rights($userid,$rights) {
    $tblname=self::table_name();
    $db=API::db();
    $r=self::_get_user($userid);
    if(!isset($r['rights'])) {
      return API::msg(202001,"err:UID:".$userid);
    }
    $r['rights']=$r['rights']|$rights;
    
    //字段名
    $ky=self::_keys();
    
    $r2=$db->update($tblname,['rights'=>$r['rights']],['id'=>$userid]);
    return API::data($r2);
  }
//=================================================
  public static function _get_user($uid ) {
    $tblname=self::table_name();
    $db = \DJApi\DB::db();
    //字段名
    $ky=self::_keys();
    
    $r=$db->get($tblname, $ky,
      ['and'=>['uid'=>$uid ,'mark'=>'']] );
    \DJApi\API::debug(['读取用户信息', "DB"=>$db->getShow()]);
    return ($r);
  }
  public static function get_admin_of_fac($facid) {
    $tblname=self::table_name();
    $db=API::db();
    //字段名
    $ky=self::_keys();
    if( strlen($facid)<5 ){
      return API::msg(202001,"facid err");
    }
    
    $r=$db->select($tblname, $ky,['and'=>[
        'is_admin[>]'=>0,
        'mark'=>'',
        'fac_can_admin[~]'=>"$facid"
      ],"ORDER" =>'update_at DESC']);
    return API::data($r);
  } 
  //======1//======//======//======
  public static function get_admin_of_obj($type,$obj_id) {
    $tblname=self::table_name();
    if(!self::_check_obj_type($type)){
      return API::msg(202001,"E:type:".$type);
    }
    
    $col_name=$type.'_can_admin';
    $db=API::db();
    //字段名
    $ky=self::_keys();
    if( $obj_id<1 ){
      return API::msg(202001,"E:obj_id:".$obj_id);
    }
    
    $r=$db->select($tblname, $ky,['and'=>[
        'is_admin[>]'=>0,
        'mark'=>'',
        $col_name.'[~]'=>"$obj_id"
      ],"ORDER" =>'update_at DESC']);
    return API::data($r);
  } 

  public static function get_admins() {
    $tblname=self::table_name();
    $db=API::db();
    //字段名
    $ky=self::_keys();
    
    $r=$db->select($tblname, $ky,['and'=>[
        'is_admin[>]'=>0,'mark'=>'',
      ],"ORDER" =>'update_at DESC']);
    return API::data($r);
  } 

  
}
