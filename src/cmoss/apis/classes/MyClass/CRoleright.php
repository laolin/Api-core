<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class CRoleright{

  /**
   * 用户是否有权限
   */
  public static function hasRight($uid, $name)
  {
    return true;

    $db = DJApi\DB::db();
    return $db->has(SteeStatic::$table['user_right'], ['AND' => [
      'uid' => $uid,
      'name' => $name,
      't1[>]' => '2000-01',
      'OR' => [
        't2' => '',
        't2[>]' => \DJApi\API::now(),
      ],
    ]]);
  }

  /**
   * 读取用户等个人信息
   *
   * @return {me}
   */
  public static function info($uid)
  {
    $db = DJApi\DB::db();
    $user = $db->get(SteeStatic::$table['user'], '*', ['uid' => $uid]);
    $attr = json_decode($user['attr'], true);
    \DJApi\API::debug(['用户个人信息', "DB"=>$db->getShow()]);

    /** 用户权限 */
    $rights = self::getUserRightArray($uid);

    $wxJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", [
      'uid' => $uid,
      'timestamp' => $timestamp,
      'sign' => $sign,
    ]);
    \DJApi\API::debug(['微信', $wxJson]);
    $wx = $wxJson['datas']['list'][0];
    return \DJApi\API::OK([
      'me' => [
        'rights' => $rights,
        'attr' => $attr,
        'uid' => $uid,
        'wx' => $wx,
      ],
    ]);
  }

  /**
   * 保存用户个人信息
   */
  public static function save_info($uid, $attr)
  {
    $db = DJApi\DB::db();

    $user = $db->get(SteeStatic::$table['user'], '*', ['uid' => $uid]);

    if (is_array($user)) {
      if(!$user['attr']) $user['attr'] = '{}';
      $attrOld = json_decode($user['attr'], true);
      if (!is_array($attrOld)) $attrOld = [];
      $attr = array_merge($attrOld, $attr);
      $db->update(SteeStatic::$table['user'], ['attr' => \DJApi\API::cn_json($attr)], ['uid' => $uid]);
    } else {
      $db->insert(SteeStatic::$table['user'], [
        'uid' => $uid,
        't1' => '2000-01',
        'attr' => \DJApi\API::cn_json($attr),
      ]);
    }
    \DJApi\API::debug(['保存用户个人信息', "DB"=>$db->getShow()]);
    return \DJApi\API::OK([]);
  }

  /**
   * 读取所有用户权限(数组)
   */
  public static function getUserRightArray($uid)
  {
    $db = DJApi\DB::db();
    $list = $db->select(SteeStatic::$table['user_right'], 'name', ['AND' => [
      'uid' => $uid,
      't1[>]' => '2000-01',
      'OR' => [
        't2' => '',
        't2[>]' => \DJApi\API::now()
      ],
    ]]);
    \DJApi\API::debug(['读取所有用户权限', "DB"=>$db->getShow()]);
    return $list;
  }

  /**
   * 读取所有用户权限
   */
  public static function getUserRight($uid)
  {
    $list = self::getUserRightArray($uid);

    if (!is_array($list) || !count($list)) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '没有任何权限');
    }

    return \DJApi\API::OK(['list' => $list]);
  }



}
