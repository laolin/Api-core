<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;

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
    $used = self::getTosendInfo($request->query);
    return DJApi\API::OK([
      'limit' =>[
        'steefac'  =>['limit' => 50, 'used' => $used['公司']],
        'steeproj' =>['limit' => 'never', 'used' => $used['项目']]
      ]
    ]);
  }
  private static function getTosendInfo($query) {
    $uid       = $query['uid'];
    $from_type = $query['from_type'];
    $from_id   = $query['from_id'];
    $to_type   = $query['to_type'];
    $to_ids    = $query['to_ids'];
    $json = \API_UseRecords\Data::count([
      'module' => 'cmoss',
      'and'=>['uid'=>$uid, "time[~]"=>DJApi\API::today() . "%"],
      'k2' => '发送推广消息',
      'k1' =>['公司', '项目']
    ]);
    return $json;
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
    $uid       = $query['uid'];
    $uid       = $query['uid'];
    $pageUri   = $query['page'];
    $from_type = $query['from_type'];
    $from_id   = $query['from_id'];
    $to_type   = $query['to_type'];
    $to_ids    = $query['to_ids'];

    // 消息额度限制
    if($from_type == 'steefac' && !in_array($uid, [
        301710, // 大照
        301171, // 况
        301168, // Authony
      ]
    )){
      $used = self::getTosendInfo($_REQUEST)['datas']['used'];
      $totleUsed = $used['公司'];
      if($totleUsed >= 50){
        return DJApi\API::error(DJApi\API::E_NEED_RIGHT, '额度已用完');
      }
    }
    $db = DJApi\DB::db();
    // 获取 推送内容基本信息
    $fields = [
      'steefac' => ['name', 'goodat(text)'],
      'steeproj' => ['name', 'need_steel(text)']
    ];
    if(!$fields[$from_type]){
      return DJApi\API::error(DJApi\API::E_PARAM_ERROR, '参数错误');
    }
    $info = $db->get(self::$table[$from_type], $fields[$from_type], ['id'=>$from_id]);
    $t0 = DJApi\API::today( 0 );
    $hash = md5($t0 . $uid . $from_id);

    $info['url'] = "$pageUri#!/hash-page/$hash";
    if($from_type == 'steefac'){
      $info['text'] = $info['text'] ? ("擅长：{$info['text']}") : '';
    }
    if($from_type == 'steeproj'){
      $info['text'] = $info['text'] ? ("采购量：{$info['text']}") : '';
    }

    // 获取 uid 列表
    $uidGroup = \API_UserBind\Steeobj::adminidgroups([
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
    if(!$uid || !in_array($uid, $uidGroup['from'])){
      return DJApi\API::error(DJApi\API::E_NEED_RIGHT, '不是管理员', ['uid'=>$uid, 'uidGroup'=>$uidGroup, 'query'=>[
        'from_type' => $from_type,
        'from_id'   => $from_id,
        'to_type'   => $to_type,
        'to_ids'    => $to_ids
      ]]);
    }

    // 获取 openid 列表
    $wxDefault = DJApi\Configs::get("WX_APPID_APPSEC_DEFAULT");
    $apps  = $wxDefault['WX_APPSEC'];
    $appid = $wxDefault['WX_APPID'];
    $openidGroupJson = \MyClass\CWxBase::openidgroups([
      'appid' => $appid,
      'uid'=> $uidGroup
    ]);
    \DJApi\API::debug(['获取 openid 列表', $openidGroupJson]);
    $openidGroup = $openidGroupJson['datas']['R'];

    // 测试, 只发给自己：
    if(0){
      $openidGroup['to'] = [
        'od6xzv0DW3ZEmJ1eC0t60w5Eqa8M', // 我
        //'od6xzvxb4M6WVdRYjLO4b9k1nHXo', // 老林
        //'od6xzv1_nHUey1cg-_zfXtiLde9w', // 大照
        'od6xzv0D-----------60w5Eqa8M', // 充当数量
        'od6xzv0D-----------60w5Eqa8M', // 充当数量
        'od6xzv0D-----------60w5Eqa8M', // 充当数量
        'od6xzv0D-----------60w5Eqa8M', // 充当数量
        'od6xzv0D-----------60w5Eqa8M', // 充当数量
      ];
    }

    // 请求发送
    $first = [
      'steefac' => '依据您的项目特点，CMOSS推荐：',
      'steeproj' => '依据贵司产能特点，CMOSS推荐：'
    ];
    $remark = [
      'steefac' => '依据为距离、价格、剩余产能…',
      'steeproj' => '依据为距离、付款、擅长构件…'
    ];
    $jsonSended = \MyClass\CWxBase::send_tpl([
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
    \DJApi\API::debug(['请求发送', $jsonSended]);

    // 保存发送结果
    if(DJApi\API::isOK($jsonSended)) {
      $jsonRecord = \API_UseRecords\Data::record([
        'module' => 'cmoss',
        'uid'    => $uid,
        'k1'     => $from_type,
        'k2'     => '用户推广',
        'v1'     => $from_id,
        'v2'     => $hash,
        'n'      => $jsonSended['datas']['sended'],
        'json'   => [
          'from_type' => $from_type,
          'from_id'   => $from_id,
          'to_type'   => $to_type,
          'to_ids'    => $to_ids
        ]
      ]);
    }

    return DJApi\API::OK(['r' => '请求成功，服务器正在为您处理', 'test'=>[
      'DB' => $db->getShow(),
      'jsonSended' => $jsonSended,
      'uidGroup' => $uidGroup,
      'openidGroupJson' => $openidGroupJson,
      'jsonRecord' => $jsonRecord
    ]]);
  }

}
