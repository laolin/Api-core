<?php
/**
 * 使用记录模块
 * 1. 记录各模块、分类情况下，各项功能的使用数量及基本信息
 * 2. 提供使用情况查询
 */
namespace API_UseRecords;
use DJApi;

class Data{
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

    $json = DJApi\API::cn_json($request->safeQuery('json', []));

    $id = $db->insert(self::$tableName, $aa = [
      'time'   => DJApi\API::now(),
      'module' => $request->safeQuery('module', '无模块'),
      'uid'    => $request->safeQuery('uid'   , 0),
      'k1'     => $request->safeQuery('k1'    ),
      'k2'     => $request->safeQuery('k2'    ),
      'v1'     => $request->safeQuery('v1'    ),
      'v2'     => $request->safeQuery('v2'    ),
      'n'      => $request->safeQuery('n'     , 0),
      'json'   => $json
    ]);

    return DJApi\API::OK(['n' => $id? 1: 0, 'id'=>$id, 'query'=>$request->query]);
  }
  /**
   * 接收一条json数据，解析后，予以记录
   * api地址: data/json_record
   *
   * @query param 要记录的参数，允许的参数：['uid', 'module', 'k1', 'k2', 'v1', 'v2', 'n', 'json']
   */
  public static function json_record($request) {
    $db = DJApi\DB::db();

    $param = json_decode($request->safeQuery('param', ''), true);
    if(!is_array($param) || count($param) == 0){
      return DJApi\API::error(DJApi\API::E_PARAM_ERROR, '参数错误', [$param, $request]);
    }
    $data = [
      'time'   => DJApi\API::now(),
      'module' => '无模块',
      'json'   => ''
    ];
    $fields = ['module', 'uid', 'k1', 'k2', 'v1', 'v2', 'n', 'json'];
    foreach($param as $k=>$v){
      if(in_array($k, $fields)){
        $data[$k] = $v;
      }
    }
    $id = $db->insert(self::$tableName, $data);
    DJApi\API::debug([$db->getShow()], "DB");
    return DJApi\API::OK(['id'=>$id]);
  }
  /**
   * 接收一条json数据，解析后，如果不存在满足条件的记录，予以记录
   * api地址: data/json_record
   *
   * @query param 要记录的参数，允许的参数：['uid', 'module', 'k1', 'k2', 'v1', 'v2', 'n', 'json']
   */
  public static function json_record_if($request) {
    $db = DJApi\DB::db();
    /* 原已有的，不再添加 */
    $if = json_decode($request->safeQuery('if', ''), true);
    if(!is_array($if) || count($if) == 0){
      $if = false;
    }
    if($if){
      if($db->has(self::$tableName, ["AND" => $if])){
        return DJApi\API::OK("already");
      }
    }
    /** 原没有的，就添加 */
    $param = json_decode($request->safeQuery('param', ''), true);
    if(!is_array($param) || count($param) == 0){
      return DJApi\API::error(DJApi\API::E_PARAM_ERROR, '参数错误', [$param, $request]);
    }
    $data = [
      'time'   => DJApi\API::now(),
      'module' => '无模块',
      'json'   => ''
    ];
    $fields = ['module', 'uid', 'k1', 'k2', 'v1', 'v2', 'n', 'json'];
    foreach($param as $k=>$v){
      if(in_array($k, $fields)){
        $data[$k] = $v;
      }
    }
    $id = $db->insert(self::$tableName, $data);
    DJApi\API::debug([$db->getShow()], "DB");
    return DJApi\API::OK(['id'=>$id, $db->getShow(), $if]);
  }


  /**
   * 内部函数
   * 解析 and 参数
   * @return $AND
   */
  protected static function parseAnd($module, $and) {
    $fieldsAll = ['uid', 'time', 'k1', 'k2', 'v1', 'v2'];
    if(is_string($and)){
      $and = json_decode($and, true);
    }
    // 解析条件， 无效的不用
    $AND = [];
    foreach($and as $k => $v){
      $subk = explode('[', $k);
      if(in_array($subk[0], $fieldsAll)){
        // PHP-BUG 在 curl 中当键名含有]时，
        if(count($subk) > 1 && substr($k, -1) != ']') $k = $k . ']';
        $AND[$k] = $v;
      }
    }
    $AND['module'] = $module;
    return $AND;
  }


  /**
   * 记录使用数量查询
   * api地址: data/select
   *
   * @query module 模块名称
   * @query field 用户id
   * @query and 条件
   * @query group 分组
   * @query order 排序
   *
   * @return 数组
   */
  public static function select($request) {
    $module = $request->query['module'];
    $field  = $request->query['field' ];
    $and    = $request->query['and'   ];
    $group  = $request->query['group' ];
    $order  = $request->query['order' ];
    if(!$module){
      return DJApi\API::error(1002, '未指定模块');
    }

    $db = DJApi\DB::db();
    $where = [
      "AND" => self::parseAnd($module, $and)
    ];
    if($group) $where['GROUP'] = $group;
    if($order) $where['ORDER'] = $order;
    if(!$field) $field = '*';
    $rows = $db->select(self::$tableName, $field, $where);
    return DJApi\API::OK(['rows' => $rows, "query"=>$request->query, "where"=>$where, "DB"=>$db->getShow()]);
  }



  /**
   * 记录使用数量查询
   * api地址: data/count
   *
   * @query module 模块名称
   * @query uid 用户id
   * @query k1 主分类
   * @query k2 次分类(可选)
   * @query v1 主值(可选)
   * @query v2 次值(可选)
   * @query n 数量
   */
  public static function count($request) {

    $fieldsAll = ['uid', 'time', 'k1', 'k2', 'v1', 'v2'];

    $and = $request->query['and'];
    if(is_string($and)){
      $and = json_decode($and, true);
    }
    // 解析条件， 无效的不用
    $AND = [];
    foreach($and as $k => $v){
      $subk = explode('[', $k);
      if(in_array($subk[0], $fieldsAll)){
        // PHP-BUG 在 curl 中当键名含有]时，
        if(count($subk) > 1 && substr($k, -1) != ']') $k = $k . ']';
        $AND[$k] = $v;
      }
    }

    $db = DJApi\DB::db();
    if($request->query['k1']){
      $fields = ['k1', 'sum(n) as n'];
      $GROUP = 'k1';
      $AND['k1'] = $request->query['k1'];
    }
    $rows = $db->select(self::$tableName, $fields, ["AND"=>$AND, "GROUP"=>$GROUP]);
    $used = [];
    foreach($rows as $row){
      $used[$row['k1']] = $row['n'] + 0;
    }

    return DJApi\API::OK(['used' => $used, "tableName" => self::$tableName, "query"=>$request->query, "AND"=>$AND, "DB"=>$db->getShow()]);
  }

}
