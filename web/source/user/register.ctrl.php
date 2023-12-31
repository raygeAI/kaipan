<?php 

defined('IN_IA') or exit('Access Denied');
define('IN_GW', true);
$_W['page']['title'] = '注册选项 - 用户设置 - 用户管理';

$setting = cache_load('setting');
if (empty($setting['register']['open'])) {
	message('本站暂未开启注册功能，请联系管理员！');
}
$extendfields = pdo_fetchall("SELECT field, title, description, required FROM ".tablename('profile_fields')." WHERE available = '1' AND showinregister = '1' ORDER BY displayorder DESC");
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
	$profile = array();
 	if (!empty($extendfields)) {
		foreach ($extendfields as $row) {
			if (!empty($row['required']) && empty($_GPC[$row['field']])) {
				message('“'.$row['title'].'”此项为必填项，请返回填写完整！');
			}
			$profile[$row['field']] = $_GPC[$row['field']];
		}
	}
 	if(!empty($setting['register']['code'])) {
		$code = $_GPC['code'];
		$hash = md5($code . $_W['config']['setting']['authkey']);
		if($_GPC['__code'] != $hash) {
			message('你输入的验证码不正确, 请重新输入.');
		}
	}
	$member['status'] = !empty($setting['register']['verify']) ? -1 : 0;
	$member['remark'] = '';
	$member['groupid'] = intval($setting['register']['groupid']);
	$uid = user_register($member);
	if($uid > 0) {
		unset($member['password']);
		$member['uid'] = $uid;
				if (!empty($profile)) {
			$profile['uid'] = $uid;
			$profile['createtime'] = TIMESTAMP;
			pdo_insert('users_profile', $profile);
		}
		pdo_update('users_invitation', array('inviteuid' => $uid), array('id' => $invite['id']));
		message('注册成功'.(!empty($setting['register']['verify']) ? '，請等待管理员审核！' : '，请重新登录！'), url('user/login', array('uid' => $uid, 'username' => $member['username'])));
	}
	message('增加用户失败，请稍候重试或联系网站管理员解决！');
}
load()->func('tpl');
template('user/register');
