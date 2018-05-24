<?php
/**
 * PHP 接口数据库层
 *
 */

namespace DJApi;
require_once( "lib/medoo/medoo.php");
require_once( "api-root.php");
use DJApi;

class DB extends DJApi\medoo {
  public static $_db;
  public static function db($options = []){
    if(!self::$_db) self::$_db = new DB($options);
    return self::$_db;
  }
  /**
   * 构造函数，保存参数
   */
  public function __construct($options = []) {
    $defaults = DJApi\Configs::get(['main_db', 'medoo']);
    $keys = [
      'database_type',
      'database_name',
      'server'       ,
      'port'         ,
      'username'     ,
      'password'     ,
      'charset'
    ];
    foreach($keys as $k){
      $optn[$k] = $options[$k]? $options[$k]: $defaults[$k];
    }
    //echo "mymedoo:";var_dump($optn);
    parent::__construct($optn);
  }
  static function table($name){
    $tableName = DJApi\Configs.get(["main_db", "tableName", $name]);
    return $tableName ? $tableName : $name;
  }
  //DEBUG
  public function show() {
    echo "database_name = " .($this->database_name)."<br>";
    var_dump($this->last_query());
    var_dump($this->error());
  }
  public function getShow() {
    return [
      "DB"         => $this->database_name,
      "last_query" => $this->last_query(),
      "error"      => $this->error()
    ];
  }
}

