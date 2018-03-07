<?php
/**
 *  errcode: 204xxx
 *  
 *  (203501,"Error cmnt type") 
 *  (203502, validate err) 
 */


class COMMENT { 
  //获取 数据表名
  static function table_name( $item='comment' ) {
    $prefix=api_g("api-table-prefix");
    return $prefix.$item;
  }
  static function columns() {
    return [
      'cid','ctype','fid','uid',
      're_cid','re_uid',
      'content','create_at'
    ];
  }
  
  //获得指定uid,fid,ctype 的评论/点赞数据
  static function comment_get($uid,$fid,$ctype,  $mark=0, $cid=0) {
    $and=['and'=>['uid'=>$uid,'fid'=>$fid,'ctype'=>$ctype,'mark'=>$mark]];
    if($cid) {
      $and['and']['cid']=$cid;
    }
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->select($tblname,self::columns(),$and);
    return $r;
  }  
  // C--- 新建
  /**
   *  $type: `like` | null 
   */

  static function create( $ctype,$uid,$fid ) {
    $db=api_g('db');
    $tblname=self::table_name();
    
    if($ctype=='') {
      $ctype='comment';
    } else if($ctype!='comment' && $ctype!='like') {
      return API::msg(203501,"Error cmnt type: ".$ctype);
    }
    
    //确认用户uid对fid的读权限
    //TODO: 后续在 FEED::feed_get 中实现
    $r=FEED::feed_get( $uid, $fid, 'publish');
    if(API::is_error($r)){
      return $r;
    }

    if($ctype=='comment') {
      $re_cid=API::INP('re_cid');
      $re_uid=API::INP('re_uid');
      $content=API::INP('content');
    } else { // == like
      //看是否点过赞
      $r=self::comment_get($uid, $fid, 'like');
      if($r) {
        return API::msg(203502,'已点过赞');
      }
      
      $re_cid='0';
      $re_uid='0';
      $content='';
    }
    $data=[
      'ctype'=>$ctype,
      'fid'=>$fid,
      'uid'=>$uid,
      're_cid'=>$re_cid,
      're_uid'=>$re_uid,
      'content'=>$content,
      'create_at'=>time(),
      'mark'=>'0'
    ];
    if($err=self::validate($data)) {
      return API::msg(203502,$err);
    }

    $r=$db->insert($tblname,$data );
    if(!$r){
      return API::msg(203000,"Error unknow cmnt-create");
    }
    return API::data($r);;
  }

  
  // -R-- 获取
  static function li(  ) {
    $db=api_g('db');
    $tblname=self::table_name();
    
    $andArray=[];
    $tik=0;
    $andArray["and#t$tik"]=['mark'=>0];//删除的不要
    
    
    $fids= API::INP('fids');
    if($fids) {
      $fids=explode(',',API::INP('fids'));
      $tik++;
      $andArray["and#t$tik"]=['fid'=>$fids];
    }


    $oldmore=API::INP('oldmore');
    if($oldmore) {
      $tik++;
      $andArray["and#t$tik"]=['cid[<]'=>intval($oldmore)];
    }
    $newmore=API::INP('newmore');
    if($newmore) {
      $tik++;
      $andArray["and#t$tik"]=['cid[>]'=>intval($newmore)];
    }

    $count=intval(API::INP('count'));
    if($count==0)$count=20;
    else if($count<2)$count=20;
    else if($count>200)$count=200;

    $page=intval(API::INP('page'));
    if($page<=0)$page=1;
    
    $lmt=[($page-1)*$count,$count];
    
    $where=["LIMIT" => $lmt , "ORDER" => ["cid DESC"]] ;
    if(count($andArray))
      $where['and'] = $andArray ;


    $r=$db->select($tblname,self::columns(),$where);
      
      
    //var_dump($db);
    if(!$r) {
      return API::msg(202003,"nothing");
    }
    
    return API::data($r);
  }

  // --U- 更新
  static function update( $cid, $data ) {
    $db=api_g('db');
    $tblname=self::table_name();
    $r=$db->update($tblname, $data,
      ['and'=>['cid'=>$cid],'LIMIT'=>1]);
    return $r;
  }

  // ---D 删除
  static function del( ) {
    $uid=API::INP('uid');
    $fid=API::INP('fid');
    $cid=API::INP('cid');
    $ctype=API::INP('ctype');

    $r=self::comment_get($uid, $fid,$ctype,0,$cid);
    if(!$r) {
      return API::msg(203502,"$uid can't del $ctype $cid of $fid");
    }
    return self::update($cid,['mark'=>1]);
  }
  

  //判断是否有效  
  static function validate( $dat ) {
    $err='';
    if( !$dat['content'] && $dat['ctype']=='comment') {
      $err.='评论内容是空的。';
    }
    // TODO: 验证 're_cid'
    // TODO: 验证 're_uid'
    return $err;
  }
}
