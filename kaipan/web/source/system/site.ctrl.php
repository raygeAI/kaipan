<?php 

defined('IN_IA') or exit('Access Denied');
$dos = array('copyright', 'close');
$do = in_array($do, $dos) ? $do : 'copyright';

load()->model('setting');
load()->func('tpl');
$settings = setting_load('copyright');
$settings = $settings['copyright'];
if(empty($settings) || !is_array($settings)) {
	$settings = array();
}

if ($do == 'copyright') {
	$_W['page']['title'] = '站点信息设置 - 系统管理';
	if (checksubmit('submit')) {
		$data = array(
				'sitename' => $_GPC['sitename'],
				'url' => strexists($_GPC['url'], 'http://') ? $_GPC['url'] : "http://{$_GPC['url']}",
				'statcode' => htmlspecialchars_decode($_GPC['statcode']),
				'footerleft' => htmlspecialchars_decode($_GPC['footerleft']),
				'footerright' => htmlspecialchars_decode($_GPC['footerright']),
				'flogo' => $_GPC['flogo'],
				'blogo' => $_GPC['flogo'],
				'baidumap' => $_GPC['baidumap'],
				'address' => $_GPC['address'],
				'phone' => $_GPC['phone'],
				'qq' => $_GPC['qq'],
				'email' => $_GPC['email'],
				'keywords' => $_GPC['keywords'],
				'description' => $_GPC['description'],
		);
		setting_save($data, 'copyright');
		message('更新设置成功！', url('system/site'));
	}
}

if ($do == 'close') {
	$_W['page']['title'] = '站点信息设置 - 关闭站点';
	if (checksubmit('submit')) {
		$close['status'] = $_GPC['status'];
		$close['reason'] = $_GPC['reason'];
		setting_save($close, 'close');
		message('站点状态更新成功！', url('system/site/close'));
	}
	$settings = setting_load('close');
}
template('system/site');