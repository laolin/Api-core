<?php

class CMessage{
  public $module;
  public $db;
  public $tableName;
  function __construct($module, $db, $tableName = "g_message") {
    $this->module = $module;
    $this->tableName = $tableName;
    if(is_string($db)){
      $this->db = new mymedoo($db);
    }
    else{
      $this->db = db;
    }
  }

  //插入新的消息
  public function insert($text, $userid = "", $type = "", $target_url = ""){
    $now = G::s_now();
    if(is_array($text){
      $userid     = $text["userid"];
      $type       = $text["type"];
      $target_url = $text["target_url"];
      $t_send_b   = $text["t_send_b"]; if(!$t_send_b)$t_send_b = $now;
      $t_send_e   = $text["t_send_e"]; if(!$t_send_e)$t_send_e = "9999-99";
    }
    $attr = cn_json(["text"=>$text]);
    $this->db->insert($this->tableName, [
      "module"    =>$this->module,
      "attr"      =>$attr,
      "userid"    =>$userid,
      "type"      =>$type,
      "t_create"  =>$now,
      "t_send_b"  =>$t_send_b,
      "t_send_e"  =>$t_send_e,
      "t_send"    =>$now,
      "target_url"=>$target_url
    ]);
  }

  //获取一行
  public function getRow($AND, $fields = false){
    if(!$fields){
      $fields = ["id", "attr", "userid", "type", "target_url"];
    }
    $R = $this->db->get($this->tableName, $fields, $AND);
    if(isset($R["attr"]))$R["attr"] = json_decode($row["attr"]);
    return $R;
  }

  //获取列表
  public function getList($AND, $fields){
    $R = $this->db->select($this->tableName, $fields, $AND);
    foreach($R as $k=>$row){
      $R[$k]["attr"] = json_decode($row["attr"]);
    }
    return $R;
  }

  //获取待发送消息列表
  public function getToSendList($AND, $fields = false){
    if(!$fields){
      $fields = ["id", "attr", "userid", "type", "target_url"];
    }
    return $this->getList(["AND"=>["AND"=>$AND, "t_send"=>"", "t_send_b[>]"=>$now, "t_send_e[<]"=>$now]], $fields);
  }

  //获取我的未读消息列表
  public function getMyUnreadedList($userid, $fields = false){
    if(!$fields){
      $fields = ["id", "attr", "userid", "type", "target_url"];
    }
    return $this->getList(["AND"=>["t_send[>]"=>"2016-00", "t_read"=>"", "userid"=>$userid]], $fields);
  }

  //获取我的未读消息列表
  public function getMyReadedList($userid, $fields = false){
    if(!$fields){
      $fields = ["id", "attr", "userid", "type", "target_url"];
    }
    return $this->getList(["AND"=>["t_read[>]"=>"2016-00", "userid"=>$userid]], $fields);
  }

  //标志消息为已读
  public function markReaded($id){
    $now = G::s_now();
    $this->db->update($this->tableName, ["t_read"=>$now], ["id"=>$id]);
  }

  //标志消息为未读
  public function markUnReaded($id){
    $this->db->update($this->tableName, ["t_read"=>""], ["id"=>$id]);
  }

  //标志消息为已完成
  public function markDone($id){
    $now = G::s_now();
    $this->db->update($this->tableName, ["t_done"=>$now], ["id"=>$id]);
  }

  //标志消息为未完成
  public function markUnDone($id){
    $this->db->update($this->tableName, ["t_done"=>""], ["id"=>$id]);
  }

  //标志消息为已删除
  public function markDone($id){
    $now = G::s_now();
    $this->db->update($this->tableName, ["t_delete"=>$now], ["id"=>$id]);
  }

  //标志消息为未删除
  public function markUnDone($id){
    $this->db->update($this->tableName, ["t_delete"=>""], ["id"=>$id]);
  }

  
}

?>