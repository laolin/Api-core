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
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];
    $facid = $request->query['facid'];
    $type = $request->query['type'];
    return \MyClass\SteeData::getReadDetailLimit($uid, $type, $facid);
  }

  /**
   * 搜索用户
   * @request text: 搜索的关键字，搜索用户呢称、id
   * 返回：
   * @return API::OK([list=>[]])
   */
  public static function search_user($request) {
    $json = \DJApi\API::post(SERVER_API_ROOT, "user/mix/search_user", $request->query);
    \DJApi\API::debug(['mix/search_user','param'=>$request->query, 'R'=> $json]);
    return $json;
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
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];
    $facid = $request->query['facid'];
    $type = $request->query['type'];

    // 近几天之内看过的，允许再看
    if(\MyClass\SteeData::newlyReadDetail($uid, $type, $facid) > 0){
      DJApi\API::debug(['近几天之内看过', $uid, $type, $facid]);
      return DJApi\API::OK(['limit' => 'never']);
    }

    // 今天额度使用多少
    $used = \MyClass\SteeData::usedReadDetail    ($uid, $type);
    $max  = \MyClass\SteeData::maxLimitReadDetail($uid, $type);
    if($max !== 'never' && $used >= $max){
      DJApi\API::debug(['额度已用完', 'used'=>$used, 'max'=>$max, 'uid'=>$uid, 'type'=>$type]);
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
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];
    $hash = $request->query['hash'];
    $json = \API_UseRecords\Data::select([
      'module' => 'cmoss',
      'and' => DJApi\API::cn_json([
        'v2' => $hash
      ])
    ]);

    $row = $json['datas']['rows'][0];
    $type = $row['k1'];
    $facid = $row['k2'];

    if($row['k1'] == 'steefac' && $row['v1'] == '用户推广'){
      $path = "/fac-detail/{$row['k2']}";
      \MyClass\SteeData::recordReadDetail($uid, $type, $facid, '推广查看');// 每次均记录，可用于推广效果分析
    }
    if($row['k1'] == 'steeproj' && $row['v1'] == '用户推广'){
      $path = "/project-detail/{$row['k2']}";
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
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];

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
    return \API_UseRecords\Data::json_record_if($data);
  }

  /**
   * stee_data/getActionDetail
   * 查询多个详情的各种操作
   * @query type: steefac/steeproj ，公司或项目
   * @query facid: id ，公司若项目的 id
   * @query scoreName: 积分名称[使用额度查看/再次查看/推广查看/点击电话/点击邮件/点击推送/用户推广]
   * @query timeFrom: 开始时间
   * @query timeTo: 结束时间
   *
   * @return DJApi\API::OK([
   *   list: [list]  // 列表
   * ])
   */
  public static function getActionDetail($request) {
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $listJson = \MyClass\SteeData::getActionDetail($request->query);
    return $listJson;
  }

  /**
   * stee_data/getActionList
   * 查询多个详情的操作列表
   * @query type: steefac/steeproj ，公司或项目
   * @query facid: id ，公司若项目的 id
   * @query scoreName: 积分名称[使用额度查看/再次查看/推广查看/点击电话/点击邮件/点击推送/用户推广]
   * @query timeFrom: 开始时间
   * @query timeTo: 结束时间
   *
   * @return DJApi\API::OK([
   *   list: [list]  // 列表
   * ])
   */
  public static function getActionList($request) {
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $listJson = \MyClass\SteeData::getActionList($request->query);
    return $listJson;
  }


  /**
   * stee_data/search
   * 产能搜索
   * @query type: steefac/steeproj ，公司或项目
   * @query facid: id ，公司或项目的 id, 可为数组
   * @query days: 近多少天的操作数量，默认15天
   *
   * @return list: 列表
   */
  public static function search($request) {
    $type = $request->query['type'];
    $db = DJApi\DB::db();

    $page = $request->query['page'] + 0;
    $count = $request->query['count'] + 0;
    $lat = $request->query['lat'];
    $lng = $request->query['lng'];
    $dist = $request->query['dist'];
    $search = $request->query['s'];
    $days = intval($request->query['days']);

    if($page < 1)$page = 1;
    if($days < 1)$days = 15;

    $tik=0;
    $andArray=[];

    //坐标范围搜索： 纬度 ,经度(*1e7), 距离(m)
    //1米 = 0.00001度 近似
    if($lat > 10E7 && $lat < 55e7
      && $lng > 70E7 && $lng < 140e7
      && $dist >= 0 && $dist < 999E3) {
        // 此条件下 假定其格式正确
      $lat1 = $lat - $dist * 100;
      $lng1 = $lng - $dist * 100;
      $lat2 = $lat + $dist * 100;
      $lng2 = $lng + $dist * 100;
      $posand = ['lngE7[>]'=>$lng1, 'lngE7[<]'=>$lng2, 'latE7[>]'=>$lat1, 'latE7[<]'=>$lat2 ];
      $tik++;
      $andArray["and#t$tik"]=$posand;
    }

    //搜索字符
    if(strlen($search)>0) {
      $k = preg_split("/[\s,;]+/", $search);
      $fields_search = \MyClass\SteeStatic::$fields_search[$type];

      $w_or = [];
      for($i=count($k); $i--;  ) {
        $or_list=[];
        for($j=count($fields_search); $j--; ) {
          $or_list[$fields_search[$j].'[~]']=$k[$i];
        }
        $w_or["or#".$i]=$or_list;
      }
      $tik++;
      $andArray["and#t$tik"]=$w_or;
    }

    //正常标记的才返回
    $tik++;
    $andArray["and#t$tik"] = ['or'=>['mark#1'=>null,'mark#2'=>'']];

    $where = [
      "LIMIT" => [$page*$count-$count, $count],
      "ORDER" => ["update_at" => "DESC", "id" => "DESC"]
    ] ;
    if(count($andArray))
      $where['and'] = $andArray ;
    \DJApi\API::debug(['where = ', $where]);

    // 读取数据库
    $dbRows = $db->select(\MyClass\SteeStatic::$table[$type], \MyClass\SteeStatic::$fields_preivew[$type], $where);
    if(!is_array($dbRows) || !count($dbRows)){
      \DJApi\API::debug(['查无数据', $db->getShow()]);
      return \DJApi\API::OK(['list'=>[]]);
    }
    // 改id为索引
    $dbRows = \DJApi\FN::array_column($dbRows, 'id', true);

    // 被查看等操作计数
    $listJson = \MyClass\SteeData::getActionDetail([
      'type' => $type,
      'timeFrom' =>  \DJApi\API::today( - $days )
    ]);
    $actionList = $listJson['datas']['rows'];
    if(is_array($actionList) && count($actionList) > 0){
      foreach($actionList as $row){
        $facid = $row['k2'];
        if($dbRows[$facid]){
          $dbRows[$facid]['action'][$row['v1']] = $row['n'];
        }
      }
    }

    // 返回
    return \DJApi\API::OK(['list' => array_values($dbRows)]);
  }


}
