<?php
class class_wp{

//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW

    /*  ==== USER ====================================
    ## api: 
      - /wp/user/login
      - /wp/user/checktok
    */
    public static function user($para1,$para2) {
      $wpwp=self::init_wp();
      switch($para1){
        case 'login':
          return $wpwp->user_login();
        case 'checktok':
          return $wpwp->user_checktok();
        case 'auth':
          return $wpwp->user_auth();
      }
      return API::msg(1001,'Invalid api of user.');
    }
    /*  ==== POST ====================================
    ## api:  
      - /wp/post/add/
    
    /wp/post/get/:id
    /wp/post/update/:id
    /wp/post/delete/:id

    /wp/post/list/
    /wp/post/list/:id


    */ 
    public static function post($para1,$para2) {
      $wpwp=self::init_wp();
      switch($para1){
        case 'add':
          API::dump($wxwp);
          return $wpwp->post_add();
        case 'get':
          return $wpwp->post_get($para2);
        case 'update':
          return $wpwp->post_update($para2);
        case 'delete':
          return $wpwp->post_delete($para2);
      }
      return API::msg(1002,'Invalid api of post.');
    }
    
    //--- sec FOR helper func --------------------
    public static function init_wp() {
    
      require_once 'wordpress-helper/wordpress-api-helper.php';// class WAPI
      $wxwp=new WAPI(  );
      return $wxwp;
      
    }
}
