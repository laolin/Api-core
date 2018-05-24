<?php
// ================================
/*
 */
namespace MyClass;
use DJApi;

if (!defined('MAX_FILE_SIZE')) {
  define('MAX_FILE_SIZE', 5e6);
}

class CUploadOss {

  public static function getOssConfig($name) {
    $configs = \DJApi\Configs::get(["OSS_access", $name]);
    if (!$configs) {
      $configs = \DJApi\Configs::get("OSS_access_default");
    }

    return $configs;
  }

  /**
   * 上传数据到 OSS
   */
  public static function uploadObject($content, $fnOnOSS, $ossName) {
    $OSS_config = self::getOssConfig($ossName);
    if (!$OSS_config) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数错误");
    }

    \DJApi\Configs::readConfig('api-lib/ali-oss-upload/autoload.php');
    $ossClient = new \OSS\OssClient($OSS_config['accessKeyId'], $OSS_config['accessKeySecret'], $OSS_config['endpoint'], false);

    try {
      $ossClient->putObject($OSS_config['bucket'], "{$OSS_config['path']}/$fnOnOSS", $content);
    } catch (OssException $e) {
      return \DJApi\API::error(9001, $e->getMessage());
    }

    $path = $OSS_config['baseurl'] . '/' . $OSS_config['path'] . '/';
    return \DJApi\API::OK(["path" => $path, "fn" => $fnOnOSS, "url" => "$path$fnOnOSS", "append" => "@100w_100h_4e"]);
  }

  /**
   * 上传文件到 OSS
   */
  public static function uploadToOSS($fnUpload, $fnOnOSS, $ossName) {
    $OSS_config = self::getOssConfig($ossName);
    if (!$OSS_config) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数错误");
    }
    // \DJApi\API::debug(["OSS_config"=>$OSS_config]); 敏感数据，测试后即关闭
    //return \DJApi\API::OK();

    \DJApi\Configs::readConfig('api-lib/ali-oss-upload/autoload.php');

    $ossClient = new \OSS\OssClient($OSS_config['accessKeyId'], $OSS_config['accessKeySecret'], $OSS_config['endpoint'], false);

    try {
      $ossClient->uploadFile($OSS_config['bucket'], "{$OSS_config['path']}/$fnOnOSS", $fnUpload);
      // 保存到树
      //$this->addImgToTree($url, $treeId);
    } catch (OssException $e) {
      return \DJApi\API::error(9001, $e->getMessage());
    }

    $path = $OSS_config['baseurl'] . $OSS_config['path'] . '/';
    return \DJApi\API::OK(["path" => $path, "fn" => $fnOnOSS, "url" => "$path$fnOnOSS", "append" => "@100w_100h_4e"]);
  }

  /**
   * 接口： upload/img
   * 上传图片
   */
  public static function img($upload, $uid, $ossName) {
    $from = $upload["tmp_name"];
    if (!$from) {
      return \DJApi\API::error(9001, "上传错误", ["upload" => $upload, "FILES" => $_FILES]);
    }
    $name = $upload["name"];
    $size = $upload["size"] + 0;
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if ($ext == 'mp4') {
      if ($size > MAX_FILE_SIZE * 3) {
        return \DJApi\API::error(9001, "超出视频大小限制");
      }
    } elseif ($size > MAX_FILE_SIZE) {
      return \DJApi\API::error(9001, "超出图片大小限制,$ext");
    }

    \DJApi\API::debug(["uid" => $uid, "upload" => $upload, "size" => $size, "name" => $name, "_FILES" => $_FILES]);

    $ms = microtime(true) * 10000;
    $fn = "u{$uid}-$ms-$name";

    return self::uploadToOSS($from, $fn, $ossName);
  }
}
