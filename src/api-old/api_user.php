<?php
require_once("app/user.class.php");
require_once("app/g.php");
require_once("app/wx.php");
require_once("api_qa.php");


class class_user{
  public static function test(&$get, &$post, &$db){
    print_r($_SERVER);
    return "";
  }
  private static function getmoreinfo(&$db, &$userinfo, $needrights = false){
    
    ASSERT_right($db, $userinfo, $needrights);
    
    //用户积分
    $arr = $db->select("z_userpoints", ["attr","SUM(n) as nn"], ["userid"=>$userinfo['id'], "GROUP"=>"attr"]);
    if($arr)foreach($arr as $row){
      $userinfo[$row['attr']] = $row['nn'] + 0;
    }
    
    //微信数据
    $wx = QgsModules::DB()->get(QgsModules::table_wxuser, "*", [ "openid"=>$userinfo['openid'] ]);
    $userinfo['wx'] = $wx ? $wx : [];
  }
  private static function getuserpoints(&$db, &$userinfo){
    //用户积分
    $arr = $db->select("z_userpoints", ["attr","SUM(n) as nn"], ["userid"=>$userinfo['id'], "GROUP"=>"attr"]);
    if($arr)foreach($arr as $row){
      $userinfo[$row['attr']] = $row['nn'] + 0;
    }
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 用户登陆
   * xxx.com/user/login?nick=NICK&t=TIMESPAN&sign=SIGN
   * 
   * @NICK  用户名/手机号
   * @TIMESPAN 当时的时间戳
   * @SIGN 密码和时间戳连接后MD5
   * 
   * 返回:用户信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function login(&$get, &$post, &$db){
    $query = &$get;
    $userinfo = ajax_get_userinfo($db, $query);
    if(is_string($userinfo)) return $userinfo;
    
    $t = $query['t'] + 0;
    $last_t = $userinfo['logintime'] + 0;
    if( $last_t >= $t )//这里不允许再使用同一时间戳，不能重用
      return json_error(1012, "签名已过期");
    
    unset($userinfo['password']);//密码不返回
    unset($userinfo['openid']);  //密码不返回
      
    //步骤4：标志时间戳，不能重用，确保拦截报文无用, 同时保存为上次登陆模块。
    QgsModules::UpdateUser(["logintime"=>$t], ["id"=>$userinfo['id']]);//步骤4
    return json_OK(["id"=>$userinfo['id'], "loginmodule"=>$userinfo['loginmodule']]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 用户微信登陆
   * xxx.com/user/wxlogin?openid=OPENID （GET）（未设置安全登陆）
   * 
   * @OPENID 微信openid
   * 
   * 返回:用户信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function wxlogin(&$get, &$post, &$db){
    $query = &$get;
    $where = [ "openid"=>$query['openid']];
    $userinfo = QgsModules::GetUser(USER::$MainInfos, $where);
    if(!is_array($userinfo)){
      //return json_error(1000, "错误" );
      //找不到就新建用户：
      $userinfo = [
        "userstate"=>3, 
        "expertstate"=>0, 
        "servicestate"=>0, 
        "loginmodule"=>"user",
        "openid"=>$query['openid']
      ];
      $uid = QgsModules::InsertUser($userinfo);
      $userinfo['id'] = $uid;
      return json_OK(["userinfo"=>$userinfo]);
    }
    self::getmoreinfo($db, $userinfo);
    unset($userinfo['password']);//密码不返回
    unset($userinfo['openid']);  //密码不返回
    
    //保存为上次登陆模块：
    if(isset($query['enmodule']) && $userinfo['loginmodule']!=$query['enmodule']) //公共登陆时，没有这个参数
      QgsModules::UpdateUser(["loginmodule"=>$query['enmodule'], "logintime"=>time()], $where);
    else
      QgsModules::UpdateUser(["logintime"=>time()], $where);
    return json_OK(["userinfo"=>$userinfo]);
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 用户注册
   * xxx.com/user/register （POST）
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function register(&$get, &$post, &$db){
    $query = &$get;
    //var_dump($query);
    //步骤1：手机号码不可重复,且有效(以1开头的11或12位数字)
    $mobile = trim($query['mobile']);
    if (!$mobile || !preg_match('/^1([0-9\-]{10,11})$/', $mobile))
      return json_error(1013, "手机号码无效"  );
    if ( QgsModules::HasUser(["mobile"=>$mobile]) )
      return json_error(1012, "手机号码已注册：$mobile"  );
    
    //步骤2：密码不可为空
    $password = trim($query['password']);
    if ( !$password )
      return json_error(1014, "密码不能为空"  );
    
    //步骤3：生成用户
    //$db->debug();
    $new_id = QgsModules::InsertUser(["userstate"=>3, "mobile"=>$query['mobile'], "password"=>$query['password']] );
    
    //步骤4：log 保留

    //步骤5：返回成功
    return json_OK(["id"=>$new_id]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 更改密码
   * xxx.com/user/changepassword （GET）
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function changepassword(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["p0", "p1"]);
    
    $old_p = trim($userinfo['password']);
    $p0 = trim($query['p0']);
    $p1 = trim($query['p1']);
    if (!$p1)return json_error(1013, "新密码无效");
    
    //原密码已加密：
    if(strlen($old_p) > 16){
      if($p0 != $old_p)return json_error(1013, "原密码错误");
    }
    
    //原密码未加密：
    else if(strlen($old_p) > 0){
      $ss = MD5("$old_p@qgs");
      if(MD5("$old_p@qgs") != strtolower($p0))return json_error(1013, "原密码错误");
    }
    
    QgsModules::UpdateUser(["password"=>$p1], ["id"=>$query["uid"]]);
    
    return json_OK([]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导重置用户密码
   * xxx.com/user/servicechangepassword （GET）
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function servicechangepassword(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid", "p1"],"service");
    ASSERT_right($db, $userinfo, "重置密码");
    //if($query["module"]!="编导")return json_error(1000, "不是编导"  );
    
    QgsModules::UpdateUser(["password"=>$query["p1"]], ["id"=>$query["userid"]]);
    
    return json_OK([]);
  }

  /* -----------------------------------------------------------------------------
   * 获取用户数据
   * ----------------------------------------------------------------------------- */
  public static function getuserdatas(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, [], 9999);
    $R = [];
    if($query['datas'])foreach($query['datas'] as $data)switch($data){
      case "me":
        unset($me['password']);//密码不返回
        unset($me['openid']);  //密码不返回
        $me['attr'] = json_decode($me["attr"], true);
        $R['me'] = $me;
        break;
      case "mynewyear2016":
        $mynewyear2016 = [];
        //是不是贵宾
        $mynewyear2016['vip'] = $me["newyear2016"] + 0;
        //已领到多少钱的红包
        $mynewyear2016['money'] = $db->get("newyear2016", "money", ["AND"=>["userid"=>$me['id'], "money[>]"=>0]]) + 0;
        //我邀请并领到红包的用户id列表
        $mynewyear2016['subusers'] = $db->select("newyear2016", "userid", ["AND"=>["parentid"=>$me['id'], "money[>]"=>0]]);
        //发出邀请的用户
        $mynewyear2016['share_vip'] = QgsModules::CountUser(["AND"=>["id"=>$query['share_id'] + 0, "newyear2016"=>1]]);
        $mynewyear2016['vip_sub'] = $db->count("newyear2016",["AND"=>["parentid"=>$me['id'], "money[>]"=>0]]);
        //返回：
        $R['mynewyear2016'] = $mynewyear2016;
        break;
    }
    return json_OK(["datas"=>$R]);
  }



  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取用户信息
   * xxx.com/user/getinfo?id=ID[nick=NICK]&t=TIMESPAN&sign=SIGN
   * 
   * @ID  用户ID
   * @NICK  用户名/手机号
   * @TIMESPAN 当时的时间戳
   * @SIGN 密码和时间戳连接后MD5
   * 
   * 返回:用户信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getinfo(&$get, &$post, &$db){
    $query = &$get;
    $userinfo = ASSERT_query_userinfo($db, $query, []);
    
    //读取更多信息
    self::getmoreinfo($db, $userinfo);
    unset($userinfo['password']);//密码不返回
    unset($userinfo['openid']);  //密码不返回
    return json_OK(["userinfo"=>$userinfo]);
  }
  public static function getfullinfo(&$get, &$post, &$db){
    return self::getinfo($get, $post, $db);
  }
  
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 保存一下模块
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function savemodule(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, []);
    exit;
  }
  
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取指定用户的基本信息
   * xxx.com/user/getuserinfobyid
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getuserinfobyid(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["userid"]);
    ASSERT_right($db, $me, "获取用户基本信息");
    
    $userinfo = QgsModules::GetWxAndUsers($query['userid']);
    return json_OK(["userinfo"=>$userinfo]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取指定用户的姓名，主要用于二维码分享
   * xxx.com/user/getusernamebyid
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getusernamebyid(&$get, &$post, &$db){
    $query = &$get;
    $name = QgsModules::GetUser("name", ["id"=>$query['userid']]);
    if($name) return json_OK(["name"=>$name, "userid"=>$query['userid']]);
    //返回缺省用户：
    return json_OK(["userid"=>200001]);
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 申请激活
   * xxx.com/user/activeaplly   POST
   * 
   * @usermodule 申请的模块
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function activeaplly(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, []);
    
    QgsModules::UpdateUser(["{$query['usermodule']}state"=>1], ["id"=>$query['uid']]);
    
    return json_OK([]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取用户列表
   * xxx.com/qa/getlist (GET、身份识别)
   * 
   * page  第几页
   * pagesize 每页几行
   * 
   * 
   * 返回: 提问ID，或错误码信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    
    $AND = [];
    switch($query['en']){
      case "newuser":
        //$AND = ["userstate[<]"=>3,"expertstate[<]"=>3, "servicestate[<]"=>3];
        $arr = QgsModules::SelectWxAndUsersByFields(
          ["id", "name", "mobile", "userstate", "expertstate", "servicestate"],
          ["headimgurl","nickname"],
          ["LIMIT"=>[$first, $pagesize], "ORDER"=>[QgsModules::table_user.".id DESC"]]);
        foreach($arr as $k=>$row){
          $arr[$k]['attr'] = json_decode($row['attr'], true);
        }
        return json_OK(["list"=>$arr, "rowcount"=>80]);
      case "activeuser":
        $AND = ["userstate[>=]"=>3];
        break;
      case "alluser":
        $AND = ["userstate[>]"=>0];
        break;
      case "newexpert":
        $AND = ["expertstate[~]"=>[1,2]];
        break;
      case "activeexpert":
        $AND = ["expertstate[>=]"=>3];
        break;
      case "allexpert":
        $AND = ["expertstate[>]"=>0];
        break;
      case "newservice":
        $AND = ["servicestate[~]"=>[1,2]];
        break;
      case "activeservice":
        $AND = ["servicestate[>=]"=>3];
        break;
      case "allservice":
        $AND = ["servicestate[>]"=>0];
        break;
      case "gz": //已关注的用户
        $AND = [QgsModules::table_wxuser . ".isgz"=>"已关注"];
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
     '近30天活跃度'=>"hhh",
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
      case '近30天活跃度': $case_n += 20;
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
      ["id", "name", "mobile", "userstate", "expertstate", "servicestate"],
      ["headimgurl","nickname"],
      ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>[QgsModules::table_user.".id"]]);
    foreach($arr as $k=>$row){
      $arr[$k]['attr'] = json_decode($row['attr'], true);
    }
    $rowcount = QgsModules::CountUser(["AND"=>$AND]);
    
    return json_OK(["list"=>$arr, "rowcount"=>$rowcount]);
  }

  /* ----------------------------------------------------
   * 删除一个用户
   * ---------------------------------------------------- */
  public static function deleteuser(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["userid"], "service");
    ASSERT_right($db, $me, "删除用户");
    QgsModules::DB()->delete_row(QgsModules::table_user, $query['userid'], "service", $query['uid']);
    return json_OK([]);
  }
  /* ----------------------------------------------------
   * 删除一行
   * ---------------------------------------------------- */
  public static function removedata(&$get, &$post, &$db){
    $query = &$post;
    $en = trim($query['en']);
    if($en == "kl"){
      require_once("api_kl.php");
      $query['ciaid'] = $query['id'];
      return class_kl::ask_servicedelete($get, $post, $db);
    }
    $me = ASSERT_query_userinfo($db, $query, ["id", "en"], "service");
    $power = ["user"=>"删除用户", "qa"=>"问答管理", "bbs"=>"论坛管理"];
    ASSERT_right($db, $me, $power[$en]);

    if($query['en'] == "user"){
      QgsModules::DB()->delete_row(QgsModules::table_user, $query['id'], "service", $query['uid']);
    }
    else{
      $tables = ["qa"=>"qa_data", "kl"=>"knowledge_lib", "bbs"=>"bbs_data"];
      $table = $tables[$query['en']];
      $n = $db->delete_row($table, $query['id'], "service", $query['uid']);
      $DD = $db->getShow();
    }
    return json_OK([$n, $DD]);
  }
  /* ----------------------------------------------------
   * 恢复一行
   * ---------------------------------------------------- */
  public static function undelete(&$get, &$post, &$db){
    $query = &$post;
    $en = trim($query['en']);
    if($en == "kl"){
      require_once("api_kl.php");
      $query['ciaid'] = $query['id'];
      return class_kl::ask_serviceundelete($get, $post, $db);
    }
    $me = ASSERT_query_userinfo($db, $query, ["id", "en"], "service");
    $power = ["user"=>"回收站", "qa"=>"回收站", "bbs"=>"回收站"];
    ASSERT_right($db, $me, $power[$en]);
    $uid = $query['uid'] + 0;
    $id = $query['id'] + 0;

    //恢复积分预授权
    if($en == "qa"){
      $qa = $db->get("qa_data", "*", ["id"=>$id]);
      $answers = $db->count("qa_data",  ["parentid"=>$id]);
      if($qa && $answers == 0 && $qa['rq']>"2015-00"){
        $attr = json_decode($qa['attr'], true);
        $neasy = $easy = $attr['easy'] + 0;
        $db->insert("z_userpoints", ["rq"=>$qa['rq'], "n"=>-$neasy, "userid"=>$qa['userid'], "attr"=>"prepoints", "k1"=>"积分预授权", "k2"=>$id]);
      }
    }

    if($query['en'] == "user"){
      QgsModules::DB()->undelete(QgsModules::table_user, $id, "service", $uid);
    }
    else{
      $tables = ["qa"=>"qa_data", "kl"=>"knowledge_lib", "bbs"=>"bbs_data"];
      $table = $tables[$query['en']];
      $db->undelete($table, $id, "service", $uid);
    }
    return json_OK([]);
  }
  /* ----------------------------------------------------
   * 回收站
   * ---------------------------------------------------- */
  public static function getrecyclelist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"], "service");
    $query = &$post;
    ASSERT_right($db, $me, "回收站");

    //分析页码
    $en = trim($query['en']);
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    $AND = ["rq_delete[>]"=>"2015-00"];
    switch($en){
      case "user":
        $arr = QgsModules::SelectWxAndUsersByFields(
          ["id", "name", "mobile", "userstate", "expertstate", "servicestate", "attr"],
          ["headimgurl","nickname"],
          ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>QgsModules::table_user . ".id"]);
        foreach($arr as $k=>$row){
          $arr[$k]['attr'] = json_decode($row['attr'], true);
        }
        $rowcount = QgsModules::CountUser(["AND"=>$AND]);
        break;
      case "qa":
      case "bbs":
        $tables = ["qa"=>"qa_data", "kl"=>"knowledge_lib", "bbs"=>"bbs_data"];
        $table = $tables[$en];
        $rowcount = $db->count($table, ["AND"=>$AND]);
        $R = $db->select($table, ["id", "rq", "attr"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"id DESC"]);
        foreach($R as $k=>$row){
          $attr = json_decode($row["attr"], true);
          $R[$k]['content'] = $attr['content'];
          unset($R[$k]['attr'], $R[$k]['rq_delete']);
        }
        return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
      case "kl":
        $R = $db->select("knowledge_lib", ["id", "rq", "attr"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"rq DESC"]);
        if(is_array($R)){
          foreach($R as $k=>$row){
            $attr = json_decode($row['attr'], true);
            $R[$k]['content'] = $attr["ask"]["content"];
            unset($R[$k]['attr']);
          }
        }
        $rowcount = $db->count("knowledge_lib", ["AND"=>$AND]);
        return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
    }
    
    return json_OK(["list"=>$arr, "rowcount"=>$rowcount]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导获取用户信息
   * xxx.com/user/servicegetuser?userid=ID&签名
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function servicegetuser(&$get, &$post, &$db){
    $query = &$get;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid"], "service");
    
    $userid = $query["userid"] + 0;
    $userinfo = QgsModules::GetWxAndUsersByFields(array_merge(USER::$ShowInfos,["parentid"]), ["headimgurl","nickname"] , [QgsModules::table_user.".id"=>$userid]);
    //echo($db->last_query());
    if(!is_array($userinfo))
      return json_error(1002, "查无此用户ID:$userid" );
    //读取用户扩展信息
    ParseAttr($userinfo, "attr");
    self::getuserpoints($db, $userinfo);
    return json_OK(["userinfo"=>$userinfo]);
  }
  /* -----------------------------------------------------------------------------
   * 编导获取某用户的用户分列表
   * ----------------------------------------------------------------------------- */
  public static function getuserpointslist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize", "userid"], "service");

    //分析页码
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;

    $userid = $query["userid"] + 0;
    $AND = ["userid"=>$userid, "attr"=>["points", "prepoints"]];
    return self::getpointslist($db, $AND, $first, $pagesize);
  }

  /* -----------------------------------------------------------------------------
   * 编导获取某用户的专家分列表
   * ----------------------------------------------------------------------------- */
  public static function getexpertpointslist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize", "userid"], "service");

    //分析页码
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    $userid = $query["userid"] + 0;
    $AND = ["userid"=>$userid, "attr"=>["expertpoints", "referrerpoints"]];
    return self::getpointslist($db, $AND, $first, $pagesize);
  }
  /* ----------------------------------------
   * 通用：获取指定条件的积分列表
   * ---------------------------------------- */
  public static function getpointslist(&$db, $AND, $first, $pagesize){
    $R = $db->select("z_userpoints", ["id","rq","attr","k1","k2","n"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"id DESC"] );
    //增加每行的累计值
    if($R){
      $lastn = $db->sum("z_userpoints", "n", ["AND"=>array_merge($AND,["id[<=]"=>$R[0]['id']])]);
      foreach($R as $i=>$row){
        if($i == 0)$R[$i]["lastn"] = $lastn;
        else $R[$i]["lastn"] = $R[$i-1]["lastn"] - $R[$i-1]["n"];
      }
    }else{
    	$R=[];
    }
    $rowcount = $db->count("z_userpoints", ["AND"=>$AND]);
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导修改用户信息
   * xxx.com/user/serviceupdateuser POST
   * 
   * name      
   * company   
   * department
   * position  
   * mobile    
   * office    
   * 
   * 返回:用户信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function serviceupdateuser(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid"],"service");
    ASSERT_right($db, $userinfo, "编辑用户信息");
    
    $userid = $query["userid"] + 0;
    
    $data = [
      'name'       => isset($query['name'      ])? $query['name'      ]:"",
      'company'    => isset($query['company'   ])? $query['company'   ]:"",
      'department' => isset($query['department'])? $query['department']:"",
      'position'   => isset($query['position'  ])? $query['position'  ]:"",
      'mobile'     => isset($query['mobile'    ])? $query['mobile'    ]:"",
      'office'     => isset($query['office'    ])? $query['office'    ]:""
      ];
    if(isset($query['serviceEdit']) && is_array($query['serviceEdit'])){
      $attr = QgsModules::GetUser("attr", ["id"=>$userid]);
      $attr = json_decode($attr, true);
      foreach($query['serviceEdit'] as $k=>$v){
        $attr["serviceEdit"][$k] = $v;
      }
      $data["attr"] = cn_json($attr);
    }
    
    QgsModules::UpdateUser($data, ["id"=>$userid]);
    
    return json_OK([]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 赠送用户积分
   * xxx.com/user/senduserpoints POST
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function senduserpoints(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid", "n"],"service");
    ASSERT_right($db, $userinfo, "赠送用户积分");
    if($query['uid'] == $query['userid'])ASSERT_right($db, $userinfo, "超级管理员");

    $db->insert("z_userpoints", [
        "rq"=>G::s_now(), 
        "userid"=>$query['userid'], 
        "n"=>$query['n'], 
        "attr"=>"points", 
        "k1"=>$query['n']>0?"系统赠送":"系统调整",
        "k2"=>$query['uid'],
        "k3"=>$query['uid']]);
    return json_OK([]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 赠送专家积分
   * xxx.com/user/sendexpertpoints POST
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function sendexpertpoints(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid", "n"],"service");
    ASSERT_right($db, $userinfo, "赠送专家积分");
    if($query['uid'] == $query['userid'])ASSERT_right($db, $userinfo, "超级管理员");

    $db->insert("z_userpoints", [
        "rq"=>G::s_now(), 
        "userid"=>$query['userid'], 
        "n"=>$query['n'], 
        "attr"=>"expertpoints", 
        "k1"=>$query['n']>0?"系统赠送":"系统调整",
        "k2"=>$query['uid'],
        "k3"=>$query['uid']]);
    return json_OK([]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导修改用户其它选项
   * xxx.com/user/serviceupdateuseroptions POST
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function serviceupdateuseroptions(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userid", "field", "value"],"service");

    return cn_json(self::update_user_options($db, $query, $userinfo));
  }
  /* -----------------------------------------------------
   * 修改用户其它选项
   * ----------------------------------------------------- */
  public static function update_user_options(&$db, &$query, &$userinfo){
    $userid = $query["userid"];
    $field = $query["field"];
    $value = $query["value"];
    switch($field){
      case "referrer":
        ASSERT_right($db, $userinfo, "设置关联人");
        if($userid == $value)return json_error_arr(2100, "关联人不可以为本人");
        $referrer = QgsModules::HasUser(["id"=>$value]);
        if(!$referrer)return json_error_arr(2100, "关联人不存在");
        //break;
        QgsModules::UpdateUser(["parentid"=>$value], ["id"=>$query["userid"]]);
        return json_OK_arr([]);
      case "alwaysrecivesq":
        ASSERT_right($db, $userinfo, "设置抢答推送");
        break;
    }
    QgsModules::DB()->SaveJson([QgsModules::table_user, "attr", ["id"=>$query["userid"]]], ["options", $query["field"]], $query["value"]);
    return json_OK_arr([]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 编导激活某用户
   * xxx.com/user/activeuser?userid=ID&签名
   * 
   * 返回:用户信息
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function activeuser(&$get, &$post, &$db){
    $query = &$post;
    return self::fn_activeuser($get, $post, $db, 3);
  }
  public static function unactiveuser(&$get, &$post, &$db){
    $query = &$post;
    return self::fn_activeuser($get, $post, $db, 2);
  }
  /* ------------------------------
  更改激活状态
  $module 用户=user，专家=expert，编导=service
  $state  1=未激活，2=取消激活，3=激活
   * ----------------------------- */
  private static function fn_activeuser(&$get, &$post, &$db, $state){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["userid", "usermodule"],"service");
    
    $usermodule = $query["usermodule"];
    $rights = ["user"=>"激活用户","expert"=>"激活专家","service"=>"激活编导"];
    ASSERT_right($db, $me, $rights[$usermodule]);
    
    $userid = $query["userid"] + 0;

    //激活用户，推送“欢迎关注请高手，请更新您的个人信息！”
    if($usermodule=="user" && $state == 3){
      $openid = QgsModules::GetUser("openid", ["id"=>$userid]);
			WX::send_service_text($openid, "欢迎关注请高手，请更新您的个人信息！");
    }

    if($usermodule=="service"){
      ASSERT_right($db, $me, "系统管理员");
      if($state<3){
        //超级管理员不可被取消
        $su = QgsModules::GetUser("id", ["AND"=>["attr[~]"=>'"权限":["超级管理员"', "id"=>$userid]]);
        if($su)return json_error(1009, "企图更改超级管理员权限");
      }
    }
    
    $b = QgsModules::UpdateUser(["{$usermodule}state"=>$state], ["id"=>$userid]);
    
    return json_OK([]);
  }




  /* --------------------------------------------------------------------------------------
   * 更新用户信息
   * -------------------------------------------------------------------------------------- */
  public static function savedatas(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["datas"]);
    $attrs = GetAttr($me, "attr");
    foreach($query["datas"] as $k=>$v){
      $attrs[$k] = $v;
    }
    QgsModules::UpdateUser(["attr"=>cn_json($attrs, JSON_UNESCAPED_UNICODE)], ["id"=>$query["uid"]]);
    return json_OK([]);
  }




  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 更新用户信息
   * xxx.com/user/updateitem
   * 
   * POST 方式
   * @field 字段名
   * @value 字段值
   * 
   * 返回:成功
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function updateitem(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["field", "value"]);

    //步骤2：更新用户数据
    $uid = $query['uid'] + 0;
    $field = $query['field'];
    $value = $query['value'];
    
    switch($field){
      case "nick":
        $nick = trim($value);
        if(strlen($nick)<3)return json_error(1101, "呢称至少3个字符"  );
        if(QgsModules::HasUser(["nock"=>$nick]))return json_error(1101, "呢称已被使用"  );//两个人同时使用一个新呢称？
        if(! QgsModules::UpdateUser([$field=>$value],["id"=>$uid]) )return json_error(1101, "未更改"  );
        break;
      case "name":
        if(strlen(trim($value))==0)return json_error(1101, "不能为空"  );
        if(! QgsModules::UpdateUser([$field=>$value],["id"=>$uid]) )return json_error(1101, "未更改"  );
        break;
      case "mobile":
        $mobile = trim($value);
        if ( QgsModules::HasUser(["AND"=>["mobile"=>$mobile, "id[!]"=>$uid]]) )
          return json_error(1012, "手机号码已注册：$mobile"  );
        if (!$mobile || !preg_match('/^1([0-9\-]{10,11})$/', $mobile))
          return json_error(1013, "手机号码无效"  );
      case "company":
      case "department":
      case "position":
      case "office":
        if(! QgsModules::UpdateUser([$field=>$value],["id"=>$uid]) )return json_error(1101, "未更改"  );;
        break;
      case "nick":
        $nick = trim($value);
        if(strlen($nick)<3)return json_error(1101, "呢称至少3个字符"  );
        if(QgsModules::HasUser(["nock"=>$nick]))return json_error(1101, "呢称已被使用"  );//两个人同时使用一个新呢称？
        if(! QgsModules::UpdateUser([$field=>$value],["id"=>$uid]) )return json_error(1101, "未更改"  );
        break;
      case "address"://地址
      case "favorites"://喜好
      case "majoruser"://用户专业
      case "majorexpert"://专家专业
      case "majorservice"://编导专业
      case "resume"://简历
      case "rank"://头衔
      case "profile"://个人简介 z_userdata
        if(strlen(trim($value))==0)return json_error(1101, "不能为空"  );
        QgsModules::DB()->SaveJson([QgsModules::table_user, "attr", ["id"=>$uid]], [$field], $value);
        break;
      default:
        return json_error(1100, "参数错误"  );
    }
    
    return json_OK([]);
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 待办数量列表
   * xxx.com/user/todocounts?list=LIST 签名
   * 
   * 返回:各种数量
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function todocounts(&$get, &$post, &$db){
    $query = &$post;
    $query["module"] = "";
    $me = ASSERT_query_userinfo($db, $query, []);

    $all_todo_list = $query['list'];
    $R = [];
    if($all_todo_list)foreach($all_todo_list as $c => $todo_list){
      switch($c){
        case "qa-service":
          if($userinfo['servicestate'] != 3)break;
          $R[$c] = class_qa::gettodocount($db, $me, $todo_list, "service");
          break;
      }
    }
    $R["allUser"] = QgsModules::CountUser("id") + 10000;
    $R["allExpert"] = QgsModules::CountUser("id", ["AND"=>["expertstate"=>3]]) + 0;
    $R["allSevvice"] = QgsModules::CountUser("id", ["AND"=>["servicestate"=>3]]) + 0;
    
    return json_OK(["nn"=>$R]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 待办数量列表
   * xxx.com/user/todocounts?list=LIST 签名
   * 
   * 返回:各种数量
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function counttodo($db, $module, $uid, $name){
    switch("$module-$name"){
      case "user-myqa":
        return $db->count("qa_list",["AND"=>["userid"=>$uid, "answerrq[>]"=>"2015-01-01", "satisfy[!]"=>["满意","不满意"]]]);
      case "service-myqa":
        //$db->debug();
        return $db->count("qa_list",["OR" =>[
              "AND #1"=>["rq[>]"=>"2015-01-01", "answerid"=>0], //新提问，未指定专家
              "AND #2"=>["satisfy"=>["满意","不满意"], "filedrq[<]"=>"2015-01-01"] //已评价，未归档
            ]]
          );
      case "service-qanew":
        return $db->count("qa_list",["AND"=>["rq[>]"=>"2015-01-01","OR"=>["AND"=>["type"=>"问答","answerid[<=]"=>0, "answerrq[<]"=>"2015-01-01","pointerrq[<]"=>"2000-01"],"satisfy"=>"不满意"]]]);//新提问，未指定专家
      case "service-qaanswered":
        return $db->count("qa_list",["AND"=>["answerrq[>]"=>"2000-01","satisfy[!]"=>"不满意","type"=>"问答", "satisfyrq[<]"=>"2000-01"]]);
      case "service-qasatisfyed":
        return $db->count("qa_list",["AND"=>["satisfyrq[>]"=>"2000-01", "filedrq[<]"=>"2000-01","type"=>"问答"]]);
      case "service-usernew":
        return QgsModules::CountUser(["AND"=>["userstate"=>1]]);
      case "service-expertnew":
        return QgsModules::CountUser(["AND"=>["expertstate"=>1]]);
      case "service-servicenew":
        return QgsModules::CountUser(["AND"=>["servicestate"=>1]]);
      case "user-allexpert":
      case "expert-allexpert":
      case "service-allexpert":
        return QgsModules::CountUser(["AND"=>["expertstate[>]"=>2]]) + 100;
      case "user-onlineexpert":
      case "expert-onlineexpert":
      case "service-onlineexpert":
        return rand(1,3) + (int)(QgsModules::CountUser(["AND"=>["expertstate[>]"=>2]])/2 + 30); 
      case "expert-myqa":
        return 0;
      case "expert-toanswer":
        //$db->debug();
        return $db->count("qa_list",["AND"=>["answerid[>]"=>0, "answerrq[<]"=>"2015-01"]]);//新提问，未指定专家  , "answerid"=>$uid
      case "expert-answered":
        return $db->count("qa_list",["AND"=>["answerrq[>]"=>"2015-01-01", "satisfy[!]"=>["满意","不满意"]]]);//新提问，未指定专家
      case "expert-satisfyed":
        return 0;
        return $db->count("qa_list",["AND"=>["satisfy"=>["满意","不满意"]]]);//新提问，未指定专家
      default:return 0;
    }
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 积分列表
   * xxx.com/user/pointslist
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function pointslist(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    switch($query['en']){
      case "userpoints":
        $AND = ["userid"=>$query['uid'], "attr"=>["points","prepoints"]];
        break;
      case "expertpoints":
        $AND = ["userid"=>$query['uid'], "attr"=>["expertpoints", "referrerpoints"]];
        break;
      case "all":
      default:
        $AND = ["userid"=>$query['uid'], "attr"=>["points","prepoints"]];
        break;
    }
    $R = $db->select("z_userpoints", ["id","rq","attr","k1","k2","n"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"id DESC"] );
    
    //增加每行的累计值
    if($R){
	    $lastn = $db->sum("z_userpoints", "n", ["AND"=>array_merge($AND,["id[<=]"=>$R[0]['id']])]);
	    //echo $db->last_query();
	    //var_dump( $db->error());
	    foreach($R as $i=>$row){
	      if($i == 0)$R[$i]["lastn"] = $lastn;
	      else $R[$i]["lastn"] = $R[$i-1]["lastn"] - $R[$i-1]["n"];
	    }
    }else{
    	$R=[];
    }
    
    $rowcount = $db->count("z_userpoints", "*",  ["AND"=>$AND]);
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 充值记录
   * xxx.com/user/paylist
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function paylist(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);
    ASSERT_right($db, $userinfo, "查看充值记录");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    
    $AND = ["userid"=>$query['uid'], "successrq[>]"=>"2015-01", "module"=>"qgsrecharge"];
    $R = $db->select("wx_pay", ["rq","n"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"successrq DESC"] );
    $rowcount = $db->count("wx_pay", "*",  ["AND"=>$AND]);
    
    if($R)foreach($R as $i=>$row)$R[$i]['n'] /= 100;
    
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 专家列表
   * xxx.com/user/superiorlist?id=ID&t=TIMESPAN&sign=SIGN
   * 
   * @page     第几页
   * @pagesize 每页几行
   * 
   * 返回:专家列表
   * 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function superiorlist(&$get, &$post, &$db){
    $query = &$get;
    $userinfo = ASSERT_query_userinfo($db, $query, []);
    ASSERT_right($db, $userinfo, "管理专家库");
    
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 3)$pagesize = 3;
    $first = $pagesize * $page;
    
    //$db->debug();
    $AND = ["state"=>null];
    switch($query['searchtype']){
      case "allexpert":
        $AND = [ "id[>]" => 0];  
        break;
      case "newexpert":
        $AND = ["state"=>null];
        break;
      case "usedexpert":
        $AND = ["state[!]"=>null];
        break;
    }
    $arr = $db->select("z_user_sys", "*", ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"id DESC"] );
    //echo $db->last_query();
    $rowcount = $db->count("z_user_sys", "*",  ["AND"=>$AND]);
    
    return json_OK(["superiorlist"=>$arr, "rowcount"=>$rowcount]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取专家库信息
   * xxx.com/user/superiorinfo?eid=EID 
   * 
   * 返回:专家库信息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function superiorinfo(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["eid"]);
    ASSERT_right($db, $me, "管理专家库");

    $userinfo = $db->get("z_user_sys", "*", ["id"=>$query['eid']]);
    
    return json_OK(["userinfo"=>$userinfo]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 更新专家库信息
   * xxx.com/user/updatesuperiorinfo 
   * 
   * 返回:专家库信息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function updatesuperiorinfo(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userinfo"],"service");
    ASSERT_right($db, $userinfo, "管理专家库");

    $r = $db->update("z_user_sys", $query['userinfo'], ["id"=>$query['userinfo']['id']]);
    
    return json_OK([]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 新增专家库信息
   * xxx.com/user/addsuperiorinfo 
   * 
   * 返回:专家库信息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function addsuperiorinfo(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["userinfo"],"service");
    ASSERT_right($db, $userinfo, "管理专家库");

    $eid = $db->insert("z_user_sys", $query['userinfo']);
    
    return json_OK(["eid"=>$eid]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 使用专家库信息
   * xxx.com/user/superioruseeid?eid=EID 
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function superioruseeid(&$get, &$post, &$db){
    $query = &$get;
    ASSERT_query_userinfo($db, $query, ["eid"]);

    //读取专家库信息
    $userinfo = $db->get("z_user_sys", "*", ["id"=>$query['eid']]);
    
    //使用专家库信息
    $attr = ["majorexpert"=>$userinfo["major"]];
    QgsModules::UpdateUser([
        "expertstate"=>3, //设为已激活专家
        "name"       =>$userinfo["name"      ],
        "company"    =>$userinfo["company"   ],
        "department" =>$userinfo["department"],
        "position"   =>$userinfo["position"  ],
        "office"     =>$userinfo["office"    ],
        "mobile"     =>$userinfo["mobile"    ]
      ], ["id"=>$query['uid']]);
    QgsModules::DB()->SaveJson([QgsModules::table_user, "attr", ["id"=>$query['uid']]], ["majorexpert"], $userinfo["major"]);
    
    return json_OK([]);
  }
  /* -----------------------------------------------------
   * 设置我的关联人
   * xxx.com/user/getmyreferrer GET
   * ----------------------------------------------------- */
  public static function setrefereeid(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["rid"]);
    ASSERT_right($db, $me, "设置关联人");
    $rid = $query['rid'] + 0;
    
    $new_rid = self::setreferee($db, $me, $rid);
    if($new_rid == -1)return json_error_arr(2100, "关联人不可以为本人");
    if($new_rid == -2)return json_error_arr(2100, "关联人不存在");

    //读取关联人信息：
    $R = QgsModules::GetWxAndUsers($new_rid);
    return json_OK(["referrer"=>$R]);
  }
  // ------------ 设置关联人，返回新关联人 ------------ 
  public static function setreferee(&$db, $me, $rid){
    $uid = $me['id'] + 0;
    if($uid == $rid)return -1;//json_error_arr(2100, "关联人不可以为本人");
    ParseAttr($me, "attr");
    $has_referee = isset($me["options"], $me["options"]["referrer"]);
    //如果已有关联人，则直接返回关联人
    if($me['parentid']>0){
      return $me["parentid"];
    }
  
    //如果已激活，则不再关联：
    if(isset($me['expertstate']) && $me['expertstate'] >= 3)
      return 0;
    //如果未激活，则关联：
    $referrer = QgsModules::HasUser(["id"=>$rid]);
    if(!$referrer)return -2;//json_error_arr(2100, "关联人不存在");
    QgsModules::UpdateUser(["parentid"=>$rid], ["id"=>$me["id"]]);
    return $rid;
  }

  /* -----------------------------------------------------
   * 获取关联我的人
   * xxx.com/user/getmyreferrer GET
   * ----------------------------------------------------- */
  public static function getmyreferrer(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, []);
    if($me["parentid"] + 0 <= 0)return json_OK(["referrer"=>[]]);
    $userid = $me["parentid"] + 0;
    $R = QgsModules::GetWxAndUsers($userid);
    return json_OK(["referrer"=>$R]);
  }
  /* -----------------------------------------------------
   * 获取我 (uid = xxx) 的关联人-列表
   * xxx.com/user/getmyreferedlist GET
   * ----------------------------------------------------- */
  public static function getmyreferedlist(&$get, &$post, &$db){
    $query = &$post;
    $uid = trim($query['uid']);
    return self::referedlist_of_userid($get, $post, $db, $uid);
  }
 
  /* -----------------------------------------------------
   * 获取 (userid = xxx) 的关联人-列表
   * xxx.com/user/getreferedlist GET
   * ----------------------------------------------------- */
   public static function getreferedlist(&$get, &$post, &$db){
    $query = &$post;
    $userid = trim($query['userid']);
    return self::referedlist_of_userid($get, $post, $db, $userid);
   }
   
  //获取 关联人 列表 共用函数
  public static function referedlist_of_userid(&$get, &$post, &$db, $userid){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);

    //分析页码
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 10;
    $first = $pagesize * $page;

    $AND = [QgsModules::table_user . ".parentid"=>$userid];
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
    $R = QgsModules::SelectWxAndUsersByFields(
      ["id", "name", "userstate", "expertstate", "servicestate"],
      ["headimgurl","nickname"],
      ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>QgsModules::table_user.".id DESC"]);

//var_dump($db->last_query());
//var_dump($db->error());

    $rowcount = QgsModules::CountUser(["AND"=>$AND]);
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }
  /* -----------------------------------------------------
   * 获取我的关联人-概况
   * xxx.com/user/>getmyreferedinfo GET
   * ----------------------------------------------------- */
  public static function getmyreferedinfo(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, []);
    
    $totle = QgsModules::CountUser(["AND"=>["parentid"=>$query['uid']]]);
    return json_OK(["info"=>["totle"=>$totle]]);
  }
  /* -----------------------------------------------------
   * 编导获取 userid=xxx 的 关联人 的头像等帐户信息
   * xxx.com/user/servicegetreferrer GET
   * ----------------------------------------------------- */
  public static function servicegetreferrer(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["userid"], "service");
    //ASSERT_right($db, $me, "关联人"); //再添加这个权限就可以启用
    $userid = $query["userid"] + 0;
    $parentid = QgsModules::GetUser("parentid", ["id"=>$userid]) + 0;
    if(!$parentid)return  json_OK(["referrer"=>[]]);
    $R = QgsModules::SelectWxAndUsersByFields(
      ["id", "name"],
      ["headimgurl","nickname"],
      [QgsModules::table_user.".id"=>$parentid]);
    return json_OK(["referrer"=>$R[0]]);
  }
  /* -----------------------------------------------------
   * 编导用户活跃度
   * xxx.com/user/gettrace POST
   * ----------------------------------------------------- */
  public static function gettrace(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);
    ASSERT_right($db, $me, "查看活跃度");
    $userid = $query["userid"] + 0;

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    
    $OR = ["userid"=>$userid];
    $R = $db->select("log_api", ["rq", "api", "call"], ["OR"=>$OR, "LIMIT"=>[$first, $pagesize], "ORDER"=>"id DESC"]);
    
    $rowcount = $db->count("log_api", ["OR"=>$OR]);
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }
  
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃               获取多项数据                                                   ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  public static function servicegetarray(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["data"], "service");
    return json_OK(["D"=>self::get_servic_data($db, $query['data'])]);
  }
  public static function get_servic_data(&$db, $data){
    $R = [];
    foreach($data as $item=>$value){
      switch($item){
        case "roles":
          $R[$item] = json_decode($db->get("g_values", "attr", ["k1"=>"SYSROLES"]), true);
          break;
        case "rights":
          $R[$item] = ["角色权限管理", "重置密码",
            "查看充值记录", "发放专家酬金", "赠送用户积分", "赠送专家积分", 
            "设置关联人", "激活用户", "激活专家", "激活编导", "编辑用户信息", "删除用户", "恢复用户",
            "推送消息", "查看活跃度", "问答管理", "论坛管理", "推荐帖子", "管理专家库", "管理知识库", "回收站"];
          break;
        case "userright":
          $attr = json_decode(QgsModules::GetUser("attr", ["id"=>$value]));
          $R[$item] = $attr['d']['权限'];
          break;
        case "user":
          $user_row = QgsModules::GetUser("*", ["id"=>$value['id']]);
          $attr = json_decode($user_row["attr"], true);
          $R[$item] = [];
          foreach($value['sub'] as $sub){
            switch($sub){
              case "jbxx":
                $wx = QgsModules::GetWxUser(["headimgurl","nickname"], ["openid"=>$user_row['openid']]);
                $R[$item][$sub] = ["id"=>$user_row['id'], "name"=>$user_row['name'],"headimgurl"=>$wx['headimgurl'],"nickname"=>$wx['nickname']];
                break;
              case "rights":
                $R[$item][$sub] = $attr['d']['权限'];
                break;
            }
          }
          break;
      }
    }
    return $R;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃               角色权限                                                       ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  /* -----------------------------------------------------
   * 添加角色
   * ----------------------------------------------------- */
  public static function addrole(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["name"]);
    ASSERT_right($db, $userinfo, "角色权限管理");

    $roles = json_decode($db->get("g_values", "attr", ["k1"=>"SYSROLES"]), true);
    $name = trim($query['name']);
    //不能重名
    foreach($roles as $role)if($role["name"] == $name)return json_error(2222, "角色名称已存在");
    //添加：
    $roles[] = ["name"=>$name, "rights"=>[]];
    $db->update("g_values", ["attr"=>cn_json($roles)], ["k1"=>"SYSROLES"]);
    return json_OK([]);
  }
  /* -----------------------------------------------------
   * 删除角色
   * ----------------------------------------------------- */
  public static function deleterole(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["name"]);
    ASSERT_right($db, $userinfo, "角色权限管理");

    $roles = json_decode($db->get("g_values", "attr", ["k1"=>"SYSROLES"]), true);
    $name = trim($query['name']);
    //找一找
    foreach($roles as $i=>$role)if($role["name"] == $name){
      unset($roles[$i]);
      $db->update("g_values", ["attr"=>cn_json($roles)], ["k1"=>"SYSROLES"]);
      return json_OK([]);
    }
    return json_error(2222, "角色名称已存在");
  }
  /* -----------------------------------------------------
   * 删除角色
   * ----------------------------------------------------- */
  public static function renamerole(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["name", "newname"]);
    ASSERT_right($db, $userinfo, "角色权限管理");

    $roles = json_decode($db->get("g_values", "attr", ["k1"=>"SYSROLES"]), true);
    $name = trim($query['name']);
    $newname = trim($query['newname']);
    //找一找
    foreach($roles as $i=>$role)if($role["name"] == $name){
      $roles[$i]['name'] = $newname;
      $db->update("g_values", ["attr"=>cn_json($roles)], ["k1"=>"SYSROLES"]);
      return json_OK([]);
    }
    return json_error(2222, "角色名称已存在");
  }
  /* -----------------------------------------------------
   * 更新角色的权限列表
   * ----------------------------------------------------- */
  public static function saverole(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["name", "rights"]);
    ASSERT_right($db, $userinfo, "角色权限管理");

    $roles = json_decode($db->get("g_values", "attr", ["k1"=>"SYSROLES"]), true);
    $name = trim($query['name']);
    $rights = $query['rights'];
    //找一找
    foreach($roles as $i=>$role)if($role["name"] == $name){
      $roles[$i]['rights'] = $rights;
      $db->update("g_values", ["attr"=>cn_json($roles)], ["k1"=>"SYSROLES"]);
      return json_OK([]);
    }
    return json_error(2222, "角色名称不存在");
  }
  /* -----------------------------------------------------
   * 保存用户权限
   * ----------------------------------------------------- */
  public static function saveuserright(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["user"]);
    ASSERT_right($db, $userinfo, "角色权限管理");
    $postuser = $query['user'];
    $postid = $postuser['id'] + 0;
    //超级管理员的权限不被修改！
    QgsModules::DB()->SaveJson([QgsModules::table_user, "attr", ["AND"=>["id"=>$postid, "OR"=>["attr[!~]"=>"超级管理员","attr"=>NULL]]]], ["d", "权限"], $postuser['rights']);
    return json_OK([]);
  }

  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃               微信部分                                                       ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

  /* -----------------------------------------------------
   * 根据微信OAuth2的code换取openid
   * xxx.com/user/codetoopenid?code=CODE
   * 
   * @CODE 微信OAuth2的code
   * 
   * 返回:openid
   * 
   * ----------------------------------------------------- */
  public static function codetoopenid(&$get, &$post, &$db){
    $query = &$get;
    $code = $query['code'];
    $openid = WX::getOpenid($code);
//var_dump($openid);
    if(!$openid) return json_error(7012, "getOpenid($code)=false");
    return json_OK(["openid"=>"$openid"]);
  }
  /* -----------------------------------------------------
   * 根据微信OAuth2的code获取用户信息
   * xxx.com/user/codetouserinfo?code=CODE
   * 
   * @CODE 微信OAuth2的code
   * 
   * 返回:用户信息
   * 
   * ----------------------------------------------------- */
  public static function codetouserinfo(&$get, &$post, &$db){
    $query = &$get;
    $code = $query['code'];
    $openid = WX::getOpenid($code);
    if(!$openid) return json_error(7012, "getOpenid($code)=false");
    $arr = QgsModules::SelectUser(USER::$ShowInfos, ["openid"=>$openid]);
    //var_dump($arr);
    if(!isset($arr[0], $arr[0]['id'])){
      //生成一个新用户：
      $id = QgsModules::InsertUser(["openid"=>$openid, "userstate"=>0, "password"=>""]);
      $arr=[["id"=>$id, "openid"=>$openid]];//不设密码，只能在微信上登陆！
    }
    return json_OK(["userinfo"=>$arr[0]]);
  }
}


?>