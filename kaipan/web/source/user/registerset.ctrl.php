<?php

defined('IN_IA') or exit('Access Denied');
$_W['page']['title'] = '注册选项 - 用户设置 - 用户管理';
load()->model('setting');
if (checksubmit('submit')) {
	setting_save(array('open' => intval($_GPC['open']), 'verify' => intval($_GPC['verify']), 'code' => intval($_GPC['code']), 'groupid' => intval($_GPC['groupid'])), 'register');
	message('更新设置成功！', url('user/registerset'));
}
$settings = setting_load('register');
$settings = $settings['register'];
$groups = pdo_fetchall("SELECT id, name FROM ".tablename('users_group')." ORDER BY id ASC");
template('user/access');
