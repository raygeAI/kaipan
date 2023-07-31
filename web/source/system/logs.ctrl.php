<?php 

defined('IN_IA') or exit('Access Denied');
$dos = array('system', 'database');
$do = in_array($do, $dos) ? $do : 'system';
load()->func('tpl');

$params = array();
$where  = '';
if ($_GPC['time']) {
		$starttime = strtotime($_GPC['time']['start']);
	$endtime = strtotime($_GPC['time']['end']);
	$where = 'AND `createtime` >= :starttime AND `createtime` < :endtime';
	$params[':starttime'] = $starttime;
	$params[':endtime'] = $endtime;
}


if ($do == 'system') {
	$pindex = max(1, intval($_GPC['page']));
		$psize = 10;
	$where .= " WHERE `type` = '1'";
	$sql = 'SELECT * FROM ' . tablename('core_performance') . " $where LIMIT " . ($pindex - 1) * $psize .','. $psize;
	$list = pdo_fetchall($sql, $params);
    if(!empty($list)) {
        foreach ($list as $key => $value) {
            $list[$key]['type'] = '系统日志';
            $list[$key]['createtime'] = date('Y-m-d H:i:s', $value['createtime']);
        }
    }
		$total = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('core_performance'). $where , $params);
	$pager = pagination($total, $pindex, $psize);
}

if ($do == 'database') {
	$pindex = max(1, intval($_GPC['page']));
		$psize = 10;
	$where .= " WHERE `type` = '2'";
	$sql = 'SELECT * FROM ' . tablename('core_performance') . " $where LIMIT " . ($pindex - 1) * $psize .','. $psize;
	$list = pdo_fetchall($sql, $params);
    if(!empty($list)){
	foreach ($list as $key=>$value) {
		$list[$key]['type'] = '数据库日志';
		$list[$key]['createtime'] = date('Y-m-d H:i:s', $value['createtime']);
	}
    }
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('core_performance'). $where , $params);
	$pager = pagination($total, $pindex, $psize);
}

template('system/logs');