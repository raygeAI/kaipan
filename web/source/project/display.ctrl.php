<?php


$_W['page']['title'] = '楼盘项目列表 - 楼盘项目';


$sql = 'select * from ' . tablename('project');
$condition = ' where 1=1 ';
$projects = biz_getUserProject($_W['user']);
if (is_array($projects)) {
    $condition .= ' and projguid in (\'' . implode("','", $projects) . '\')';
}
$status = biz_getDictionary('projstatus');
$pars = array();
$keyword = trim($_GPC['keyword']);
if (!empty($keyword)) {
    $condition = " AND `projname` LIKE :name";
    $pars[':name'] = "%{$keyword}%";
}
$sql.=$condition;
$list= pdo_fetchall($sql,$pars);
$import_enable=$_W['isfounder'];

include template('project/display', TEMPLATE_INCLUDEPATH);