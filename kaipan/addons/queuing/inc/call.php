<?php
$project=$_W['project'];
$groups=biz_getWillCallGroup($project['id']);
$signset=biz_unserializer($project,'signset');
if(empty($signset)){
    message('未配置叫号相关选项！');
}
$maxselect=$signset['max'];
if(checksubmit()){

    if(isset($_GPC['callnum'])) {
        $callnum = explode(',', $_GPC['callnum']);
    }
    $keys=array_keys($groups);
    if(empty($callnum)){
        $callnum[]=$keys[0];
    }
    foreach($callnum as $num)
    {
        biz_callSignGroup($num,$_W['pid']);
    }
    memcached_set('callnum',implode(',',$callnum));
    $groups=biz_getWillCallGroup($_W['pid']);
}
$last=memcached_get('callnum');
$called=biz_getCalledGroup($_W['pid']);
include $this->template('queuing_list');