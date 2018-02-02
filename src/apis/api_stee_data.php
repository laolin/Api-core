<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;


class class_stee_data {


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

    // 不受限制
    if(\MyClass\SteeData::isSimpleAdmin($uid, $type, $facid)){
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 今天额度使用多少
    $used = \MyClass\SteeData::usedReadDetail    ($uid, $type);
    $max  = \MyClass\SteeData::maxLimitReadDetail($uid, $type);

    // 不受限制
    if($max == 'never'){
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 近几天之内看过的，允许再看
    if(\MyClass\SteeData::newlyReadDetail($uid, $type, $facid) > 0){
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 返回
    return DJApi\API::OK(['limit' => ["max"=>$max, "used"=>$used]]);
  }

  /**
   * 请求查看详情
   * @request type: steefac/steeproj ，要查看详情的类型，公司或项目
   * @request facid: id ，公司若项目的 id
   * 返回：
   * @return API::OK([])
   * 备注：
   * 本请求不理会一些不受限制情况，而直接使用额度。
   * 本请求不会重复使用额度
   */
  public static function applyReadDetail($request) {
    $uid = $request->query['uid'];
    $facid = $request->query['facid'];
    $type = $request->query['type'];

    // 今天额度使用多少
    $used = \MyClass\SteeData::usedReadDetail    ($uid, $type);
    $max  = \MyClass\SteeData::maxLimitReadDetail($uid, $type);
    if($max !== 'never' && $used >= $max){
      return DJApi\API::error(DJApi\API::E_NEED_RIGHT, '额度已用完');
    }
    // 记录
    \MyClass\SteeData::recordReadDetail($uid, $type, $facid);

    // 返回
    return DJApi\API::OK([]);
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
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid);
    }
    if($row['k1'] == 'steeproj' && $row['k2'] == '用户推广'){
      $path = "/project-detail/{$row['v1']}";
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid);
    }

    return DJApi\API::OK([
      'json' => $json,  // 页面地址
      'path' => $path,  // 页面地址
      'search' => $search // 页面参数
    ]);
  }


}
