<?php
require_once("wxpay/Helper.php");
require_once("wxpay/lib/WxPay.Api.php");
require_once("api_qgs.php");

class class_wxpay{
  public static function out_trade_no($orderid){
    return WXPAY_PRE_PAYID . "$orderid";
  }
  /* --------------------------------------------------------------------------------------------
   * 获取扫码支付(模式二)的二维码：
   * -------------------------------------------------------------------------------------------- */
  public static function getqrpay2url(&$get, &$post, &$db){
    $query = &$post;
    $base_fields = ["uid", "orderid", "fen", "paymodule"];
    foreach($base_fields as $k)if(!isset($query[$k])){
      echo json_error(1000, "参数缺失: $k"  );
      exit;
    }

    $uid = $query['uid'] + 0;
    $orderid = $query['orderid'] + 0;
    $fen =  $query['fen'] + 0;
    $paymodule = trim($query['paymodule']);
    //获取用户的openid
    $openid = QgsModules::GetUser("openid", ["id"=>$uid]);
    //在数据库中生成一个支付订单
    $orderid = $db->insert("wx_pay", [
      "module" =>$paymodule,
      "orderno"=>"微信扫码支付",
      "userid" =>$uid,
      "openid" =>$openid,
      "rq"     =>G::s_now(),
      "n"      =>$fen
    ]);
    $input = new WxPayUnifiedOrder();
    $input->SetBody("扫码支付");
    $input->SetAttach("总金额：{$yuan}元");
    $input->SetOut_trade_no(self::out_trade_no($orderid));
    $input->SetTotal_fee($fen);
    $input->SetTime_start(date("YmdHis"));
    $input->SetTime_expire(date("YmdHis", time() + 600));
    $input->SetGoods_tag("订单");
    $input->SetNotify_url(WXPAY_NOTIFY_URL);
    $input->SetTrade_type("NATIVE");
    $input->SetProduct_id(self::out_trade_no($orderid));
    $result = WxPayApi::unifiedOrder($input);
    //var_dump($result);
    $url2 = $result["code_url"];
    return json_OK(["url2"=>$url2, "pay_order"=>["orderid"=>$orderid], "result"=>$result, "WXPAY_NOTIFY_URL"=>WXPAY_NOTIFY_URL]);
  }
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 生成一个支付订单 参数
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function get_pay_param(&$db, $openid, $recharge100, $paymodule, $uid=false){
    if(!$uid)$uid = QgsModules::GetUser("id", ["openid"=>$openid]);
    $total_fee = $recharge100 * 100;
    
    //在数据库中生成一个支付订单
    $orderid = $db->insert("wx_pay", [
      "module" =>$paymodule,
      "orderno"=>"qgs",
      "userid" =>$uid,
      "openid" =>$openid,
      "rq"     =>G::s_now(),
      "n"      =>$total_fee
    ]);
    
    //生成支付参数：
    $myorder_info = [
      'openid'       =>$openid,
      'body'         =>"用户充值",
      'detail'       =>"充值金额：{$recharge100}元",
      'out_trade_no' =>self::out_trade_no($orderid),
      'total_fee'    =>$total_fee
      ];
    $pay_order = self::get_pay_order($myorder_info);
    if(!$pay_order[0])return false;
    return ["orderid"=>$orderid, "js"=>$pay_order[1]];
    //return json_error(1, "AA", ["orderid"=>$orderid, 'openid'=>$openid, "js"=>$pay_order[1], "APPID"=>WxPayConf_pub::APPID, "KEY"=>WxPayConf_pub::KEY]);
  }
  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 充值
   * xxx.com/API/pay/recharge (POST、身份识别)
   * 
   * 参数：
   *     recharge100：充值金额（元）
   *     paymodule  ：充值模块，做标记
   * 
   * 返回：
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function recharge(&$get, &$post, &$db){
    $query = &$post;
    $base_fields = ["uid","recharge100", "paymodule"];
    foreach($base_fields as $k)if(!isset($query[$k])){
      echo json_error(1000, "参数缺失: $k"  );
      exit;
    }
    //还要验证用户？
    
    $uid = $query['uid'] + 0;
    $recharge100 =  $query['recharge100'] + 0;
    $paymodule = trim($query['paymodule']);
    
    //获取用户的openid
    $openid = QgsModules::GetUser("openid", ["id"=>$uid]);
    $R = self::get_pay_param($db, $openid, $recharge100, $paymodule, $uid);
    if(!$R)return json_error(8000, "请求失败");
    $R["openid"] = $openid;
    return json_OK($R);
  }

  
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 查询支付订单状态
   * xxx.com/API/pay/checkpay (POST、身份识别)
   * 返回：是否支付成功json
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function checkpay(&$get, &$post, &$db){
    $query = &$post;
    $base_fields = ["orderid"];
    foreach($base_fields as $k)if(!isset($query[$k])){
      echo json_error(1000, "参数缺失: $k"  );
      exit;
    }
    //还要验证用户？

    $uid = $query["uid"];
    $orderid =  $query['orderid'] + 0;
    $out_trade_no = self::out_trade_no($orderid);
    
    //已经确认是支付成功？即使是通知的，也相信？
    $successrq = $db->get("wx_pay", "successrq", ["id"=>$orderid]);
    if($successrq > "2015-01")return json_OK(["rq"=>$successrq]);
    
    //使用订单查询接口
    $orderQuery = new OrderQuery_pub();
    //设置必填参数
    //appid已填,商户无需重复填写
    //mch_id已填,商户无需重复填写
    //noncestr已填,商户无需重复填写
    //sign已填,商户无需重复填写
    $orderQuery->setParameter("out_trade_no","$out_trade_no");//商户订单号 
    //非必填参数，商户可根据实际情况选填
    //$orderQuery->setParameter("sub_mch_id","XXXX");//子商户号  
    //$orderQuery->setParameter("transaction_id","XXXX");//微信订单号
    
    //获取订单查询结果
    $orderQueryResult = $orderQuery->getResult();
    
    //处理订单查询结果
    if ($orderQueryResult["return_code"] == "FAIL") {
      return json_error(8001, "通信出错：".$orderQueryResult['return_msg']."<br>");
    }
    elseif($orderQueryResult["result_code"] == "FAIL"){
      return json_error(8001, "错误代码：".$orderQueryResult['err_code']."<br>"
        ."错误代码描述：".$orderQueryResult['err_code_des']."<br>");
    }
    else{
      if($orderQueryResult['trade_state']=="SUCCESS" && substr($orderQueryResult['time_end'],0,4) >= 2015 ){
        $s = $orderQueryResult['time_end'];
        $time_end = substr($s, 0, 4) ."-" . substr($s, 4, 2) ."-" . substr($s, 6, 2) ." " . substr($s, 8, 2) .":" . substr($s, 10, 2) .":" . substr($s, 12, 2);
        $db->update("wx_pay", [
          "successrq"=>($successrq=G::s_now()), 
          "successway"=>"check", 
          "json_query"=>json_encode($orderQueryResult), 
          "time_end"=>$time_end
          ], ["AND"=>["id"=>$orderid, "OR"=>["successrq[<]"=>"2015-01","json_query"=>""]]]);//标志为已收到付款，下次查就快了
        //通知收到款项
        class_qgs::notify_recharge($db, $orderid);
        return json_OK(["rq"=>$successrq]);
      }
      
      return json_error(8002, "");
      return json_error(8002, "", [      
        "交易状态"           =>$orderQueryResult['trade_state'],
        "设备号"             =>$orderQueryResult['device_info'],
        "用户标识"           =>$orderQueryResult['openid'],
        "是否关注公众账号"   =>$orderQueryResult['is_subscribe'],
        "交易类型"           =>$orderQueryResult['trade_type'],
        "付款银行"           =>$orderQueryResult['bank_type'],
        "总金额"             =>$orderQueryResult['total_fee'],
        "现金券金额"         =>$orderQueryResult['coupon_fee'],
        "货币种类"           =>$orderQueryResult['fee_type'],
        "微信支付订单号"     =>$orderQueryResult['transaction_id'],
        "商户订单号"         =>$orderQueryResult['out_trade_no'],
        "商家数据包"         =>$orderQueryResult['attach'],
        "支付完成时间"       =>$orderQueryResult['time_end']
      ]);
    }  
    
    
    //return json_error(8000, "请求失败");
    return json_OK(["payed"=>0]);
  }













  //$myorder_info['openid'        ]
  //$myorder_info['body'          ]  //商品描述
  //$myorder_info['detail'        ]  //商品详情String(8192)
  //$myorder_info['out_trade_no'  ]  //商户订单号
  //$myorder_info['total_fee'     ]  //总金额
  static function get_pay_order($myorder_info){
    //使用jsapi接口
    $jsApi = new JsApi_pub();
    $jsApi->openid = $myorder_info['openid'];
    
    //=========步骤2：使用统一支付接口，获取prepay_id============
    //使用统一支付接口
    $unifiedOrder = new UnifiedOrder_pub();
    
    //设置统一支付接口参数
    //设置必填参数
    //appid已填,商户无需重复填写
    //mch_id已填,商户无需重复填写
    //noncestr已填,商户无需重复填写
    //spbill_create_ip已填,商户无需重复填写
    //sign已填,商户无需重复填写
    $unifiedOrder->setParameter("openid"      ,"{$myorder_info['openid']}"      );//openid //注：原此处微信官方demo注释有误，已更正。
    $unifiedOrder->setParameter("body"        ,"{$myorder_info['body']}"        );//商品描述String(32)
    $unifiedOrder->setParameter("detail"      ,"{$myorder_info['detail']}"      );//商品详情String(8192)
    $unifiedOrder->setParameter("out_trade_no","{$myorder_info['out_trade_no']}");//商户订单号 String(32)
    $unifiedOrder->setParameter("total_fee"   ,"{$myorder_info['total_fee']}"   );//总金额
    $unifiedOrder->setParameter("notify_url"  , WXPAY_NOTIFY_URL        );//通知地址 
    $unifiedOrder->setParameter("trade_type"  ,"JSAPI"                          );//交易方式
    //非必填参数，商户可根据实际情况选填
    //$unifiedOrder->setParameter("sub_mch_id","XXXX");   //子商户号  
    //$unifiedOrder->setParameter("device_info","XXXX");  //设备号 
    //$unifiedOrder->setParameter("attach","XXXX");       //附加数据 
    //$unifiedOrder->setParameter("time_start","XXXX");   //交易起始时间
    //$unifiedOrder->setParameter("time_expire","XXXX");  //交易结束时间 
    //$unifiedOrder->setParameter("goods_tag","XXXX");    //商品标记 
    //$unifiedOrder->setParameter("product_id","XXXX");   //商品ID

    $prepayResult = $unifiedOrder->getPrepayResult();
    if(!isset($prepayResult['prepay_id'])) return array(0, $prepayResult['err_code_des']);
    //=========步骤3：使用jsapi调起支付============
    $jsApi->setPrepayId($prepayResult['prepay_id']);

    $jsApiParameters = $jsApi->getParametersArray();
    
    return array(1, $jsApiParameters);
  }
  

  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             发放红包                                                         ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function pay_HB($re_openid, $money, $mch_billno, $wishing, $act_name){
    $pay = new WxPayOutHelper();
    $pay->setParameter("nonce_str", G::createNoncestr(32));//随机字符串，丌长于 32 位
    $pay->setParameter("mch_billno", $mch_billno);//订单号
    $pay->setParameter("mch_id", WXPAY_MCHID);//商户号
    $pay->setParameter("wxappid", APPID);
    $pay->setParameter("nick_name", '请高手平台');//提供方名称
    $pay->setParameter("send_name", $act_name);//红包发送者名称1、测试红包请截屏
    $pay->setParameter("re_openid", $re_openid);//openid
    $pay->setParameter("total_amount", $money);//付款金额，单位分
    $pay->setParameter("min_value", $money);//最小红包金额，单位分
    $pay->setParameter("max_value", $money);//最大红包金额，单位分
    $pay->setParameter("total_num", 1);//红包収放总人数
    $pay->setParameter("wishing", $wishing);//红包祝福
    $pay->setParameter("client_ip", '127.0.0.1');//调用接口的机器 ip 地址
    $pay->setParameter("act_name", $act_name."活动");//活动名称
    $pay->setParameter("remark", $wishing);//备注信息3、这是备注！
    $postXml = $pay->create_hongbao_xml();
    $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';
    return $responseXml = $pay->curl_post_ssl($url, $postXml);
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             企业付款                                                         ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function pay_QYFK($openid, $money, $mch_billno, $wishing){
    $pay = new WxPayOutHelper();
    $pay->setParameter("mch_appid", APPID);
    $pay->setParameter("nonce_str", G::createNoncestr(32));//随机字符串，丌长于 32 位
    $pay->setParameter("mchid", WXPAY_MCHID);//商户号
    $pay->setParameter("partner_trade_no", $mch_billno);//订单号
    $pay->setParameter("openid", $openid);//openid
    $pay->setParameter("check_name", "NO_CHECK");//不校验真实姓名
    $pay->setParameter("amount", $money);//付款金额，单位分
    $pay->setParameter("desc", $wishing);//企业付款操作说明信息
    $pay->setParameter("spbill_create_ip", '127.0.0.1');//调用接口的机器 Ip 地址
    $postXml = $pay->create_hongbao_xml();
    $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
    return $responseXml = $pay->curl_post_ssl($url, $postXml);
  }
}




?>