<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;

class class_stee_data{
  const MAX_REREAD_DAYS = 10; // 在几天之内查看的，允许直接再次查看而不用额度





  /** 最近几天查看过的数量
   * @param uid
   * @param type: steefac/steeproj ，公司或项目
   * @param facid: id ，公司若项目的 id
   * 返回：
   * @return 数量
   */
  protected static function newlyReadDetail($uid, $type, $facid) {
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
  protected static function maxLimitReadDetail($uid, $type) {
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
  protected static function usedReadDetail($uid, $type) {
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
   * 返回：
   * @return 数量
   */
  protected static function recordReadDetail($uid, $type, $facid) {
    $jsonReaded = DJApi\API::call(LOCAL_API_ROOT, "use-records/data/record", [
      'module' => 'cmoss',
      'uid'    => $uid,
      'k1'     => $type,
      'k2'     => '使用额度查看',
      'v1'     => $facid,
      'n'      => 1
    ]);
  }
}
