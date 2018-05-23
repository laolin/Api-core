<?php
  require_once("app/user.class.php");
  require_once("app/g.php");
  require_once("app/wx.php");
  require_once("api_wx.php"); //微信语音功能等

define("ANSWER_BY_KL", 110001);
/*
DROP TABLE IF EXISTS `qa_data`;
CREATE TABLE `qa_data` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `parentid`   int(11) DEFAULT '0',
  `userid`     int(11) DEFAULT 0,
  `rq`         varchar(32) DEFAULT '',       -- 大于 '1999-99' 才是有效的
  `senderid`   int(11) DEFAULT 0,
  `rq_send`    varchar(32) DEFAULT '',  -- 回答中为编导找专家时间。提问中冗余，用于快速识别当前有有效专家在回答
  `rq_timer`   varchar(32) DEFAULT '',
  `rq_delete`  varchar(32) DEFAULT '',
  `attr`       text,
  `state`      varchar(32) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `userid` (`userid`),
  KEY `rq_timer` (`rq_timer`),
  KEY `state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



 * 
 * ----------------------------------------*/



class class_qa{

    
  /*/处理问或答的属性数据：json转数组，处理声音等
  private static function  process_attr($db, &$attr, $column){
    if(!isset($attr[$column]) || !$attr[$column]){
      $attr[$column] = [];
      return;
    }
    $v = json_decode($attr[$column], true);
    if(isset($v['audios']) && $v['audios'])$v["audioslist"] = class_wx::str_audios_to_list($db, $v['audios']);
    $attr[$column] = $v;
  }//*/
  //处理问或答的属性数据：json转数组，处理声音等
  private static function  process_attr(&$db, &$row){
    if(!$row['attr']){
      $row['attr'] = [];
      return; 
    }
    if(!is_array($row['attr']))$row['attr'] = json_decode($row['attr'], true);
    if(isset($row['attr']['audios']) && $row['attr']['audios'])$row['attr']["audioslist"] = class_wx::str_audios_to_list($db, $row['attr']['audios']);
  }
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取问答详情
   * xxx.com/qa/getdetail (GET、身份识别)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getdetail(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["qaid"]);
    //ASSERT_moduleactive();

    //步骤2：读取数据
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    
    $rows = $db->select("qa_data", "*", ["OR"=>["id"=>$qaid, "parentid"=>$qaid], "ORDER"=>"id"]);
    if(!$rows)return json_error(3000, "no qa!");

    $userids = [];
    $R = [];
    switch($query['module']){
      case "user":
        //可见范围：
        $ks=[];//当前的一组问答
        foreach($rows as $k=>$row){
          //提问，可见：
          if(!$k){
            self::process_attr($db, $rows[$k]);
            $R['jbxx'] = $rows[$k];
            continue;
          }
          //放弃回答，用户不可见，直接删除
          if($row['state'] == "放弃回答") continue;
          //回答超时，用户不可见，直接删除
          if($row['state'] == "回答超时") continue;
          //已收集的，是可见的：
          $ks[] = $k;
          if(in_array($row['state'], ["待评价", "评价满意", "评价不满意"])){
            foreach($ks as $k){
              self::process_attr($db, $rows[$k]);
              $R['answers'][] = $rows[$k];
            }
            $ks = [];
            continue;
          }          
        }
        break;
      case "expert":
        foreach($rows as $k=>$row){
          self::process_attr($db, $rows[$k]);
          if(!$k)$R['jbxx'] = $rows[$k];
          else $R['answers'][] = $rows[$k];
          //当前的用户id
          $userids[$row['userid'  ]] = $row['userid'  ];
          $userids[$row['senderid']] = $row['senderid'];
        }
        break;
      case "service":
        ASSERT_right($db, $me, "问答管理");
        foreach($rows as $k=>$row){
          self::process_attr($db, $rows[$k]);
          if(!$k)$R['jbxx'] = $rows[$k];
          else $R['answers'][] = $rows[$k];
          //当前的用户id
          $userids[$row['userid'  ]] = $row['userid'  ];
          $userids[$row['senderid']] = $row['senderid'];
        }
        //归档情况
        $kl = $db->get("knowledge_lib", "*", ["AND" => ["qaid"=>$qaid, "type"=>"问答"]]);
        if($kl){
          $R['fileinfo'] = ["includeid"=>$kl['includeid']];//用于识别是否可以进行归档操作
          $R['fileinfo']['keywords'] = $db->select("knowledge_keys", "key", ["klid"=>$kl['id']]);
        }
        break;
    }
    //所有头像：
    $arr = QgsModules::GetWxAndUsers($userids);
    if($arr)foreach($arr as $row)$R['users'][$row['id']] = $row;

    return json_OK(["qadetail"=>$R]);
  }


  /* -----------------------------------------------------------------
   * 请求正在提问的草稿，没有的要新建一个(每种类型的草稿保存一个)
   * xxx.com/qa/usertoask (GET、身份识别)
   * ----------------------------------------------------------------- */
  public static function usertoask(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, []);
    $uid = $query['uid'] + 0;

    //当前有个漏洞：用户可以随便灌水，限制每天提问数量？

    $attr = json_decode($userinfo["attr"], true);
    $majoruser = isset($attr['majoruser']) ? $attr['majoruser'] : "";
    $R = $db->get("qa_data", "*", ["AND"=>["parentid"=>0, "userid"=>$query['uid'], "rq[<]"=>"1999-99"]]);
    if($R){
      $qaid = $R['id'];
      self::process_attr($db, $R);
    }else{
      $attr=["easy"=>"", "timelimit"=>"", "major"=>$majoruser];
      $qaid = $db->insert("qa_data", ["parentid"=>0, "userid"=>$uid, "rq"=>"", "attr"=>cn_json($attr)]);
      $R = ["id"=>$qaid, "userid"=>$uid, "attr"=>$attr];
    }
    return json_OK(["qadetail"=>["jbxx"=>$R]]);
  }
  /* -----------------------------------------------------------------
   * 修改草稿的某项
   * xxx.com/qa/updatedraft (POST、身份识别)
   * ----------------------------------------------------------------- */
  public static function updatedraft(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["qaid", "field", "value"]);

    //当前有个漏洞：用户可以随便灌水，需要判断是否是本人提问

    $qaid = $query['qaid'] + 0;
    $field = $query['field'];
    $value = $query['value'];
    
    switch($field){
      case "content":
      case "major":
      case "timelimit":
      case "images":
      case "audios":
      case "easy":
      case "tags":
        $db->UpdateJson("qa_data", "attr", $field, $value, ["id"=>$qaid]);
        break;
      case "wxappendimage": // 这个是为了将上传图像（class_wx）和修改提问（这里）合并成一个请求。
        //先获取图像：
        $fn = class_wx::imageid2local($query, $db);
        //原来有没有图像？
        $db->UpdateJson("qa_data", "attr", "images", $fn, ["id"=>$qaid], ",");
        return json_OK(["imageurl"=>$fn]);
      default:
        return json_error(1100, "参数错误"  );
    }
    return json_OK([]);
  }
  /* -----------------------------------------------------------------
   * 发出提问
   * xxx.com/qa/toask (POST、身份识别)
   * ----------------------------------------------------------------- */
  public static function toask(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["qaid"]);
    $qaid = $query['qaid'] + 0;
    $uid = $query['uid'] + 0;

    $attr = $db->get("qa_data", "attr", ["AND"=>["parentid"=>0, "userid"=>$uid, "id"=>$qaid, "rq[<=]"=>"1999-99"]]);
    if(!$attr)return json_error(1000, "没有找到草稿,$qaid");
    $attr = json_decode($attr, true);
    //专业:
    $allMajor = ['混凝土结构','砌体结构','钢结构','组合结构、混合结构、复杂结构','桥梁工程','岩土、地下、隧道工程','土木工程施工与管理','环境科学与工程','其它'];
    if(!in_array($attr['major'], $allMajor))return json_error(1000, "请选择专业",["field"=>"major"]);

    //正文:
    if(strlen($attr['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content"]);
    
    //检查其它数据：
    $neasy = $easy = $attr['easy'] + 0;if($easy<0)return json_error(2101, "出价不低于0元" ,["field"=>"easy"] );
    $timelimit = $attr['timelimit'] + 0;if($timelimit<1)return json_error(2101, "时效不低于1天" ,["field"=>"timelimit"] );
    if($timelimit>30)return json_error(2101, "时效不超过30天" ,["field"=>"timelimit"] );

    //预扣积分
    $db->insert("z_userpoints", ["rq"=>API_now(), "n"=>-$neasy, "userid"=>$uid, "attr"=>"prepoints", "k1"=>"积分预授权", "k2"=>$qaid]);
    //标志为已提问
    $db->update("qa_data", ["rq"=>API_now(), "rq_timer"=>API_now(600), "rq_send"=>"新提问"], ["id"=>$qaid]);//10分钟后触发

    return json_OK(["qaid"=>$qaid]);
  }
  /* ---------------------------------------------------------
   * 直接退回，不管草稿
   * --------------------------------------------------------- */
  public static function diredtbacktoask(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "data"], "service");
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;

    //验证数据:
    $data = $query["data"];
    if(!isset($data['content']) || strlen($data['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content"]);

    $backdata = ["id"=>$uid, "rq"=>API_now(), "ac"=>"编导退回", "content"=>$data['content']];
    if(isset($data["images"]))$backdata["images"] = $data['images'];
    if(isset($data["audios"]))$backdata["audios"] = $data['audios'];

    $attr = $db->get("qa_data", "attr", ["AND"=>["parentid"=>0, "id"=>$qaid, "rq[>]"=>"1999-99"]]);
    if(!$attr)return json_error(2211, "未退回");
    $attr = json_decode($attr, true);
    $attr["all_modify"][] = $backdata;

    $db->update("qa_data", ["rq"=>"1999-99", "rq_timer"=>"9999-99", "attr"=>cn_json($attr)], ["id"=>$qaid]);//退回操作不设延时，不可收回，直接通知用户

    //推送消息，退回提问：
    //

    return json_OK([]);
  }

  /* ------------------------------------------------------
   * 修改回答草稿的某项
   * xxx.com/qa/updateanswerdraft (POST、身份识别)
   * ------------------------------------------------------ */
  public static function updateanswerdraft(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "field", "value"]);
    ASSERT_module($me, "expert");

    $qaid = $query['qaid'] + 0;
    $uid = $query['uid'] + 0;
    $field = $query['field'];
    $value = $query['value'];

    $answer = $db->get("qa_data", "*", ["AND"=>["id"=>$qaid, "userid"=>$uid, "rq[<]"=>"2015-01", "state"=>"待回答"]]);
    if(!$answer)return json_error(1100, "不可回答"  );

    switch($field){
      case "content":
      case "images":
      case "audios":
        $db->UpdateJson("qa_data", "attr", $field, $value, ["id"=>$answer['id']]);
        break;
      case "wxappendimage": // 这个是为了将上传图像（class_wx）和修改提问（这里）合并成一个请求。
        $fn = class_wx::imageid2local($query, $db);
        $db->UpdateJson("qa_data", "attr", "images", $fn, ["id"=>$answer['id']], ",");
        return json_OK(["imageurl"=>$fn]);
      default:
        return json_error(1100, "参数错误"  );
    }

    return json_OK([]);
  }

  /* ------------------------------------------------------
   * 进行回答
   * xxx.com/qa/toanswer (POST、身份识别)
   * ------------------------------------------------------ */
  public static function toanswer(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["qaid"]);

    $qaid = $query['qaid'] + 0;
    $uid = $query['uid'] + 0;
    
    $row = $db->get("qa_data", ["id", "attr", "rq"], ["AND"=>["id"=>$qaid, "parentid[>]"=>0, "userid"=>$uid, "rq[<]"=>"2015-01", "state"=>"待回答"]]);
    //var_dump($db->last_query());    var_dump($db->error());
    if(!$row)return json_error(2101, "未回答" ,["field"=>"answer"]);
    $answer = json_decode($row['attr'], true);
    if(!isset($answer['content']) || strlen($answer['content'])<3)return json_error(2101, "回答正文至少3个字符" ,["field"=>"answer"] );
    //标志为已回答：
    $db->update("qa_data", ["rq"=>API_now(), "rq_timer"=>API_now(600), "state"=>'已回答'], ["id"=>$qaid]);//10分钟后触发
    return json_OK([]);
  }
  
  /* ------------------------------------------------------
   * 专家放弃回答
   * xxx.com/qa/nottoanswer (POST、身份识别)
   * ------------------------------------------------------ */
  public static function  nottoanswer(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["qaid", "text"], "expert");

    //处理数据
    $uid  = $query['uid' ] + 0;
    $qaid = $query['qaid'] + 0;
    $answer = $db->get("qa_data", ["id", "attr", "rq"], ["AND"=>["id"=>$qaid, "parentid[>]"=>0, "userid"=>$uid, "rq[<]"=>"2015-01", "state"=>"待回答"]]);
    if(!$answer)return json_error(2101, "放弃回答错误"); 

    //保存数据
    $attr = json_decode($answer['attr'], true);
    $attr['reason'] = $query['text'];
    $db->update("qa_data", ["state"=>"放弃回答", "rq"=>API_now(), "rq_timer"=>API_now(600), "attr"=>cn_json($attr)], ["id"=>$qaid]);
    return json_OK([]);
  }
  /* -----------------------------------------------------
   * 满意度反馈
   * xxx.com/qa/saysatisfy (POST、身份识别)
   * ----------------------------------------------------- */
  public static function saysatisfy(&$get, &$post, &$db){
    $query = &$post;
    $userinfo = ASSERT_query_userinfo($db, $query, ["qaid","howsatisfy"]);
    $uid = $query['uid'] + 0;
    $satisfy_id = $query['qaid'] + 0;//API请求时都用qaid,请小心！

    //是本人的提问：
    $satisfy_row = $db->get("qa_data", "*", ["AND"=>["parentid[>]"=>0, "id"=>$satisfy_id, "userid"=>$uid, "state"=>"待评价", "rq[<]"=>"2015-01"]]);
    if(!$satisfy_row)return json_error(1000, "参数错误：qaid");
    $qaid = $satisfy_row['parentid'] + 0;
    $qa = $db->get("qa_data", "*", ["AND"=>["id"=>$satisfy_row['parentid'], "userid"=>$uid]]);
    if(!$qa)return json_error(1000, "非本人提问");

    //步骤2：检查数据
    $howsatisfy = trim($query['howsatisfy']);
    if(!in_array($howsatisfy, ["满意","不满意"]))return json_error(2101, "不合法满意度。");
    $answer_AND = ["parentid"=>$qaid, "rq[>]"=>"2015-01", "state"=>"已回答"];//有些放弃回答的，就不管了。
    $answers = $db->select("qa_data", "*", ["AND"=>$answer_AND]);
    if(!is_array($answers) || count($answers)==0)return json_error(2005, "无未评价的回答");

    //步骤3：保存满意度
    $db->update("qa_data", ["rq"=>API_now(), "rq_timer"=>API_now(600), "state"=>"评价$howsatisfy"], ["id"=>$satisfy_id]);//10分钟后触发定时

    return json_OK([]);
  }


  /* --------------------------------------------------
   * 指定专家
   * -------------------------------------------------- */
  public static function pointanswer(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "answerid"],"service");
    ASSERT_right($db, $me, "问答管理");

    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    $answerid = $query['answerid'] + 0;
    if($answerid == 0) return json_error(1000, "未指定一个专家"  );

    //验证问答状态
    $qa = $db->get("qa_data", "*", ["AND"=>["id"=>$qaid, "parentid"=>0, "rq[>]"=>"2000-01"]]);
    if(!is_array($qa)) return json_error(1000, "请求无效");
    //最后一行回答的state：
    $last_state = $db->get("qa_data", "state", ["AND"=>["parentid"=>$qaid], "ORDER"=>"id DESC"]);
    if($last_state && strpos($last_state, "评价") > 0)return json_error(2200, "问答状态错误：不允许找专家。");
    //不重复指定:
    if($db->has("qa_data", ["AND"=>["parentid"=>$qaid, "userid"=>$answerid, "state"=>["待回答", "已回答", "放弃回答", "用户不满意"]]])) return json_error(2200, "该专家已指定，或已放弃回答");
    //增加一行数据，让专家回答：
    $db->insert("qa_data", ["parentid"=>$qaid, "senderid"=>$uid, "rq_send"=>API_now(), "userid"=>$answerid, "state"=>"待回答"]);

    //推送消息：
    $attr = json_decode($qa['attr'], true);
    $content = $attr['content'];
    $ask_id = !$last_state || strpos($last_state, "评价") > 0 ? $qa['userid'] : 0;//本组回答已找过专家的，不再通知提问者
    class_wx::notice_toanswer($db, $qaid, $content, $answerid, $ask_id);

    return json_OK([]);
  }

  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 匹配知识库
   * xxx.com/qa/getklmatchlist (POST)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function getklmatchlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "page","pagesize"], "service");
    ASSERT_right($db, $me, "问答管理");

    //读取数据、翻页要求
    $qaid = $query['qaid'] + 0;
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 3)$pagesize = 3;
    $first = $pagesize * $page;

    //步骤1：读取所有标签
    $keys_arr = $db->select("knowledge_keys", ["klid", "key"]);

    foreach($keys_arr as $row){
      $keys[$row['key']][] = $row['klid'];
    }

    //步骤2：匹配所有标签
    $content = json_decode($db->get("qa_data", "attr", ["id"=>$qaid]), true)["content"];
//var_dump($keys);
    foreach($keys as $key=>$klids){
      if(strpos($content, $key) !== false){
        foreach($klids as $klid){
          if(!isset($kl["a$klid"]))$kl["a$klid"] = 0;
          $kl["a$klid"] ++;
        }
      }
    }
    if(!isset($kl))return json_OK(["list"=>[], "rowcount"=>0]);//没有任何匹配的标签

    //步骤3：排序，获取指定页的id列表
    arsort($kl);
    $klids = array_keys($kl);
    $klid_page = array_slice($klids, $first, $pagesize);
    foreach($klid_page as $k=>$v)$klid_page[$k] = substr($v, 1);//清除前面的"a"

    //步骤4：读取知识库
    $AND = ["id"=>$klid_page];
    $list = $db->select("knowledge_lib", "*", $AND);
    $rowcount = count($kl);
    
    return json_OK(["list"=>$list, "rowcount"=>$rowcount]);
  }
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 使用知识库进行回答
   * xxx.com/qa/answerfromkl (POST、身份识别)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function answerfromkl(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "klid"], "service");
    ASSERT_right($db, $me, "问答管理");

    $qaid = $query['qaid'] + 0;
    $klid = $query['klid'] + 0;
    $uid = $query['uid'] + 0;

    //判断知识库合法：
    $kl_row = $db->get("knowledge_lib", "*", ["AND"=>["id"=>$klid]]);
    if(!$kl_row)return json_error(2101, "指定的知识库错误");

    //判断知识库的回答合法：
    $aa = $kl_row['type'] == "问答" ? 'answer' : 'ask';
    $answer = $kl_row[$aa];
    if(strlen($answer)<3)return json_error(2101, "指定的知识库回答正文不足3个字符");

    //生成回答数据：
    $now = API_now();
    $attr = ["content"=>$answer];
    if($kl_row[$aa."audios"])$attr['audios'] = $kl_row[$aa."audios"];
    if($kl_row[$aa."images"])$attr['images'] = $kl_row[$aa."images"];
    $db->insert("qa_data", [
        "parentid"=>$qaid,
        "senderid"=>$me['id'],
        "rq_send"=>$now,
        "userid"=>ANSWER_BY_KL,
        "state"=>"已回答",
        "rq"=>$now,
        "attr"=>json_encode($attr, JSON_UNESCAPED_UNICODE)
      ]);
    return json_OK([]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 推荐专家
   * xxx.com/qa/recommendexpert (POST)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function recommendexpert(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "page","pagesize"], "service");
    ASSERT_right($db, $me, "问答管理");

    //步骤2：读取数据、翻页要求
    $qaid = $query['qaid'] + 0;
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 3)$pagesize = 3;
    $first = $pagesize * $page;
  
    //步骤3：读取所有专家
    $AND = [QgsModules::table_user . ".expertstate[>=]"=>3];

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


    // 获取专业
    $attr = $db->get("qa_data", "attr", ["id"=>$qaid]);
    $attr = json_decode($attr, true);
    $major = $attr["major"];

    //准备初始数据
    $experts = [];
    // 优先找：专业对口的专家
    if(1){
      $AND_here = ["AND"=>$AND, "attr[~]"=>'"serviceEditExpertMajor_":"'. $major . '"'];
      $thisCount = QgsModules::CountUser(QgsModules::join_wxuser(), QgsModules::table_user . ".id", ["AND"=>$AND_here]);
      //error_log("对口 = $thisCount");
      if($first < $thisCount){
        $experts = QgsModules::SelectWxAndUsersByFields(
          ["id", "name", "attr", "mobile", "userstate", "expertstate", "servicestate"],
          ["headimgurl","nickname"],
          ["AND"=>$AND_here, "LIMIT"=>[$first, $pagesize], "ORDER"=>QgsModules::table_user.".logintime DESC"]);
      }
      $pagesize = $first < $thisCount ? $pagesize + $first - $thisCount : $pagesize;
      $first -= $thisCount;
      if($first < 0)$first = 0;
    }


    //再找有设置专业（但不对口）的专家
    if($pagesize > 0){
      // 专业对口的专家优先，先查下有几个同专业的专家
      $AND_here = ["AND"=>$AND, "attr[~]"=>'"serviceEditExpertMajor_":"', "attr[!~]"=>'"serviceEditExpertMajor_":"'. $major . '"'];
      $thisCount = QgsModules::CountUser(QgsModules::join_wxuser(), QgsModules::table_user . ".id", ["AND"=>$AND_here]);
      //error_log("专业但不对口 = $thisCount");
      if($first < $thisCount){
        $arr = QgsModules::SelectWxAndUsersByFields(
          ["id", "name", "attr", "mobile", "userstate", "expertstate", "servicestate"],
          ["headimgurl","nickname"],
          ["AND"=>$AND_here, "LIMIT"=>[$first, $pagesize], "ORDER"=>QgsModules::table_user.".logintime DESC"]);
        $experts = array_merge($experts, $arr);
      }
      $pagesize = $first < $thisCount ? $pagesize + $first - $thisCount : $pagesize;
      $first -= $thisCount;
      if($first < 0)$first = 0;
    }


    //如果还要非专业（没有任何专业）的专家
    if($pagesize > 0){
      $AND_here = ["AND"=>$AND, "OR"=>["attr[!~]"=>'"serviceEditExpertMajor_":"', "attr"=>null]];
      $thisCount = QgsModules::CountUser(QgsModules::join_wxuser(), QgsModules::table_user . ".id", ["AND"=>$AND_here]);
      //error_log("非专业 = $thisCount");
      if(1){
        $arr = QgsModules::SelectWxAndUsersByFields(
          ["id", "name", "attr", "mobile", "userstate", "expertstate", "servicestate"],
          ["headimgurl","nickname"],
          ["AND"=>$AND_here, "LIMIT"=>[$first, $pagesize], "ORDER"=>QgsModules::table_user.".logintime DESC"]);
        $experts = array_merge($experts, $arr);
      }
    }

    foreach($experts as $i=>$row){
      $attr = json_decode($row["attr"], true);
      $experts[$i]["attr"] = [];
      if($attr["serviceEdit"]["serviceEditExpertMajor1"]) $experts[$i]["attr"]["major1"] = $attr["serviceEdit"]["serviceEditExpertMajor1"];
      if($attr["serviceEdit"]["serviceEditExpertMajor2"]) $experts[$i]["attr"]["major2"] = $attr["serviceEdit"]["serviceEditExpertMajor2"];
      if($attr["serviceEdit"]["serviceEditExpertMajor3"]) $experts[$i]["attr"]["major3"] = $attr["serviceEdit"]["serviceEditExpertMajor3"];
    }

    $rowcount = QgsModules::CountUser(QgsModules::join_wxuser(), QgsModules::table_user . ".id", ["AND"=>$AND]);
    //error_log("全部 = $rowcount");
    return json_OK(["list"=>$experts, "rowcount"=>$rowcount]);
  }

  /* ------------------------------------------------
   * 问题归档前，增加一个关键词
   * xxx.com/qa/addkeyword (POST、身份识别)
   * ------------------------------------------------ */
  public static function addkeyword(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "text"],"service");
    ASSERT_right($db, $me, "问答管理");

    $qaid = $query['qaid'] + 0;
    $uid = $query['uid'] + 0;
    $keytext = trim($query['text']);

    //确认可以对问答进行操作，不可多个编导同时进行
    $kl = $db->get("knowledge_lib", "*", ["AND" => ["qaid"=>$qaid, "type"=>"问答"]]);
    if(!$kl){
      //新建一个知识库，占个粪坑再拉屎
      $qa = $db->select("qa_data", "*", ["OR"=>["id"=>$qaid, "AND"=>["parentid"=>$qaid, "state"=>"用户满意"]], "ORDER"=>"id ASC"]);
      $n_answer = count($qa) - 1;
      if($n_answer < 1)return json_error(3001, "该问答不可归档");
      foreach($qa as $k => $row) $qa[$k]["attr"] = json_decode($row["attr"], true);
      $attr = [];
      if($qa[0]['attr']["content"])$attr["ask"   ]   ["content"] = $qa[0]['attr']["content"];
      if($qa[0]['attr']["audios" ])$attr["ask"   ]   ["audios" ] = $qa[0]['attr']["audios" ];
      if($qa[0]['attr']["images" ])$attr["ask"   ]   ["images" ] = $qa[0]['attr']["images" ];
      for($i=1; $i<=$n_answer; $i++){
        $answer = [];
        if($qa[$i]['attr']["content"])$answer["content"] = $qa[$i]['attr']["content"];
        if($qa[$i]['attr']["audios" ])$answer["audios" ] = $qa[$i]['attr']["audios" ];
        if($qa[$i]['attr']["images" ])$answer["images" ] = $qa[$i]['attr']["images" ];
        $attr["answer"][] = $answer;
      }
      //生成一条经验知识
      $klid = $db->insert("knowledge_lib", ["qaid"=>$qaid, "type"=>"问答", 
            "includeid"=>$uid, 
            "includerq"=>API_now(), //提前记录日期
            "articleid"=>$qa['answerid'], 
            "rq"       =>$qa['answerrq'],
            "attr"     =>cn_json($attr)
        ]);
    }
    else if($kl['includeid'] == $uid){
      //占了粪坑
      $klid = $kl['id'];
    }
    else{
      //别人占了粪坑
      return json_error(3001, "其它编导已开始归档");
    }

    //添加关键词，允许多个关键词之间用空格和逗号分开，以便一次输入多个。
    $db->insert("knowledge_keys", ["klid"=>$klid, "key"=>$keytext]);

    return json_OK(["keys"=>$keys]);
  }

  /* ----------------------------------------------
   * 问题归档前，减少一个关键词
   * xxx.com/qa/delkeyword (POST、身份识别)
   * ---------------------------------------------- */
  public static function delkeyword(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "text"],"service");
    ASSERT_right($db, $me, "问答管理");
    
    $qaid = $query['qaid'] + 0;
    $uid = $query['uid'] + 0;
    $keytext = trim($query['text']);
    
    //有没有占了粪坑？
    $kl = $db->get("knowledge_lib", "*", ["AND" => ["qaid"=>$qaid, "type"=>"问答", "includeid"=>$uid]]);
    if(!$kl){
      return json_error(3001, "非法操作");
    }
    else {
      //我已经操作的
      $klid = $kl['id'];
      $db->delete("knowledge_keys", ["AND"=>["klid"=>$klid, "key"=>$keytext]]);//重复的关键词一并删除！！！
    }
    
    return json_OK([]);
  }

  /* ----------------------------------
   * 问题归档
   * xxx.com/qa/fileqa (POST、身份识别)
   * ---------------------------------- */
  public static function fileqa(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid"], "service");
    ASSERT_right($db, $me, "问答管理");
    if(!$db->has("qa_data", ["AND"=>["parentid"=>$query['qaid'], "state"=>"用户满意"]]))return json_error(3300, "用户未评价满意");
    $db->update("qa_data", ["state"=>API_now()], ["AND"=>["id"=>$query['qaid'], "state[<]"=>"2015-00"]]);
    return json_OK([]);
  }


// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
// ┃               待办数量                                                       ┃
// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  /* -----------------------------------------------------------------------------------
   * 待办数量
   * xxx.com/qa/getusertodocount (POST、身份识别)
   * ----------------------------------------------------------------------------------- */
  public static function getusertodocount(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en"], "user");

    $todolist = $query['en'];
    $R = [];
    foreach($todolist as $todo){
      $AND = self::get_qalist_AND($db, $me, $todo, "user");
      $R[$todo] = $db->count("qa_data",["AND"=>$AND]);//self::counttodo($db, "user", $query['uid'], $todo);
    }
    return json_OK(["todocounts"=>$R]);
  }
  public static function gettodocount(&$db, $me, $todo_list, $module){
    $R = [];
    foreach($todo_list as $todo){
      $AND = self::get_qalist_AND($db, $me, $todo, $module);
      $R[$todo] = $db->count("qa_data",["AND"=>$AND]);//self::counttodo($db, "user", $query['uid'], $todo);
    }
    return $R;
  }


// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
// ┃               问答修改                                                       ┃
// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  /* ---------------------------------------------------------
   * 编导修改一项 CIA
   * --------------------------------------------------------- */
  public static function servicemodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "data"], "service");
    ASSERT_right($db, $me, "问答管理");
    return $db->modify_item($me, "service", "qa_data", $query['qaid'], $query['data']);
  }
  /* ---------------------------------------------------------
   * 用户修改一项 CIA
   * --------------------------------------------------------- */
  public static function usermodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid", "data"], "service");
    return $db->modify_item($me, "user", "qa_data", $query['qaid'], $query['data']);
  }

  /* ---------------------------------------------------------
   * 编导 - 删除一行
   * --------------------------------------------------------- */
  public static function servicedelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid"], "service");
    ASSERT_right($db, $me, "问答管理");
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    $db->delete_row("qa_data", $qaid, "service", $uid);
    return json_OK([]);
  }
  /* ---------------------------------------------------------
   * 编导 - 撤消删除一行
   * --------------------------------------------------------- */
  public static function serviceundelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid"], "service");
    ASSERT_right($db, $me, "问答管理");
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    $db->undelete("qa_data", $qaid, "service", $uid);
    return json_OK([]);
  }
  /* ---------------------------------------------------------
   * 用户删除一行
   * --------------------------------------------------------- */
  public static function userdelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["qaid"], "service");
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    //验证数据:
    $item = $db->get("qa_data", "*", ["AND"=>["id"=>$qaid, "userid"=>$uid]]);//要自己的
    if(!$item)return json_error(1000, "问答不存在或没有权限");
    $answers = $db->count("qa_data",  ["parentid"=>$qa_row['id']]);
    if($answers > 0){
      return json_error(1000, "提问正在回答中，不可删除");
    }
    $db->delete_row("qa_data", $qaid, "service", $uid);
    //删除积分预授权
    $db->delete("z_userpoints",["AND"=>["k2"=>$qaid, "attr"=>"prepoints"]]);
    return json_OK([]);
  }
  /* ---------------------------------------------------------
   * 撤消删除一行
   * --------------------------------------------------------- */
  public static function userundelete(&$get, &$post, &$db){
    return json_error(1000, "暂不提供此项功能，若要删除，请与管理员联系。");
  }

  /* -------------------------------------------------------
   * 获取问答列表
   * xxx.com/qa/getlist (GET、身份识别)
   * ------------------------------------------------------- */
  public static function getlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);

    $AND = self::get_qalist_AND($db, $me, $query['en'], $query['module']);
    if(!in_array($query['en'], ["user-all","user-tosatisfy","user-satisfyed"]))$AND["rq_delete[<]"] = "2015-00";

    //分析页码
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;

    //需要搜索？
    $searchtext = isset($query['searchtext']) ? trim($query['searchtext']) : false;
    if($searchtext){
      $qaids = $db->select("qa_data", "parentid", ["AND"=>["attr[~]"=>$searchtext]]);
      if(is_array($qaids) && count($qaids)>0)$AND["OR #1"]["id"] = $qaids;
      $AND["OR #1"]["attr[~]"] = $searchtext;
    }

    $rowcount = $db->count("qa_data", ["AND"=>$AND]);
    $R = $db->select("qa_data", ["id","state","rq","rq_delete","attr"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>(isset($ORDER)? $ORDER :"rq DESC")]);
    foreach($R as $k=>$row){
      $attr = json_decode($row["attr"], true);
      $R[$k]['content'] = $attr['content'];
      $R[$k]['state'  ] = self::get_qa_state($db, $row, $query['module']);
      unset($R[$k]['attr'], $R[$k]['rq_delete']);
    }
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }

  /* ----------------------------
   * 获取某一问题的状态
   * ---------------------------- */
  public static function get_qa_state(&$db, $qa_row, $module){
    if($qa_row['rq_delete'] > "2015-00")return "已删除";
    if($qa_row['state'] > "2015-00"){
      $a = ["service"=>"已归档", "expert"=>"满意", "user"=>"已评价"];
      return $a[$module];
    }
    $answers = $db->select("qa_data", ["state"], ["parentid"=>$qa_row['id']]);
    if(!$answers || count($answers)==0)return "未回答";
    $last_answer = &$answers[count($answers) - 1];
    if($last_answer['state'] == "待评价")return "待评价";
    if($last_answer['state'] == "评价满意")return "满意";
    if($last_answer['state'] == "评价不满意")return "不满意";

    $has_answered = $has_noanswer = false;
    foreach($answers as $answer){
      if($answer['state']=="不满意"){
        $has_answered = $has_noanswer = false;
      }
      else if($answer['state']=="已回答"){
        $has_answered = true;
      }
      else if($answer['state']=="待回答"){
        $has_noanswer = true;
      }
    }
    if( $has_answered &&  $has_noanswer) return "未回答";
    if( $has_answered && !$has_noanswer) return "未回答";
    if(!$has_answered && !$has_noanswer) return "未回答";

    return "未回答";
  }

  /* ----------------------------
   * 问答列表AND
   * ---------------------------- */
  public static function get_qalist_AND(&$db, $me, $en, $module){
    switch($en){
      //用户部分
      case "user-all":
        return ["parentid"=>0, "rq[>=]"=>"1999-99", "userid"=>$me['id']];
      case "user-tosatisfy": //待评价
        //最后一行是待评价
        $sql_row_last = "SELECT parentid, `state` FROM qa_data WHERE id IN (SELECT MAX(id) as pid FROM `qa_data` GROUP BY parentid)";
        $sql_id_last_satisfy = "SELECT parentid FROM ($sql_row_last) a WHERE a.`state`='待评价'";
        $ids = $db->query($sql_id_last_satisfy)->fetchAll(PDO::FETCH_COLUMN);
        return ["parentid"=>0, "userid"=>$me['id'], "id"=>$ids];
      case "user-satisfyed": //已评价
        //最后一行是待评价
        $sql_row_last = "SELECT parentid, `state` FROM qa_data WHERE id IN (SELECT MAX(id) as pid FROM `qa_data` GROUP BY parentid)";
        $sql_id_last_satisfy = "SELECT parentid FROM ($sql_row_last) a WHERE a.`state`='评价满意'";
        $ids = $db->query($sql_id_last_satisfy)->fetchAll(PDO::FETCH_COLUMN);
        return ["parentid"=>0, "userid"=>$me['id'], "id"=>$ids];

      //专家部分
      case "expert-toanswer":
        ASSERT_module($me, "expert");
        $ids_y = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "userid"=>$me['id'], "rq[<]"=>"2015-01", "state"=>"待回答"]]);
        return ["parentid"=>0, "id"=>$ids_y];
      case "expert-answered"://专家回答过的：
        ASSERT_module($me, "expert");
        $ids_y = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "userid"=>$me['id'], "rq[>]"=>"2015-01"]]);
        return ["parentid"=>0, "id"=>$ids_y];

      //编导部分
      case "new":
        ASSERT_module($me, "service");
        //rq>'1999-99' && (没有满意，没有未回答)
        $ids = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "state"=>['用户满意', "已回答", "待回答"]]]);
        return ["parentid"=>0, "rq[>]"=>"1999-99", "state[<]"=>"2015-01", "id[!]"=>$ids];
      case "toanswer"://待回答
      case "noanswer":
        ASSERT_module($me, "service");
        //有未回答
        $ids_y = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "state"=>"待回答"]]);
        return ["parentid"=>0, "rq[>]"=>"1999-99", "state[<]"=>"2015-01", "id"=>$ids_y];
      case "tosatisfy": //待评价
      case "tosatisfyed": //待评价
        ASSERT_module($me, "service");
        //最后一行是待评价
        $sql_row_last = "SELECT parentid, `state` FROM qa_data WHERE id IN (SELECT MAX(id) as pid FROM `qa_data` GROUP BY parentid)";
        $sql_id_last_satisfy = "SELECT parentid FROM ($sql_row_last) a WHERE a.`state`='待评价'";
        $ids = $db->query($sql_id_last_satisfy)->fetchAll(PDO::FETCH_COLUMN);
        return ["parentid"=>0, "rq[>]"=>"1999-99", "id"=>$ids];
      case "satisfyed": //已评价，未归档
        ASSERT_module($me, "service");
        //有满意， 不含不满意
        $ids_y = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "state"=>'评价满意']]);
        return ["parentid"=>0, "state[<]"=>"2015-01", "id"=>$ids_y];
      case "tofile"://待归档
        ASSERT_module($me, "service");
        //state<'2015-01' && (有满意，  没有未回答)
        $ids_y = $db->select("qa_data", "DISTINCT(parentid) as idid", ["AND"=>["parentid[>]"=>0, "state"=>'评价满意']]);
        return ["parentid"=>0, "state[<]"=>"2015-01", "id"=>$ids_y];
      case "filed"://已归档
        ASSERT_module($me, "service");
        return ["parentid"=>0, "state[>]"=>"2015-01"];
      case "all":
      default:
        ASSERT_module($me, "service");
        return ["parentid"=>0, "rq[>]"=>"1999-99"];
    }
  }

  /* -------------- 处理已经有了回答 ---------------- */
  public static function do_has_answer(&$db, $qa){
    //结束这些已回答的定时器，此前的回答不可再触发定时器，以保证定时器的正确！
    $db->update("qa_data", ["rq_timer"=>"9999-99"], ["AND"=>["parentid"=>$qa['id'], "state"=>["已回答", "待回答", "放弃回答"]]]);//若有历史回答，就有点多了，不过没关系，方便
    //结束半时效未回答的定时器
    $db->update("qa_data", ["rq_timer"=>"9999-99"], ["AND"=>["id"=>$qa['id']]]);
    //生成评价行，用户可以立即评价了
    $db->insert("qa_data", ["parentid"=>$qa['id'], "userid"=>$qa['userid'], "state"=>"待评价"]);
    //推送消息，回答完成：
    class_wx::notice_qaanswered($db, $qa);
  }
  /* -------------- 处理专家提交一个回答，仅定时器调用此函数。在定时器不正确的情况下，不可预知 ---------------- */
  public static function ontimer_answered(&$db, $answer){
    $qaid = $answer['parentid'];
    $answers = $db->select("qa_data", ["state", "rq_timer"], ["parentid"=>$answer['parentid']]);
    $has_answered = $has_noanswer = false;
    foreach($answers as $answer){
      if($answer['rq_timer'] >= API_now() && $answer['rq_timer'] < "3333-33"){
        $has_noanswer = true;//有定时器，且未到的，当做待回答
      }
      else if($answer['state']=="待回答"){
        $has_noanswer = true;
      }
      else if($answer['state']=="不满意"){
        $has_answered = $has_noanswer = false;
      }
      else if($answer['state']=="已回答"){
        $has_answered = true;
      }
    }
    //无论如何，取消这个定时器：
    $db->update("qa_data", ["rq_timer"=>"9999-99"], ["id"=>$answer["id"]]);

    //有尚未回答的，就可以不管了：
    if($has_noanswer) return true;

    if($has_answered){
      //有回答，且已全部回答了
      $qa = $db->get("qa_data", "*", ["id"=>$qaid]);
      $qa['attr'] = json_decode($qa['attr'], true);
      //处理已有回答：
      class_qa::do_has_answer($db, $qa);
      return true;
    }
    //因为是定时器调用，没有已回答，就一定有放弃回答，所以现在是全部放弃回答了。需要再指定专家：
    //推送消息，全部放弃回答：
    //
  }
  /* -------------- 处理时器调：到达时效没有人回答 ---------------- */
  public static function ontimer_no_answer(&$db, $qa){
    $qaid = $qa['id'];
    
    //未回答问题，且到达时效后，系统自动重新发起，并通知用户延时回答，且免费。
    
    //问答不再有时效
    
    //免费
    $db->delete("z_userpoints", ["AND"=>["userid"=>$qa['userid'], "attr"=>"prepoints", "k1"=>["预扣积分", "积分预授权"], "k2"=>$qaid]]);
    
    //重新发起
    //推送消息，到达时效没有人回答：
    //
  }
  /* -------------- 处理时器调：不满意 ---------------- */
  public static function ontimer_unsatisfy(&$db, $answer){
    $qaid = $answer['parentid'];
    //所有已回答的，改为“用户不满意”
    $db->update("qa_data", ["state"=>"用户不满意"], ["AND"=>["parentid"=>$qaid, "state"=>"已回答"]]);
    
    //推送消息，“用户不满意”：
    //
  }
  /* -------------- 处理时器调：满意 ---------------- */
  public static function ontimer_satisfy(&$db, $answer){
    $qaid = $answer['parentid'];
    $now = API_now();
    $qa = $db->get("qa_data", "*", ["id"=>$qaid]);
    $qa_attr = json_decode($qa['attr'], true);
    $points = $qa_attr['easy'] + 0;

    //分配积分：
    if($points){
      //预扣积分改为实扣积分：
      $db->update("z_userpoints", ["rq"=>$now, "attr"=>"points", "k1"=>"问答满意"], ["AND"=>["userid"=>$qa['userid'], "attr"=>"prepoints", "k1"=>["预扣积分", "积分预授权"], "k2"=>$qaid]]);
      //$db->show();
      //给专家积分：
      $answers = $db->select("qa_data", "*", ["AND"=>["parentid"=>$qaid, "state"=>"已回答"]]);
      $nexpert = count($answers);//有几个专家进行回答
      if($nexpert > 1){
        // 需要报告错误：指定了多个专家回答！
        return;
      }
      $n   = ((int)($points * 80 / $nexpert)/ 100);//专家将得到 80% 的积分
      $n3  = ((int)($points *  3 / $nexpert)/ 100);//问答两边的关联人将得到 3% 的积分
      $n10 = ((int)($points * 10 / $nexpert)/ 100);//裁判专家将得到 10% 的积分
      $parentids = [];
      foreach($answers as $row){
        $db->insert("z_userpoints", ["rq"=>$now, "n"=> $n, "userid"=>$row['userid'], "attr"=>"expertpoints" , "k1"=>"问答满意", "k2"=>$qaid]);
        //给专家关联人积分：
        $parentid = QgsModules::GetUser("parentid", ["id"=>$row['userid']]);
        if($parentid > 0){
          $parentids[] = $parentid;
          $db->insert("z_userpoints", ["rq"=>$now, "n"=> $n3, "userid"=>$parentid, "attr"=>"referrerpoints" , "k1"=>"专家问答满意，关联", "k2"=>$qaid]);
        }
        //给“裁判专家”积分：
        $senderid = QgsModules::GetUser("id", ["id"=>$row['senderid']]);
        if($senderid > 0){
          $db->insert("z_userpoints", ["rq"=>$now, "n"=> $n10, "userid"=>$senderid, "attr"=>"senderpoints" , "k1"=>"裁判，用户满意", "k2"=>$qaid]);
        }
      }
      //给提问关联人积分：
      $parentid = QgsModules::GetUser("parentid", ["id"=>$qa['userid']]);
      if($parentid > 0){
        $parentids[] = $parentid;
        $db->insert("z_userpoints", ["rq"=>$now, "n"=> $n3, "userid"=>$parentid, "attr"=>"referrerpoints" , "k1"=>"提问完成，关联", "k2"=>$qaid]);
      }
      //推送消息，“关联积分”：
      foreach($parentids as $id){
        
      }
    }
    //所有已回答的，改为“用户满意”
    $db->update("qa_data", ["state"=>"用户满意"], ["AND"=>["parentid"=>$qaid, "state"=>"已回答"]]);

    //推送消息，“用户满意”：
    //
  }
  
  public static function run_timer(&$db){
    $qa_datas = $db->select("qa_data", "*", ["AND"=>["rq_timer[<]"=>API_now()], "ORDER"=>"id DESC"]);//策略：要先处理回答定时器！
    $qaid_done = [];//每个问答每次只定时一次
    if($qa_datas)foreach($qa_datas as $qa_data_row){
      //每次只触发20个问答
      if(count($qaid_done) >= 20)return;

      $qaid = $qa_data_row['parentid'] > 0 ? $qa_data_row['parentid'] : $qa_data_row['id'];
      //一个提问有多个定时的，只执行一次：
      if(isset($qaid_done[$qaid]))continue;

      //回答完成：
      if(in_array($qa_data_row['state'], ["放弃回答","已回答"])){
        if(self::ontimer_answered($db, $qa_data_row))
          $qaid_done[$qaid] = true;//每个问答每次只定时一次
      }

      //新提问（用户发出提问后10分钟）、时效过半：当前时效再过半后提醒：
      if($qa_data_row['parentid'] == 0){// && $qa_data_row['rq_sender'] == "新提问")
        $qaid_done[$qaid] = true;//每个问答每次只定时一次
        $attr = $qa_data_row['attr'] = json_decode($qa_data_row['attr'], true);
        $days = $attr['timelimit'] + 0;
        $t0 = G::str2date($qa_data_row['rq']);
        $t1 = G::str2date($qa_data_row['rq_timer']);
        $t_last = $t0 + $days * 3600 * 24;
        if($t_last - $t1 <= 0){
          //完成本定时器：
          $db->update("qa_data", ["rq_timer"=>"9999-99"], ["AND"=>["id"=>$qaid]]);
          //将未回答的，设置为“回答超时”
          $db->update("qa_data", ["state"=>"回答超时"], ["AND"=>["parentid"=>$qaid, "state"=>"待回答"]]);
          //全部时效到了，有回答的(不管定时器)，就当做全部已回答：
          if($db->has("qa_data", ["AND"=>["parentid"=>$qaid, "state"=>"已回答"]])){
            //处理已有回答（在函数中推送消息）：
            class_qa::do_has_answer($db, $qa_data_row);
          }
          else{
            //超时没有回答：
            class_qa::ontimer_no_answer($db, $qa_data_row);
          }
          continue;
        }
        if($t_last - $t1 < 2 * 7200){
          //下一个半时效不足2小时，不再提醒半时效，而是提醒到期未回答完成
          $db->update("qa_data", ["rq_timer"=>date("Y-m-d H:i:s", $t_last)], ["AND"=>["id"=>$qaid]]);
          continue;
        }
        $t2 = (int)(($t1 + $t_last) / 2);
        $t2 = date("Y-m-d H:i:s", $t2);//得到下一个半时效
        $db->update("qa_data", ["rq_timer"=>$t2], ["AND"=>["id"=>$qaid]]);
        if($t1 - $t0 < 660){
          //新提问，已过了10分钟：
          if($attr["major"]){
            $major = $attr["major"];
            $content = mb_substr($attr["content"], 0 , 30, 'UTF-8');
            if($content != $attr["content"])$content .= "... ?";
            error_log("新提问，qaid = $qaid, 专业=$major \n\n");
            $openids = QgsModules::SelectUser("openid", ["AND"=>["attr[~]"=>'"serviceEditServiceMajor_":"'. $major . '"', "expertstate"=>3]]);
            error_log("发送给用户，openids = " . cn_json($openids)."\n\n");
            require_once("wx/wx.class.php");
            $wx = new CWxApi(DB_NAME_QGS_MODULES);
            $r = $wx->SendTPL($openids, "用户提问通知", [
              "有一个新提问等待处理",
              $content,
              $major,
              substr($qa_data_row['rq'], 0, 16),
              "请抓紧时间处理",
            ], WX_DOMAIN . "/qgs/index.htm#/frame/service-qa-show?qaid={$qaid}");
            error_log("发送模板消息 r=" . cn_json($r)."\n\n\n");
          }
        }
        else {
          //推送消息，编导要知道专家未回答完成：
          //推送消息，催促专家回答：
          class_wx::notice_passhalf($db, $qaid, $attr);
        }
        continue;
      }

      //用户不满意
      if($qa_data_row['state'] == "评价不满意"){
        //无论如何，取消这个定时器：
        $db->update("qa_data", ["rq_timer"=>"9999-99"], ["id"=>$qa_data_row["id"]]);
        class_qa::ontimer_unsatisfy($db, $qa_data_row);
      }

      //用户不满意
      if($qa_data_row['state'] == "评价满意"){
        //无论如何，取消这个定时器：
        $db->update("qa_data", ["rq_timer"=>"9999-99"], ["id"=>$qa_data_row["id"]]);
        class_qa::ontimer_satisfy($db, $qa_data_row);
      }
    }

  }
}

?>