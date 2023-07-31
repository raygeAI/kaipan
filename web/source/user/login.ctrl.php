<?php

defined('IN_IA') or exit('Access Denied');
define('IN_GW', true);
if(checksubmit()) {
	_login($_GPC['referer']);
}
template('user/login');

function _login($forward = '') {
	global $_GPC;
	load()->model('user');
	$member = array();
	$username = trim($_GPC['username']);
	if(empty($username)) {
		message('请输入要登录的用户名');
	}
	$member['username'] = $username;
	$member['password'] = $_GPC['password'];
	if(empty($member['password'])) {
		message('请输入密码');
	}
    if($_GPC['admin']) {
        $record = user_single($member);
    }else{
        $record=biz_login($username,$member['password']);
        if(!empty($record)){
            $record['username'] =$record['UserName'];
            $record['uid']=$record['UserGUID'];
            $record['password']=$record['Password'];
        }
    }
	if(!empty($record)) {
		if($record['status'] == -1) {
			message('您的账号正在核合或是已经被系统禁止，请联系网站管理员解决！');
		}
		$cookie = array();
		$cookie['uid'] = $record['uid'];
		$cookie['lastip'] = $record['lastip'];
        $cookie['token'] = $record['Token'];
        $cookie['hash'] = md5($record['password'] . $record['salt']);
        if($_GPC['admin']) {
            $cookie['admin'] = 1;
        }
		$session = base64_encode(json_encode($cookie));
		isetcookie('__session', $session,   86400);
		if(empty($forward)) {
			$forward = $_GPC['forward'];
		}
		if(empty($forward)) {
			$forward = './index.php?c=project&a=display';
		}
		message("欢迎回来，{$record['username']}。", $forward);
	} else {
		message('登录失败，请检查您输入的用户名和密码！');
	}
}
