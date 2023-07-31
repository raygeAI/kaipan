<?php

defined('IN_IA') or exit('Access Denied');
if (!$_W['isfounder']) {
    message('非管理员，无权处理');
}
$do = !empty($_GPC['do']) ? $_GPC['do'] : 'display';
$pid = intval($_GPC['pid']);
$project = db_getProject($pid, false, false);
$stationGUID = trim($_GPC['StationGUID']);
if ($do == 'display') {
    $list = db_getAllStation($project['projguid'], 'StationGUID');
    foreach ($list as $k => $v) {
        $level = substr_count($v['HierarchyCode'], '.');
        if ($level > 0) {
            $list[$k]['subtree'] = str_pad('&nbsp;', $level) . '├';
        }
    }
    // 部门员工列表

    $keys = array_keys($list);
    if (!in_array($stationGUID, $keys)) {
        $stationGUID = $keys[0];
    }
    $station = $list[$stationGUID];
    $moduleRight = biz_getStationRight($stationGUID, $project['projguid']);
    $users = db_getUserOfStation($stationGUID);
    include template('project/user', TEMPLATE_INCLUDEPATH);
}

if ($do == 'set') {
    $backUrl = url('project/user/display', array('pid' => $pid, 'StationGUID' => $stationGUID));
    $url = url('project/user/set', array('pid' => $pid, 'StationGUID' => $stationGUID));
    if (empty($stationGUID) || empty($project)) {
        $msg = '无效参数';
        exit;
        if ($_W['isajax']) {
            echo($msg);
            exit;
        } else {
            message($msg, $backUrl);
        }
    }

    $moduleRight = biz_getStationRight($stationGUID, $project['projguid']);
    if ($_W['token'] == $_GPC['token']) {
        $rights = array();

        foreach ($moduleRight as $k => $m) {
            $rights[$k] = $_GPC['m_' . $k];
        }
        if (biz_updateStationRight($rights, $stationGUID, $project['projguid'])) {
            memcached_clean($project['projguid']);
            message('部门权限更新成功！', $backUrl, 'success');
        }else{
            message('部门权限更新失败！', $backUrl, 'error'); 
        }
    }
    include template('project/rightSet', TEMPLATE_INCLUDEPATH);
}


if($do=='changepwd'){
    
}
