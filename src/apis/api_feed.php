<?php
// ================================
/**
 *  errcode:
 *  
 *  以下编号和客户端代码相关
 *  202001 error userVerify
 *  202002 发布内容无效
 *  202003 获取内容结果为空
 *  
 *  以下编号目前和客户端代码不相关
 *  2021xx feed get err
 *  202201 undelete err
 *  
 */


class class_feed {
  public static function main( $para1,$para2) {
    $res=API::data(['time'=>time().' - feed is ready.']);
    return $res;
  }
  static function userVerify() {
    return USER::userVerify();
  }
  
  //test
  public static function test( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    return API::data('Test passed.');
  }
  

  /**
   *  API:
   *    /feed/draft_create
   *  输入: 
   *    uid
   *  
   *  返回:
   *    各字段
   *  
   *  注：为简化系统， 默认规定只能有一个 草稿
   *  所以当 uid 用户已有草稿时，此API是返回原有的草稿
   */
  public static function draft_init( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    $uid=API::INP('uid');    
    $app=API::INP('app');    
    $cat=API::INP('cat');
    if(!$app || !$cat ) {
      return API::msg('Error init draft data');
    }
    $r=FEED::draft_get_by_uid($uid,$app,$cat);
    if(!$r) {
      $r=FEED::draft_create($uid,$app,$cat);
    }
    return API::data($r);
  }

  
  /**
   *  API:
   *    /feed/draft_update
   *  输入: 
   *    uid
   *    fid
   *    其他要更新的字段 : FEED::data_all()函数
   *  
   *  返回:
   *    1 or 0 表示有无更新
   *  
   */
  public static function draft_update( ) {
    return self::_update('draft',false);
  }
  //feed_update只允许管理员使用
  public static function feed_update( ) {
    return self::_update('*',true);
  }
  static function _update($type, $needadmin ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    $uid=API::INP('uid');
    $fid=API::INP('fid');
    if($needadmin &&
       ! USER::checkUserRights($uid,0x10000) ) {//TODO 暂写死管理员权限数值
      return API::msg(202001,'error adminVerify');
    }
    
    //要确保fid是对应一个存在的数据
    $r=FEED::feed_get($uid,$fid,$type);
    if(API::is_error($r)){
      return $r;
    }

    $data=FEED::data_all();
    $r=FEED::feed_update($fid,$data);
    
    $attr=API::INP('attr');
    if($attr)
      $r=FEED::feed_update_attr($fid,$attr);
    
    return API::data($r);
  }

  /**
   *  API:
   *    /feed/draft_publish
   *  输入: 
   *    uid
   *    fid
   *  
   *  返回:
   *    1 or 0 表示有无更新
   */
  public static function draft_publish( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');

    $uid=API::INP('uid');    
    $fid=API::INP('fid');
    //要确保fid是对应一个存在的数据
    $r=FEED::feed_get($uid,$fid,'draft');
    if(API::is_error($r)){
      return $r;
    }
    $err=FEED::feed_validate($r['data']);
    if($err){
      return API::msg(202002,$err);
    }
    $r=FEED::draft_publish($fid );
    return API::data($r);
  }
  
  //fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff
  
  /**
   *  API:
   *    /feed/get
   *  输入: 
   *    uid
   *    fid 
   *  
   *  返回:
   *    各字段
   */
  public static function get( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    
    $uid=API::INP('uid');    
    $fid=API::INP('fid');
    $r=FEED::feed_get($uid,$fid,'publish');
    return $r;
  }
 
  /**
   *  API:
   *    /feed/li
   *  输入: 
   *    uid
   *  
   *  返回:
   *    各字段
   */
  public static function li( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    
    $uid=API::INP('uid');    
    $app=API::INP('app');    
    $cat=API::INP('cat');
    if(!$app || !$cat ) {
      return API::msg('require data: app.cat');
    }
    $getdel=API::INP('getdel');
    if($getdel &&
       ! USER::checkUserRights($uid,0x10000) ) {//TODO 暂写死管理员权限数值
      return API::msg(202001,'getdel if for admin only');
    }

    $r=FEED::feed_list($uid,$app,$cat ,$getdel);
    return $r;
  }
  /**
   *  API:
   *    /feed/del
   *  输入: 
   *    uid
   *    fid
   *  
   *  返回:
   *    1 or 0 表示有无更新
   */
  public static function del( ) {
    $r=self::userVerify();
    if(!$r)
      return API::msg(202001,'error userVerify');
    
    $uid=API::INP('uid');    
    $fid=API::INP('fid');
    //要确保fid是对应一个存在的数据
    $r=FEED::feed_get($uid,$fid,'publish');
    if(API::is_error($r)){
      return $r;
    }
    $r=FEED::feed_delete( $fid );
    return API::data($r);
  }  
  
}

