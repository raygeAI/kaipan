<?php

defined('IN_IA') or exit('Access Denied');
isetcookie('__session', '', -10000);
$forward = $_GPC['forward'];
if(empty($forward)) {
	$forward = './index.php?c=user&a=login';
}
header('location:'.$forward);
