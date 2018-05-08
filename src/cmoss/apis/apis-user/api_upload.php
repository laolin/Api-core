<?php
// ================================
/*
*/
namespace RequestByApiShell;

if(!defined('MAX_FILE_SIZE')){
  define('MAX_FILE_SIZE', 5e6);
}

class class_upload {
  public static function getOssConfig($name) {
    $configs = \DJApi\Configs::get(["OSS_access", $name]);
    if(!$configs)$configs = \DJApi\Configs::get("OSS_access_default");
    return $configs;
  }

  /**
   * 接口： upload/img
   * 上传图片
   */
  public static function img($request) {
    $verify_token = \MyClass\CUser::verify($request->query);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];

    $upload = $_FILES['file'];
    $from = $upload["tmp_name"];
    if(!$from){
      return \DJApi\API::error(9001, "上传错误", ["upload"=>$upload, "FILES" => $_FILES]);
    }
    $name = $upload["name"];
    $size = $upload["size"] + 0;
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if($ext=='mp4'){
      if($size > MAX_FILE_SIZE * 3){
        return \DJApi\API::error(9001, "超出视频大小限制");
      }
    }
    else if($size > MAX_FILE_SIZE){
      return \DJApi\API::error(9001, "超出图片大小限制,$ext");
    }

    \DJApi\API::debug(["uid"=>$uid, "upload"=>$upload, "size"=>$size, "name"=>$name, "_FILES"=>$_FILES]);


    $OSS_config = self::getOssConfig($request->query['name']);
    if(!$OSS_config) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数错误");
    }
    // \DJApi\API::debug(["OSS_config"=>$OSS_config]); 敏感数据，测试后即关闭
    //return \DJApi\API::OK();

    \DJApi\Configs::readConfigOnce('api-lib/ali-oss-upload/autoload.php');

    $ossClient = new \OSS\OssClient($OSS_config['accessKeyId'], $OSS_config['accessKeySecret'], $OSS_config['endpoint'], false);

    $sha_file = sha1_file($from);
    $fn = "sha-$sha_file.$ext";
    $to = $OSS_config['path'] . '/' . $fn;
    $url = $OSS_config['baseurl'] . "/" .$to;
    $path = $OSS_config['baseurl'] . "/" . $OSS_config['path'] . '/';
    //return \DJApi\API::OK([$OSS_config['bucket'], $to, $from]);
    try{
      $ossClient->uploadFile($OSS_config['bucket'], $to, $from);
      // 此处可以增加代码, 以在本地数据库记录上传情况
    } catch(OssException $e) {
      return \DJApi\API::error(9001, $e->getMessage());
    }
    return \DJApi\API::OK(["path"=>$path, "fn"=>$fn, "url"=>$url, "append"=>"@100w_100h_4e"]);


    $ms = microtime(true) * 10000;
    $fn = "u{$uid}-$ms-$name";
    $to = $OSS_config['path'] . '/' . $fn;
    $url = $OSS_config['baseurl'] . "/" .$to;
    $path = $OSS_config['baseurl'] . "/" . $OSS_config['path'] . '/';
    //return \DJApi\API::OK([$OSS_config['bucket'], $to, $from]);
    try{
      $ossClient->uploadFile($OSS_config['bucket'], $to, $from);
      // 此处可以增加代码, 以在本地数据库记录上传情况
    } catch(OssException $e) {
      return \DJApi\API::error(9001, $e->getMessage());
    }

    return \DJApi\API::OK(["path"=>$path, "fn"=>$fn, "url"=>$url, "append"=>"@100w_100h_4e"]);
  }


}
