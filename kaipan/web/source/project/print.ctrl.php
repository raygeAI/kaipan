<?php
/**
 * 打印模块管理
 */
defined('IN_IA') or exit('Access Denied');
$op = trim($_GPC['op']);
if(!in_array($op,array('list','delete','set'))){
    $op='list';
    //message('无效的功能调用！', url('project/display/'));
}
if($op=='list') {
    $list = pdo_fetchall('select * from ' . tablename('printmodule'));
    include template('project/print', TEMPLATE_INCLUDEPATH);
    exit;
}
if($op=='delete'){
    $id=intval($_GPC['id']);
    if($id>0) {
        $module = pdo_fetch('select * from ' . tablename('printmodule') . ' where id=:id', array(':id' => $id));
        if(!empty($module)){
            pdo_delete('printer',array('moduleid'=>$module['id']));
            pdo_delete('printmodule',array('id'=>$module['id']));
        }
    } 
    message('删除成功', url('project/print/'));
}
