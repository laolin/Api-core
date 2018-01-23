<?php
// ================================
/*
*/
use DJApi\DB;
use DJApi\API;
use DJApi\Request\Request;

class class_data{
  static $tableName = 'api_tbl_use_record';
  public static function count($request) {

    $fieldsAll = ['uid', 'time', 'k1', 'k2'];

    // 解析条件， 无效的不用
    $AND = [];
    foreach($request->query['and'] as $k => $v){
      $subk = explode('[', $k);
      if(in_array($subk[0], $fieldsAll)){
        $AND[$k] = $v;
      }
    }
    
    $db = DB::db();
    if($request->query['k1']){
      $fields = ['k1', 'count(n) as n'];
      $GROUP = 'k1';
      $AND['k1'] = $request->query['k1'];
    }
    $rows = $db->select(self::$tableName, $fields, ["AND"=>$AND, "GROUP"=>$GROUP]);

    return API::OK(['rows' => $rows, "DB"=>$db->getShow()]);
  }

}
