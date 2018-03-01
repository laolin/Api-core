<?php
namespace NApiUnit;

/**
 * 基础类
 */
class CDbBase {
  static $table = [
    'user'       => 'z_user',
    'wx_user'    => 'z_wx_user',
    'bind'       => 'z_user_bind',
    'token'      => 'z_user_token',
    'g_settings' => 'g_settings',
  ];
  static $db;
  static function db($configName = 'user_login_db'){
    if(!self::$db){
      $db_config = \DJApi\Configs::get($configName);
      $dbParams = $db_config['medoo'];
      self::$db = new \DJApi\DB($dbParams);
    }
    return self::$db;
  }
  static function table($baseTableName = 'user', $configName = 'user_login_db'){
    $db_config = \DJApi\Configs::get($configName);
    $tableNames = $db_config['tableName'];
    if(!$tableNames) $tableNames = self::$table;
    $tableName = $tableNames[$baseTableName];
    if(!$tableName) return $baseTableName;
    return $tableName;
  }

}

