<?php
namespace NApiUnit;


class CWhiteHost {
  /**
   * 统一入口，只允许 https 调用
   */
  public static function check(){
    $whiteList = \DJApi\Configs::get("wx_token_white_list");
    // 允许同域名调用
    $sameDomain  = $_SERVER['HTTP_ORIGIN'] == "https://".$_SERVER['HTTP_HOST'] || $_SERVER['HTTP_ORIGIN'] == "http://".$_SERVER['HTTP_HOST'];
    $sameIP      = $_SERVER['SERVER_ADDR'] && $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'];
    $whiteDomain = in_array($_SERVER['HTTP_ORIGIN'], $whiteList["whiteDomain"]);
    $whiteIP     = in_array($_SERVER['REMOTE_ADDR'], $whiteList["whiteIP"    ]);
    if(!$sameDomain && !$whiteDomain && !$sameIP && !$whiteIP){
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "非法调用");
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "非法调用", [
        'sameDomain'=>$sameDomain,
        'whiteDomain'=>$whiteDomain,
        'sameIP'=>$sameIP,
        $_SERVER,
      ]);
    }
    return \DJApi\API::OK();
  }
}
