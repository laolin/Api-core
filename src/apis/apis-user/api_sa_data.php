<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;


class class_sa_data {
  static $table = [
    "wx" => 'api_tbl_user_wx',
    "user" => 'api_tbl_user',
  ];


  /**
   * 获取用户信息
   * @request userid: 被查看用户的 id
   * 返回：
   * @return API::OK([limit])
   */
  public static function getUserInfo($request) {
    $uid = $request->query['uid'];
    $userid = $request->query['userid'];

    DJApi\API::debug(__FILE__, 'FILE');

    $wxinfo = \MyClass\WX::getWxInfo($userid)[0];
    // 返回
    return DJApi\API::OK([
      "wxinfo" => \MyClass\WX::getWxInfo($userid)[0],
      "activity" => \MyClass\SteeLog::countActivity($userid, [0.083, 1, 30]),
      "objAdmin" =>[
        "steefac" => \MyClass\SteeObj::listAdminObj($userid, 'steefac'),
        "steeproj" => \MyClass\SteeObj::listAdminObj($userid, 'steeproj')
      ]
    ]);
  }


}
