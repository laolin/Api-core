<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class CUser extends SteeStatic{
  static $field = [
    'stee_user' => ['id','uid','name','is_admin','update_at','fac_can_admin','steefac_can_admin','steeproj_can_admin','rights','score']
  ];
  public function __construct($uid){
    $db = DJApi\DB::db();
    $this->data = $db->get(
      self::$table['stee_user'],
      self::$field['stee_user'],
      ['and'=>['uid'=>$uid ,'mark'=>'']]
    );
  }

  /** 自己管理的产能/项目
   * @param type: steefac/steeproj
   * @return 数组 [id1, id2 ...]
   */
  public function adminObjIds($type){
    $str = $this->data[$type . '_can_admin'];
    return explode(',', $str);
  }

  /** 已登录的用户 */
  static $me;
  public static function me(){
    if(!self::$me){
      $loginRight = self::checkSign($_REQUEST);
      if($loginRight){
        self::$me = new \MyClass\CUser($_REQUEST['uid']);
      }
    }
    return self::$me;
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function checkSign($query){
    $uid       = $query['uid'          ];
    $tokenid   = $query['tokenid'      ];
    $timestamp = $query['timestamp'    ];
    $sign      = $query['api_signature'];
    $api       = $query['api'];
    $call      = $query['call'];
    // 所有参数不能为空
    if( !$uid || !$tokenid || !$timestamp || !$sign )return false;
    // 仅允许5分钟以内的时间差
    if( abs($timestamp - time()) > 300)return false;
    // 获取真正的token
    $token = self::getToken($uid, $tokenid);
    if(!$token)return false;
    // 计算正确的签名
    $signRight = md5("$api$call$uid$token$timestamp");
    // 前端给的签名是否正确
    return $signRight == $sign;
  }

  /** 获取真正的用户登录票据 */
  public static function getToken($uid, $tokenid) {
    $db = DJApi\DB::db();
    $token = $db->get(self::$table['token'], 'token', ['and'=>['uid'=>$uid, 'tokenid'=>$tokenid]]);
    return $token;
  }
}
class SteeUser extends CUser{

  
  /** 获取指定id产能/项目[列表]
   * @param ids: 产能/项目的id，或其数组
   * @param type: steefac/steeproj ，产能/项目
   * 返回：
   * @return 数组
   */
  static function listObj($ids, $type) {
    $db = DJApi\DB::db();
    $list = $db->select(self::$table[$type], self::$fieldOfObj[$type],
      ['and' => [
        'id' => $ids,
        'or' => ['mark'=>null, 'mark#'=>'']
      ]]
    );
    DJApi\API::debug($db->getShow(), "DB-$type");
    return $list;
  }
  
  /** 获取用户管理的产能/项目列表
   * @param user: 用户，一个数组，包含[]字段
   * @param type: steefac/steeproj ，公司或项目
   * 返回：
   * @return 数组
   */
  static function listAdminObj($user, $type) {
    $db = DJApi\DB::db();
    $list = $db->select(self::$table[$type], self::$fieldOfObj[$type],
      ['and' => [
        'id' => $userid,
        'or' => ['mark'=>null, 'mark#'=>'']
      ]]
    );
    DJApi\API::debug($db->getShow(), "DB-$type");
    return $list;
  }

}
