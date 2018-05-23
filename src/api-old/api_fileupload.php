<?php
define('MAX_FILE_SIZE', 5e6);
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 上传图片，独立API
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
class class_fileupload{
  public static function uploadpicsized(&$get, &$post, &$db){
    $query = &$post;
    //ASSERT_query_userinfo($db, $query, []);
    //var_dump($query);
    //var_dump($_FILES);
    //$kk = each($_FILES);
    //$fileFormDataName = $kk['key'];
    //$upload = $_FILES[ $fileFormDataName ];
    $ff = var_export($_FILES, true);
    $upload = $_FILES['myFile'];
    if(!$upload){
      //echo $ff;
      //var_dump($query);
      return json_error(3001, "未上传");
    }
    
    $size = $upload['size'] + 0; if($size <100) return json_error(3002, "无数据");
    $tmpFile = $upload['tmp_name'];
    
    $ms = microtime(true) * 10000;
    $uid = $query['uid'] + 0;
    $url = "/downloads/upload/$uid-$ms.jpg";
    $path = realpath("./");

    $fn = "{$_SERVER['DOCUMENT_ROOT']}/../../config/config.php";
    if(file_exists($fn)){
      $path = $_SERVER['DOCUMENT_ROOT'];
      $path = substr($path, 0, strrpos($path, "\\"));
      $path = substr($path, 0, strrpos($path, "\\"));
      $path = "$path/downloads/upload";
    }
    else{
      $path = "{$_SERVER['DOCUMENT_ROOT']}/downloads/upload";
    }

    $path = "$path/$uid-$ms.jpg";
    copy($tmpFile, $path);
    //生成缩略图96*96
    G::make_mini_jpg($path, 96);
    return json_OK([ "url"=>$url]);
  }

  
  public static function getOssConfig($name) {
    $configs = \DJApi\Configs::get(["OSS_access", $name]);
    if(!$configs)$configs = \DJApi\Configs::get("OSS_access_default");
    return $configs;
  }
  public static function uploadpic(&$get, &$post, &$db){

    $upload = $_FILES['myFile'];
    $from = $upload["tmp_name"];
    if(!$from){
      return json_error(3001, "未上传");
    }
    $name = $upload["name"];
    $size = $upload["size"] + 0;
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if($ext=='mp4'){
      if($size > MAX_FILE_SIZE * 3){
        return json_error(3001, "超出视频大小限制");
      }
    }
    else if($size > MAX_FILE_SIZE){
      return json_error(3001, "超出图片大小限制,$ext");
    }

    $OSS_config = self::getOssConfig($request->query['name']);
    if(!$OSS_config) {
      return json_error(3001, "参数错误");
    }

    \DJApi\Configs::readConfig('api-lib/ali-oss-upload/autoload.php', 5, '', true);

    $ossClient = new \OSS\OssClient($OSS_config['accessKeyId'], $OSS_config['accessKeySecret'], $OSS_config['endpoint'], false);

    $ms = microtime(true) * 10000;
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
    return json_OK([ "url"=>$url]);
  }
  public static function uploadpic_old(&$get, &$post, &$db){
    $query = &$post;
    //ASSERT_query_userinfo($db, $query, []);
    //var_dump($query);
    //var_dump($_FILES);
    //$kk = each($_FILES);
    //$fileFormDataName = $kk['key'];
    //$upload = $_FILES[ $fileFormDataName ];
    $ff = var_export($_FILES, true);
    $upload = $_FILES['myFile'];
    if(!$upload){
      //echo $ff;
      //var_dump($query);
      return json_error(3001, "未上传");
    }
    $fileExt = strtolower(substr($upload['name'],-4));
    $tmpFile = $upload['tmp_name'];
    
    $ms = microtime(true) * 10000;
    $uid = $query['uid'] + 0;
    $url = "/downloads/upload/$uid-$ms{$fileExt}";
    $path = realpath("./");

    $fn = "{$_SERVER['DOCUMENT_ROOT']}/../../config/config.php";
    if(file_exists($fn)) $path = realpath("{$_SERVER['DOCUMENT_ROOT']}/../../downloads/upload");
    else $path = realpath("{$_SERVER['DOCUMENT_ROOT']}/downloads/upload");

    $path = "$path/$uid-$ms{$fileExt}";
    
    if($fileExt == ".gif" || $fileExt == ".png"){
      copy($tmpFile, $path);
      //生成缩略图96*96
      G::make_mini_jpg($path, 96);
      return json_OK([ "url"=>$url]);
    }
    
    //读出图片
    switch($fileExt){
      case ".gif":
        $img_upload = @imagecreatefromgif($tmpFile);//获取图像
        break;
      case ".png":
        $img_upload = @imagecreatefrompng($tmpFile);//获取图像
        break;
      case ".jpg":
      default:
        $img_upload = @imagecreatefromjpeg($tmpFile);//获取图像
        break;
    }
    if(!$img_upload) return json_error (3002, "上传失败");
    
    //调整大小，如果大于 1280px 宽度
    $max_w = 1280;
    $w0 = imagesx($img_upload);
    if($w0 <= $max_w){
      $img = $img_upload;
    }
    else{
      $h0 = imagesy($img_upload);
      $w = $max_w;
      $h = $w * $h0 / $w0;
      $img = imagecreatetruecolor($w, $h);
      imagecopyresampled($img, $img_upload, 0,0,0,0, $w,$h, $w0,$h0);
    }
    switch($fileExt){
      case ".gif":
        imagegif($img, $path);//保存图像
        break;
      case ".png":
        imagepng($img, $path);//保存图像
        break;
      case ".jpg":
      default:
        imagejpeg($img, $path);//保存图像
        break;
    }
    //生成缩略图96*96
    G::make_mini_jpg($path, 96);
    return json_OK([ "url"=>$url]);
  }
}

?>