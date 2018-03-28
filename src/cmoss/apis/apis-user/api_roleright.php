<?php
// ================================
/*
 */
namespace RequestByApiShell;

class class_roleright
{
  static $table = [
    "user" => 'api_tbl_stee_user',
    "user_right" => 'api_tbl_user_right',
  ];
  /**
   * 统一入口，要求登录
   */
  public static function API($call, $request)
  {
    if (!method_exists(__CLASS__, $call)) {
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$call]);
    }
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uidLogin = $verify['datas']['uid'];
    // 检查权限
    if (!\MyClass\CRoleright::hasRight($uidLogin, '用户管理')) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '无权限', [$call]);
    }
    return self::$call($request, $uidLogin);
  }

  /**
   * 接口： roleright/get_user
   * 读取一个用户的个人信息和权限
   *
   * @return {user}
   */
  public static function get_user($request, $uidLogin)
  {
    // 检查权限
    if (!\MyClass\CRoleright::hasRight($uidLogin, '权限管理')) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '无权限', ['权限管理']);
    }
    $userid = $request->query['userid'];
    $db = \DJApi\DB::db();
    $user = $db->get(self::$table['user'], '*', ['uid' => $userid]);
    \DJApi\API::debug($db->getShow());
    if (!is_array($user)) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '用户不存在');
    }

    // 读取权限
    $rights = \MyClass\CRoleright::getUserRightArray($userid);
    $user['rights'] = $rights;
    $user['attr'] = json_decode($user['attr'], true);
    // 返回
    return \DJApi\API::OK(['user' => $user]);
  }

  /**
   * 接口： roleright/save_user
   * 保存一个用户的权限
   *
   * @return {user}
   */
  public static function save_user($request, $uidLogin)
  {
    // 检查权限
    if (!\MyClass\CRoleright::hasRight($uidLogin, '权限管理')) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '无权限', ['权限管理']);
    }
    $userid = $request->query['userid'];
    $rights = $request->query['rights'];

    $db = \DJApi\DB::db();

    $user = $db->get(self::$table['user'], '*', ['uid' => $userid]);
    \DJApi\API::debug($db->getShow());
    if (!is_array($user)) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '用户不存在');
    }

    // 读取现有权限，包括过时的
    $list = $db->select(\MyClass\SteeStatic::$table['user_right'], '*', ['AND' => ['uid' => $userid, 'param1' => '系统授权']]);

    \DJApi\API::debug(['读取现有权限 list' => $list, 'DB' => $db->getShow()]);
    // 修改权限
    $now = \DJApi\API::now();
    $saved = [];
    foreach ($list as $row) {
      // 标志一下已处理
      $saved[$row['name']] = 1;
      // 需要移除的
      if (!in_array($row['name'], $rights)) {
        $db->update(self::$table['user_right'], ['t2' => $now], ['id' => $row['id']]);
        \DJApi\API::debug(['需要移除的 row' => $row, 'DB' => $db->getShow()]);
      }
      // 需要恢复的
      else if ($row['t2']) {
        $db->update(self::$table['user_right'], ['t2' => ''], ['id' => $row['id']]);
        \DJApi\API::debug(['需要恢复的 row' => $row, 'DB' => $db->getShow()]);
      }
    }
    // 未处理的，要添加
    foreach ($rights as $name) {
      if (!$saved[$name]) {
        $db->insert(self::$table['user_right'], [
          'uid' => $userid,
          'name' => $name,
          'param1' => '系统授权',
          't1' => $now,
          't2' => '',
        ]);
        \DJApi\API::debug(['未处理的，要添加 name' => $name, 'DB' => $db->getShow()]);
      }
    }
    // 返回
    return \DJApi\API::OK([]);
  }
}
