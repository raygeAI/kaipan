<?php

defined('IN_IA') or exit('Access Denied');
$op = trim($_GPC['op']);
if (!in_array($op, array('delete', 'import', 'update'))) {
    message('无效的功能调用！', url('project/display/'));
}
if (!$_W['isfounder']) {
    message('非管理员，无权限操作！', url('project/display/'));
}

$guid = getInputGUID($_GPC['guid']);
if (empty($guid) ) {
    message('请填写有效项目GUID！', url('project/display/'));
}

$project = db_getProject($guid, true, false);
if ($op == 'delete') {
    if (!empty($project)) {
        if($project['status']==9) {
            biz_deleteProject($project);
            message('项目已删除！', url('project/display/'));
        }else{
            message('项目非关闭状态，禁止删除！', url('project/display/')); 
        }
    } else {
        message('无效的参数！', url('project/display/'));
    }
    exit;
}


load()->web('progress');
if ($op == 'import') {
    if (empty($project)) {
        $callback = function () use ($guid) {
            if (ERP_ENABLE) {
                load()->web('dbexchange');
                importFromErp_handler($guid);
            } else {
                load()->web('dbapi');
                importFromCenter_handler($guid);
            }
        };
        showProgress($callback);

    } else {
        message('楼盘项目已存在！', url('project/display/'));
    }
    exit;
}

if ($op == 'update') {
    if (empty($project)) {
        message('无效的楼盘项目参数！', url('project/display/'));
    }

    if ($_W['isajax']) {
        $url=url('project/post/',array('op'=>'update','guid'=>$guid));
        include template('project/update', TEMPLATE_INCLUDEPATH);
        exit;
    }

    $option = $_GPC['options'];
    if(count($option)>0) {
        $callback = function () use ($guid, $option) {
            load()->web('dbexchange');
            
            updateToErp_handler($guid, $option);
        };
        showProgress($callback, '项目数据同步');
    }else{
        message('没有选择要处理的事项。',url('project/display/'));
    }
    exit;
}
