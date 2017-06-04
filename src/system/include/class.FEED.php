<?php

class FEED { 
  
  //获取 数据表名
  static function table_name( $item='feed' ) {
    $prefix=api_g("api-table-prefix");
    return $prefix.$item;
  }
  
  static function data_val( $key, & $data ) {
    if(false === API::INP($key)) return;
    $data[$key]=API::INP($key);
  }
  
  //TODO: 有效性检查
  static function data_all( ) {
    $data=[];
    self::data_val('app',$data);
    self::data_val('cat',$data);
    self::data_val('k1',$data);
    self::data_val('k2',$data);
    self::data_val('k3',$data);
    self::data_val('k4',$data);
    self::data_val('content',$data);
    self::data_val('pics',$data);
    self::data_val('d1',$data);
    self::data_val('d2',$data);
    self::data_val('d3',$data);
    self::data_val('d4',$data);
    //self::data_val('attr',$data);
    
    $data['update_at']=time();
    return $data;
  }
  
  //-----------------------------------------------
  
  // C--- 新建  【草稿】
  static function draft_create( $uid,$app,$cat ) {
    $db=api_g('db');
    $tblname=self::table_name();
    $data=[
      'uid'=>$uid,
      'flag'=>'draft',
      'del'=>0,
      'app'=>$app,
      'cat'=>$cat,
      'k1'=>'',
      'k2'=>'',
      'k3'=>'',
      'k4'=>'',
      'content'=>'',
      'pics'=>'',
      'update_at'=>time(),
      'd1'=>'',
      'd2'=>'',
      'd3'=>'',
      'd4'=>'',
      'attr'=>''
    ];
    $r=$db->insert($tblname,$data );
    if(!$r)return false;
    $data['fid']=$r;
    return $data;
  }

  // -R-- 获取 uid 的最新【草稿】
  static function draft_get_by_uid( $uid,$app,$cat ) {
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->get($tblname,
      self::feed_columns(),
      ['and'=>['uid'=>$uid,'app'=>$app,'cat'=>$cat,'flag'=>'draft','del'=>0]]);
    
    return $r;
  }
  
  // -R-- 获取 uid 的最新【已删除草稿】
  static function draft_get_deleted_by_uid( $uid,$app,$cat ) {
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->get($tblname,
      self::feed_columns(),
      ['and'=>['uid'=>$uid,'app'=>$app,'cat'=>$cat,'flag'=>'draft','del'=>1]]);
    
    return $r;
  }
  
  // --U- 更新 草稿 同  feed

  // ---D 删除 草稿 同  feed

  //发布 【草稿】
  static function draft_publish( $fid ) {
    $db=api_g('db');
    $tblname=self::table_name();

    //发布的算法：
    //1,复制一份草稿为 正式 publish
    //2,然后把草稿文字内容清空（科目等保留）
    $sth = $db->pdo->prepare("INSERT INTO $tblname 
      (`app`,`cat`,`k1`,`k2`,`k3`,`k4`,`content`,`pics`,
        `d1`,`d2`,`d3`,`d4`,`attr`,
        `uid`,`flag`,`del`,`publish_at`)
      SELECT `app`,`cat`,`k1`,`k2`,`k3`,`k4`,`content`,`pics`,
        `d1`,`d2`,`d3`,`d4`,`attr`,
        :uid ,'publish','0', :now
      FROM $tblname 
      WHERE fid = $fid" );
 
    $uid=API::INP('uid');
    $now=time();
    $sth->bindParam(':uid', $uid, PDO::PARAM_INT);
    $sth->bindParam(':now', $now, PDO::PARAM_INT);
     
    $sth->execute();
    
    return self::feed_update($fid,['content'=>'','pics'=>'']);
  }
  
  
  //WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
  static function feed_columns() {
    return
      ['fid','uid','flag','access','del',
      'app','cat','k1','k2','k3','k4',
      'content','pics',
      'd1','d2','d3','d4','attr',
      'create_at','update_at','publish_at'];
  }
  // C--- 不提供创建 feed 的接口，使用草稿的发布功能创建 feed
  
  // -R-- 获取
  static function feed_get( $uid, $fid, $type='publish',$include_del=false,$isAdmin=false ) {
    if(!$fid) {
      return API::msg(202101,"fid required");
    }
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->get($tblname,
      self::feed_columns(),
      ['and'=>['fid'=>$fid]]);
      
    if(!$r) {
      return API::msg(202102,"fid $fid not exist");
    }
    
    //草稿只允许自己看
    if(!$isAdmin && $type=='draft' && $r['uid']!=$uid) {
      return API::msg(202103,"draft $fid is not belongs to uid $uid");
    }
    if($type!='*' && $r['flag']!=$type) {
      return API::msg(202104,"fid $fid is not $type");
    }
    if( !$isAdmin && !$include_del && $r['del']) {
      return API::msg(202105,"fid $fid was deleted yet");
    }
    return API::data($r);
  }
  
  // -R-- 获取
  static function feed_list( $uid,$app,$cat,$include_del=false ) {
    $db=api_g('db');
    $tblname=self::table_name();
    
    $andArray=[];
    $andArray["and#app"]=['app'=>$app];
    $andArray["and#cat"]=['cat'=>$cat];
    $tik=0;

    $oldmore=API::INP('oldmore');
    if($oldmore) {
      $tik++;
      $andArray["and#t$tik"]=['fid[<]'=>intval($oldmore)];
    }
    $newmore=API::INP('newmore');
    if($newmore) {
      $tik++;
      $andArray["and#t$tik"]=['fid[>]'=>intval($newmore)];
    }
    
    
    $type=API::INP('type');
    if(!$type) {//publish, draft, etc.
      $type='publish';
    }
    $tik++;
    $and=['flag'=>$type];
    $andArray["and#t$tik"]=$and;

    $searchCols=['content','k1','k2','k3','k4','d1','d2','d3','d4'];
    $sVal;
    for($i=count($searchCols); $i--; ) {
      $sVal=API::INP($searchCols[$i]);
      if(!$sVal)continue;
      $tik++;
      $and=[$searchCols[$i].'[~]'=>$sVal];//用 like 查询
      $andArray["and#t$tik"]=$and;
    }
    
    
    $and_DEL=false;
    if($include_del=='only') {
      $and_DEL=['del'=>1];
    } else if( ! $include_del) {
      $and_DEL=['del'=>0];
    }
    if($and_DEL) {
      $tik++;
      $andArray["and#t$tik"]=$and_DEL;
    }
    
    
    $count=intval(API::INP('count'));
    if($count==0)$count=20;
    else if($count<1)$count=1;
    else if($count>200)$count=200;
    
    $where=["LIMIT" => $count , "ORDER" => ["publish_at DESC", "update_at DESC"]] ;
    if(count($andArray))
      $where['and'] = $andArray ;


    $r=$db->select($tblname,self::feed_columns(),$where);
      
      
    //var_dump($db);
    if(!$r) {
      return API::msg(202003,"nothing");
    }
    
    //无权限的删掉，不能返回给客户端
    $rtt=USER::getUserRights( $uid );
    for($i=$r.count();$i--; ) {
      if($r[$i]['access'] && !(intval($r[$i]['access']) & $rtt) ) 
        unset($r[$i]);
      }
    }
    $r2=array_values($r);

    
    return API::data($r2);
  }

  // --U- 更新
  static function feed_update( $fid, $data ) {
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->update($tblname, $data,
      ['and'=>['fid'=>$fid],'LIMIT'=>1]);
    return $r;
  }
  // --U- 更新
  static function feed_update_attr( $fid, $attr ) {
    $a1=json_decode($attr,true);
    if(!$a1)return -1;
    
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->get($tblname, ['fid','attr'], ['and'=>['fid'=>$fid]] );
    if(!$r) {
      return ['error get attr',$r];
    }
    $a0=json_decode($r['attr'],true);
    if(!$a0) $a0=[];
    
    $data=[];
    $a2= array_merge($a0,$a1);
    $data['attr']=json_encode($a2,JSON_UNESCAPED_UNICODE);
      
    $r=$db->update($tblname, $data,
      ['and'=>['fid'=>$fid],'LIMIT'=>1]);
    return $r;
  }
  // ---D 删除
  static function feed_delete( $fid ) {
    return self::feed_update($fid,['del'=>1]);
  }
  
  //QQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQ

  //判断是否有效，主要用于草稿发布为正式feed  
  static function feed_validate($feed) {
    $err='';
    if( !$feed['content'] && !$feed['pics']) {
      $err.='发布内容是空的。';
    }
    return $err;
  }
  // 撤销删除
  static function feed_undelete( $fid ) {
    return self::feed_update($fid,['del'=>0]);
  }
  // 撤销为草稿
  static function feed_to_draft( $fid ) {
    return self::feed_update($fid,['flag'=>'draft']);
  }
}
