<?php
/**
 * 使用方法
 * file: index.php:
<?php
require_once('../index.api-shell.php');
// 加载配置
search_require('config.inc.php');
// 开启调试信息
DJApi\API::enable_debug(true);
//输出
apiShellCall('DJApi\WxToken');

 */


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

// 主框架 api-shell
search_require('dj-api-shell/api-all.php', 5, '', true);


/**
 * 仅使用 api-shell 进行调用
 */
function apiShellCall($namespace = 'RequestByApiShell'){
  $request = new DJApi\Request($_GET['api'], $_GET['call']);
  $json = $request->getJson($namespace);
  DJApi\Response::response(DJApi\Request::debugJson($json));
}



/**
 * 从当前文件夹的某个上级文件夹开始查找配置文件，并使用配置
 *
 * @param onlyonce:
 *     true : 找到一个最近的，即完成。
 *     false: 找到所有的匹配，从远处先使用，最近的将最优先。
 */
function search_require($fileName, $deep = 5, $path = '', $onlyonce = false){
  if(!$path){
    $path = dirname($_SERVER['PHP_SELF']);
  }
  $foundHere = file_exists("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
  // 如果找到了，而且只要求一次，那么就仅使用这一个了
  if($foundHere &&$onlyonce ){
    require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
    return;
  }
  // 到上一级目录去找，有找到的先引用，然后再引用本级目录的(若有)，以保存越近的配置越优先
  if($deep > 0 && strlen($path) > 1){
    search_require($fileName, $deep - 1, dirname($path), $onlyonce);
  }
  if($foundHere){
    require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
  }
}