<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;

class class_stee_data{
  const MAX_REREAD_DAYS = 10; // 在几天之内查看的，允许直接再次查看而不用额度


  /**
   * 查看详情前，预请求
   * @request type: steefac/steeproj ，要查看详情的类型，公司或项目
   * @request facid: id ，公司若项目的 id
   * 返回：
   * @return API::OK([limit])
   * @return limit='never': 不受限制
   * @return limit.max: 当前剩余额度
   * @return limit.used: 当前已用额度
   */
  public static function getReadDetailLimit($request) {
    $uid = $request->query['uid'];
    $facid = $request->query['facid'];
    $type = $request->query['type'];

    // 今天额度使用多少
    $used = self::usedReadDetail    ($uid, $type);
    $max  = self::maxLimitReadDetail($uid, $type);

    // 不受限制
    if($max == 'never'){
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 近几天之内看过的，允许再看
    if(self::newlyReadDetail($uid, $type, $facid) > 0){
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 返回
    return DJApi\API::OK(['limit' => ["max"=>$max, "used"=>$used]]);
  }

  /**
   * 请求 hash 页面
   * @request hash : 页面推送时生成的 hash
   * @return DJApi\API::OK([
   *   path,  // 页面地址
   *   search // 页面参数
   * ])
   */
  public static function hash($request) {
    $uid = $request->query['uid'];
    $hash = $request->query['hash'];
    $json = DJApi\API::call(LOCAL_API_ROOT, "use-records/data/select", [
      'module' => 'cmoss',
      'and' => DJApi\API::cn_json([
        'v2' => $hash
      ])
    ]);

    $row = $json['datas']['rows'][0];
    $type = $row['k1'];
    $facid = $row['k2'];

    if($row['k1'] == 'steefac' && $row['k2'] == '用户推广'){
      $path = "/fac-detail/{$row['v1']}";
      self::recordReadDetail($uid, $type, $facid);
    }
    if($row['k1'] == 'steeproj' && $row['k2'] == '用户推广'){
      $path = "/project-detail/{$row['v1']}";
      self::recordReadDetail($uid, $type, $facid);
    }

    return DJApi\API::OK([
      'json' => $json,  // 页面地址
      'path' => $path,  // 页面地址
      'search' => $search // 页面参数
    ]);
  }








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
