<?php

define('IN_SYS', true);
error_reporting(E_ALL & ~E_NOTICE);
require './framework/bootstrap.inc.php';
load()->web('business');
load()->web('print');
//调用接口测试地址：182.254.197.221/print.php

$func =strtolower( $_GPC['act']);
$res=array('result'=>false,'msg'=>'');

if($func=='clientregister'){
    //注册处理,computerName,computerId
    $param=array('computer'=>$_GPC['computerName'],'key'=>$_GPC['computerId']);
    $data=biz_Print_ModuleRegister($param);
    if(!empty($data)) {
        $res['result'] = true;
        $res['data'] =$data['token'];
        memcached_set('print_'.$data['token'],$data,3600*8);
    }else{
        $res['msg']='注册失败';
    }
    returnJson($res);
}

$token=$_GPC['token'];
$module=memcached_get('print_'.$token);
if(empty($module)||empty($module['id'])){
    $res['result']=false;
    $res['msg']='token失效';
    $res['data']=-1;
    returnJson($res);
}

if($func=='statusreport'){
    $param=array('key'=>$_GPC['printerId'],'status'=>$_GPC['printerStatus']);
    $res['result']=true;
    $res['data']=biz_Print_StatusReport($param,$module);
    returnJson($res);
}

if($func=='gettasks'){
    $res['result']=true;
    if(!empty($module['id'])){
        $res['data']=biz_Print_getTask($module);
    }else{
        $res['data']=''; 
    }
    returnJson($res);
}

if($func=='taskreport'){
    $param=array(
        'key'=>$_GPC['taskId'],'status'=>$_GPC['taskStatus']);
    $res['result']=true;
    biz_Print_TaskReport($param,$module);
    returnJson($res);
}

if($func=='printerregister'){
    //注册处理,title=,type=0,1
    $param=array('name'=>$_GPC['printerName'],'type'=>$_GPC['type'],'index'=>$_GPC['index']);
    $data=biz_Print_Register($param,$module);
    $res['result']=true;
    $res['data']=$data['id'];
    returnJson($res);
}

if($func=='printerdelete'){
    $param=array('name'=>$_GPC['printerName'],'type'=>$_GPC['type'],'index'=>$_GPC['index']);
    if(biz_Print_Delete($param,$module)) {
        
    }
    $res['result'] = true;
    returnJson($res);
}

if($func=='gettemplatebyid'){
    $res['result'] = true;
    $res['data']=biz_Print_getTemplateById($_GPC['templateId']);
    returnJson($res);
}

$res['msg']='无效的功能调用';
returnJson($res);
