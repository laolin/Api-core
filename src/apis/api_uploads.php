<?php
// ================================
/*
*/

class class_uploads{
  public static function main( $para1,$para2 ) {
    return API::data('uploads ready.');
  }
  //需要 处理 userVerify 结果 没通过的情况
  //$para1 代表文件表单控件名（$_FILES的下标），省略 按默认值 "name" 
  public static function save( $para1,$para2 ) {
    if( ! USER::userVerify() ) {
      //return API::msg(2001,'Error verify token.');
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
    if( ! in_array($file_extension, $extension_allowed) ) {
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

    return API::data([$r]);
  }
}
