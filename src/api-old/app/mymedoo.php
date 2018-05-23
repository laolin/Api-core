<?php
require_once("medoo.php");
  
class mymedoo extends medoo {

  public function get($table, $join = null, $column = null, $where = null){
    $R = parent::get($table, $join, $column, $where);
    if(is_array($R)) return array_change_key_case($R, CASE_LOWER);
    return $R;
  }
  public function select($table, $join = null, $column = null, $where = null){
    $R = parent::select($table, $join, $column, $where);
    if(is_array($R)) return array_change_key_case($R, CASE_LOWER);
    return $R;
  }

  //DEBUG
  public function show() {
    echo "DB = " .($this->database_name)."<br>";
    var_dump($this->last_query());
    var_dump($this->error());
  }
  public function getShow() {
    return [
      "DB"         => $this->database_name,
      "last_query" => $this->last_query(),
      "error"      => $this->error()];
  }
  
  //如果一个表内存在一个符合条件的行，就更新value
  //如果没有，就插入一行
  public function surerow($table, $values, $where) {
    $n = $this->count($table, ["AND"=>$where]);
    if($n > 1)$this->delete($table, $where);
    if($n == 1){
      return $this->update($table, $values, ["AND"=>$where]);
    }
    else {
      return $this->insert($table, array_merge($values, $where));
    }
  }
  
  //返回一个以$key列为索引的数组
  public function get_array($table, $columns, $where, $key, $value=false) {
    $arr = $this->select($table, $columns, $where);
    if(!is_array($arr))return [];
    $R = [];
    foreach($arr as $row){
      $R[$row[$key]] = $value===false ? $row : $row[$value];
    }
    return $R;
  }
  
  


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 保存回答一项json数据
   * glue: 
   *     非空: 在这项内容的后面用这个字符（串）连接到末尾
   *     false：直接更新这项内容
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public function UpdateJson($table, $column, $field, $value, $AND, $glue=false){
    $oldanswer = $this->get($table, $column, ["AND"=>$AND]);
    if($oldanswer){
      $arr = json_decode($oldanswer, true);
      $arr[$field] = $glue && isset($arr[$field]) && $arr[$field] ? "{$arr[$field]}$glue$value" : $value;
      $newanswer = json_encode($arr, JSON_UNESCAPED_UNICODE);
      $this->update($table, [$column=>$newanswer], ["AND"=>$AND]);
    }
    else{//没有旧数据：
      $this->update($table, [$column=>json_encode([$field=>$value], JSON_UNESCAPED_UNICODE)], ["AND"=>$AND]);
    }
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 保存回答一项json数据
   * TCA: table、column、where
   * KKK：k1、k2...
   * V  : value, 任意
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public function SaveJson($TCA, $KKK, $V){
    $old_v = $this->get($TCA[0], $TCA[1], $TCA[2]);
    $arr = $old_v ? json_decode($old_v, true) : [];
    $p = &$arr;
    foreach($KKK as $k){
      if(!isset($p[$k]) || !$p[$k])$p[$k] = [];
      $p = &$p[$k];
    }
    $p = $V;
    $new_v[$TCA[1]] = json_encode($arr, JSON_UNESCAPED_UNICODE);
    return $this->update($TCA[0], $new_v, $TCA[2]);
  }
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 添加一项文本到指定json
   * TCA : table、column、where
   * KKK ：k1、k2...
   * str : 要添加的文本
   * glue: 文本之间的连接符
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public function AppendJson($TCA, $KKK, $str, $glue){
    $old_v = $this->get($TCA[0], $TCA[1], $TCA[2]);
    $arr = $old_v ? json_decode($old_v, true) : [];
    $p = &$arr;
    foreach($KKK as $k){
      if(!$p[$k])$p[$k] = [];
      $p = &$p[$k];
    }
    if(is_string($p) && strlen($p) > 0)$p .= $glue . $str;
    else $p = $str;
    $new_v[$TCA[1]] = json_encode($arr, JSON_UNESCAPED_UNICODE);
    return $this->update($TCA[0], $new_v, $TCA[2]);
  }

  // -------------------------- 修改一条数据的 CIA -------------------------------
  public function modify_item($me, $module, $table, $ciaid, $data, $KKK=false){
    //验证数据:
    if(!isset($data['content']) || strlen($data['content'])<3)return json_error(1000, "正文最少3个字符",["field"=>"content", $data['content']]);
    //操作信息：
    $modify = ["id"=>$me['id'], "rq"=>API_now(), "ac"=>$module=='service'?"编导修改":"用户修改", "module"=>$module];

    //获取旧数据
    $item = $this->get($table, "*", ["AND"=>["id"=>$ciaid]]);
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
    $this->update($table, ["attr"=>cn_json($attr)], ["id"=>$ciaid]);
    return json_OK([]);
  }
  /* ----------------------------------------------------
   * 删除一行
   * ---------------------------------------------------- */
  public function delete_row($table, $id, $module, $uid){
    //获取 attr 数据
    $attr = json_decode($this->get($table, "attr", ["AND"=>["id"=>$id]]), 1);
    //保存操作信息
    $attr["all_modify"][] = ["id"=>$uid, "rq"=>API_now(), "ac"=>"删除", "module"=>$module];
    //写入数据库：
    $this->update($table, ["rq_delete"=>API_now(), "attr"=>cn_json($attr)], ["id"=>$id]);
  }
  /* ----------------------------------------------------
   * 恢复一行
   * ---------------------------------------------------- */
  public function undelete($table, $id, $module, $uid){
    //获取 attr 数据
    $attr = json_decode($this->get($table, "attr", ["AND"=>["id"=>$id]]), 1);
    //保存操作信息
    $attr["all_modify"][] = ["id"=>$uid, "rq"=>API_now(), "ac"=>"撤消删除", "module"=>$module];
    //写入数据库：
    $this->update($table, ["rq_delete"=>"", "attr"=>cn_json($attr)], ["id"=>$id]);
  }
}
?>