<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class WX extends SteeStatic{

  /** 获取微信信息
   * @param uid: 用户id，可为数组或单个用户id
   * 返回：
   * @return 微信信息数组
   */
  static function getWxInfo($uid) {

    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid' => $uid]);
    \DJApi\API::debug(['从独立服务器获取微信信息', $wxInfoJson]);
    if(!\DJApi\API::isOk($wxInfoJson)){
      return $wxInfoJson;
    }
    $wxInfo = $wxInfoJson['datas']['list'];

    // 两者合并
    $list = [];
    foreach($wxInfo as $k=>$row){
      $list[] = [
        'headimgurl'=>$row['headimgurl'],
        'nickname'  =>$row['nickname'  ],
        'uidBinded' =>$row['uid']
      ];
    }
    return $list;
  }
}
