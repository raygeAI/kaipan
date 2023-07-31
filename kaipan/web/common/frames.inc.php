<?php

defined('IN_IA') or exit('Access Denied');
$ms['setting'][] = array(
	'title' => '管理人员',
	'items' => array(
		array('title' => '操作人员列表', 	'url' => url('profile/worker')),
	)
);
$ms['setting'][] = array(
	'title' => '其他功能选项',
	'items' => array(
			)
);

$ms['ext'][] = array(
	'title' => '管理',
	'items' => array(
		array('title' => '扩展功能管理', 'url' => url('profile/module'))
	)
);

return $ms;
