<?php
// ================================
/*
*/
require_once dirname(dirname(__FILE__)) . "/dj-api-shell/api-all.php";

class class_stee_msg{
  static $table = [
    steefac => 'api_tbl_steelfactory',
    steeproj => 'api_tbl_steelproject',
  ];
  /**
   * 发送前预请求
   * @request from_type
   * @request from_id
   * @request to_type
   * @request to_ids
   * 返回：
   * @return API::OK([here, rest])
   * @return here: 当前将发送几条
   * @return rest: 你的剩余额度有几条
   */
  public static function presend($request) {
    return self::getTosendInfo($_REQUEST);
  }
  private static function getTosendInfo($query) {
    $json = DJApi\API::post(LOCAL_API_ROOT, "use-records/data/count", [
      'module' => 'cmoss',
      'and'=>['uid'=>$uid, 'time[~]'=>"%$today"],
      'k1'=>['公司推广', '项目推广']
    ]);
  }

  /**
   * 请求发送
   * @request from_type
   * @request from_id
   * @request to_type
   * @request to_ids
   * 返回：
   * @return API::OK([accept, rest])
   * @return accept: 已受理，将发送几条
   * @return rest: 你的剩余额度有几条
   */
  public static function send($request) {
    $query = $_REQUEST;
    $from_type = $query['from_type'];
    $from_id   = $query['from_id'];
    $to_type   = $query['to_type'];
    $to_ids    = $query['to_ids'];
    $db = DJApi\DB::db();
    // 获取 推送者 使用量(openid, name, )
    $fields = [
      'steefac' => ['name', 'goodat(text)'],
      'steeproj' => ['name', 'need_steel(text)']
    ];
    if(!$fields[$from_type]){
      return DJApi\API::error(DJApi\API::E_PARAM_ERROR, '参数错误');
    }
    $info = $db->get(self::$table[$from_type], $fields[$from_type], ['id'=>$from_id]);
    if($from_type == 'steefac'){
      $info['text'] = $info['text'] ? ("擅长：{$info['text']}") : '没有数据，这里填什么？';
      $info['url'] = "https://qinggaoshou.com/cmoss.html#!/fac-detail/$from_id";
    }
    if($from_type == 'steeproj'){
      $info['text'] = $info['text'] ? ("采购量：{$info['text']}") : "没有数据，这里填什么？";
      $info['url'] = "https://qinggaoshou.com/cmoss.html#!/project-detail/$from_id";
    }

    // 获取 uid 列表
    $uidGroup = DJApi\API::post(LOCAL_API_ROOT, "user-bind/steeobj/adminidgroups", [
      'group'=> [
        "to" => [
          'type' => $to_type,
          'objid' => $to_ids
        ],
        "from" => [
          'type' => $from_type,
          'objid' => $from_id,
        ]
      ]
    ])['datas']['R'];

    // 获取 openid 列表
    $apps  = api_g('WX_APPS');
    $appid = $apps['main'][0];
    $openidGroup = DJApi\API::post(LOCAL_API_ROOT, "user-bind/wx/openidgroups", [
      'appid' => $appid,
      'uid'=> $uidGroup
    ])['datas']['R'];

    // 测试, 只发给自己：
    $openidGroup['to'] = [
      'od6xzv0DW3ZEmJ1eC0t60w5Eqa8M', // 我
      'od6xzvxb4M6WVdRYjLO4b9k1nHXo', // 老林
      'od6xzv1_nHUey1cg-_zfXtiLde9w'  // 大照
    ];

    // 请求发送
    $first = [
      'steefac' => '依据您的项目特点，CMOSS推荐：',
      'steeproj' => '依据贵司产能特点，CMOSS推荐：'
    ];
    $remark = [
      'steefac' => '依据为距离、价格、剩余产能…',
      'steeproj' => '依据为距离、付款、擅长构件…'
    ];
    $jsonSended = DJApi\API::post(LOCAL_API_ROOT, "local-wx/msg/send_tpl", [
      'name' => '请高手',
      'openids' => $openidGroup['to'],
      'url' => $info['url'],
      'tplName' => 'cmoss-资料发送提醒',
      'data' =>[
        $first[$from_type],
        [
          $info['name'],
          $info['text']
        ],
        $remark[$from_type]
      ]
    ]);

    // 保存发送结果
    if(DJApi\API::isOK($jsonSended)) {
      $jsonRecord = DJApi\API::post(LOCAL_API_ROOT, "use-records/data/record", [
        'module' => 'cmoss',
        'uid'    => $uidGroup['from'][0],
        'k1'     => $from_type=='steefac' ? '公司推广': '项目推广',
        'n'      => $jsonSended['datas']['sended']
      ]);
    }

    return DJApi\API::OK(['r' => '请求成功，服务器正在为您处理', 'test'=>[
      'DB' => $db->getShow(),
      'jsonSended' => $jsonSended,
      'uidGroup' => $uidGroup,
      'openidGroup' => $openidGroup,
      'jsonRecord' => $jsonRecord
    ]]);
  }

}
