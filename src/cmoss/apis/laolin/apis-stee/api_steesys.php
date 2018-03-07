<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
 
*/

use DJApi\API as NewApi;


require_once dirname( __FILE__ ) . '/class.stee_user.php';
class class_steesys {
    
    
  public static function main( $para1,$para2) {
    $res=API::data(['time'=>time().' - steefac is ready.']);
    return $res;
  }
  static function userVerify() {
    return USER::userVerify();
  }
  
  //test
  public static function test( ) {
    //$r=self::userVerify();
    //if(!$r)
    //  return API::msg(202001,'error userVerify');
    $tk=1;//WX::GetToken();
    return API::data(['Test passed.',$tk]);
  }
 
//=========================================================
  //获取 数据表名
  static function table_name($item ) {
    $prefix=api_g("api-table-prefix");
    return $prefix.$item;
  }
  
  
  /*
  $data = $database->query(
    "SELECT * FROM account WHERE user_name = :user_name AND age = :age", [
      ":user_name" => "John Smite",
      ":age" => 20
    ]
  )->fetchAll();
 
  print_r($data);
  
  */
  public static function info( ) {
    $data=[];
    $db = \DJApi\DB::db();
    
    $tblname=self::table_name('steelfactory');
    $data['nFac'] = $db->query(
      "SELECT count(*) as nFac FROM $tblname WHERE `mark` is  null or `mark` = '' "
    )->fetchAll()[0]['nFac'];

    $tblname=self::table_name('steelproject');
    $data['nProj'] = $db->query(
      "SELECT count(*) as nProj FROM $tblname WHERE `mark` is  null or `mark` = '' "
    )->fetchAll()[0]['nProj'];
    
    $uid = USER::userVerify();
    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid'=>$uid, 'bindtype'=>'wx-unionid']);
    \DJApi\API::debug(['读取微信信息', $uid, $wxInfoJson]);
    if(\DJApi\API::isOk($wxInfoJson)){
      $data['wx'] = $wxInfoJson['datas']['list'][0];
    }
    if(!$uid) {
      \DJApi\API::debug('签名错误');
      return \DJApi\API::OK($data);
    }
    $r=stee_user::_get_user($uid);
    $data['me']=($r?$r:1);//这里待改进（需要客户端更新）

    // 该用户id($uid)今天已发送的几种数量['and'=>['uid'=>$uid, 'time[~]'=>"%$today"], 'type'=>['公司推广', '项目推广']]
    $json = \DJApi\API::post(LOCAL_API_ROOT, "use-records/data/count", [
      'module' => 'cmoss',
      'and'=>['uid'=>$uid, "time[~]"=>NewApi::today() . "%"],
      'k2' => '发送推广消息',
      'k1' =>['公司', '项目']
    ]);
    $data['datas']['msg'] = [
      'json' => $json,
      'used' => $json['datas']['used'], //['公司'=> 0, '项目'=> 0],
      'max' => ['公司'=>50, '项目'=>50, '全部'=>55]
    ];

    return \DJApi\API::OK($data);
  }
  
 
  public static function send_todo_msg( ) {
    
    $uid=intval(API::INP('uid'));
    $user=stee_user::_get_user($uid );
    if(!($user['is_admin'] & 0x10000)) {
      return API::msg(202001,'not sysadmin '.$user['is_admin']);
    }
    
    $to_uid=API::INP('to_uid');
    if(!$to_uid) {
      return API::msg(202001,'E:to_uid:'.$to_uid);
    }
    $title=API::INP('title');
    if(!$title) {
      return API::msg(202001,'E:title:'.$title);
    }
    $content=API::INP('content');
    if(!$content) {
      return API::msg(202001,'E:content:'.$content);
    }
    $url=API::INP('url');
    if(!$url) {
      return API::msg(202001,'E:url:'.$url);
    }
    $url='https://qinggaoshou.com/gstools/steefac/#!'.$url;
    
    return self::sendTodoTplMsg($to_uid,$title,$content,$url);
  }  
  
  
  
  
  /**
   *
   *  【模板消息】  
   *
   *  标题：
   *     待办事项提醒
   *  
   *  详细内容：
   *    {{first.DATA}}
   *    待办内容：{{keyword1.DATA}}
   *    基本信息：{{keyword2.DATA}}
   *    时间：{{keyword3.DATA}}
   *    {{remark.DATA}}
   *  
   */
  static function sendTodoTplMsg(
    $msg_to_uid, $title, $content,$url
  ) {
  
    $app='qgs-mp';//使用哪个公众号发消息
    $appid=api_g('WX_APPS')[$app][0];
    
    $oids=WX::getOpenIdByUid($appid, [$msg_to_uid] );
    if(!count($oids)) {
      return API::msg(701,"Cannot send to uid:$msg_to_uid");
    }

    $D=[];
    $D["touser"] = $oids[0]['openid'];
    $D["template_id"]="Nv4ZwapozItcKb6VvkcCk7m1-UjkqLEss8xwMuAAQj8";
		$D["url"] = $url;
    $D["data"]=[
      'first'=>['value'=>'中国钢结构产能地图系统消息','color'=>'#0099ff'],
      'keyword1'=>['value'=>$title,'color'=>'#222222'],
      'keyword2'=>['value'=>$content,'color'=>'#222222'],
      'keyword3'=>['value'=>date('Y年m月d日 H:i'),'color'=>'#222222'],
      'remark'=>['value'=>"请点击详情及时处理",'color'=>'#0099ff']
    ];

    return WX::send_tpl_message($D);
    
  }

  

}
