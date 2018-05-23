<?php

class CQA{
  public $db;
  public $tableName;
  function __construct($db, $tableName = "qa_data") {
    $this->module = $module;
    $this->tableName = $tableName;
    if(is_string($db)){
      $this->db = new mymedoo($db);
    }
    else{
      $this->db = db;
    }
  }

  //处理问或答的属性数据：json转数组，处理声音等
  private function  process_attr(&$db, &$row){
    if(!$row['attr']){
      $row['attr'] = [];
      return; 
    }
    if(!is_array($row['attr']))$row['attr'] = json_decode($row['attr'], true);
    if(isset($row['attr']['audios']) && $row['attr']['audios'])$row['attr']["audioslist"] = class_wx::str_audios_to_list($db, $row['attr']['audios']);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 以用户身份获取问答详情
   * xxx.com/qa/getdetail (身份识别)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public function getDetailUser($uid, $qaid){    
    $rows = $db->select($this->tableName, "*", ["OR"=>["id"=>$qaid, "parentid"=>$qaid], "ORDER"=>"id"]);
    if(!$rows)return CJson::errorData("no qa");

    $userids = [];
    $R = [];
    
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
        $ks[] = [];
        continue;
      }          
    }
    
    return CJson::OK(["qadetail"=>$R]);
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 获取问答详情
   * xxx.com/qa/getdetail (身份识别)
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public function getdetail(&$query){
    //步骤2：读取数据
    $uid = $query['uid'] + 0;
    $qaid = $query['qaid'] + 0;
    
    $rows = $db->select($this->tableName, "*", ["OR"=>["id"=>$qaid, "parentid"=>$qaid], "ORDER"=>"id"]);
    if(!$rows)return CJson::errorData("no qa");

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
            $ks[] = [];
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
    return CJson::OK(["qadetail"=>$R]);
  }


  
}

?>