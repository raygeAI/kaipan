<?php

defined('IN_IA') or exit('Access Denied');

$pid = intval($_GPC['pid']);
$project=db_getProject($pid,false,false);
if(empty($project)) {
	message('无效项目信息.');
}
isetcookie('__pid', $project['projguid'], 46800);
header('location: ' . url('project/module'));