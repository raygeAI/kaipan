<?php

defined('IN_IA') or exit('Access Denied');

//$_W['fans']['from_user'] = $_W['member']['uid'];
//$_W['weid'] = $_W['uniacid'];

if (!function_exists('fans_search')) {
	function fans_search($user, $fields = array()) {
		global $_W;
		load()->model('mc');
		$uid = intval($user);
		if($uid == 0) {
			$uid = pdo_fetchcolumn("SELECT uid FROM ".tablename('mc_mapping_fans')." WHERE openid = :openid AND acid = :acid", array(':openid' => $user, ':acid' => $_W['uniacid']));
			if (empty($uidrow)) {
				return ''; 			}
		}
		return mc_fetch($uid, $fields);
	}
}

if (!function_exists('fans_fields')) {
	function fans_fields() {
		return mc_fields();
	}
}

if(!function_exists('fans_update')) {
	function fans_update($user, $fields) {
		global $_W;
		load()->model('mc');
		$uid = intval($user);
		if($uid == 0) {
			$uid = pdo_fetchcolumn("SELECT uid FROM ".tablename('mc_mapping_fans')." WHERE openid = :openid AND acid = :acid", array(':openid' => $user, ':acid' => $_W['uniacid']));
			if (empty($uidrow)) {
				return ''; 			}
		}
		return mc_update($uid, $fields);
	}
}

if (!function_exists('create_url')) {
	function create_url($segment = '', $params = array()) {
		return url($segment, $params);
	}
}

if (!function_exists('toimage')) {
	function toimage($src) {
		return tomedia($src);
	}
}