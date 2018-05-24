<?php
// ================================
/*
 */
namespace MyClass;
use DJApi;

class CLog {

  /** 保存记录
   * @return 新的行id
   */
  public static function log() {
    $query = $_REQUEST;
    $uid = $query['uid'];
    $api = $query['api'];
    $call = $query['call'];

    $db = DJApi\DB::db();
    $id = $db->insert(self::$table['log'], [
      "uid" => $uid,
      "api" => "/$api/$call",
      "host" => $_SERVER['REMOTE_ADDR'],
      "cur_time" => time(),
      "get" => DJApi\API::cn_json($_GET),
      "post" => DJApi\API::cn_json($_POST),
    ]);
    return $id;
  }

  /** 获取用户活跃度，多个时间段
   * @param userid
   * @param arrDays: 要计数的天数，数组
   * 返回：
   * @return 数组
   */
  public static function countActivity($userid, $arrDays = [1, 30]) {
    $db = DJApi\DB::db();
    $R = [];
    foreach ($arrDays as $nDay) {
      $n = $db->count(self::$table['log'], ["AND" => [
        'uid' => $userid,
        'cur_time[>]' => time() - 3600 * 24 * $nDay,
      ]]
      );
      $R["day$nDay"] = $n;
      DJApi\API::debug($db->getShow(), "DB-{$nDay}天");
    }
    return $R;
  }

}
