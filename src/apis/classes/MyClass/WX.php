<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class WX extends SteeStatic{

  /** 获取微信信息
   * @param userid: 用户id，可为数组或单个用户id
   * 返回：
   * @return 微信信息数组
   */
  static function getWxInfo($userid) {
    return DJApi\DB::db()->select(
      self::$table['user'], ["[>]" .self::$table['wx']=>['uid' => 'uidBinded']],
      ['headimgurl', 'uidBinded', 'nickname', self::$table['wx'].'.id'],
      [
        "AND" =>[
          'appFrom' => DJApi\Configs::get(["WX_APPID_APPSEC_DEFAULT", "WX_APPID"]),
          "uidBinded" => $userid,
        ],
        "LIMIT" => 1000
      ]
    );
  }
}
