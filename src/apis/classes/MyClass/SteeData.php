<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class SteeData extends SteeStatic{

  /** 用户是否可以查看指定公司或项目的详情
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * 返回：
   * @return bool
   *   用户是这个指定公司或项目的管理员
   *   这种类型（公司或项目）不受限制
   *   最近查看过这个指定公司或项目
   */
  static function canReadDetail($uid, $type, $facid) {
    return self::isSimpleAdmin($uid, $type, $facid)
        || self::maxLimitReadDetail($uid, $type) == 'never'
        || self::newlyReadDetail($uid, $type, $facid) > 0;
  }

  /** 最近几天查看过的数量
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * 返回：
   * @return 数量
   */
  static function newlyReadDetail($uid, $type, $facid) {
    $jsonReaded = DJApi\API::call(LOCAL_API_ROOT, "use-records/data/select", [
      'module' => 'cmoss',
      'field' => 'sum(n) as n',
      'and' => DJApi\API::cn_json([
        'uid'     => $uid,
        "time[>]" => DJApi\API::today(- self::MAX_REREAD_DAYS),
        'k1'      => $type,
        'k2'      => ['使用额度查看', '推广查看'],
        'v1'      => $facid,
      ])
    ]);
    return 0 + $jsonReaded['datas']['rows'][0];
  }

  /** 今天的最大额度
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return 数量
   */
  static function maxLimitReadDetail($uid, $type) {
    // 一些人，不限额度
    if(in_array($uid, [
      301710, // 大照
      301171, // 况
      301168, // Authony
    ])) return 'never';

    // 项目，目前不限额度
    if($type == 'steeproj') return 'never';

    // 一般情况下，每天 10 条额度
    return 10;
  }

  /** 今天的额度使用
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return 数量
   */
  static function usedReadDetail($uid, $type) {
    $jsonReaded = DJApi\API::call(LOCAL_API_ROOT, "use-records/data/select", [
      'module' => 'cmoss',
      'field' => 'sum(n) as n',
      'and' => DJApi\API::cn_json([
        'uid'     => $uid,
        "time[>]" => DJApi\API::today( 0 ),
        'k1'      => $type,
        'k2'      => ['使用额度查看']
      ])
    ]);
    return 0 + $jsonReaded['datas']['rows'][0];
  }

  /** 记录一次使用
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * @param k2: 查看方式 (使用额度查看/推广查看/再次查看)
   * 返回：
   * @return 记录结果, json
   */
  static function recordReadDetail($uid, $type, $facid, $k2='使用额度查看') {
    return DJApi\API::call(LOCAL_API_ROOT, "use-records/data/record", [
      'module' => 'cmoss',
      'uid'    => $uid,
      'k1'     => $type,
      'k2'     => $k2,
      'v1'     => $facid,
      'n'      => 1
    ]);
  }

  /** 记录一条数据
   * @param uid
   * @param param: 要记录的参数，允许的参数：['module', 'k1', 'k2', 'v1', 'v2', 'n', 'json']
   *        当为字符串时，就当作 k1 的值
   * 返回：
   * @return 记录结果, json
   */
  static function recordItem($uid, $param) {
    if(is_string($param)){
      $param = ['k1' => $param];
    }
    $data = [
      'uid' => $uid,
      'n' => 1,
      'module' => 'cmoss'
    ];
    $fields = ['module', 'k1', 'k2', 'v1', 'v2', 'n', 'json'];
    foreach($param as $k=>$v){
      if(in_array($k, $fields)){
        $data[$k] = $v;
      }
    }
    $data = ['param'=>DJApi\API::cn_json($data)];
    return DJApi\API::call(LOCAL_API_ROOT, "use-records/data/json_record", $data);
  }

  /** 用户是否超级管理员
   * @param uid
   * 返回：
   * @return bool
   */
  static function isSuperAdmin($uid) {
    $db = DJApi\DB::db();
    $row = $db->get(self::$table['stee_user'], ['is_admin'], ['uid'=>$uid]);
    if(!$row)return false;
    // 超级管理员, 就算是
    return $row['is_admin'] & 0x10000;
  }

  /** 用户是否是指定公司/项目的管理员
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * 返回：
   * @return bool
   */
  static function isSimpleAdmin($uid, $type, $facid) {
    $db = DJApi\DB::db();
    $col_name = $type . '_can_admin';
    $row = $db->get(self::$table['stee_user'], [$col_name], ['uid'=>$uid]);
    if(!$row)return false;
    // 我管理的公司/项目
    $facids = explode(',', $row[$col_name]);
    DJApi\API::debug([$db->getShow(), $col_name, $row[$col_name], $row, $facids], "DB");
    return in_array($facid, $facids);
  }

  /** 用户是否是超级管理员, 或指定公司/项目的管理员
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * 返回：
   * @return bool
   */
  static function isAdmin($uid, $type, $facid) {
    $db = DJApi\DB::db();
    $col_name = $type . '_can_admin';
    $row = $db->get(self::$table['stee_user'], ['is_admin', $col_name], ['uid'=>$uid]);
    if(!$row)return false;
    // 超级管理员, 就算是
    if($row['is_admin'] & 0x10000) return true;
    // 我管理的公司/项目
    $facids = explode(',', $row[$col_name]);
    DJApi\API::debug([$db->getShow(), $col_name, $row[$col_name], $row, $facids], "DB");
    return in_array($facid, $facids);
  }
}
