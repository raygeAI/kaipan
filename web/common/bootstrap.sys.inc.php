<?php
if(!isset($_W['config']['client'])&&(!empty($_W['config']['mssql']))) {
    load()->classs('mssql');
    load()->web('erp');
}
load()->web('business');
load()->model('user');

$session = json_decode(base64_decode($_GPC['__session']), true);
if(is_array($session)) {
    if(empty($session['token'])) {
        $user = user_single(array('uid' => $session['uid']));
    }else{
        $user=biz_getUser($session['token']);
        $user['password']=$user['Password'];
        $user['uid']=$user['UserGUID'];
        $user['username']=$user['UserName'];
        $_W['role'] == 'operator';
    }
	if(is_array($user) && $session['hash'] == md5($user['password'] . $user['salt'])) {
		$_W['uid'] = $user['uid'];
		$_W['username'] = $user['username'];
		$user['currentvisit'] = $user['lastvisit'];
		$user['currentip'] = $user['lastip'];
		$user['lastvisit'] = $session['lastvisit'];
		$user['lastip'] = $session['lastip'];
		$_W['user'] = $user;
		$founder = explode(',', $_W['config']['setting']['founder']);
		$_W['isfounder'] = in_array($_W['uid'], $founder) ? true : false;
	} else {
		isetcookie('__session', false, -100);
	}
	unset($user);
}
unset($session);
$_W['template'] = 'default';

