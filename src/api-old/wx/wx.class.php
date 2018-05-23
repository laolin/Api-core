<?php

class CWxApi{
  public $db;
  public $tableName;
  public $APPID;
  public $SECRET;
  function __construct($db, $tableName = "g_settings", $APPID="", $SECRET="") {
    $this->APPID  = $APPID ? $APPID  : APPID ;
    $this->SECRET = $SECRET? $SECRET : SECRET;
    $this->tableName = $tableName;
    if(is_string($db)){
      $this->db = new mymedoo($db);
    }
    else{
      $this->db = db;
    }
  }

	// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
	// ┃              获取 Token                                                      ┃
	// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  public function GetToken(){
    $row = $this->db->get($this->tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'access_token', "k2"=>$this->APPID]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局Token仍然有效
    }

    //获取新的 全局Token:
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential"
           . "&appid=" . $this->APPID . "&secret=" . $this->SECRET;
    $r = G::httpGet($url);
    
    $dick_token = json_decode($r);
    if(isset($dick_token->errcode)) return "";
    //得到了：
    $token = $dick_token->access_token;
    if(strlen($token)<10)return "";
    //写入数据库，以后可以再用：
    if ($row){
      $this->db->update($this->tableName, ["v1"=>$token,"v2"=>$t_now], ["AND"=>["k1"=>'access_token', "k2"=>$this->APPID]]);
    }else{
      $this->db->insert($this->tableName, ["v1"=>$token,"v2"=>$t_now,"k1"=>'access_token', "k2"=>$this->APPID]);
    }
    return $token;
  }
  public function GetJsApiTicket(){
    $row = $this->db->get($this->tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'jsapi_ticket', "k2"=>$this->APPID]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局token仍然有效
    }

    //获取新的 全局Token:
    $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$this->GetToken();
    $res = json_decode(G::httpGet($url));
    if(!isset($res->errcode) || $res->errcode != 0) return "";
    $ticket = $res->ticket;
    if (!$ticket) return "";

    //写入数据库，以后可以再用：
    if ($row){
      $this->db->update($this->tableName, array("v1"=>$ticket,"v2"=>$t_now), ["AND"=>["k1"=>'jsapi_ticket', "k2"=>$this->APPID]]);
    }else{
      $this->db->insert($this->tableName, ["v1"=>$ticket,"v2"=>$t_now,"k1"=>'jsapi_ticket', "k2"=>$this->APPID]);
    }
    return $ticket;
  }
  
  
  
    
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             发送模板消息                                                     ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  public function SendTPL($openids, $tplName, $data, $url){
    foreach($GLOBALS["WX_TPL"] as $name=>$tpl){
      if($name!=$tplName)continue;
      //找到模板了，整理一下openids：
      if(!is_array($openids))$openids = [$openids];
      //开始发送：
      $send_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->GetToken();
      foreach($openids as $openid){
        if(strlen($openid)<10)continue;
        $D = [
          "touser"=> $openid,
          "template_id"=> $tpl["template_id"], 
          "url"=> $url,
          "topcolor"=>"#FF0000",
          "data"=>[]
        ];
        $i = 0; foreach($tpl["data"] as $k=>$color) $D["data"][$k] = ["value"=>$data[$i++], "color"=>$color];
        $json = urldecode(json_encode($D));//G::JSON_from($D);
        $r = G::post($send_url, $json);
        $R[] = [$send_url, $json, $r];
      }
      return $R;
    }
    return [];
  }
    
    
}

?>