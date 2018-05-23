<?php
	require_once("app/user.class.php");
	require_once("app/g.php");
	require_once("app/wx.php");
	
	require_once("api_wx.php"); //微信语音功能等


class class_kl{
  /* ------------------------------------------
   * 获取知识库列表
   * ------------------------------------------ */
  public static function getlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);
    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;
    //查询：
    $AND = [];
    if(isset($query['en']))switch($query['module'] . "-" . $query['en']){
      case "service-qa":
        ASSERT_right($db, $me, "管理知识库");
        $AND = ["type"=>"问答", "rq[>]"=>"2015-01"];
        break;
      case "service-outside":
        ASSERT_right($db, $me, "管理知识库");
        $AND = ["type"=>"专家输入", "includerq[<]"=>"2015-01", "rq[>]"=>"2015-01"];
        break;
      case "service-inside":
        ASSERT_right($db, $me, "管理知识库");
        $AND = ["type"=>"专家输入", "includerq[>]"=>"2015-01"];
        break;
      case "service-all":
        ASSERT_right($db, $me, "管理知识库");
        $AND = ["rq[>]"=>"2015-01"];
        break;
      case "expert-all":
        $AND = ["type"=>"专家输入", "articleid"=>$uid];
        break;
      default:
        return json_OK(["list"=>[], "rowcount"=>0]);
    }
    $AND["rq_delete[<]"] = "2015-00";
    $R = $db->select("knowledge_lib", ["id", "rq", "attr"], ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"rq DESC"]);
    if(is_array($R)){
      foreach($R as $k=>$row){
        $attr = json_decode($row['attr'], true);
        $R[$k]['ask'] = $attr["ask"]["content"];
        unset($R[$k]['attr']);
      }
      $n = count($R); if($n > 1 && !$R[$n-1]['rq']) array_unshift($R, array_pop($R));//草稿移到第一个
    }
    $rowcount = $db->count("knowledge_lib", ["AND"=>$AND]);
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
  }

  /* -------------------------------------------------------
   * 获取知识库详情
   * ------------------------------------------------------- */
  public static function getdetail(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["klid"]);
    
    //步骤1：读取权限
    ASSERT_right($db, $me, "管理知识库");
    
    //步骤2：读取数据
    $R = self::get_full_data($db, $query['klid']);
    return json_OK(["detail"=>$R]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 读取完整的知识库数据，包含声音的服务端id列表
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  private static function get_full_data(&$db, $klid){
    if(is_array($klid))
      $R = $db->get("knowledge_lib", "*", $klid);
    else
      $R = $db->get("knowledge_lib", "*", ["id"=>$klid]);
    if(!is_array($R))return false;
    $klid = $R['id'] + 0;
    $R['keywords'] = $db->select("knowledge_keys", "key", ["klid"=>$klid]);
    
    $R['attr'] = json_decode($R['attr'], true);
    $R['attr']['ask']['audioslist'] = class_wx::str_audios_to_list($db, $R['attr']['ask']['audios']);
    foreach($R['attr']['answer'] as $k=>$row)
      $R['attr']['answer'][$k]['audioslist'] = class_wx::str_audios_to_list($db, $R['attr']['answer'][$k]['audios']);
    return $R;
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 专家获取知识库详情
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function expertgetdetail(&$get, &$post, &$db){
    $query = &$get;
    ASSERT_query_userinfo($db, $query, [], "expert");
    $uid = $query["uid"] + 0;
    $klid = isset($query["klid"])? $query["klid"]+0 : 0;
    //步骤1：读取数据
    $R = self::get_full_data($db, ["AND"=>["id"=>$klid, "articleid"=>$uid]]);
    //echo $db->last_query();
    if(is_array($R)){
      return json_OK(["detail"=>$R]);
    }
    
    //步骤2：找现有草稿
    $R = self::get_full_data($db, ["AND"=>["articleid"=>$uid, "rq[<]"=>"2015-01"]]);
    if(is_array($R)){
      return json_OK(["detail"=>$R]);
    }
    
    //没有数据，新建草稿
    $id = $db->insert("knowledge_lib", ["type"=>"专家输入", "articleid"=>$uid, "rq"=>"", "includerq"=>""]);
    return json_OK(["detail"=>["id"=>$id,"articleid"=>$uid, "rq"=>"", "attr"=>[]]]);
  }
  /* ---------------------------------------------
   * 专家修改知识库草稿
   * --------------------------------------------- */
  public static function updatedraft(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["field","value","klid"], "expert");

    $uid = $query["uid"] + 0;
    $klid = $query["klid"] + 0;
    $field = $query['field'];
    $value = $query['value'];

    $AND = ["AND"=>["id"=>$klid, "articleid"=>$uid, "rq[<]"=>"2015-01"]];
    switch($field){
      case "content":
      case "images":
      case "audios":
        $db->SaveJson(["knowledge_lib", "attr",["id"=>$klid]], ["ask", $field], $value);
        break;
      case "wxappendimage": // 这个是为了将上传图像（class_wx）和修改提问（这里）合并成一个请求。
        //先获取图像：
        $fn = class_wx::imageid2local($query, $db);
        //原来有没有图像？
        $db->AppendJson(["knowledge_lib", "attr",["id"=>$klid]], ["ask", "images"], $fn, ",");
        return json_OK(["imageurl"=>$fn]);
      default:
        return json_error(1100, "参数错误"  );
    }
    //修改数据
    return json_OK([]);
  }
  /* ---------------------------------------------
   * 专家提交知识库
   * --------------------------------------------- */
  public static function savedraft(&$get, &$post, &$db){
    $query = &$post;
    ASSERT_query_userinfo($db, $query, ["klid"], "expert");
    
    $uid = $query["uid"] + 0;
    $klid = $query["klid"] + 0;
    
    //修改数据
    $n = $db->update("knowledge_lib", ["rq"=>G::s_now()], ["AND"=>["id"=>$klid, "articleid"=>$uid, "rq[<]"=>"2015-01"]]);
    return json_OK(["n"=>$n]);
  }
  // -------------------------- 修改一条数据的 CIA -------------------------------
  public static function modify_item(&$db, $me, $module, $table, $ciaid, $data, $KKK=false){
    //验证数据:
    if(!isset($data['content']) || strlen($data['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content", $data['content']]);
    //操作信息：
    $modify = ["id"=>$me['id'], "rq"=>API_now(), "ac"=>$module=='service'?"编导修改":"用户修改", "module"=>$module];

    //获取旧数据
    $item = $db->get($table, "*", ["AND"=>["id"=>$ciaid]]);
    if($module != 'service' && $item['userid'] != $me['id'] && $item['articleid'] != $me['id']){
      var_dump($me);
      var_dump($item);
      var_dump($module);
      return json_error(1000, "没有修改权限");
    }
    $attr = GetAttr($item, "attr");
    $sub_attr = &$attr;
    if($KKK){
      foreach($KKK as $k){
        if(!isset($sub_attr[$k]) || !$sub_attr[$k])$sub_attr[$k] = [];
        $sub_attr = &$sub_attr[$k];
      }
    }
    $modify['olddata'] = ["content"=>$sub_attr['content']];
    if(isset($sub_attr["images"]))$modify['olddata']["images"] = $sub_attr['images'];
    if(isset($sub_attr["audios"]))$modify['olddata']["audios"] = $sub_attr['audios'];
    
    //保存旧数据、操作信息
    $sub_attr["all_modify"][] = $modify;
    $sub_attr["modified"] = 1;

    //更新数据：
    $sub_attr["content"] = $data['content'];
    unset($sub_attr["images"], $sub_attr["audios"]);
    if(isset($data["images"]))$sub_attr["images"] = $data['images'];
    if(isset($data["audios"]))$sub_attr["audios"] = $data['audios'];

    //写入数据库：
    $db->update($table, ["attr"=>cn_json($attr)], ["id"=>$ciaid]);
    return json_OK([]);
  }
  // -------------------------- 删除一条数据的 CIA -------------------------------
  public static function delete_item(&$db, $me, $module, $table, $ciaid, $KKK=false){
    //操作信息：
    $modify = ["id"=>$me['id'], "rq"=>API_now(), "ac"=>$module=='service'?"编导删除":"用户删除", "module"=>$module];

    //获取旧数据
    $item = $db->get($table, "*", ["AND"=>["id"=>$ciaid]]);
    if($module != 'service' && $item['userid'] != $me['id'] && $item['articleid'] != $me['id']){
      return json_error(1000, "没有删除权限");
    }
    $attr = GetAttr($item, "attr");
    $sub_attr = &$attr;
    if($KKK){
      foreach($KKK as $k){
        if(!isset($sub_attr[$k]) || !$sub_attr[$k])$sub_attr[$k] = [];
        $sub_attr = &$sub_attr[$k];
      }
    }
    $sub_attr["all_modify"][] = ["id"=>$uid, "rq"=>API_now(), "ac"=>"删除", "module"=>$query['module']];
    $sub_attr["modified"] = 1;

    //写入数据库：
    $db->update($table, ["rq_delete"=>API_now(), "attr"=>cn_json($attr)], ["id"=>$ciaid]);
    return json_OK([]);
  }
  // -------------------------- 恢复一条数据的 CIA -------------------------------
  public static function undelete_item(&$db, $me, $module, $table, $ciaid, $KKK=false){
    //操作信息：
    $modify = ["id"=>$me['id'], "rq"=>API_now(), "ac"=>$module=='service'?"编导恢复":"用户恢复", "module"=>$module];

    //获取旧数据
    $item = $db->get($table, "*", ["AND"=>["id"=>$ciaid]]);
    if($module != 'service' && $item['userid'] != $me['id'] && $item['articleid'] != $me['id']){
      return json_error(1000, "没有恢复权限");
    }
    $attr = GetAttr($item, "attr");
    $sub_attr = &$attr;
    if($KKK){
      foreach($KKK as $k){
        if(!isset($sub_attr[$k]) || !$sub_attr[$k])$sub_attr[$k] = [];
        $sub_attr = &$sub_attr[$k];
      }
    }
    $sub_attr["all_modify"][] = ["id"=>$uid, "rq"=>API_now(), "ac"=>"恢复", "module"=>$query['module']];
    $sub_attr["modified"] = 1;

    //写入数据库：
    $db->update($table, ["rq_delete"=>"", "attr"=>cn_json($attr)], ["id"=>$ciaid]);
    return json_OK([]);
  }
  /* ---------------------------------------------
   * 专家修改知识库
   * --------------------------------------------- */
  public static function ask_usermodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid", "data"], "expert");
    $ciaid = $query["ciaid"] + 0;
    return self::modify_item($db, $me, "expert", "knowledge_lib", $ciaid, $query['data'], ["ask"]);
  }
  /* ---------------------------------------------
   * 专家删除知识库
   * --------------------------------------------- */
  public static function ask_userdelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "expert");
    $ciaid = $query["ciaid"] + 0;
    return self::delete_item($db, $me, "expert", "knowledge_lib", $ciaid, ["ask"]);
  }
  /* ---------------------------------------------
   * 专家恢复知识库
   * --------------------------------------------- */
  public static function ask_userundelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "expert");
    $ciaid = $query["ciaid"] + 0;
    return self::undelete_item($db, $me, "expert", "knowledge_lib", $ciaid, ["ask"]);
  }
  /* ---------------------------------------------
   * 编导修改知识库
   * --------------------------------------------- */
  public static function ask_servicemodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid", "data"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::modify_item($db, $me, "service", "knowledge_lib", $ciaid, $query['data'], ["ask"]);
  }
  /* ---------------------------------------------
   * 编导删除知识库
   * --------------------------------------------- */
  public static function ask_servicedelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::delete_item($db, $me, "service", "knowledge_lib", $ciaid, ["ask"]);
  }
  /* ---------------------------------------------
   * 编导恢复知识库
   * --------------------------------------------- */
  public static function ask_serviceundelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::undelete_item($db, $me, "service", "knowledge_lib", $ciaid, ["ask"]);
  }
  /* ---------------------------------------------
   * 编导修改知识库 - 回答内容
   * --------------------------------------------- */
  public static function answer_servicemodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid", "data"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::modify_item($db, $me, "service", "knowledge_lib", $ciaid, $query['data'], ["answer", $query['sub']]);
  }
  /* ---------------------------------------------
   * 编导删除知识库 - 回答内容
   * --------------------------------------------- */
  public static function answer_servicedelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::delete_item($db, $me, "service", "knowledge_lib", $ciaid, ["answer", $query['sub']]);
  }
  /* ---------------------------------------------
   * 编导恢复知识库 - 回答内容
   * --------------------------------------------- */
  public static function answer_serviceundelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["ciaid"], "service");
    ASSERT_right($db, $me, "管理知识库");
    $ciaid = $query["ciaid"] + 0;
    return self::undelete_item($db, $me, "service", "knowledge_lib", $ciaid, ["answer", $query['sub']]);
  }
}

?>