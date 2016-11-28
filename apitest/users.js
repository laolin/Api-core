// 在APItest中的API地址中输入 ./users.js，然后get一下即可加载此JS文件。

  
//--------------------------------------
function books_create(title,rating,price,  user,password ) {
  rating=parseInt(rating)
  ptoken=gen_pass_token(user,password)
  finger=gen_finger(['books.create',title,rating,price])
  d=gen_action_data(user,password, finger)
  
  d.title=title
  d.rating=rating
  d.price=price
  
  console.log(JSON.stringify(d,null," "))
  return d
}
function books_update(id,title,rating,price,  user,password ) {
  id=parseInt(id)
  rating=parseInt(rating)
  ptoken=gen_pass_token(user,password)
  finger=gen_finger(['books.update',id,title,rating,price])
  d=gen_action_data(user,password, finger)
  
  d.id=id
  if(title)d.title=title
  if(rating)d.rating=rating
  if(price)d.price=price
  
  console.log(JSON.stringify(d,null," "))
  return d
}
function books_delete(id,  user,password ) {
  id=parseInt(id)
  ptoken=gen_pass_token(user,password)
  finger=gen_finger(['books.delete',id])
  d=gen_action_data(user,password, finger)
  
  d.id=id
  
  console.log(JSON.stringify(d,null," "))
  return d
}

//--------------------------------------

/**
 * 根据用户名，密码生成 提交 password 的post数据
 */
function gen_chgpwd_data(user,password,newpassword  ,prefix){
  prefix=prefix||""

  ptoken=gen_pass_token(user,password)
  ntoken=gen_pass_token(user,newpassword)
  d=gen_action_data(user,password,gen_finger(['chgpwd',user,ptoken,ntoken]),prefix)
  d.__catusers_action='chgpwd'
  d[prefix+'ptoken']=ptoken
  d[prefix+'ntoken']=ntoken
  console.log(JSON.stringify(d,null," "))
  return d;

  
}

    
/**
 * 根据用户名，密码生成 提交 reg 的post数据
 */
function gen_reg_data(user,password,email,prefix) {
  prefix=prefix||""
  ptoken=gen_pass_token(user,password)
  d=gen_action_data(user,password, gen_finger(['reg',user,ptoken,email]),prefix)
  d.__catusers_action='reg'

  d[prefix+'ptoken']=ptoken
  d[prefix+'email']=email
  console.log(JSON.stringify(d,null," "))
  return d;
}
     
function gen_finger(ar) {
  if( !Array.isArray(ar) ) return false
  str=''
  ar.forEach( function(v){str+=v} )
  return hex_md5(str);
  
}
/**
 * 根据用户名，密码生成 提交 action 的post数据
 */
function gen_action_data(user,password, afinger,  prefix) {
  prefix=prefix||""
  dt=new Date()
  time=Math.round( (dt.getTime()/1000)) - 480*60 - dt.getTimezoneOffset()*60;//修正为东8区
  atoken=gen_action_token(user, afinger, time, password)
  d={}
    if(prefix) d['__catusers_prefix']=prefix
    d[prefix+'user']=user
    d[prefix+'afinger']=afinger
    d[prefix+'atime']=time
    d[prefix+'atoken']=atoken
  
  //console.log(JSON.stringify(d,null," "))
  return d
}


/**
 * 根据用户名，密码生成 gen_pass_token
 */
function gen_pass_token(user,password) {
  _md5Salt='Laolin_user_PUSHAN';
  return hex_md5(_md5Salt + user.toLowerCase()  + password  );
}

//Math.round( (new Date().getTime()/1000))

/**
 * 由4个变量一起计算出来一个验证字符串
*/    
function gen_action_token(user,afinger,time,password) {
  pass_token=gen_pass_token(user,password);  
  $ret=hex_md5(user.toLowerCase() + afinger + time + pass_token);
  return $ret;
}
    