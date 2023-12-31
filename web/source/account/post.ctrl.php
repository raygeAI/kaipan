<?php

defined('IN_IA') or exit('Access Denied');
load()->func('tpl');
$dos = array('basic', 'list', 'high');
$do = in_array($do, $dos) ? $do : 'basic';

$id = $uniacid = intval($_GPC['uniacid']);
if(!empty($id)) {
	$state = uni_permission($_W['uid'], $id);
	if($state != 'founder' && $state != 'manager') {
		message('没有该楼盘项目操作权限！');
	}
} else {
	if(empty($_W['isfounder']) && is_error($permission = uni_create_permission($_W['uid'], 1))) {
		message($permission['message'], '' , 'error');
	}
}

if (empty($_W['isfounder'])) {
	$group = pdo_fetch("SELECT * FROM ".tablename('users_group')." WHERE id = '{$_W['user']['groupid']}'");
	$group['package'] = uni_groups((array)iunserializer($group['package']));
} else {
	$group['package'] = uni_groups();
}
$allow_group = array_keys($group['package']);
$allow_group[] = 0;
if(!empty($_W['isfounder'])) {
	$allow_group[] = -1;
}


if($do == 'basic') {
	$_W['page']['title'] = '楼盘项目基本信息 - 编辑主楼盘项目';
	if(empty($id)) {

	} elseif (checksubmit('submit')) {
		$groupid = intval($_GPC['groupid']);
		if(!in_array($groupid, $allow_group)) {
			message('您所在的用户组没有使用该服务套餐的权限');
		}
		load()->model('module');
		$uniaccount = array(
				'name' => $_GPC['name'],
				'groupid' => intval($_GPC['groupid']),
				'description' => $_GPC['description'],
		);
		if($_GPC['isexpire'] == '1') {
			strtotime($_GPC['endtime']) > TIMESTAMP ? '' : message('服务套餐过期时间必须大于当前时间', '', 'error');
			$updatedata['groupdata'] = iserializer(array('isexpire' => 1, 'oldgroupid' => intval($_GPC['groupidhide']), 'endtime' => strtotime($_GPC['endtime'])));
		} else {
			$updatedata['groupdata'] = iserializer(array('isexpire' => 0, 'oldgroupid' => intval($_GPC['groupidhide']), 'endtime' => TIMESTAMP));
		}

		if($_W['isfounder']) {
			$notify['sms']['balance'] = intval($_GPC['balance']);
			$notify['sms']['signature'] = trim($_GPC['signature']);
			$notify = iserializer($notify);
			$updatedata['notify'] = $notify;
		}
		$updatedata['bootstrap'] = trim($_GPC['bootstrap']);
		pdo_update('uni_settings', $updatedata , array('uniacid' => $id));
		pdo_update('uni_account', $uniaccount, array('uniacid' => $id));
		module_build_privileges();
		message('更新楼盘项目成功！', referer(), 'success');
	}

	$account = array();
	if (!empty($id)) {
		$account = pdo_fetch("SELECT * FROM ".tablename('uni_account')." WHERE uniacid = :id", array(':id' => $id));
		$settings = uni_setting($id, array('notify', 'groupdata', 'bootstrap'));
		$groupdata = $settings['groupdata'] ? $settings['groupdata'] : array('isexpire' => 0, 'oldgroupid' => '' ,'endtime' => TIMESTAMP);
		$notify = $settings['notify'] ? $settings['notify'] : array();
		$bootstrap = $settings['bootstrap'];
	} else {
		$groupdata = array('isexpire' => 0, 'oldgroupid' => '' ,'endtime' => TIMESTAMP);
	}
	$group = array();
	if (empty($_W['isfounder'])) {
		$group = pdo_fetch("SELECT * FROM ".tablename('users_group')." WHERE id = '{$_W['user']['groupid']}'");
		$group['package'] = uni_groups((array)iunserializer($group['package']));
	} else {
		$group['package'] = uni_groups();
	}
	template('account/post');
}

if ($do == 'list') {
	$_W['page']['title'] = '子楼盘项目列表 - 编辑主楼盘项目';
	$accounts = uni_accounts($uniacid);
	$types = account_types();
	template('account/list');
}
