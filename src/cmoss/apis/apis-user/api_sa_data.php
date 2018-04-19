<?php
// ================================
/*
*/
namespace RequestByApiShell;
use DJApi;


class class_sa_data extends \MyClass\SteeStatic {
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
    // if (!\MyClass\CRoleright::hasRight($uidLogin, '用户管理')) {
    //   return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '无权限', [$call]);
    // }
    return self::$call($request, $uidLogin);
  }

  /**
   * 接口 sa_data/close_fac
   * 关闭/打开项目
   * @request type:
   * @request facid:
   * @request close: 'close':关闭, 'open':恢复
   * 返回：
   * @return API::OK([limit])
   */
  public static function close_fac($request, $uid) {
    $type = $request->query['type'];
    $facid = $request->query['facid'];
    $close = $request->query['close'];

    if($type !== 'steeproj') return \DJApi\API::error(DJApi\API::E_PARAM_ERROR, '类型不正确');
    if(!in_array($close, ['close','open'])) return \DJApi\API::error(DJApi\API::E_PARAM_ERROR, '参数不正确');

    $time = $close == 'open' ? '' : \DJApi\API::now();

    $db = DJApi\DB::db();
    $n = $db->update(\MyClass\SteeStatic::$table[$type], ['close_time'=>$time], ['id'=>$facid]);
    if($n) return \DJApi\API::OK(1);
    return \DJApi\API::error(DJApi\API::E_PARAM_ERROR, '修改失败');
  }

  /**
   * 获取用户信息
   * @request userid: 被查看用户的 id
   * 返回：
   * @return API::OK([limit])
   */
  public static function getUserInfo($request, $uid) {
    $userid = $request->query['userid'];

    DJApi\API::debug(__FILE__, 'FILE');

    $wxinfo = \MyClass\CWxBase::getWxInfo($userid)[0];
    // 返回
    return DJApi\API::OK([
      "wxinfo" => \MyClass\CWxBase::getWxInfo($userid)[0],
      "activity" => \MyClass\SteeLog::countActivity($userid, [0.083, 1, 30]),
      "objAdmin" =>[
        "steefac" => \MyClass\SteeObj::listAdminObj($userid, 'steefac'),
        "steeproj" => \MyClass\SteeObj::listAdminObj($userid, 'steeproj')
      ]
    ]);
  }

  /** 获取微信信息
   * @request userid: 用户id，可为数组或单个用户id
   * 返回：
   * @return 微信信息数组
   */
  static function getWxInfo($request, $uid) {
    $R = \MyClass\CWxBase::getWxInfo($request->query['userid']);
    \DJApi\API::debug(\DJApi\DB::db()->getShow(), "DB");
    return DJApi\API::OK($R);
  }


  protected static function getAND($query, $uid) {
    $from = intval($query['from']);
    $to = intval($query['to']);
    $AND = [];

    /* 按开始和结束时间查询 */
    if($from){
      $AND["cur_time[>]"] = $from;
      if($to){
        $AND["cur_time[<]"] = $to;
      }
    }
    else{
      $now = time();
      $day = intval($query['day']);
      if($day > 365)$day = 365;
      /* 按最近几天查询 */
      if($day > 0){
        $AND["cur_time[>]"] = $now - $day * 24 * 3600;
      }
      else{
        $hour = intval($query['hour']);
        if($hour > 24) $hour=24;
        /* 按最近几小时查询 */
        if($hour > 0){
          $AND["cur_time[>]"] = $now - $hour * 3600;
        }
        /* 按最近 2 小时查询 */
        else{
          $AND["cur_time[>]"] = $now - 2 * 3600;
        }
      }
    }
    /** 根据索引加速 */
    if($AND["cur_time[>]"]){
      $db = \DJApi\DB::db();
      $min_id = $db->get(self::$table['log'] . '_time', 'id', [
        'AND'=>['cur_time[<]'=>$AND["cur_time[>]"]],
        'ORDER'=>['cur_time'=>'DESC']
        ]);
      if($min_id){
        $AND["id[>]"] = $min_id;
      }
      \DJApi\API::debug(['DB'=>$db->getShow(), "min_id"=>$min_id]);
    }
    return $AND;
  }
  /**
   * 活跃度排行
   */
  public static function countUserLog($request, $uid) {
    $AND = self::getAND($request->query);

    $db = \DJApi\DB::db();
    $tt[] = microtime();
    $rows = $db->select(self::$table['log'], ['count(id) as n', 'uid'],
      [
        "AND"   => $AND,
        "GROUP" => 'uid',
        "ORDER" => ["n" =>"DESC"]
      ]
    );
    $tt[] = microtime();
    \DJApi\API::debug(['DB'=>$db->getShow(), "T"=>$tt]);
    return \DJApi\API::OK($rows);
  }
  /**
   * 列出一个用户指定时间内的请求
   * 敏感数据不返回
   * 意义：多人活跃度
   */
  public static function listUserLog($request, $uid) {

    $AND = self::getAND($request->query);
    $userid = $request->query['userid'];
    $AND['uid'] = $userid;

    $db = \DJApi\DB::db();
    $rows = $db->select(self::$table['log'], ['get', 'post', 'time', 'host'],
      [
        "AND"   => $AND,
        "ORDER" => ["time" =>"DESC"],
        "LIMIT" => 1000
      ]
    );
    \DJApi\API::debug($db->getShow(), "DB");
    // 部分数据不要返回
    $dontReturn = ['api_signature', 'tokenid', 'timestamp', 'uid', 'callback'];
    if($rows) foreach($rows as $row){
      $item = [
        'host' => $row['host'],
        'date' => substr($row['time'], 0, 10),
        'time' => substr($row['time'], 11, 8),
      ];
      $api = array_merge(json_decode($row['get'], true), json_decode($row['post'], true));
      foreach($api as $k=>$v){
        if($v && !in_array($k, $dontReturn) && strlen($v) < 64){
          $item[$k] = strlen($v) > 64? substr($v, 0, 64) : $v;
        }
      }
      $R[] = $item;
    }
    return \DJApi\API::OK($R);
  }

}
