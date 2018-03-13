<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
 
*/
use DJApi;
require_once dirname( __FILE__ ) . '/class.stee_user.php';
class class_steeobj{
    
  const MAX_REREAD_DAYS = 10; // 在几天之内查看的，允许直接再次查看而不用额度
    
  public static function main( $para1,$para2) {
    $res=API::data(['time'=>time().' - steefac is ready.']);
    return $res;
  }
//=========================================================
  //获取 数据表名 $type 1
  static function table_name( $type ) {
    $tnames=[
      'steefac'=>'steelfactory',
      'steeproj'=>'steelproject',
    ];
    
    $name=$tnames[$type];
    if(!$name)$name=$tnames['steefac'];
    $prefix=api_g("api-table-prefix");
    return $prefix.$name;
  }
  
  //预览用到的字段，用在 /overview_addr API中
  static function keys_addr( $type ) {
    $kr=[];
    //钢构厂
    $kr['steefac']=[
      'id',
      //"name",
      "update_at",
      "latE7",
      "lngE7",
      "citycode",
      "goodat",
      "cap_6m"
    ];
    $kr['steeproj']= [
      'id',
      "name",
      "update_at",
      "latE7",
      "lngE7",
      "citycode",
      "in_month",
      "need_steel"
    ];
    
    if(!$kr[$type])$type='steefac';
    return $kr[$type];
  }
  
  
  //预览用到的字段，用在 /li API中
  static function keys_preivew( $type ) {
    $kr=[];
    //钢构厂
    $kr['steefac']=[
      'id',
      'mark',
      "update_at",
      "level",
      "name",
      "addr",
      "latE7",
      "lngE7",
      //"province",
      //"city",
      //"district",
      
      "cap_y",
      "cap_6m",
      "goodat",
      "area_factory"
    ];
    $kr['steeproj']= [
      'id',
      'mark',
      "update_at",
      "close_time",
      "name",
      "addr",
      "latE7",
      "lngE7",
      
      'steel_shape',
      'steel_Qxxx',
      
      "in_month",
      "need_steel"
    ];
    
    if(!$kr[$type])$type='steefac';
    return $kr[$type];
  }
  //搜索查找 的字段，用在 /search API中
  static function keys_search( $type ) {
    $kr=[];
    //钢构厂
    $kr['steefac']=[
      "id",
      "license",
      "name",
      "addr",

      "province",
      "city",
      "district",
      "citycode",
      //"adcode",
      "formatted_address",

      "goodat"
    ];
    $kr['steeproj']= [
      'id',
      "name",
      "addr",
      "province",
      "city",
      "district",
      "citycode",
      //"adcode",
      "formatted_address",
      
      'steel_shape',
      'steel_Qxxx'
    ];
    
    if(!$kr[$type])$type='steefac';
    return $kr[$type];
  }
  // $type 2
  static function keys_req( $type ) {
    
    $kr=[];
    
    //钢构厂
    $kr['steefac']=[
      "update_at"=>0,
      "level"=>1,
      "license"=>4,
      "name"=>4,
      "addr"=>4,
      "latE7"=>5,
      "lngE7"=>5,
      "province"=>2,
      "city"=>0,
      "district"=>0,
      "citycode"=>2,
      "adcode"=>0,
      "formatted_address"=>4,
      
      "contact_person"=>0,
      "contact_tel"=>0,
      "contact_email"=>0,
      "cap_y"=>0,
      "cap_1m"=>0,
      "cap_2m"=>0,
      "cap_3m"=>0,
      "cap_6m"=>0,
      "workers"=>0,
      "workers_hangong"=>0,
      "workers_maogong"=>0,
      "workers_gongyi"=>0,
      "workers_xiangtu"=>0,
      "workers_other"=>0,
      "goodat"=>0,
      "fee"=>0,
      "area_factory"=>0,
      "area_duichang"=>0,
      "max_hangche"=>0,
      "max_paowan"=>0,
      "max_duxin"=>0,
      "dist_port"=>0,
      "dist_expressway"=>0
    ];
    
    //项目信息
    $kr['steeproj']= [
      "update_at"=>0,
      "close_time"=>0,
      "name"=>4,
      "addr"=>4,
      "latE7"=>5,
      "lngE7"=>5,
      "province"=>2,
      "city"=>0,
      "district"=>0,
      "citycode"=>2,
      "adcode"=>0,
      "formatted_address"=>4,
      
      "size"=>3,
      "type"=>2,
      
      'steel_shape'=>2,
      'steel_Qxxx'=>3,
      'stee_price'=>3,
      'advance_pay'=>1,
      'provide_raw'=>1,
      'require_check'=>1,
      'contact_person'=>2,
      'contact_tel'=>6,
      'contact_email'=>6,
      'notes'=>0,
      
      "in_month"=>1,
      "need_steel"=>1
    ];
    
    if(!$kr[$type])$type='steefac';
    return $kr[$type];
  }
  
  
  
  // $type 3
  //TODO: 有效性检查
  //这些是用于 update API 中 能直接通过参数能修改的字段
  //其他字段不可用参数修改，比如 del flag access 等字段
  static function data_all($type ) {
    $data=[];
    $keys=self::keys_req($type);
    
    $d= json_decode(API::INP('d'), true);
    api_g('query-d',$d);
    foreach ($keys as $key => $v){
      if(isset($d[$key]))
        $data[$key]=$d[$key];
    }
    
    $data['update_at']=time();
    return $data;
  }
  // $type 4
  static function data_check( $type, $data ) {
    $keys=self::keys_req($type);
    $err='';
    foreach ($keys as $k => $v){
      if( isset($data[$k]) && strlen($data[$k])<$v ) {
        $err.="E:$k.";
      }
    }
    return $err;
  }
  // $type 5
  static function keys_list($type ) {
    $keys=self::keys_req($type);
    $ky=[];
    foreach ($keys as $k => $v){
      $ky[]=$k;
    }
    return $ky;
  }
// \\=========================================================
   
   //=====【C---】==【Create】==============
   /**
   *  API:
   *    /steefac/add
   */
  public static function add( ) {
    $verify_token = \MyClass\SteeUser::verify_token($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];
    
    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }
    
    // $user=stee_user::_get_user($uid );
    // if(!($user['is_admin'] & 0x10000)) {
      // return API::msg(202001,'not sysadmin '.$user['is_admin']);
    // }
    
    $data=self::data_all($type);
    $err=self::data_check( $type, $data );
    if($err) {
      return API::msg(202001,'Error: '.$err);
      //return API::data([$err,$data]);
    }
    $db=API::db();
    $tblname=self::table_name($type);
    $r=$db->insert($tblname,$data );
    //var_dump($db);
    if(!$r) {
      return API::msg(202001,'Error: Create data.');
    }
    $data['id']=$r;
    
    //不是系统管理员，创建后，自动成为新厂的管理员
    $user=stee_user::_get_user($uid );
    if(!($user['is_admin'] & 0x10000)) {
      $appadmin=stee_user::apply_admin($type,$uid,$r);
      $data['appadmin'] = $appadmin;
    }

    return API::data($data);
  }  


  //=====【-R--】==【Restrive】==============
   /**
   *  API:
   *    /steefac/detail
   */
  public static function detail( ) {
    $verify_token = \MyClass\SteeUser::verify_token($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];
    $type=API::INP('type');
    $id=intval(API::INP('id'));
    $confirm=intval(API::INP('confirm'));
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }

    /** 先获取权限情况 */
    $limitJson = \MyClass\SteeData::getReadDetailLimit(['uid'=>$uid, 'type'=>$type, 'facid'=>$id]);
    $limit = $limitJson['datas']['limit'];

    if($limit === 'never'){
      \DJApi\API::debug('查看不受限制');
    }
    else if($confirm){
      \DJApi\API::debug('用户要求查看');
      \MyClass\SteeData::recordReadDetail($uid, $type, $id, '使用额度查看');
    }
    else if($limitJson['datas']['admin'] || $limitJson['datas']['superadmin']){
      // 不要返回限制了
    }
    else {
      \DJApi\API::debug('查看受限');
      return API::data(['limit'=>$limit]);
    }


    $tblname=self::table_name($type);
    $db=API::db();
    
    //字段名
    $ky=self::keys_list($type);
    $ky[]='id';
    $ky[]='mark';
    $id=intval(API::INP('id'));
    if($id<1) {
      return API::msg(202001,'Error: id');
    }
    
    $r=$db->get($tblname, $ky,
      ['and' => ['id'=>$id,'or'=>['mark'=>null,'mark#'=>''] ] ] );

    return API::data($r);
  }
  /**
   *  API:
   *    /steefac/li
   */
  public static function li( ) {
    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }
    $tblname=self::table_name($type);
    $db=API::db();
    
    //字段名
    $ky=self::keys_preivew($type);
    
    $ids=API::INP('ids');
    if(strlen($ids)==0) {
      return API::data([]);
      return API::msg(202001,'Error: ids');
    }
    $idsArr=explode(',',$ids);
    $r=$db->select($tblname, $ky,
      ['and' => ['id'=>$idsArr,'or'=>['mark'=>null,'mark#'=>''] ] ] );

    return API::data($r);
  }
  /**
   *  API:
   *    /steefac/overview_addr
   *  返回总览，全部的地址信息
   */
  public static function overview_addr ( ) {
    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }
    $tblname=self::table_name($type);
    $db=API::db();
    
    //字段名
    $ky=self::keys_addr($type);
    
    $idsArr=explode(',',$ids);
    $r=$db->select($tblname, $ky,
      [
        'or'=>['mark'=>null,'mark#'=>''],
        "ORDER" => ["update_at DESC","id DESC"]
      ] );

    return API::data($r);
  }

   /**
   *  API:
   *    /steefac/search
   */
  public static function search( ) {
    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }
    $tblname=self::table_name($type);
    $db=API::db();
    
    //要返回的字段名 
    //$kyRes=self::keys_list($type);
    $kyRes=self::keys_preivew($type);
    $kyRes[]='id';
    $kyRes[]='mark';
    
    $uid = \MyClass\SteeUser::sign2uid($_REQUEST);

    //页数
    $count=intval(API::INP('count'));
    $count_max=50;
    $user=stee_user::_get_user($uid );
    if( (intval($user['is_admin']) & 0x10000) ) {
      $count_max=5000;
    }
    if($count<1)$count=1;
    if($count>$count_max)$count=$count_max;

    $page=intval(API::INP('page'));
    if($page<1)$page=1;
    
    $tik=0;
    $andArray=[];


    //坐标范围搜索： 纬度 ,经度(*1e7), 距离(m)
    //1米 = 0.00001度 近似
    $lat=intval(API::INP('lat'));
    $lng=intval(API::INP('lng'));
    $dist=intval(API::INP('dist'));
    if($lat>10E7 && $lat < 55e7 
      && $lng>70E7 && $lng < 140e7
      && $dist>100 && $dist < 999E3) {
        // 此条件下 假定其格式正确
      $lat1=$lat-$dist*100;
      $lng1=$lng-$dist*100;
      $lat2=$lat+$dist*100;
      $lng2=$lng+$dist*100;
      $posand=['lngE7[>]'=>$lng1,'lngE7[<]'=>$lng2,'latE7[>]'=>$lat1,'latE7[<]'=>$lat2 ];
      $tik++;
      $andArray["and#t$tik"]=$posand;
    }

    //搜索字符
    $search=API::INP('s');
    if(strlen($search)>0) {
      $k= preg_split("/[\s,;]+/",$search);
      $s_key=self::keys_search($type);
      
      $w_or=[];
      for($i=count($k); $i--;  ) {
        $or_list=[];
        for($j=count($s_key); $j--; ) {
          $or_list[$s_key[$j].'[~]']=$k[$i];
        }
        $w_or["or#".$i]=$or_list;
      }
      $tik++;
      $andArray["and#t$tik"]=$w_or;
    }

    //正常标记的才返回
    $tik++;
    $andArray["and#t$tik"]=['or'=>['mark#1'=>null,'mark#2'=>'']];
    
    $where=["LIMIT" => [$page*$count-$count, $count] , "ORDER" => ["update_at DESC","id DESC"]] ;
    if(count($andArray))
      $where['and'] = $andArray ;


    //var_dump($where);
    $r=$db->select($tblname, $kyRes,$where);
    $res['data']=$r;
    return API::data($r);

  } 


  //=====【--U-】==【Update】==============
   /**
   *  API:
   *    /steefac/update
   */
  public static function update( ) {
    $verify_token = \MyClass\SteeUser::verify_token($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];

    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }
    
    $id=intval(API::INP('id'));
    if( !$id) {
      return API::msg(202001,'Error: id'.$id);
    }
    
    $user=stee_user::_get_user($uid );
    
    if(!($user['is_admin']& 0x10000) && !strpos('#,'.$user[$type.'_can_admin'].',', ','.$id.',') ) {
      return API::msg(202001,"not admin($id) or sysadmin");
    }

    $data=self::data_all($type);
    
    $err=self::data_check( $type, $data );
    if($err) {
      return API::msg(202001,'Err:'.$err);
      //return API::data([$err,$data]);
    }
    $db=API::db();
    $tblname=self::table_name($type);
    unset($data['id']);
    $data['update_at']=time();
    
    $r=$db->update($tblname, $data, ['id'=>$id] );

    //var_dump($db);
    return API::data($r);
  }  
  //=====【---D】==【Delete】==============
   /**
   *  API:
   *    /steefac/delete
   */
  public static function delete( ) {
    $verify_token = \MyClass\SteeUser::verify_token($_REQUEST);
    if(!\DJApi\API::isOk($verify_token)) return $verify_token;
    $uid = $verify_token['datas']['uid'];

    $type=API::INP('type');
    if(!stee_user::_check_obj_type( $type )) {
      return API::msg(202001,'E:type:',$type);
    }

    $db=API::db();
    $tblname=self::table_name($type);
    
    $id=intval(API::INP('id'));
    if(!$id) {
      return API::msg(202001,'Error: id');
    }

    $user=stee_user::_get_user($uid );
    
    //只允许系统管理员删除
    //if(!($user['is_admin']& 0x10000) && !strpos('#,'.$user['fac_can_admin'].',', ','.$id.',') ) {
      //return API::msg(202001,"not admin($id) or sysadmin");
    //}
    if(!($user['is_admin']& 0x10000) ) {
      return API::msg(202001,"not sysadmin");
    }
    
    $r=$db->update($tblname, ['mark'=>'DEL'], ['id'=>$id] );

    return API::data($r);
  }  

}
