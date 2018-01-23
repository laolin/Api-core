<?php
// ================================
/*
*/
require_once dirname(dirname(__FILE__)) . "/dj-api-shell/api-all.php";

class class_stee_msg{
  static $table = [
    stee_msg => 'api_tbl_stee_msg',
    send_msg => 'api_tbl_send_msg',
  ];
  public static function send($request) {
    $query = $_REQUEST;
    $from_type = $query['from_type'];
    $from_id   = $query['from_id'];
    $to_type   = $query['to_type'];
    $to_ids    = $query['to_ids'];
    $db = DB::db();
    // 获取 推送者 使用量(openid, name, )

    // 获取 推送者 详情(openid, name, )

    // 获取 接收者 详情(openid[])

    // 请求发送
    $json = DJApi\API::post(LOCAL_API_ROOT, "send-msg/send/wxtpl", [
      'sender_openid' => $from_id,
      'to_openids' => $to_ids,
      'tpl' =>[
        'name' => '资料发送提醒',
        'data' => [
          'title' => '尊敬的管理员，有新项目啦，快来围观',
          'body'  => [
            '公司名',
            '这个公司的简介'
          ],
          'remark' =>'洽谈机会，不妨一试！'
        ]
      ]
    ]);


    return API::OK(['r' => '请求成功，服务器正在为您处理', 'test'=>[
      $from_type,
      $from_id  ,
      $to_type  ,
      $to_ids   ,
    ]]);
  }

}
