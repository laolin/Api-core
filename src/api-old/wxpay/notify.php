<?php
/**
 * 通用通知接口demo
 * ====================================================
 * 支付完成后，微信会把相关支付和用户信息发送到商户设定的通知URL，
 * 商户接收回调信息后，根据需要设定相应的处理流程。
 * 
 * 这里举例使用log文件形式记录回调信息。
*/

require_once("{$_SERVER['DOCUMENT_ROOT']}/qgs/config/config.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/qgs/api/app/mymedoo.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/qgs/api/app/g.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/qgs/api/app/api_qgs.php");

require_once("Helper.php");

    //使用通用通知接口
	$notify = new Notify_pub();

	//存储微信的回调
	$xml = $GLOBALS['HTTP_RAW_POST_DATA'];	
	
	//DB::Insert("LOG_DB", array("SQL"=>"执行日期：".date("Y-m-d H:i:s",time())."\n".$word));
	
	$notify->saveData($xml);
	
	$checkSign = $notify->checkSign();
	
	//验证签名，并回应微信。
	//对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
	//微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
	//尽可能提高通知的成功率，但微信不保证通知最终能成功。
	if($checkSign == FALSE){
		$notify->setReturnParameter("return_code","FAIL");//返回状态码
		$notify->setReturnParameter("return_msg","签名失败");//返回信息
	}else{
		$notify->setReturnParameter("return_code","SUCCESS");//设置返回码
		$notify->setReturnParameter("return_msg","OK");//返回信息
	}
	$returnXml = $notify->returnXml();
	echo $returnXml;
	
	//==商户根据实际情况设置相应的处理流程，此处仅作举例=======
	
	//以log文件形式记录回调信息
  $log_ = new Log_();
  $log_name="{$_SERVER['DOCUMENT_ROOT']}". WXPAY_NOTIFY_LOG_NAME;//log文件路径
	$log_->log_result($log_name,"【接收到的notify通知】:\n".$xml."\n");
	$log_->log_result($log_name,"【回复】:\n".($checkSign? "签名正确":"签名失败")."[".$returnXml."]\n");
	
	//WXPAY::handle_notify($notify->data);

	if($checkSign == TRUE)
	{
		if ($notify->data["return_code"] == "FAIL") {
			//此处应该更新一下订单状态，商户自行增删操作
			$log_->log_result($log_name,"【通信出错】:\n\n");
		}
		elseif($notify->data["result_code"] == "FAIL"){
			//此处应该更新一下订单状态，商户自行增删操作
			$log_->log_result($log_name,"【业务出错】:\n\n");
		}
		else{
			$orderid = substr($notify->data["out_trade_no"], 3) + 0;
			$s = $notify->data["time_end"];
			$time_end = substr($s, 0, 4) ."-" . substr($s, 4, 2) ."-" . substr($s, 6, 2) ." " . substr($s, 8, 2) .":" . substr($s, 10, 2) .":" . substr($s, 12, 2);
			$db = new mymedoo();
		  $db->update("wx_pay", [
		  		"successrq"=>G::s_now(), 
		  		"successway"=>"notify", 
		  		"json_notify"=>json_encode($notify->data), 
		  		"time_end"=>$time_end
		  		], ["AND"=>["id"=>$orderid, "OR"=>["successrq[<]"=>"2015-01","json_notify"=>""]]]);//标志为已收到付款

			//通知收到款项
			class_qgs::notify_recharge($db, $orderid);

			$log_->log_result($log_name,"【SQL】:\n".$db->last_query()."\n");

		}
		
		//商户自行增加处理流程,
		//例如：更新订单状态
		//例如：数据库操作
		//例如：推送支付完成信息
	}
?>