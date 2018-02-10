<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;


class class_stee_data {
  static $table = [
    "wx" => 'api_tbl_user_wx',
    "user" => 'api_tbl_user',
  ];


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

    // 超级管理员，虽有使用记录，但仍返回已用 0，以便无限使用，而可以同一般人员一样记录操作
    // 当然也可以当作灌水功能，提高某项目/厂的受欢迎度
    if(\MyClass\SteeData::isSuperAdmin($uid)){
      return DJApi\API::OK(['limit' => ["max"=>10000, "used"=>0]]);
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
   * 搜索用户
   * @request text: 搜索的关键字，搜索用户呢称、id
   * 返回：
   * @return API::OK([list=>[]])
   */
  public static function search_user($request) {
    $text = $request->query['text'];
    $db = DJApi\DB::db();
    $list = $db->select(self::$table['user'], ["[>]" .self::$table['wx']=>['uid' => 'uidBinded']],
     ['headimgurl', 'uidBinded', 'nickname', self::$table['wx'].'.id'],
     ["AND" =>[
       'appFrom' => DJApi\Configs::get(["WX_APPID_APPSEC_DEFAULT", "WX_APPID"]),
       'OR'=>[
          "uidBinded[~]" => $text,
          "nickname[~]" => $text,
        ]
      ]]
    );
    DJApi\API::debug($db->getShow());
    // 返回
    return DJApi\API::OK(['list' => $list]);
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
    if($max == 'never'){
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid, '再次查看');
    }
    else{
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid, '使用额度查看');
    }

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
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid, '推广查看');// 每次均记录，可用于推广效果分析
    }
    if($row['k1'] == 'steeproj' && $row['k2'] == '用户推广'){
      $path = "/project-detail/{$row['v1']}";
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid, '推广查看');// 每次均记录，可用于推广效果分析
    }

    return DJApi\API::OK([
      'json' => $json,  // 页面地址
      'path' => $path,  // 页面地址
      'search' => $search // 页面参数
    ]);
  }

  /**
   * 记录前端用户操作
   * 5分钟之内(k1,k2,v1)相同的，不再重复记录
   * @request hash : 页面推送时生成的 hash
   * @return DJApi\API::OK([
   *   path,  // 页面地址
   *   search // 页面参数
   * ])
   */
  public static function logAction($request) {
    $uid  = $request->query['uid' ];
    $k1   = $request->query['k1'  ];
    $k2   = $request->query['k2'  ];
    $v1   = $request->query['v1'  ];
    $v2   = $request->query['v2'  ];
    $json = $request->query['json'];
    $base = [
      'module' => 'cmoss',
      'uid'    => $uid,
      'k1'     => $k1,
      'k2'     => $k2,
      'v1'     => $v1
    ];
    $if = array_merge($base, [
      'time[>]' => \DJApi\API::now(-300) // 5分钟之内不再重复记录
    ]);
    $param = array_merge($base, [
      'v2'     => $v2,
      'n'      => 1
    ]);
    if($json) $param['json'] = \DJApi\API::cn_json($json);
    $data = [
      'if'   =>\DJApi\API::cn_json($if),
      'param'=>\DJApi\API::cn_json($param)
    ];
    DJApi\API::debug($if, 'if');
    DJApi\API::debug($param, 'param');
    return \DJApi\API::call(LOCAL_API_ROOT, "use-records/data/json_record_if", $data);
  }


}
