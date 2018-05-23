<?php


class QgsModules{
	const database_name = "qgs_modules";
	const table_user = "qgs_user";
	const table_wxuser = "wx_user";

	public static function join_wxuser(){
    return ["[>]".self::table_wxuser=>"openid"];
  }

  public static function DB(){
    return new mymedoo(self::database_name);
  }


  public static function FieldsUserAndWx($userFields, $wxFielde){
    $fields = [];
    foreach($userFields as $field){
      $fields[] = self::table_user . "." . $field;
    }
    foreach($wxFielde as $field){
      $fields[] = self::table_wxuser . "." . $field;
    }
    return $fields;
  }


  public static function GetWxAndUsers($userids){
    $db = new mymedoo(self::database_name);
    return $db->select(self::table_user,  ["[>]".self::table_wxuser=>"openid"],
      [self::table_user.".id", self::table_user.".name",self::table_wxuser.".headimgurl",self::table_wxuser.".nickname"],
      [self::table_user.".id"=>$userids]);
  }
  public static function GetWxAndUsersByFields($userFields, $wxFielde, $AND){
    $fields = self::FieldsUserAndWx($userFields, $wxFielde);
    $db = new mymedoo(self::database_name);
    return $db->get(self::table_user,  ["[>]".self::table_wxuser=>"openid"], $fields, $AND);
  }
  public static function SelectWxAndUsersByFields($userFields, $wxFielde, $AND){
    $fields = self::FieldsUserAndWx($userFields, $wxFielde);
    $db = new mymedoo(self::database_name);
    $r = $db->select(self::table_user,  ["[>]".self::table_wxuser=>"openid"], $fields, $AND);
    //error_log(cn_json($db->getShow()));
    return $r;
    return $db->select(self::table_user,  ["[>]".self::table_wxuser=>"openid"], $fields, $AND);
  }
  public static function AddWxAndUserDataByUserid(&$userRows, $userFields, $wxFielde, $addby="userid", $oldkey="userid"){
    $values = [];
    foreach($userRows as $k=>$row){
      $values[$row[$oldkey]] = $k;
    }
    $keyField = $addby == "userid" ? "id": "openid";
    $AND = [self::table_user . ".$keyField"=>array_keys($values)];
    $hasOldKey = in_array($oldkey, self::table_user . ".$keyField");
    if(!$hasOldKey){
      $userFields[] = $keyField;
    }
    $fields = self::FieldsUserAndWx($userFields, $wxFielde);
    $db = new mymedoo(self::database_name);
    $arr = $db->select(self::table_user,  ["[>]".self::table_wxuser=>"openid"], $fields, $AND);
    foreach($arr as $newData){
      $oldValue = $newData[$keyField];
      if(!$hasOldKey){
        unset($row[$keyField]);
      }
      foreach($userRows as $nthUser=>$userRow){
        if($userRow[$oldkey] != $oldValue) continue;
        foreach($newData as $k=>$v){
          $userRows[$nthUser][$k] = $v;
        }
      }
    }
  }

  public static function GetWxUser($fields, $AND){
    $db = new mymedoo(self::database_name);
    return $db->get(self::table_wxuser, $fields, $AND);
  }

  public static function GetUser($fields, $AND){
    $db = new mymedoo(self::database_name);
    return $db->get(self::table_user, $fields, $AND);
  }

  public static function SelectUser($fields, $AND){
    $db = new mymedoo(self::database_name);
    return $db->select(self::table_user, $fields, $AND);
  }

  public static function UpdateUser($values, $AND){
    $db = new mymedoo(self::database_name);
    return $db->update(self::table_user, $values, $AND);
  }

  public static function InsertUser($values){
    $db = new mymedoo(self::database_name);
    return $db->insert(self::table_user, $values);
  }

  public static function HasUser($join, $where = null){
    $db = new mymedoo(self::database_name);
    return $db->has(self::table_user, $join, $where);
  }

  public static function CountUser($join = null, $column = null, $where = null){
    $db = new mymedoo(self::database_name);
    $r = $db->count(self::table_user, $join, $column, $where);
    //error_log(cn_json($db->getShow()));
    return $r;
    return $db->count(self::table_user, $join, $column, $where);
  }


  public static function select($table, $join, $columns = null, $where = null){
    $db = new mymedoo(self::database_name);
    return $db->select($table, $join, $columns, $where);
  }
  public static function get($table, $join = null, $columns = null, $where = null){
    $db = new mymedoo(self::database_name);
    return $db->get($table, $join, $columns, $where);
  }
  public static function has($table, $join, $where = null){
    $db = new mymedoo(self::database_name);
    return $db->has($table, $join, $where);
  }
  public static function count($table, $join = null, $column = null, $where = null){
    $db = new mymedoo(self::database_name);
    return $db->count($table, $join, $column, $where);
  }
  public static function query($query){
    $db = new mymedoo(self::database_name);
    return $db->query($query);
  }
}

class USER{
	
//--------- 用户标准信息 --------------------
	public static $MainInfos = [
	"id"           ,  //ID
	"name"         ,  //姓名
	"nick"         ,  //呢称
	"openid"       ,  //微信openid
	"password"     ,  //密码
	"attr"         ,  //其它属性
	"loginmodule"  ,  //上次登陆的模块
	"wxbind"       ,  //允许重新绑定微信的次数，openid为空时，允许绑定。
	"mobile"       ,  //手机
	"office"       ,  //办公电话
	"userstate"    ,  //用户类型的状态
	"expertstate"  ,  //专家类型的状态
	"servicestate" ,  //编导类型的状态
	"company"      ,  //工作单位
	"department"   ,  //工作部门
	"position"     ,  //岗位职务
	"logintime"       //时间戳
	];
//--------- 用户可显示的标准信息 --------------------
	public static $ShowInfos = [
	"id"           ,  //ID
	"name"         ,  //姓名
	"nick"         ,  //呢称
	"attr"         ,  //其它属性
	//"openid"       ,  //微信openid
	"wxbind"       ,  //允许重新绑定微信的次数，openid为空时，允许绑定。
	"mobile"       ,  //手机
	"office"       ,  //办公电话
	"userstate"    ,  //用户类型的状态
	"expertstate"  ,  //专家类型的状态
	"servicestate" ,  //编导类型的状态
	"company"      ,  //工作单位
	"department"   ,  //工作部门
	"position"        //岗位职务
	];
// --------- 用户扩展信息 --------------------
	public static $ExtendInfos = [
		"address",    //地址
		"favorites",  //喜好
		"major",      //专业
		"resume",     //简历
		"rank",       //头衔
		"profile"     //个人简介
	];
  
	// --------- 专家简要信息 --------- 
	public static $ExpertMainInfos = [
		"id", 
		"name", 
		"company",    //公司
		"department", //部门
		"position",   //职务
		"office",     //办公电话
		"mobile",     //手机号
		"expertstate" //
	];
}


/*   */
function addtableuser($k){
	return QgsModules::table_user.".$k";
}
function addtablewxuser($k){
	return QgsModules::table_wxuser.".$k";
}



/*   */
function module2en($module){
	return $module;
}


/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 验证签名是否有效
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function check_password_sign($t, $password, $sign){
	//echo "<br/>".MD5($t . $password);
	if(!$password)return false;//不允许空密码
  return MD5($t . $password) == strtolower($sign);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 根据$query(可指定为GET,POST)验证用户身份
 * $query['uid']
 * $query['t']
 * $query['sign']
 * 若无效，直接返回JSON
 * 有效，返回 true
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function check_ajax($db, $query, $module=false){
	//步骤1：每次登陆的时间戳在前后30秒内有效
	if(!isset($query['t']))return system_error("时间戳");
	$t0 = time();
	$t = $query['t'] + 0;
	//if($t-$t0 > 3000 || $t0-$t > 3000)return system_error("时间戳");

	ASSERT_uid($db, $query, $module);
	
  return true;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 根据$query(可指定为GET,POST)获取用户基础信息
 * $query['nick/id'] : 手机、nick
 * $query['t']
 * $query['sign']
 * 
 * 若无效，直接返回JSON串
 * 有效，返回 数组
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function ajax_get_userinfo($db, $query){
	if(!isset($query['t']) || !isset($query['sign']) || !isset($query['module']))json_error(1000, "参数缺失"  );
	if(!isset($query['uid']) && !isset($query['nick']))json_error(1000, "参数缺失"  );
	//步骤1：每次登陆的时间戳在前后30秒内有效
	$t0 = time();
	$t = $query['t'] + 0;
	//if($t-$t0 > 3000 || $t0-$t > 3000)return system_error("时间戳");


	//步骤2：读取用户数据，当且仅当只有一行时有效
	//$where = [ "AND"=>[ "usertype[~]"=>$query['module'] ]];
	$where = [ "AND"=>[ ]];
	if(isset($query['uid']) && $query['uid'] + 0 > 0)$where["AND"]["id"] = $query['uid'];
	else $where["AND"]["OR"] = ["nick"=>$query['nick'], "mobile"=>$query['nick'], "id"=>$query['nick']];

	$userinfo = ASSERT_uid($db, $query, false, $where);
  return $userinfo;
}

function ASSERT_query(&$db, &$query, $fields, $check_module=false){
	$base_fields = ["uid","t","sign","module"];
  foreach($base_fields as $k)if(!isset($query[$k])){
  	echo json_error(1000, "参数缺失: $k"  );
  	exit;
  }
  foreach($fields as $k)if(!isset($query[$k])){
  	echo json_error(1000, "参数缺失: $k"  );
  	exit;
  }
	$check = check_ajax($db, $query, $check_module ? $query['module'] : false);
	if($check !== true){
  	echo $check;
  	exit;
  }
}





//===========  之前的，要更新！ ==========================================



/* ----------------------------------------------------------
 * 验证时间戳
 * ---------------------------------------------------------- */
function ASSERT_timespan($query){
	$t0 = time();
	$t = $query['t'] + 0;
	if($t-$t0 > 3000 || $t0-$t > 3000){
		//echo system_error("时间戳");exit;
	}
  return true;
}
/* ----------------------------------------------------------
 * 验证激活模块
 * ---------------------------------------------------------- */
function ASSERT_module($userinfo, $module){
	if(in_array($module, ["user","expert","service"]) && $userinfo[$module.'state']<3){
		echo json_error(1011, "用户未激活" );
		exit;
	}
}

/* ----------------------------------------------------------
 * 验证 uid, 成功返回用户数据，整行
 * ---------------------------------------------------------- */
function ASSERT_uid(&$db, &$query, $module=false, $where=false){
	if($where === false)$where = ["id"=>$query['uid']];
	$userinfo = QgsModules::GetUser("*", $where);
	if(!is_array($userinfo)){
    if($GLOBALS['require_id']>0)  $db->update("log_api", ["result"=>"1002, 用户ID错误"], ["id"=>$GLOBALS['require_id']]);
		echo json_error(1002, "用户ID错误12");
		exit;
	}
  $userinfo['userstate'] = 3;//不再限制用户的激活状态
	//是否需要验收模块身份？
	if(in_array($module, ["user","expert","service"]) && $userinfo[$module.'state']<3){
    if($GLOBALS['require_id']>0)  $db->update("log_api", ["result"=>"1011, 用户未激活"], ["id"=>$GLOBALS['require_id']]);
		echo json_error(1011, "用户未激活" );
		exit;
	}
	
	//过渡期，识别原密码未加密的
	$password = trim($userinfo['password']);
	//原密码已加密：
  if(!$password)$password = strtoupper( MD5("@qgs") );
	if(strlen($password) < 16 && strlen($password) > 0){
		$password = strtoupper( MD5("$password@qgs") );
	}
	
	//验证密码：
	$t = $query['t'] + 0;
	if(!check_password_sign($t, $password, $query['sign']) &&
		 !check_password_sign($t, $userinfo['openid'], $query['sign']) 
	){
    if($GLOBALS['require_id']>0)  $db->update("log_api", ["result"=>"1003, 密码错误"], ["id"=>$GLOBALS['require_id']]);
		echo json_error(1003, "密码错误", [$password]);
		exit;
	}
  return $userinfo;
}

/* ----------------------------------------------------------
 * 验证用户权限, 成功用户属性写入
 * ---------------------------------------------------------- */
function ASSERT_right(&$db, &$userinfo, $needrights = false){
	//解析用户扩展信息
	if(isset($userinfo['attr']) && !is_array($userinfo['attr'])){
		$arr = json_decode($userinfo['attr'], true);
		unset($userinfo['attr']);
		if($arr)foreach($arr as $k=>$v){
			$userinfo[$k] = $v;
		}
	}
	
	if($needrights){
		if(!isset($userinfo['d']) || !isset($userinfo['d']['权限'])){
			echo system_error("没有权限");
			exit;
		}
		if(in_array("超级管理员", $userinfo['d']['权限']))return;
		
		if(is_string($needrights))$needrights = [$needrights];
		foreach($needrights as $myright){
			if(in_array("$myright", $userinfo['d']['权限']))continue ;
			echo system_error("没有权限");
			exit;
		}
	}
}
/* ----------------------------------------------------------
 * 验证用户权限, 成功用户属性写入
 * ---------------------------------------------------------- */
function ParseAttr(&$row, $field){
	if(!isset($row['attr']) || is_array($row[$field]))return;
	//解析用户扩展信息
	$arr = json_decode($row[$field], true);
	unset($row[$field]);
	if($arr)foreach($arr as $k=>$v){
		$row[$k] = $v;
	}
}
/* ----------------------------------------------------------
 * 解释或获取属性
 * ---------------------------------------------------------- */
function GetAttr(&$row, $field){
  if(is_array($row[$field]))return $row[$field];
	if(!isset($row['attr']))return [];
	//解析用户扩展信息
	return json_decode($row[$field], true);
}



/* ----------------------------------------------------------
 * 验证用户
 * ---------------------------------------------------------- */
function ASSERT_query_userinfo(&$db, &$query, $fields, $check_module=false){
	$base_fields = ["uid","t","sign","module"];
  foreach($base_fields as $k)if(!isset($query[$k])){
  	echo json_error(1000, "参数缺失: $k"  );
  	exit;
  }
  foreach($fields as $k)if(!isset($query[$k])){
  	echo json_error(1000, "参数缺失: $k"  );
  	exit;
  }
  ASSERT_timespan($query);
  $me = ASSERT_uid($db, $query, $check_module);
  //保存登陆模块：
  if($query['module'] && $me['loginmodule'] != $query['module'])
    QgsModules::UpdateUser(["loginmodule"=>$query['module']], ["id"=>$query['uid']]);
  return $me;
}

/* ---------------------------------------------------------
 * 现有的用户数据，增加微信个人数据
 * --------------------------------------------------------- */
function append_wx_info(&$db, $arr_user, $fileds = false){
  if(!$fileds) $fields = ["headimgurl","nickname"];
  $openids = [];
  foreach($arr_user as $k => $row)$openids[$row['openid']] = $k;
  $wx_info = QgsModules::DB()->select(QgsModules::table_wxuser, array_merge($fields, ["openid"]), ["AND"=>["openid"=>array_keys($openids)]]);
  foreach($wx_info as $row){
    $k = $openids[$row['openid']];
    foreach($fields as $field) $arr_user[$k][$field] = $row[$field];
  }
}

/* ---------------------------------------------------------
 * 根据AND条件获取分页数据
 * --------------------------------------------------------- */
function get_page_data(&$db, $query, $table, $AND, $fileds="*", $ORDER=false){
  $page = $query['page'] - 1; if($page < 0)$page = 0;
  $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
  $first = $pagesize * $page;
  $where = ["AND"=>$AND, "LIMIT"=>[$first, $pagesize], "ORDER"=>"rq DESC"];
  if($ORDER)$where["ORDER"] = $ORDER;
  $R = $db->select($table, "*", $where);
  $rowcount = $db->count($table, ["AND"=>$AND]);
  return ["list"=>$R, "rowcount"=>$rowcount];
}
?>