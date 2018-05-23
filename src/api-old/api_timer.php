<?php
  require_once("app/user.class.php");
  require_once("app/g.php");
  require_once("app/wx.php");
  require_once("api_qa.php");

class class_timer{

  public static function run(&$get, &$post, &$db){
    error_log("请高手定时器");
    self::run_timer($db);
    return 1;
  }
  /* ------------------------------------------------------------------------------
   * 触发定时器
    5.1	新提问
      用户发出提问后10分钟
      通知编导
      积分预授权
    5.2	全部回答完成
      所有专家已提交回答后10分钟
      通知用户已回答完成
      生成评价行
    5.3	全部放弃回答
      所有专家已提交回答后10分钟
      通知编导，重新找专家
    5.4	时效过半，未提交回答
      提醒编导催促
      催促专家回答
    5.5	时效再过半，且剩余时效不止24小时，仍未回答
      提醒编导催促
      催促专家回答
    5.6	问答时限到了，有回答
      通知用户已回答完成
      生成评价行
    5.7	问答时限到了，没回答 ？？？
      通知编导，重新找专家
    5.8	用户评价不满意
      评价后10分钟 
      通知编导
    5.9	用户评价满意
      评价后10分钟 
      通知编导
      积分授权改为“评价满意”扣积分
      通知专家：收到积分

   * ------------------------------------------------------------------------------ */
  public static function run_timer(&$db, $delay = 10){
    $hhmm = Date('Hi');
    $hh = substr($hhmm,0,2) + 0;
    $mm = substr($hhmm,2) + 0;
    if($hh < 8) return;
    if($GLOBALS['run_timer_runign']) return;
    $GLOBALS['run_timer_runign'] = 1;
    //确保$delay秒内不重复触发
    $t_now = time();
    $last_timer = $db->get("g_settings", "v1", [ "k1"=>'网页定时器']); if(!$last_timer) $last_timer = 0;
    if(!$last_timer){
      $db->insert("g_settings", ["v1"=>$t_now, "k1"=>'网页定时器']);
    }
    else{
      if($t_now - $last_timer < $delay)return;
      $db->update("g_settings", ["v1"=>$t_now], ["k1"=>'网页定时器']);
    }

    //error_log("run_timer");
    class_qa::run_timer($db);
  }
}

?>