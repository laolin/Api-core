<?php
	require_once("app/user.class.php");
	require_once("app/g.php");

class class_search{
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 获取搜索结果
	 * xxx.com/search/search (GET、身份识别)
	 * 
	 * page  第几页
	 * pagesize 每页几行
	 * 
	 * 
	 * 返回: 提问ID，或错误码信息
	 * 
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function search(&$get, &$post, &$db){
		$query = &$post;
		ASSERT_query_userinfo($db, $query, ["page","pagesize","searchtext"]);

		$from = array('/\//', '/\^/', '/\./', '/\$/', '/\|/', '/\(/', '/\)/',
				'/\[/', '/\]/', '/\*/', '/\+/', '/\?/', '/\{/', '/\}/', '/\,/',
				'/\s+/');
		$to = array('\/', '\^', '\.', '\$', '\|', '\(', '\)', 
				'\[', '\]', '\*', '\+', '\?', '\{', '\}', '\,',
				" ");
		$pattern = preg_replace($from, $to, trim($query['searchtext']));//消除多个空格
		if(!$pattern)return json_error(2000, "无搜索内容"  );
		$s = explode(' ', $pattern);
		$ns = count($s);
		//由于5.5不支持全文搜索，所以自己做个，慢就慢些，待数据库大了，再想办法。
		
		
		//步骤2：读取数据
		switch($query['module']){
			case "service":
				$QA = self::search_qa($db, $s, $ns, ["userid[>]"=>0]);
				$US = self::search_user($db, $s, $ns, ["id[>]"=>0]);
				break;
			case "expert":
				$QA = self::search_qa($db, $s, $ns, ["answerid[>]"=>0]);//$query['uid']]);
				$US = [];
				break;
			case "user":
				$QA = self::search_qa($db, $s, $ns, ["userid"=>$query['uid']]);
				$US = self::search_user($db, $s, $ns, ["id[>]"=>0]);
			default:
				break;
		}
		
		
	  return json_OK(["QA"=>$QA, "USER"=>$US]);
	}
	
	
	public static function search_qa($db, $s, $ns, $where){
		//读取所有$where问答：
		$qalists = $db->select("qa_list", "*", $where);
		$qadatas = $db->select("qa_data", ["qaid", "attr","v"]);
		$qas = [];
		$ids = [];
		foreach($qalists as $k=>$row){
			$ids[$row['id']] = $k;
			$qas[$row['id']] = "Q{$row['rq']} A{$row['answerrq']} Q{$row['userid']} A{$row['answerid']} {$row['title']} {$row['content']} {$row['id']} {$row['satisfy']} ";
		}
		if($qadatas)foreach($qadatas as $row){
			if(!isset($qas[$row['qaid']]))continue;
			$qas[$row['qaid']] .= " {$row['v']}";
		}
		$R = [];
		foreach($qas as $id=>$text){
			for($i=0; $i<$ns; $i++)
				if(preg_match("/{$s[$i]}/", $text)==0)continue 2;
			$R[] = $qalists[$ids[$id]]; 
		}		
	  return $R;
	}
	
	public static function search_user($db, $s, $ns, $where){
		//读取所有$where问答：
		$qalists = QgsModules::SelectUser(USER::$ShowInfos, $where);
		$qadatas = $db->select("z_userdata", ["userid", "attr","v"]);
		$qas = [];
		$ids = [];
		foreach($qalists as $k=>$row){
			$ids[$row['id']] = $k;
			$qas[$row['id']] = implode(" ", $row);
		}
		$R = [];
		foreach($qas as $id=>$text){
			for($i=0; $i<$ns; $i++)
				if(preg_match("/{$s[$i]}/", $text)==0)continue 2;
			$R[] = $qalists[$ids[$id]]; 
		}		
	  return $R;
	}
}

?>