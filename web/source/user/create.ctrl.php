<?php

defined('IN_IA') or exit('Access Denied');
$_W['page']['title'] = '添加用户 - 用户管理 - 用户管理';
if(checksubmit()) {
	load()->model('user');
	$member = array();
	$member['username'] = trim($_GPC['username']);
	if(!preg_match(REGULAR_USERNAME, $member['username'])) {
		message('必须输入用户名，格式为 3-15 位字符，可以包括汉字、字母（不区分大小写）、数字、下划线和句点。');
	}
	if(user_check(array('username' => $member['username']))) {
		message('非常抱歉，此用户名已经被注册，你需要更换注册名称！');
	}
	$member['password'] = $_GPC['password'];
	if(istrlen($member['password']) < 8) {
		message('必须输入密码，且密码长度不得低于8位。');
	}
	$member['remark'] = $_GPC['remark'];
	$member['groupid'] = intval($_GPC['groupid']) ? intval($_GPC['groupid']) : message('请选择所属用户组');
	$uid = user_register($member);
	if($uid > 0) {
		unset($member['password']);
		message('用户增加成功！', url('user/edit', array('uid' => $uid)));
	}
	message('增加用户失败，请稍候重试或联系网站管理员解决！');
}
$groups = pdo_fetchall("SELECT id, name FROM ".tablename('users_group')." ORDER BY id ASC");
template('user/create');
