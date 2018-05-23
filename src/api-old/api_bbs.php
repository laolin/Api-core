<?php
  require_once("app/user.class.php");
  require_once("app/g.php");
  require_once("app/wx.php");
  
  require_once("api_wx.php"); //微信语音功能等


class class_bbs{
  /* ---------------------------------------------------------
   * 获取论坛帖子列表
   * --------------------------------------------------------- */
  public static function getlist(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);
    $uid = trim($query['uid']);

    $AND = [];
    $ORDER = "rq DESC";
    if(isset($query['en']))switch($query['en']){
      case "user-commend":
        $AND = ["rq_recommend[!]"=>""];
        $ORDER = "rq_recommend DESC";
        break;
      case "user-all":
        $AND = ["parentid"=>0, "OR"=>["AND #1"=>["userid"=>$uid,"state[!]"=>"草稿"], "AND #2"=>["state[!]"=>["草稿", "新帖"]]]];
        break;
      case "user-bbs-all":
        $AND = ["userid"=>$uid, "parentid"=>0, "state[!]"=>"草稿"];
        break;
      case "user-followed"://已回帖的
        $ids = $db->select("bbs_data", "parentid", ["AND"=>["parentid[!]"=>0, "userid"=>$uid]]);
        //var_dump($db->last_query());var_dump($db->error());
        $AND = ["id"=>$ids, "parentid"=>0];
        break;

      //case "user-unchecked":
      //  $AND = ["userid"=>$uid, "parentid"=>0, "state"=>"新帖"];
      //  break;
      //case "user-unfiled":
      //  $AND = ["userid"=>$uid, "parentid"=>0, "state"=>"未结"];
      //  break;
      //case "user-filed":
      //  $AND = ["userid"=>$uid, "parentid"=>0, "state"=>"已结"];
      //  break;
      //case "user-tofollow"://所有可回帖，包括已回帖的
      //  $AND = ["parentid"=>0];
      //  break;
      //case "user-unfollowed"://未回帖，也无草稿的
      //  $ids = $db->select("bbs_data", "parentid", ["AND"=>["parentid[!]"=>0, "userid"=>$uid]]);
      //  //var_dump($db->last_query());var_dump($db->error());
      //  $AND = ["id[!]"=>$ids, "parentid"=>0, "state"=>"未结"];
      //  break;

      case "service-commend":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["rq_recommend[!]"=>""];
        $ORDER = "rq_recommend DESC";
        break;
      case "service-tocheck-all":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $ids = $db->select("bbs_data", "parentid", ["AND"=>["state"=>"新跟帖"]]);
        $AND = ["parentid"=>0, "OR"=>["state"=>"新帖", "id"=>$ids]];
        break;
      case "service-bbs-all":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid"=>0, "state"=>["新帖", "已结", "未结"]];
        break;
      case "service-bbs-tocheck":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid"=>0, "state"=>"新帖"];
        break;
      case "service-bbs-checked":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid"=>0, "state"=>["未结", "已结"]];
        break;
      case "service-follow-all":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid[>]"=>0, "state"=>["新跟帖" ,"公开跟帖", "私密跟帖"]];
        break;
      case "service-follow-tocheck":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid[>]"=>0, "state"=>"新跟帖"];
        break;
      case "service-follow-checked":
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid[>]"=>0, "state"=>["公开跟帖", "私密跟帖"]];
        break;
      default: return json_OK(["list"=>[], "rowcount"=>0]);
    }
    
    $json = get_page_data($db, $query, "bbs_data", $AND, "*", $ORDER);
    if($json['list'])foreach($json['list'] as $i=>$row){
      $attr = GetAttr($row,  "attr");
      self::process_attr_cia($db, $json['list'][$i], $query['module']=="service");
      $json['list'][$i]['followed'] = $db->count("bbs_data", ["AND"=>["parentid"=>$row['id']]]);
      if($query['module'] == "service")$json['list'][$i]['newfollowed'] = $db->count("bbs_data", ["AND"=>["parentid"=>$row['id'], "state"=>"新跟帖"]]);
    }
    return json_OK($json);
  }

  /* ---------------------------------------------------------
   * 获取帖子详情
   * --------------------------------------------------------- */
  public static function getdetail(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"]);

    $uid = $query["uid"] + 0;
    $bbsid = $query["bbsid"] + 0;
    $bbs = $db->get("bbs_data", "*", ["id"=>$bbsid]);

    //是不是编导，请求查看一个回帖？
    if($bbs['parentid'] > 0){
      ASSERT_module($me, "service");
      $follows = [$bbs];
      $bbs = $db->get("bbs_data", "*", ["id"=>$bbs['parentid']]);
    }

    else{
      //获取跟帖列表
      if($query['module'] == "service") {
        //编导模块：全部可见
        ASSERT_module($me, "service");
        ASSERT_right($db, $me, "论坛管理");
        $AND = ["parentid"=>$bbsid];
      }
      else if($uid == $bbs['userid']){
        //发帖的，所有已审核的跟帖可见
        $AND = ["parentid"=>$bbsid, "OR"=>["userid"=>$uid, "state[!]"=>"新跟帖"]];
      }
      else {
        //我的跟帖、公开跟帖
        $AND = ["parentid"=>$bbsid, "OR"=>["userid"=>$uid, "state"=>"公开跟帖"]];
      }
      $follows = $db->select("bbs_data", "*", ["AND"=>$AND]);
      //var_dump($query);var_dump($db->last_query());var_dump($db->error());
    }

    self::process_attr_cia($db, $bbs, $query['module']=="service");

    //获取用户头像和呢称
    $userids[] = $bbs['userid'];
    if($follows)foreach($follows as $i=>$row){
      $userids[] = $row['userid'];
      self::process_attr_cia($db, $follows[$i], $query['module']=="service");
    }
    $arr = QgsModules::GetWxAndUsers($userids);
    $users = [];
    foreach($arr as $row)$users[$row['id']] = $row;
 
    //返回数据：
    return json_OK(["bbs"=>$bbs, "follows"=>$follows, "users"=>$users]);
  }

  /* ---------------------------------------------------------
   * 发帖，有草稿的，返回草稿，没有的要新建一个
   * --------------------------------------------------------- */
  public static function getbbsdraft(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, []);
    $uid = $query['uid'] + 0;

    //当前有个漏洞：用户可以随便灌水，限制每天提问数量？

    $R = $db->get("bbs_data", "*", ["AND"=>["userid"=>$uid, "state"=>"草稿", "parentid"=>0]]);
    if(!$R){
      $bbsid = $db->insert("bbs_data", ["userid"=>$uid, "state"=>"草稿", "parentid"=>0, "attr"=>"{}"]);
      $R = ["id"=>$bbsid, "userid"=>$uid, "state"=>"草稿", "parentid"=>0, "attr"=>[]];
    }else{
      $bbsid = $R['id'];
      self::process_attr_cia($db, $R);
    }
    return json_OK(["bbs"=>$R]);
  }
  /* ---------------------------------------------------------
   * 完成发帖
   * --------------------------------------------------------- */
  public static function donebbsdraft(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"]);
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    //验证数据:
    $bbs = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "userid"=>$uid, "state"=>"草稿"]]);
    if(!$bbs)return json_error(1000, "没有找到草稿,$bbsid");
    $attr = GetAttr($bbs, "attr");
    if(!isset($attr['content']) || strlen($attr['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content"]);

    //标志为已发帖
    $db->update("bbs_data", ["rq"=>G::s_now(), "state"=>"未结"], ["id"=>$bbsid]);

    //推送消息
    //class_wx::notice_ToAsk($db, $qa);

    return json_OK(["bbsid"=>$bbsid]);
  }
  /* ---------------------------------------------------------
   * 更新草稿，发帖和跟帖通用
   * --------------------------------------------------------- */
  public static function updatedraft(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid", "field", "value"]);

    $bbsid = $query['bbsid'] + 0;
    $field = $query['field'];
    $value = $query['value'];

    if(!$db->has("bbs_data", ["AND"=>["id"=>$bbsid, "state"=>"草稿"]]))return json_error(3001, "草稿不存在。");

    switch($field){
      case "content":
      case "images":
      case "audios":
      case "tags":
        $db->UpdateJson("bbs_data", "attr", $field, $value, ["id"=>$bbsid]);
        break;
      case "wxappendimage": // 这个是为了将上传图像（class_wx）和修改提问（这里）合并成一个请求。
        //先获取图像：
        $fn = class_wx::imageid2local($query, $db);
        //添加图像？
        $db->UpdateJson("bbs_data", "attr", "images", $fn, ["id"=>$bbsid], ",");
        return json_OK(["imageurl"=>$fn]);
      default:
        return json_error(1100, "参数错误"  );
    }
    return json_OK([]);
  }
  /* ---------------------------------------------------------
   * 跟帖，有草稿的，返回草稿，没有的要新建一个
   * --------------------------------------------------------- */
  public static function getfollowdraft(&$get, &$post, &$db){
    $query = &$get;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"]);
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    $R = $db->get("bbs_data", "*", ["AND"=>["userid"=>$uid, "state"=>"草稿", "parentid"=>$bbsid]]);
    if(!$R){
      $followid = $db->insert("bbs_data", ["userid"=>$uid, "state"=>"草稿", "parentid"=>$bbsid, "attr"=>"{}"]);
      $R = ["id"=>$followid, "userid"=>$uid, "state"=>"草稿", "parentid"=>$bbsid, "attr"=>[]];
    }else{
      $followid = $R['id'];
      self::process_attr_cia($db, $R);
    }
    return json_OK(["follow"=>$R]);
  }
  /* ---------------------------------------------------------
   * 完成跟帖
   * --------------------------------------------------------- */
  public static function donefollowdraft(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["followid"]);
    $uid = $query['uid'] + 0;
    $followid = $query['followid'] + 0;

    $follow = $db->get("bbs_data", "*", ["AND"=>["id"=>$followid, "userid"=>$uid, "parentid[>]"=>0, "state"=>"草稿"]]);
    if(!$follow)return json_error(1000, "没有找到草稿,$followid");
    
    //验证数据:
    $attr = GetAttr($follow, "attr");
    if(!isset($attr['content']) || strlen($attr['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content"]);

    //标志为已跟帖
    $db->update("bbs_data", ["rq"=>G::s_now(), "state"=>"新跟帖"], ["id"=>$followid]);

    //推送消息
    //class_wx::notice_ToAsk($db, $qa);

    return json_OK(["bbsid"=>$bbsid]);
  }
  /* ---------------------------------------------------------
   * 直接跟帖，不管草稿
   * --------------------------------------------------------- */
  public static function diredtfollow(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid", "data"]);
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    //验证数据:
    $data = $query["data"];
    if(!isset($data['content']) || strlen($data['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content"]);

    $attr = ["content"=>$data['content']];
    if(isset($data["images"]))$attr["images"] = $data['images'];
    if(isset($data["audios"]))$attr["audios"] = $data['audios'];

    if(isset($query['n_follow']))$attr['n_follow'] = $query['n_follow'] + 0;

    $followid = $db->insert("bbs_data", ["userid"=>$uid, "rq"=>G::s_now(), "state"=>"公开跟帖", "parentid"=>$bbsid, "attr"=>cn_json($attr)]);
    return json_OK(["followid"=>$followid]);
  }

  /* ---------------------------------------------------------
   * 审核发帖
   * --------------------------------------------------------- */
  public static function checkbbs(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid", "state"]);
    ASSERT_module($me, "service");

    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;
    $state = $query['state'];

    $bbs = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "parentid"=>0, "state"=>"新帖"]]);
    if(!$bbs)return json_error(1000, "没有找到新帖,$bbsid");

    //验证数据:
    if(!in_array($state, ["未结"]))return json_error(1000, "审核方式错误");

    //完成审核，并保存审核记录
    $attr = GetAttr($bbs, "attr");
    if(!$attr)$attr = [];
    $attr['checks'][]=["userid"=>$uid, "rq"=>G::s_now(), "state"=>$state];
    $db->update("bbs_data", ["attr"=>cn_json($attr), "state"=>$state], ["id"=>$bbsid]);

    //推送消息
    //class_wx::notice_ToAsk($db, $qa);

    return json_OK(["bbsid"=>$bbsid]);
  }

  /* ---------------------------------------------------------
   * 审核跟帖
   * --------------------------------------------------------- */
  public static function checkfollow(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["followid", "state"]);
    ASSERT_module($me, "service");

    $uid = $query['uid'] + 0;
    $followid = $query['followid'] + 0;
    $state = $query['state'];

    $follow = $db->get("bbs_data", "*", ["AND"=>["id"=>$followid, "parentid[>]"=>0, "state"=>"新跟帖"]]);
    if(!$follow)return json_error(1000, "没有找到跟帖,$followid");

    //验证数据:
    if(!in_array($state, ["公开跟帖", "私密跟帖"]))return json_error(1000, "审核方式错误");

    //完成审核，并保存审核记录
    $attr = GetAttr($follow, "attr");
    if(!$attr)$attr = [];
    $attr['checks'][]=["userid"=>$uid, "rq"=>G::s_now(), "state"=>$state];
    $db->update("bbs_data", ["attr"=>cn_json($attr), "state"=>$state], ["id"=>$followid]);

    //推送消息
    //class_wx::notice_ToAsk($db, $qa);

    return json_OK(["bbsid"=>$bbsid]);
  }

  /* ---------------------------------------------------------
   * 修改帖子
   * --------------------------------------------------------- */
  public static function servicemodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid", "data"], "service");
    ASSERT_right($db, $me, "论坛管理");
    return $db->modify_item($me, "service", "bbs_data", $query['bbsid'], $query['data']);
  }

  /* ---------------------------------------------------------
   * 删除帖子
   * --------------------------------------------------------- */
  public static function servicedelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"], "service");
    ASSERT_right($db, $me, "论坛管理");
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;
    $db->delete_row("bbs_data", $bbsid, "service", $uid);
    return json_OK([]);
  }

  /* ---------------------------------------------------------
   * 撤消删除帖子
   * --------------------------------------------------------- */
  public static function serviceundelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"], "service");
    ASSERT_right($db, $me, "论坛管理");
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;
    $db->undelete("bbs_data", $bbsid, "service", $uid);
    return json_OK([]);
  }

  /* ---------------------------------------------------------
   * 用户修改帖子
   * --------------------------------------------------------- */
  public static function usermodify(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid", "data"]);
    return $db->modify_item($me, "user", "bbs_data", $query['bbsid'], $query['data']);
  }

  /* ---------------------------------------------------------
   * 用户删除帖子
   * --------------------------------------------------------- */
  public static function userdelete(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"]);
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    //验证数据:
    $item = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "userid"=>$uid]]);//要自己的帖子
    if(!$item)return json_error(1000, "帖子不存在或没有权限");
    $db->delete_row("bbs_data", $bbsid, "user", $uid);
    return json_OK([]);
  }

  /* ---------------------------------------------------------
   * 用户撤消删除帖子
   * --------------------------------------------------------- */
  public static function userundelete(&$get, &$post, &$db){
    return json_error(1000, "暂不提供此功能，请与管理员联系。");
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"]);
    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;
    //验证数据:
    $item = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "userid"=>$uid]]);//要自己的帖子
    if(!$item)return json_error(1000, "帖子不存在或没有权限");
    $db->undelete("bbs_data", $bbsid, "user", $uid);
    return json_OK([]);
  }

  /* ---------------------------------------------------------
   * 向所有用户推荐帖子
   * --------------------------------------------------------- */
  public static function recommendtoall(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"], "service");
    ASSERT_right($db, $me, "推荐帖子");

    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    $bbs = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "parentid"=>0]]);
    if(!$bbs)return json_error(1000, "没有找到该帖子,$bbsid");

    //获取 attr 数据
    $attr = json_decode($bbs["attr"], 1);
    //操作记录：
    $attr["service_conmmend"][] = ["id"=>$uid, "rq"=>G::s_now(), "ac"=>"推荐"];
    //写入数据库
    $db->update("bbs_data", ["rq_recommend"=>G::s_now(), "attr"=>cn_json($attr)], ["id"=>$bbsid]);

    //生成最新的帖子推荐列表
    $list = $db->select("bbs_data", ["id", "attr"], ["AND"=>["id"=>$bbsid], "ORDER"=>"rq_recommend DESC", "LIMIT"=>5]);
    $text = [];
    foreach($list as $row){
      $attr = json_decode($row['attr'], 1);
      $content = mb_substr($attr['content'], 0 , 20, 'UTF-8');
      if($content != $attr['content'])$content .= "...";
      $url = WX_DOMAIN . "/qgs/wx?hash=%23%2Fframe%2Fbbs-show-user%3Fbbsid={$row['id']}";
      $text[] = "<a href='$url'>$content</a>";
    }

    //推送消息
    WX::send_bbs_recommend_news($text);

    return json_OK([]);
  }

  /* ---------------------------------------------------------
   * 取消推荐帖子
   * --------------------------------------------------------- */
  public static function unrecommend(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["bbsid"], "service");
    ASSERT_right($db, $me, "推荐帖子");

    $uid = $query['uid'] + 0;
    $bbsid = $query['bbsid'] + 0;

    $bbs = $db->get("bbs_data", "*", ["AND"=>["id"=>$bbsid, "parentid"=>0]]);
    if(!$bbs)return json_error(1000, "没有找到该帖子,$bbsid");

    //获取 attr 数据
    $attr = json_decode($bbs["attr"], 1);
    //操作记录：
    $attr["service_conmmend"][] = ["id"=>$uid, "rq"=>G::s_now(), "ac"=>"取消推荐"];
    //写入数据库
    $db->update("bbs_data", ["rq_recommend"=>"", "attr"=>cn_json($attr)], ["id"=>$bbsid]);

    //不重新生成最新的帖子推荐列表（下次推荐就好了）

    return json_OK([]);
  }


  /* ---------------------------------------------------------
   * 处理帖子的图文声音、各种属性
   * --------------------------------------------------------- */
  static function process_attr_cia(&$db, &$R, $is_service=false){
    $attr = GetAttr($R, "attr");
    $R['attr'] = [];
    if(!$is_service && $R["rq_delete"])return;//已删除的，不给信息了
    if(isset($attr['modified']))$R['attr']['modified'] = $attr['modified'];
    if(isset($attr['tags'    ]))$R['attr']['tags'    ] = $attr['tags'    ];
    if(isset($attr['content' ]))$R['attr']['content' ] = $attr['content' ];
    if(isset($attr['images'  ]))$R['attr']['images'  ] = $attr['images'  ];
    if(isset($attr['audios'  ])){
      $R['attr']['audios'] = $attr['audios'];
      $R['attr']["audioslist"] = class_wx::str_audios_to_list($db, $attr['audios']);
    }

    //回复几楼
    if(isset($attr['n_follow']))$R['attr']['n_follow'] = $attr['n_follow'];

    //显示修改记录
    //$attr["service_modify"][] = ["id"=>$uid, "rq"=>G::s_now(), "ac"=>"撤消删除"];$modify['olddata']["audios"]
    //$attr["user_modify"][] = $modify;

    if(!isset($attr['service_modify']))$attr['service_modify'] = [];
    if(!isset($attr['user_modify']))$attr['user_modify'] = [];
    if(!isset($attr['all_modify']))$attr['all_modify'] = [];
    $R['attr']['all_modify'] = array_merge($attr["all_modify"], $attr["service_modify"], $attr["user_modify"]);
    //排序
    usort($R['attr']['all_modify'], function ($a, $b){
      if($a['rq'] > $b['rq'])return 1;
      if($a['rq'] < $b['rq'])return -1;
      if($a['id'] > $b['id'])return -1;
      if($a['id'] < $b['id'])return 1;
      return 0;
    });
    if(count($R['attr']['all_modify'])>0){
      //升级一下数据库：
      $attr['all_modify'] = $R['attr']['all_modify'];
      unset($attr['service_modify'], $attr['user_modify']);
      $db->update("bbs_data", ["attr"=>cn_json($attr)], ["id"=>$R['id']]);
    }
    $R['debug'] = $attr;
      
    //显示修改记录（以后改成只有下面一句）：
    //$R['attr']['all_modify'] = $attr["all_modify"];

    //提供推荐信息：
    if($is_service){
      $R['attr']['service_conmmend'] = $attr['service_conmmend'];
    }



  }
  /* ------------------ 建表 ------------------------
    drop table if exists bbs_data;
    create table if not exists `bbs_data`
      (
        `id`             int(11)  auto_increment ,-- id
        `parentid`       int(11)     default 0   , -- 0 表示为楼主帖子，>0 表示跟帖，允许树结构
        `userid`         int(11)                 ,
        `state`          varchar(32) default ''  ,
        `rq_delete`      varchar(32) default ''  ,
        `rq_recommend`   varchar(32) default ''  ,
        `rq`             varchar(32) default ''  ,
        `attr`           text                    ,-- 正文、附件、审核记录、结帖记录
        primary key (`id`),
        unique key `id` (`id`)
      );
      -- ALTER TABLE `bbs_data` ADD `rq_delete` VARCHAR(32) NULL DEFAULT '' AFTER `state`;
      -- ALTER TABLE `bbs_data` ADD `rq_recommend` VARCHAR(32) NULL DEFAULT '' AFTER `rq_delete`;
  ------------------ 建表 ↑↑↑------------------------*/
}

?>