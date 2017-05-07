<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
api: hello
method: get, post, put, delete
功能：就是测试用的，没有什么别的功能。
*/
class class_log {
  static function _assertAdmin($uid) { 
    $r=USER::getUserRights($uid);
    if( intval($r)> 0x10000)
      return API::data(1);
    return API::msg(2001,'Not Admin.');
  }
  static function _tableName($name='log') {
    $prefix=api_g("api-table-prefix");
    return $prefix.$name;
  }

  public static function n() { 
    $uid=API::INP('uid');
    $r=self::_assertAdmin($uid);
    if(API::is_error($r))
      return $r;
    
    //0~30天
    $day=intval(API::INP('day'));
    if($day<0)$day=0;
    if($day>30)$day=30;
    
    //0~24小时
    $hour=intval(API::INP('hour'));
    if($hour<0)$hour=0;
    if($hour>24) $hour=24;
    
    //默认2小时
    if($hour==0 && $day==0) {
      $hour=2;
    }
    $secAfter = time() - $hour * 3600 - $day * 24 * 3600;
      
    $tablename=self::_tableName();
    
    $db=api_g("db");

    $sql=
      "select count(*) as n, `uid`
      from `$tablename`
      where uid > 0 and 
      `cur_time` > $secAfter 
      group by uid 
      order by n DESC 
      LIMIT 100";
    $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($db);
    if(count($r))
      return API::data($r);
    return API::msg(1000,'No-log');
    
    
  }
}
