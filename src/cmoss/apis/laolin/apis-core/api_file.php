<?php
// ================================
/**
 *  上传文件：
 *  API: /file/upload/$name
 *  $name: 代表文件表单控件名（$_FILES的下标），省略 按默认值 "name"
 *  
 *  下载文件（通过API）：
 *  API: /file/g/$fileId
 *  $fileId: 上传文件后，根据文件内容生成的Id
 *  
 *  下载文件（不每次通过API）：
 *  API: /file/path
 *  先通过API获取目录路径 `path`，然后以后可直接 `path/fileId` 访问
 *  返回的path可以是真正的目录，为安全起见，可以采用rewrite的路径
 *  
 */


class class_file {
  public static function main( $para1,$para2 ) {
    return API::data('files ready.');
  }
  

  //先通过API获取目录路径 `path`，然后以后可直接 `path/fileId` 访问
  public static function path( ) {
    return API::data(api_g("upload-settings")['path-pub']);
  }
  //需要 处理 userVerify 结果 没通过的情况
  /**
   *  $para1 代表文件表单控件名（$_FILES的下标），省略 按默认值 "name" 
   *  
   */
  public static function upload( $para1 ) {
    if( ! USER::userVerify() ) {
      return API::msg(2001,'Error verify token.');
    }
    if( ! $para1 ) {
      $para1='name';
      //return API::msg(1001,'Error parameter1.');
    }
    if( !isset($_FILES[$para1]['error']) ) {
      return API::msg(1002,'Invalid parameter1.');
    }
    
    //多文件暂不支持
    if( is_array($_FILES[$para1]['error']) ) {
      return API::msg(1003,'Multiple files not supported.');
    }
    switch ($_FILES[$para1]['error']) {
      case UPLOAD_ERR_OK:
        break;
      case UPLOAD_ERR_NO_FILE:
        return API::msg(1004,'No file sent.');
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        return API::msg(1005,'Exceeded filesize limit.');
      default:
        return API::msg(1006,'Unknown errors.');
    }
    
    $pweb=api_g('path-web');
    $upstn=api_g("upload-settings");
    if( ! isset($upstn['path']) ) {
      return API::msg(2001,'upload-settings errors.');
    }
    $pup=$pweb . $upstn['path'];

    
    $filename=$_FILES[$para1]['name'];
    $allowed=$upstn['ext'];  //which file types are allowed seperated by comma

    $extension_allowed=  explode(',', $allowed);
    $file_extension=  pathinfo($filename, PATHINFO_EXTENSION);
    if($file_extension && ! in_array($file_extension, $extension_allowed) ) {
      return API::msg(1007,"$file_extension is not allowed");
    }
    $uid=api_g('userVerify')['uid'];
    if(!$uid)$uid='0';
    //$r=FILES::save();
    $newname= sprintf( '%s.%s',
        sha1_file($_FILES[$para1]['tmp_name']),
        $file_extension
      );
    if( file_exists ($pup.'/'.$newname) ) {
      //相同的文件不用重复保存
    } else if (!move_uploaded_file( $_FILES[$para1]['tmp_name'],$pup.'/'.$newname ) ) {
      return API::msg(1008, "Failed to move uploaded file. $newname");
    }
    $fdata=[
      'uid'=>$uid, 
      'fdesc'=>API::POST('desc'),
      'fname'=>$newname,
      'oname'=>$filename,
      'ftype'=>$_FILES[$para1]['type'],
      'ftime'=>time(),
      'fsize'=>$_FILES[$para1]['size']
    ];
    $db=api_g("db");
    $prefix=api_g("api-table-prefix");
    
    $r=$db->insert($prefix.'uploads',$fdata );

    return API::data(['name'=>$newname]);
  }
  public static function g( $para1 ) {
    if(!$para1){
      return API::msg(1001,'Error file name. @file.g');
    }
    $upstn=api_g("upload-settings");
    $filename=api_g('path-web') . $upstn['path'] . '/' . $para1;

    if( __class_uploads_helper::
      respone_download($filename,$para1)) {
        return false;
    }
    return API::msg(1099,'Unkown error. @file.g');
  }
  
  //不提供按 id 下载附件的功能
  /*
  public
  static function get( $para1 ) {
    $fid=intval($para1);
    if($fid<=0) {
        return API::msg(1001,'Error file id.');
    }
    
    $upstn=api_g("upload-settings");
    if( ! isset($upstn['path']) ) {
      return API::msg(2001,'upload-settings errors.');
    }
    
    $db=api_g("db");
    $prefix=api_g("api-table-prefix");
    
    $r=$db->get($prefix.'uploads',['uid','fdesc','fname','fsize','count'],
      ['fid'=>$fid]);
    if(! isset($r['fname'])) {
      return API::msg(1002,"File id $fid not exist.");
    }

    $pup=api_g('path-web') . $upstn['path'];
    
    if( __class_uploads_helper::
      respone_download($pup .'/'.$r['fname'],$r['fdesc'] )) {
        return false;
    }

    return API::data([$r,$pup],1099,'Unkown error');
  }
  */
}

class __class_uploads_helper{
  public static function respone_download($file,$file_display_name){
    //$file = './Penguins.jpg';
    $pinfo = pathinfo($file_display_name);
    $file_display_name = basename($file_display_name,'.'.$pinfo['extension']);
    $file_extension = pathinfo($file, PATHINFO_EXTENSION);
    $fsize = @filesize($file);
    if (!empty($fsize)) {			
      $start = null ; 
      $end = $fsize - 1;
      if (isset($_SERVER['HTTP_RANGE']) && ($_SERVER['HTTP_RANGE'] != "") && preg_match("/^bytes=([0-9]+)-([0-9]*)$/i", $_SERVER['HTTP_RANGE'], $match) && ($match[1] < $fsize) && ($match[2] < $fsize)) {     
        $start = $match[1]; 
        if (!empty($match[2]))$end = $match[2];
      }
      header("Cache-control: public"); 
      header("Pragma: public"); 
      if ($start === null) {
        header("HTTP/1.1 200 OK");
        header("Content-Length: $fsize");
        header("Accept-Ranges: bytes");
      } else {  				
        header("HTTP/1.1 206 Partial Content");     
        header("Content-Length: " . ($end - $start + 1));     
        header("Content-Ranges: bytes " . $start . "-" . $end . "/" . $fsize); 
      }
      header("Content-Type: application/octet-stream"); 
      header("Content-Disposition: attachment; filename=" . urlencode ( $file_display_name . '.' . $file_extension ) );
      ob_clean();
      flush();
      $fp = fopen($file, "rb");
      fseek($fp,$start);
      $chunk = 8192;
      while(($nowNum = ftell($fp)) < $end){
        if($nowNum >= ($end - $chunk)){
          $chunk = $end - $nowNum + 1;
        }
        echo fread($fp, $chunk );
      }
       
      fclose($fp);
      return true;
    }else {
      header("HTTP/1.1 404 Not Found");
      header('Content-Type: text/html; charset=utf-8');
      return false;
    }
  }
}