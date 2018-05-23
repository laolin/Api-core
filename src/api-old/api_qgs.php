<?php
  require_once("app/user.class.php");
  require_once("app/g.php");
  require_once("app/wx.php");
  require_once("api_wxpay.php");

class class_qgs{

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导 获取充值列表
   * xxx.com/qgs/getcashlist
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getcashlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");
    ASSERT_right($db, $me, "查看充值记录");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    //暂时不判断查询权限
    
    //充值记录
    $AND = ["module"=>"qgsrecharge", "time_end[>]"=>'2005-01'];
    $rowcount = $db->count("wx_pay", ["AND"=>$AND]);
    $R = $db->select("wx_pay", ["userid","time_end","n"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"rq DESC"]);
    QgsModules::AddWxAndUserDataByUserid($R, ["id", "name"], ["headimgurl", "nickname"], "userid", "userid");
    
    foreach($R as $i=>$row)$R[$i]['n'] = $row['n']/100;
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }
  /* ------------------------------------------------------------------------------
   * 编导 获取酬金发放记录
   * xxx.com/qgs/getpayexpertpointslist
   * ------------------------------------------------------------------------------ */
  public static function getpayexpertpointslist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");
    ASSERT_right($db, $me, "发放专家酬金");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;

    //充值记录
    $AND = ["z_userpoints.userid[>]"=>"0", "z_userpoints.attr"=>"expertpoints", "z_userpoints.k1"=>"企业付款"];
    $rowcount = $db->count("z_userpoints", ["AND"=>$AND]);
    $R = $db->select("z_userpoints",["userid","rq","n"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"z_userpoints.rq DESC"]); 
    QgsModules::AddWxAndUserDataByUserid($R, ["id", "name"], ["headimgurl", "nickname"], "userid", "userid");
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }

  /* ------------------------------------------------------------------------------
   * 获取待发放专家酬金列表
   * ------------------------------------------------------------------------------ */
  public static function getpayexpertlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");
    ASSERT_right($db, $me, "发放专家酬金");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    //暂时不判断查询权限
    
    //专家分列表
    $AND = ["attr"=>['expertpoints', 'referrerpoints']];
    if(isset($query['till'])) switch($query['till']){
      case "lastmonth": $AND['rq[<]'] = G::s_month1stday();
    }
    $sql = "SELECT userid, SUM(n) as expertpoints FROM z_userpoints WHERE attr IN ('expertpoints', 'referrerpoints') GROUP BY userid";
    $points = $db->select("z_userpoints", ["userid", "SUM(n) as expertpoints"], ["AND"=>$AND, "ORDER"=>"expertpoints DESC", "LIMIT"=>[$first, $pagesize], "GROUP"=>"userid", "HAVING"=>["expertpoints[>]"=>0]]);
    if(!$points)return json_OK(["list"=>[], "rowcount"=>0]);
    //读取用户信息
    foreach($points as $k=>$row) $ids[$row['userid']] = $k;
    $R = QgsModules::GetWxAndUsers(array_keys($ids));
    //对应专家分：
    foreach($R as $k=>$row) $R[$k]['expertpoints'] = $points[ $ids[$row['id']] ]['expertpoints'];
    //总行数
    $rows = $db->select("z_userpoints", "SUM(n) as expertpoints", ["AND"=>$AND, "GROUP"=>"userid", "HAVING"=>["expertpoints[>]"=>0]]);
    $rowcount = count($rows);
    //返回
    return json_OK(["list"=>$R, "rowcount"=>$rowcount, "ids"=>$ids]);
  }
  /* ------------------------------------------------------------------------------
   * 获取待发放专家酬金概况
   * ------------------------------------------------------------------------------ */
  public static function getpayexpertinfo(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, [], "service");
    ASSERT_right($db, $me, "发放专家酬金");

    $AND = ["attr"=>"expertpoints"];
    if(isset($query['till'])) switch($query['till']){
      case "lastmonth": $AND['rq[<]'] = G::s_month1stday();
    }
    $points = $db->select("z_userpoints", "SUM(n) as expertpoints", ["AND"=>$AND, "GROUP"=>"userid", "HAVING"=>["expertpoints[>]"=>0]]);
    if(!$points)return json_OK(["n"=>0, "totle"=>0]);
    return json_OK(["n"=>count($points), "totle"=>array_sum($points)]);
  }
  /* ------------------------------------------------------------------------------
   * 开始发放专家酬金（单位：元）
   * ------------------------------------------------------------------------------ */
  public static function dopayexpert(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["users"], "service");
    ASSERT_right($db, $me, "发放专家酬金");

    $users = $query['users'];
    if($users != "all" && !is_array($users)) return json_error(8000, "数据不正确");

    //获取待专家酬金列表：
    $AND = ["attr"=>"expertpoints"];
    if($users != "all") $AND['userid'] = $users;
    if(isset($query['till'])) switch($query['till']){
      case "lastmonth": $AND['rq[<]'] = G::s_month1stday();
    }
    $points = $db->select("z_userpoints", ["userid", "SUM(n) as expertpoints"], ["AND"=>$AND, "GROUP"=>"userid", "HAVING"=>["expertpoints[>]"=>0]]);

    //获取所有的openid
    $moneys = [];
    foreach($points as $row) $moneys[$row["userid"]] = $row["expertpoints"];
    $openids = QgsModules::SelectUser(["id", "openid"], ["AND"=>["id"=>array_keys($moneys)]]);

    //发放酬金
    $n = 0;
    foreach($openids as $row){
      $money = $moneys[$row["id"]];
      $b = self::qypay($db, $row["id"], $row["openid"], $money, "expertpoints", "支持");
      if($b === true){
        $n++;
        $totle += $money;
      }
    }

    return json_OK(["payed"=>["n"=>$n, "totle"=>$totle]]);
  }
  /* ------------------------------------------------------------------------------
   * 向某一专家发放专家酬金（单位：元）
   * ------------------------------------------------------------------------------ */
  public static function dopayexpertsingle(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["userid", "money", "type"], "service");
    ASSERT_right($db, $me, "发放专家酬金");
    
    $userid = $query['userid'] + 0;if($userid <= 0) return json_error(8001, "用户id不正确"  );
    $money = $query['money'] + 0; if($money <= 0) return json_error(8000, "金额不正确"  );
    $payType = $query['type'];
    if(!in_array($payType, ["回答", "裁判", "推广", "支持"])) return json_error(8000, "酬金分类不正确"  );
    $openid = QgsModules::GetUser("openid", ["id"=>$userid]);if(!$openid) return json_error(8001, "用户id不正确");

    //获取待专家酬金列表：
    $AND = ["userid"=>$userid, "attr"=>["expertpoints", "referrerpoints"]];
    $points = $db->sum("z_userpoints", "n", ["AND"=>$AND]);
    if($points < $money) return json_error(8000, "金额超过专家分金额$points");

    //return json_error(8000, $openid);
    //发放酬金
    $b = self::qypay($db, $userid, $openid, $money, "expertpoints", $payType);
    if($b !== true) return json_error(8006, "发放失败。", ["XML"=>$b]);
//echo APPID;
    //推送消息：
    WX::send_payexpert_news($openid, $money, $payType);
    
    return json_OK([]);
  }
  /* ------------------------------------------------------------------------------
   * 企业付款（单位：元）
   * ------------------------------------------------------------------------------ */
  public static function qypay(&$db, $userid, $openid, $money, $pointType, $payType=""){
    $id = $db->insert("z_userpoints", ["rq"=>G::s_now(), "n"=>-$money, "userid"=>"", "attr"=>$pointType , "k1"=>"企业付款", "k2"=>$payType]);
//var_dump($id);
    $xml = class_wxpay::pay_QYFK($openid, $money*100, $id, $payType. "酬金");//（单位：分）
//var_dump($xml);
    $ret = Common_util_pub::xmlToArray($xml);
    $success = isset($ret['payment_no']) && $ret['payment_no'];
    if($success){
      $db->update("z_userpoints", ["userid"=>$userid], ["id"=>$id]);
      return true;
    }
    return $xml;
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 收到用户充值通知： 确认已有积分入账
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function notify_recharge(&$db, $payid){
    $pay = $db->get("wx_pay", "*", ["id"=>$payid]);
    if(!$pay) return false;
    if($pay['module']!='qgsrecharge') return false;
    if($pay['time_end']<'2005-01') return false;
    
    $n = $db->count("z_userpoints", ["AND"=>["userid"=>$pay['userid'], "attr"=>"points" , "k1"=>"充值", "k2"=>$payid]]);
    if($n > 0) return true;//已有积分入账
    
    //充值积分入账，1元1个积分
    $db->insert("z_userpoints", ["rq"=>$pay['time_end'], "userid"=>$pay['userid'], "n"=>$pay['n']/100, "attr"=>"points", "k1"=>"充值", "k2"=>$payid]);
    return true;
  }
  
}

?>