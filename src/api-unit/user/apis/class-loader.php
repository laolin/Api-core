<?php

/**
 * 自动加载自己的类库
 * @类库基目录: 从 index.php 所在目录开始计算
 *   默认：['classes', 'apis/classes', 'system/include']
 *   自定义：
 *     DJApi\Configs::set('main-include-path', [include_path1, ...])
 */
spl_autoload_register(
  function ($class){
    $fn = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $fullFilename = dirname(__FILE__) . "/$fn";
    if(file_exists($fullFilename)){
      require_once $fullFilename;
    }
  }
);

