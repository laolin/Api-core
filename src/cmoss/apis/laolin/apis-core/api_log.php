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

  protected static function getAND() {
    $from = intval(API::INP('from'));
    $to = intval(API::INP('to'));
    $AND = [];
    /* 按开始和结束时间查询 */
    if($from){
      $AND["cur_time[>]"] = $from;
      if($to){
        $AND["cur_time[<]"] = $to;
      }
    }
    else{
      $now = time();
      $day = intval(API::INP('day'));
      if($day > 365)$day = 365;
      /* 按最近几天查询 */
      if($day > 0){
        $AND["cur_time[>]"] = $now - $day * 24 * 3600;
      }
      else{
        $hour = intval(API::INP('hour'));
        if($hour > 24) $hour=24;
        /* 按最近几小时查询 */
        if($hour > 0){
          $AND["cur_time[>]"] = $now - $hour * 3600;
        }
        /* 按最近 2 小时查询 */
        else{
          $AND["cur_time[>]"] = $now - 2 * 3600;
        }
      }
    }
    return $AND;
  }

  /**
   * 列出一个用户指定时间内的请求
   * 敏感数据不返回
   */
  public static function list_user() {
    $uid=API::INP('uid');
    $r=self::_assertAdmin($uid);
    if(API::is_error($r))
      return $r;

    $AND = self::getAND();
    $userid = API::INP('userid');
    $AND['uid'] = $userid;

    $db = \DJApi\DB::db();
    $rows = $db->select(self::_tableName(), ['get', 'time', 'host'],
      [
        "AND"   => $AND,
        "ORDER" => ["time" =>"DESC"],
        "LIMIT" => 1000
      ]
    );
    \DJApi\API::debug($db->getShow(), "DB");
    // 部分数据不要返回
    $dontReturn = ['api_signature', 'tokenid', 'timestamp', 'uid', 'callback'];
    if($rows) foreach($rows as $row){
      $item = [
        'host' => $row['host'],
        'date' => substr($row['time'], 0, 10),
        'time' => substr($row['time'], 11, 8),
      ];
      $api = json_decode($row['get'], true);
      foreach($api as $k=>$v){
        if($v && !in_array($k, $dontReturn)){
          $item[$k] = $v;
        }
      }
      $R[] = $item;
    }
    return \DJApi\API::OK($R);
  }

  public static function n() {
    $uid=API::INP('uid');
    $r=self::_assertAdmin($uid);
    if(API::is_error($r))
      return $r;

    $AND = self::getAND();

    $db = \DJApi\DB::db();
    $rows = $db->select(self::_tableName(), ['count(*) as n', 'uid'],
      [
        "AND"   => $AND,
        "GROUP" => 'uid',
        "ORDER" => ["n" =>"DESC"]
      ]
    );
    \DJApi\API::debug($db->getShow(), "DB");
    if(count($rows))
      return API::data($rows);
    return API::msg(1000, 'No-log');





    //0~30天
    $day=intval(API::INP('day'));
    if($day<0)$day=0;
    if($day>365)$day=365;
    
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
      LIMIT 1000";
    $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($db);
    if(count($r))
      return API::data($r);
    return API::msg(1000,'No-log');
    
    
  }
  public static function user() { 
    $uid=API::INP('uid');
    $r=self::_assertAdmin($uid);
    if(API::is_error($r))
      return $r; 
    $userid=API::INP('userid');
    
    //0~30天
    $day=intval(API::INP('day'));
    if($day<0)$day=0;
    if($day>365)$day=365;
    
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
      "select  `api`,`get`,`host`,`time`
      from `$tablename`
      where uid = $userid and 
      `cur_time` > $secAfter 
      order by id DESC 
      LIMIT 1000";
    $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($db);
    if(count($r))
      return API::data($r);
    return API::msg(1000,'E:noLog:'.$userid);
    
    
  }
}
