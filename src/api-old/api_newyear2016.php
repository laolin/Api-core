<?php
require_once("app/user.class.php");
require_once("app/g.php");
require_once("app/wx.php");
require_once("api_wxpay.php");


define("MONEY", 888);
class class_newyear2016{

  /* -------------------------------------------------------
   * 领取红包
   * ------------------------------------------------------- */
  public static function getredpacket(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, []);
    $share_id = $query['share_id'] + 0;
    $share_sn = $query['share_sn'];
    //是不是贵宾
    $vip = $me["newyear2016"] + 0;
    //时间限制
    global $now;
    //if($now < "2016-02-06")return json_error(2016, "活动从农历腊月廿八开始，敬请期待");
    if($now > "2016-02-15")return json_error(2016, "活动已结束");
    //有测试特权，先用掉！嘿嘿，不管
    if($me['newyear2016mark'] > 0){
      //先看是不是来自分享
      $hb_id = $db->get("newyear2016", "id", ["AND"=>["parentid"=>$share_id, "sn"=>$share_sn, "userid"=>0, "money"=>0]]);
      if($hb_id > 0 && $share_id >200000 && strlen($share_sn)>=8){
        $ret = self::pay_HB($db, $me, $share_id, $hb_id, MONEY);
        //消除测试特权
        QgsModules::UpdateUser(["newyear2016mark"=>0], ["id"=>$me['id']]);
        return json_OK(["ret"=>$ret]);
      }
      //不是特权，就当作贵宾
      else{
        $ret = self::pay_HB($db, $me, "vip", 0, MONEY);
        //消除测试特权
        QgsModules::UpdateUser(["newyear2016mark"=>0], ["id"=>$me['id']]);
        return json_OK(["ret"=>$ret]);
      }
    }
    if($db->get("newyear2016", "money", ["AND"=>["userid"=>$me['id'], "money[>]"=>0]]) > 0){
      return json_error(2016, "每人只能领取一次红包");
    }
    if($vip){
      $ret = self::pay_HB($db, $me, "vip", 0, MONEY);
      return json_OK(["ret"=>$ret]);
    }
    //发出邀请的用户
    $share_vip = QgsModules::CountUser(["AND"=>["id"=>$share_id, "newyear2016"=>1]]);
    if(!$share_vip || $share_id < 200000 || strlen($share_sn)<8){
      return json_error(2016, "不是贵宾");
    }
    $hb_id = $db->get("newyear2016", "id", ["AND"=>["parentid"=>$share_id, "sn"=>$share_sn, "userid"=>0, "money"=>0]]);
    if($hb_id <= 0){
      return json_error(2016, "红包无效");
    }
    $isgz = QgsModules::DB()->has(QgsModules::table_wxuser, ["AND"=>["isgz"=>"已关注", "openid"=>$me['openid']]]);
    if(!$isgz){
      return json_error(2016, "未关注");
    }
    ParseAttr($me, "attr");
    if(strlen($me['name'])<3){
      return json_error(2016, "个人资料未完善");
    }
    if(strlen($me['mobile'])<11){
      return json_error(2016, "个人资料未完善");
    }
    $ret = self::pay_HB($db, $me, $share_id, $hb_id, MONEY);
    return json_OK(["ret"=>$ret]);
  }
  // ---------- 尝试领红包
  /*
  几种情况：
    1、贵宾●已领取/8.88〓左 右
    2、用户，已领取●已领取/8.88〓张大照给您发了一个8.88的红包
    3、用户，未关注●未关注〓张大照给您发了一个8.88的红包
    4、用户，资料未完善●资料未完善/8.88〓张大照给您发了一个8.88的红包
    5、用户，已被抢●已被抢〓手慢了，张大照发的红包已被领取
    6、用户，贵宾15个名额已用完●名额已用完〓手慢了，张大照发红包的名额已使用完毕
    7、非法
  */
  public static function get_red_packet(&$db, $me, $share_id, $share_sn){
    //是不是贵宾
    $vip = $me["newyear2016"] + 0;
    //时间限制
    global $now;
    //if($now < "2016-02-06")return json_error(2016, "活动从农历腊月廿八开始，敬请期待");
    if($now > "2016-02-15")return ["活动已结束"];
    //有测试特权，先用掉！嘿嘿，不管
    if($me['newyear2016mark'] > 0){
      //先看是不是来自分享
      $hb_id = $db->get("newyear2016", "id", ["AND"=>["parentid"=>$share_id, "sn"=>$share_sn, "userid"=>0, "money"=>0]]);
      if($hb_id > 0 && $share_id >200000 && strlen($share_sn)>=8){
        $money = MONEY;
        $share_vip = QgsModules::GetUser("*", ["AND"=>["id"=>$share_id]]);
        ParseAttr($share_vip, "attr");
        $ret = self::pay_HB($db, $me, $share_id, $hb_id, $money);
        //消除测试特权
        QgsModules::UpdateUser(["newyear2016mark"=>0], ["id"=>$me['id']]);
        return ["已领取","{$share_vip['name']}给您发了一个".sprintf("%0.2f", $money/100)."元的红包，测试特权，来自分享"];
      }
      //不是特权，就当作贵宾
      else{
        $money = MONEY;
        $ret = self::pay_HB($db, $me, "vip", 0, $money);
        //消除测试特权
        QgsModules::UpdateUser(["newyear2016mark"=>0], ["id"=>$me['id']]);
        return ["已领取", "测试特权，模拟贵宾"];
      }
    }
    $getted_row = $db->get("newyear2016", "*", ["AND"=>["userid"=>$me['id'], "money[>]"=>0]]);
    if($getted_row["money"] > 0){
      $share_vip = QgsModules::GetUser("*", ["AND"=>["id"=>$getted_row["parentid"]]]);
      ParseAttr($share_vip, "attr");
      if($vip)return ["已领取"];
      return ["已领取","{$share_vip['name']}给您发了一个".sprintf("%0.2f", $getted_row['money']/100)."元的红包"];
    }
    if($vip){
      $ret = self::pay_HB($db, $me, "vip", 0, MONEY);
      return ["已领取"];
    }
    //发出邀请的用户
    $share_vip = QgsModules::GetUser("*", ["AND"=>["id"=>$share_id, "newyear2016"=>1]]);
    ParseAttr($share_vip, "attr");
    if(!$share_vip || $share_id < 200000 || strlen($share_sn)<8){
      return "";//["红包无效"];
    }
    $hb_row = $db->get("newyear2016", "*", ["AND"=>["parentid"=>$share_id, "sn"=>$share_sn]]);
    $hb_id = $hb_row['id'] + 0;
    if($hb_row['money'] > 0){
      return ["手慢了", "手慢了，{$share_vip['name']}发的红包已被领取"];
    }
    $n = $db->count("newyear2016",["AND"=>["parentid"=>$share_id,  "money[>]"=>0]]);
    if($n >= 15){
      return ["手慢了", "手慢了，{$share_vip['name']}发红包的名额已使用完毕"];
    }
    $isgz = QgsModules::DB()->has(QgsModules::table_wxuser, ["AND"=>["isgz"=>"已关注", "openid"=>$me['openid']]]);
    if(!$isgz){
      return ["未关注", "{$share_vip['name']}给您发了一个".sprintf("%0.2f", MONEY/100)."元的红包", "请关注请高手后领取红包"];
    }
    ParseAttr($me, "attr");
    if(strlen($me['name'])<3){
      return ["资料未完善", "{$share_vip['name']}给您发了一个".sprintf("%0.2f", MONEY/100)."元的红包", "请完善资料后领取红包"];
    }
    if(strlen($me['mobile'])<11){
      return ["资料未完善", "{$share_vip['name']}给您发了一个".sprintf("%0.2f", MONEY/100)."元的红包", "请完善资料后领取红包"];
    }
    $ret = self::pay_HB($db, $me, $share_id, $hb_id, MONEY);
    return ["已领取","{$share_vip['name']}给您发了一个".sprintf("%0.2f", MONEY/100)."元的红包"];
  }
  // ----------------- 发放红包 -----------------
  public static function pay_HB(&$db, $me, $parentid, $hb_id, $fen){
    $openid = $me['openid'];
    $id = $hb_id;
    if($hb_id == "vip"){
      $id = $db->insert("newyear2016", ["userid"=>$me['id'], "parentid"=>$parentid, "money"=>0, "rq"=>G::s_now()]);
    }
    $xml = class_wxpay::pay_HB($openid, $fen, "newyear2016".$id, "猴年吉祥，新春大吉！", "请高手平台拜年");
    //var_dump($xml);
    //echo "\n<br>", WXPAY_MCHID, "<br>\n";
    $ret = Common_util_pub::xmlToArray($xml);
    $success = isset($ret['result_code']) && $ret['result_code']=="SUCCESS";
    if($success)
      $db->update("newyear2016", ["userid"=>$me['id'], "money"=>$fen, "rq"=>G::s_now()], ["id"=>$id]);
    return $ret;
  }
  /* -------------------------------------------------------
   * 分享红包给朋友
   * ------------------------------------------------------- */
  public static function sendtofriend(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, []);
    $share_sn = $query['share_sn'];
    //标志为已分享
    $db->update("newyear2016", ["rq"=>G::s_now()], ["AND"=>["parentid"=>$me['id'], "sn"=>$share_sn, "money"=>0]]);
    return json_OK([]);
  }
  /* -------------------------------------------------------
   * 获取红包领取信息
   * ------------------------------------------------------- */
  public static function getreadpacketinfo(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, []);
    $share_id = $query['share_id'] + 0;
    $share_sn = $query['share_sn'];
    $R = [];
    $R['geted'] = self::get_red_packet($db, $me, $share_id, $share_sn);
    //已领到多少钱的红包¥
    $R['money'] = $db->sum("newyear2016", "money", ["AND"=>["userid"=>$me['id'], "money[>]"=>0]]);
    //是不是贵宾
    $R['vip'] = $me["newyear2016"] + 0;
    //是贵宾
    if($R['vip']){
      $R['canget'] = $R['money'] <= 0;
      $R["shared"] = $db->count("newyear2016", ["AND"=>["parentid"=>$me['id'], "rq[>]"=>"2016-01"]]);//已分享几个
      $R["used"  ] = $db->count("newyear2016", ["AND"=>["parentid"=>$me['id'], "money[>]"=>0]]);//已领取几个
      $R["sn"    ] = $db->get("newyear2016", "sn", ["AND"=>["parentid"=>$me['id'], "rq[<]"=>"2016-01"]]);//未发送的码
      //最多生成15个红包:
      if(!$R['sn'] && ($R['used'] < 15 || $me['newyear2016mark'] > 0)){
        $sn = rand(10000000, 99999999). substr("".time(), 3, 8);
        $db->insert("newyear2016", ["parentid"=>$me['id'], "sn"=>$sn, "userid"=>0, "money"=>0, "rq"=>""]);//有日期的，表示已分享，有userid表示正在领取，有金额的表示已领取。
        $R["sn"] = $sn;
        if($me['newyear2016mark'] > 0){
          //消除测试特权
          QgsModules::UpdateUser(["newyear2016mark"=>0], ["id"=>$me['id']]);
        }
      }
    }
    else{
      $share_id = $query['share_id'] + 0;
      $share_sn = $query['share_sn'];
      if($share_id >200000 && strlen($share_sn)>=8)
        $R["shareinfo"] = $db->get("newyear2016", ["userid", "money"], ["AND"=>["parentid"=>$share_id, "sn"=>$share_sn]]);//读取这个分享
      $R['canget'] = $R['shareinfo']['money'] <= 0;
    }
    
    unset($me['password']);//密码不返回
    unset($me['openid']);  //密码不返回
    return json_OK(["me"=>$me, "data"=>$R]);
  }
  /* -------------------------------------------------------
   * 选择或不选一个用户
   * ------------------------------------------------------- */
  public static function changeselect(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["userid"], "service");
    ASSERT_right($db, $me, "发放专家酬金");
    $userid = $query["userid"] + 0;
    QgsModules::DB()->query("update ".QgsModules::table_user." set newyear2016 = 1 - newyear2016 WHERE id=$userid");
    return json_OK([]);
  }
  /* -------------------------------------------------------
   * 选择或不选一个用户(测试特权)
   * ------------------------------------------------------- */
  public static function changeselectmark(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["userid"], "service");
    ASSERT_right($db, $me, "发放专家酬金");
    $userid = $query["userid"] + 0;
    QgsModules::DB()->query("update ".QgsModules::table_user." set newyear2016mark = 1 - newyear2016mark WHERE id=$userid");
    return json_OK([]);
  }


  /* -------------------------------------------------------
   * 获取用户列表
   * ------------------------------------------------------- */
  public static function getlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");
    ASSERT_right($db, $me, "发放专家酬金");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;

    $AND = [];
    switch($query['en']){
      case "newuser":
        $AND = ["newyear2016[!]"=>1, "userstate[<]"=>3,"expertstate[<]"=>3, "servicestate[<]"=>3];
        break;
      case "activeuser":
        $AND = ["newyear2016[!]"=>1, "userstate[>=]"=>3];
        break;
      case "activeexpert":
        $AND = ["newyear2016[!]"=>1, "expertstate[>=]"=>3];
        break;
      case "activeservice":
        $AND = ["newyear2016[!]"=>1, "servicestate[>=]"=>3];
        break;
      case "alluser":
        $AND = ["newyear2016[!]"=>1];
        break;
      case "newyear2016":
        $AND = ["newyear2016"=>1];
        break;
      //测试特权：
      case "newuser-mark":
        $AND = ["newyear2016mark[!]"=>1, "userstate[<]"=>3,"expertstate[<]"=>3, "servicestate[<]"=>3];
        break;
      case "activeuser-mark":
        $AND = ["newyear2016mark[!]"=>1, "userstate[>=]"=>3];
        break;
      case "activeexpert-mark":
        $AND = ["newyear2016mark[!]"=>1, "expertstate[>=]"=>3];
        break;
      case "activeservice-mark":
        $AND = ["newyear2016mark[!]"=>1, "servicestate[>=]"=>3];
        break;
      case "alluser-mark":
        $AND = ["newyear2016mark[!]"=>1];
        break;
      case "newyear2016-mark":
        $AND = ["newyear2016mark"=>1];
        break;
        
      default:
        $AND = [QgsModules::table_user . ".id[>]"=>0];
    }
    $AND["rq_delete[<]"] = "2015-01";

    //需要搜索？
    $searchtext = isset($query['searchtext']) ? trim($query['searchtext']) : false;
    if($searchtext){
      //用户ID=搜索内容
      $AND["OR"][QgsModules::table_user . ".id"] = $searchtext;
      //在属性中含有搜索内容
      $AND["OR"][QgsModules::table_user . ".attr[~]"] = $searchtext;
      //用户姓名中含有搜索内容
      $AND["OR"][QgsModules::table_user . ".name[~]"] = $searchtext;
      //用户手机号中含有搜索内容
      $AND["OR"][QgsModules::table_user . ".mobile[~]"] = $searchtext;
      //用户微信呢称中含有搜索内容
      $AND["OR"][QgsModules::table_wxuser . ".nickname[~]"] = $searchtext;
    }

    //需要排序？
    $sortby = isset($query['sortby']) ? trim($query['sortby']) : false;
    $sortk = [
    '领取贵宾红包时间'=>"sss",
    '领取用户红包时间'=>"sss",
    '发送红包数量'    =>"sss",
    '已领红包数量'    =>"sss",

     '近10天活跃度'=>"hhh",
     '近2天活跃度' =>"hhh",
     '专家分' =>"ref",
     '推荐人数' =>"ref",
    ];
    if($sortby && $sortk[$sortby]){
      $sortk = $sortk[$sortby];
      $R0 = QgsModules::SelectWxAndUsersByFields(
        ["id", "name", "mobile", "userstate", "expertstate", "servicestate"],
        ["headimgurl","nickname"],
        ["AND"=>$AND]);
      //都给算出来
      $ids = [];
      foreach($R0 as $k=>$row){
        $R0[$k][$sortk] = 0;
        $ids[$row['id']]=$k;
      }
      $str_ids = implode(",", array_keys($ids));
    }
    $case_n = 0;
    switch($sortby){
      case '领取贵宾红包时间': 
        $rq_min = G::s_now(-24*3600*$case_n);//最早的时间
        $sss = $db->query("SELECT userid, rq as sss FROM newyear2016 WHERE money>0 AND parentid='vip'")->fetchAll();
    //var_dump($sss);
        foreach($sss as $row)if(isset($R0[$ids[$row['userid']]]))$R0[$ids[$row['userid']]]['sss'] = $row['sss'];
        usort($R0, function ($a, $b){
          if($a['sss'] > $b['sss'])return -1;
          if($a['sss'] < $b['sss'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        $R1= array_slice($R0, $first, $pagesize);//var_dump($R1);
        foreach($R1 as $k=>$str)$R1[$k]['sss'] = $R1[$k]['sss']>"2016-01" ? ("VIP ".$R1[$k]['sss']) : "";
        return json_OK(["list"=>$R1, "rowcount"=>$rowcount]);
      case '领取用户红包时间': 
        $rq_min = G::s_now(-24*3600*$case_n);//最早的时间
        $sss = $db->query("SELECT userid, rq as sss FROM newyear2016 WHERE money>0 AND parentid>0")->fetchAll();
    //var_dump($sss);
        foreach($sss as $row)if(isset($R0[$ids[$row['userid']]]))$R0[$ids[$row['userid']]]['sss'] = $row['sss'];
        usort($R0, function ($a, $b){
          if($a['sss'] > $b['sss'])return -1;
          if($a['sss'] < $b['sss'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        $R1= array_slice($R0, $first, $pagesize);//var_dump($R1);
        foreach($R1 as $k=>$str)$R1[$k]['sss'] = $R1[$k]['sss']>"2016-01" ? ("邀请".$R1[$k]['sss']) : "";
        return json_OK(["list"=>$R1, "rowcount"=>$rowcount]);
      case '发送红包数量'    :
        $rq_min = G::s_now(-24*3600*$case_n);//最早的时间
        $sss = $db->query("SELECT parentid, COUNT(id) as sss FROM newyear2016 WHERE rq>'2016-01' GROUP BY parentid")->fetchAll();
    //var_dump($sss);
        foreach($sss as $row)if(isset($R0[$ids[$row['parentid']]]))$R0[$ids[$row['parentid']]]['sss'] = $row['sss'] + 0;
        usort($R0, function ($a, $b){
          if($a['sss'] > $b['sss'])return -1;
          if($a['sss'] < $b['sss'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        $R1= array_slice($R0, $first, $pagesize);//var_dump($R1);
        foreach($R1 as $k=>$str)$R1[$k]['sss'] = $R1[$k]['sss']>0 ? ($sortby." ".$R1[$k]['sss']) : "";
        return json_OK(["list"=>$R1, "rowcount"=>$rowcount]);
      
      	  
      case '已领红包数量'    : 
        $rq_min = G::s_now(-24*3600*$case_n);//最早的时间
        $sss = $db->query("SELECT parentid, COUNT(id) as sss FROM newyear2016 WHERE rq>'2016-01' AND money>0 GROUP BY parentid")->fetchAll();
    //var_dump($sss);
        foreach($sss as $row)if(isset($R0[$ids[$row['parentid']]]))$R0[$ids[$row['parentid']]]['sss'] = $row['sss'] + 0;
        usort($R0, function ($a, $b){
          if($a['sss'] > $b['sss'])return -1;
          if($a['sss'] < $b['sss'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        $R1= array_slice($R0, $first, $pagesize);//var_dump($R1);
        foreach($R1 as $k=>$str)$R1[$k]['sss'] = $R1[$k]['sss']>0 ? ($sortby." ".$R1[$k]['sss']) : "";
        return json_OK(["list"=>$R1, "rowcount"=>$rowcount]);
      
      case '近10天活跃度': $case_n += 8;
      case '近2天活跃度': $case_n += 2;
        $rq_min = G::s_now(-24*3600*$case_n);//最早的时间
        $hhh = $db->query("SELECT userid, COUNT(id) as hhh FROM log_api WHERE rq>'$rq_min' AND userid IN ($str_ids) GROUP BY userid")->fetchAll();
        foreach($hhh as $row)if(isset($R0[$ids[$row['userid']]]))$R0[$ids[$row['userid']]]['hhh'] = $row['hhh'] + 0;
        usort($R0, function ($a, $b){
          if($a['hhh'] > $b['hhh'])return -1;
          if($a['hhh'] < $b['hhh'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        return json_OK(["list"=>array_slice($R0, $first, $pagesize), "rowcount"=>$rowcount]);
      case "专家分":
        $ep = $db->query("SELECT userid, SUM(n) as ep FROM z_userpoints WHERE attr IN ('expertpoints', 'referrerpoints') AND userid IN ($str_ids) GROUP BY userid")->fetchAll();
        foreach($ep as $row)if(isset($R0[$ids[$row['userid']]]))$R0[$ids[$row['userid']]]['ep'] = $row['ep'] + 0;
        usort($R0, function ($a, $b){
          if($a['ep'] > $b['ep'])return -1;
          if($a['ep'] < $b['ep'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        return json_OK(["list"=>array_slice($R0, $first, $pagesize), "rowcount"=>$rowcount]);
      case "推荐人数":
        $ref = QgsModules::query("SELECT parentid, COUNT(id) as ref FROM ".QgsModules::table_user." WHERE parentid IN ($str_ids) GROUP BY parentid")->fetchAll();
        foreach($ref as $row)if(isset($R0[$ids[$row['parentid']]]))$R0[$ids[$row['parentid']]]['ref'] = $row['ref'] + 0;
        usort($R0, function ($a, $b){
          if($a['ref'] > $b['ref'])return -1;
          if($a['ref'] < $b['ref'])return 1;
          if($a['id'] > $b['id'])return 1;
          if($a['id'] < $b['id'])return -1;
          return 0;
        });
        $rowcount = count($R0);
        return json_OK(["list"=>array_slice($R0, $first, $pagesize), "rowcount"=>$rowcount]);
    }

    $arr = QgsModules::SelectWxAndUsersByFields(
      ["id", "name", "mobile", "userstate", "expertstate", "servicestate", "attr"],
      ["headimgurl","nickname"],
      ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>[QgsModules::table_user.".id"]]);

    foreach($arr as $k=>$row){
      $arr[$k]['attr'] = json_decode($row['attr'], true);
    }
    $rowcount = QgsModules::CountUser(["AND"=>$AND]);
    $data = [
      "newyear2016"=>QgsModules::CountUser(["newyear2016"=>1]),
      "newyear2016mark"=>QgsModules::CountUser(["newyear2016mark"=>1])];
    return json_OK(["list"=>$arr, "rowcount"=>$rowcount, "data"=>$data]);
  }

}


?>