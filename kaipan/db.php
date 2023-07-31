<?php
/**
 * 数据传输接口
 */


//增加接口调用
define('IN_SYS', true);
error_reporting(E_ALL & ~E_NOTICE);

require './framework/bootstrap.inc.php';
load()->web('business');
load()->web('dbapi');

$func = strtolower($_GPC['func']);
$res = array('ok' => false, 'msg' => '');

//检查项目授权，是否允许项目传输
if ($func == 'get_token') {
    //检查是否存在token？
    $guid=trim($_GPC['guid']);
    $key='sync_'.$guid;
    $token=memcached_get($key);
    if ($token == '') {
        $project=db_getProject($guid,true);
        if(empty($project)) {
            $res['msg']='无效的参数';
            returnJson($res);
        }
        if(memcached_addKey('Add'.$key)){
            $data=array('guid'=>$guid,'ip'=>CLIENT_IP);
            $token=GUID();
            memcached_set($key,$token,300);
            memcached_set('sync_t_'.$token,$data,300);
            memcached_delete('Add'.$key);
            $res['ok']=true;
            $res['token'] = $token;
        }else{
            $res['msg']='无法增加cache数据';
        }
        
    }else{
        $res['msg']='此项目已有下载未完成，请等5分钟再试';
    }
    returnJson($res);
}

if ($func == 'release_token') {
    $token = $_GPC['token'];
    $data=memcached_get('sync_t_'.$token);
    memcached_delete('sync_'.$data['guid']);
    memcached_delete('sync_t_'.$token);
}
//获取数据接口
if ($func == 'get_data') {
    $token = $_GPC['token'];
    $data=memcached_get('sync_t_'.$token);
    $type = $_GPC['type'];
    $enable  =!empty($data)&&!empty($data['guid']);
    //$enable=$enable && ($data['ip']==CLIENT_IP);
    $list=array('project','build','room','chips','user');
    if ( $enable && in_array($type, $list)) {
        $call = 'download_' . $type;
        $res['ok']=true;
        $res['data'] = $call($data['guid']);
    } else {
        $res['msg'] = '无效调用';
    }
    returnJson($res);
}
$res['msg']='无效的功能调用';
returnJson($res);
