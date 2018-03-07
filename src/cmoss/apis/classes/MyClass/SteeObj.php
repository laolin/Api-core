<?php
// ================================
/*
*/
namespace MyClass;
use DJApi;

class SteeObj extends SteeStatic{

  static $fieldOfObj = [
    'steefac' => [
      'id',
      'mark',
      "update_at",
      "level",
      "name",
      "addr",
      "latE7",
      "lngE7",
      "cap_y",
      "cap_6m",
      "goodat",
      "area_factory"
    ],
    'steeproj' => [
      'id',
      'mark',
      "update_at",
      "name",
      "addr",
      "latE7",
      "lngE7",
      'steel_shape',
      'steel_Qxxx',
      "in_month",
      "need_steel"
    ]
  ];

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
  static function listAdminObj($userid, $type) {
    $user = new \MyClass\SteeUser($userid);
    return self::listObj($user->adminObjIds($type), $type);
  }

}
