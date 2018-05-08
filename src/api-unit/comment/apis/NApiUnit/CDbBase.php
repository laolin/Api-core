<?php
namespace NApiUnit;

/**
 * 基础类
 */
class CDbBase {
  /** 这些表，laolin设计时，已命名。若要重命名，在配置中对应改之 */
  static $table = [
  ];
  static $db = [];
  static function db($configName = 'main_db'){
    if(!self::$db[$configName]){
      $db_config = \DJApi\Configs::get($configName);
      $dbParams = $db_config['medoo'];
      self::$db[$configName] = new \DJApi\DB($dbParams);
    }
    return self::$db[$configName];
  }
  static function table($baseTableName, $configName = 'main_db'){
    $db_config = \DJApi\Configs::get($configName);
    $tableNames = $db_config['tableName'];
    if(!$tableNames) $tableNames = self::$table;
    $tableName = $tableNames[$baseTableName];
    if(!$tableName) return $baseTableName;
    return $tableName;
  }

}

