<?php
namespace MyClass;

class CDbBase {

  // 用到的表名
  static $table = [
    'user' => 'store_user', // 仓库系统的用户信息
     'assert_index' => 'store_assert_index', // 资产索引
     'place' => 'store_place', // 仓库
     'action' => 'store_action', // 操作记录
     'oauth' => 'store_oauth', // 操作授权
     'assert_io' => 'store_assert_io', // 资产进出库流水
     'assert_place' => 'store_assert_place', // 资产数据
     'assert_data' => 'store_assert_data', // 资产数据
     'user_right' => 'store_user_right', // 权限
     'dick' => 'store_dick', // 字典
  ];

  static $db = [];
  public static function db($configName = 'main_db') {
    if (!self::$db[$configName]) {
      $db_config = \DJApi\Configs::get($configName);
      $dbParams = $db_config['medoo'];
      self::$db[$configName] = new \DJApi\DB($dbParams);
    }
    return self::$db[$configName];
  }
  public static function table($baseTableName = 'user', $configName = 'main_db') {
    $db_config = \DJApi\Configs::get($configName);
    $tableNames = $db_config['tableName'];
    if (!$tableNames) {
      $tableNames = self::$table;
    }

    $tableName = $tableNames[$baseTableName];
    if (!$tableName) {
      return $baseTableName;
    }

    return $tableName;
  }

}
