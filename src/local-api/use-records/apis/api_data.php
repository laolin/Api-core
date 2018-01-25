<?php
/**
 * 使用记录模块
 * 1. 记录各模块、分类情况下，各项功能的使用数量及基本信息
 * 2. 提供使用情况查询
 */
namespace DJApi\UseRecords;
use DJApi;

class class_data{
  static $tableName = 'api_tbl_use_record';


  /**
   * 将使用情况予以记录
   * api地址: data/record
   *
   * @query module 模块名称
   * @query uid 用户id
   * @query k1 主分类
   * @query k2 次分类(可选)
   * @query v1 主值(可选)
   * @query v2 次值(可选)
   * @query n 数量
   */
  public static function record($request) {
    $db = DJApi\DB::db();

    $id = $db->insert(self::$tableName, $aa = [
      'time'   => DJApi\API::now(),
      'module' => $request->safeQuery('module', '无模块'),
      'uid'    => $request->safeQuery('uid'   , 0),
      'k1'     => $request->safeQuery('k1'    ),
      'k2'     => $request->safeQuery('k2'    ),
      'v1'     => $request->safeQuery('v1'    ),
      'v2'     => $request->safeQuery('v2'    ),
      'n'      => $request->safeQuery('n'     , 0)
    ]);

    return DJApi\API::OK(['n' => $id? 1: 0, 'aa'=>$aa, 'query'=>$request->query]);
  }

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
