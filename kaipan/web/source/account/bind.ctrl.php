<?php

defined('IN_IA') or exit('Access Denied');
$dos = array('post', 'delete', 'display', 'details');
$do = in_array($_GPC['do'], $dos) ? $_GPC['do']: 'display';
$acid = intval($_GPC['acid']);
$uniacid = intval($_GPC['uniacid']);

if(!empty($uniacid)) {
	$uniaccount = pdo_fetch("SELECT * FROM ".tablename('uni_account')." WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
	if(empty($uniaccount)) {
		message('楼盘项目不存在或已被删除！');
	}
	$state = uni_permission($uid, $uniacid);
	if($state != 'founder' && $state != 'manager') {
		message('没有该楼盘项目操作权限！');
	}
}

$settings = uni_setting($uniacid, array('notify', 'groupdata', 'bootstrap'));
$groupdata = $settings['groupdata'] ? $settings['groupdata'] : array('isexpire' => 0, 'oldgroupid' => '' ,'endtime' => TIMESTAMP);
$notify = $settings['notify'] ? $settings['notify'] : array();
$bootstrap = $settings['bootstrap'];
$data = uni_groups();
$groups = array();
foreach($data as $da){
	$groups[$da['id']] = $da;
}
$groups[0] = array('id' => 0, 'name' => '基础服务');
$groups[-1] = array('id' => -1, 'name' => '所有服务');


if ($do == 'post') {
	$_W['page']['title'] = '编辑子楼盘项目 - 编辑主楼盘项目';
		if(empty($acid)) {
		$_W['page']['title'] = '添加子楼盘项目 - 编辑主楼盘项目';
		if(empty($_W['isfounder']) && is_error($error = uni_create_permission($_W['uid'], 2))) {
			message($error['message'], '' , 'error');
		}
	}
	load()->func('tpl');
	load()->func('file');
	if (checksubmit('submit')) {
		if ($_GPC['type'] == 2) {
			$tablename = 'account_yixin';
			$type = 'yixin';
		} else {
			$tablename = 'account_wechats';
			$type = 'wechat';
		}
		
		$account = array();
				if (!empty($_GPC['model']) && $_GPC['model'] == 2) {
			$username = $_GPC['wxusername'];
			$password = md5($_GPC['wxpassword']);
			
			if (!empty($username) && !empty($password)) {
				if ($type == 'wechat') {
					$loginstatus = account_weixin_login($username, $password, $_GPC['verify']);
					$basicinfo = account_weixin_basic($username);
				} elseif ($_GPC['type'] == 'yixin') {
					$loginstatus = account_yixin_login($username, $password, $_GPC['verify']);
					$basicinfo = account_yixin_basic($username);
				}
				if (empty($basicinfo['name'])) {
					message('一键获取信息失败，请手动添加该公众帐号并反馈此信息给管理员！');
				}
				$account['username'] = $_GPC['wxusername'];
				$account['password'] = md5($_GPC['wxpassword']);
				$account['lastupdate'] = TIMESTAMP;
				$account['name'] = $basicinfo['name'];
				$account['account'] = $basicinfo['account'];
				$account['original'] = $basicinfo['original'];
				$account['signature'] = $basicinfo['signature'];
				$account['key'] = $basicinfo['key'];
				$account['secret'] = $basicinfo['secret'];
				$account['type'] = intval($_GPC['type']);
			}
		} else {
			if (empty($_GPC['name'])) {
				message('抱歉，名称和楼盘项目账号为必填项请返回填写！');
			}
			$account['name'] = $_GPC['name'];
			$account['account'] = $_GPC['account'];
			$account['level'] = intval($_GPC['level']);
			$account['key'] = $_GPC['key'];
			$account['secret'] = $_GPC['secret'];
			$account['type'] = intval($_GPC['type']);
		}

		if (empty($acid)) {
			$acid = account_create($uniacid, $account);			
		} else {
			$account['token'] = $_GPC['wetoken'];
			unset($account['type']);
			pdo_update($tablename, $account, array('acid' => $acid, 'uniacid' => $uniacid));
		} 
		
		if ($_GPC['model'] == 2) {
						if (!empty($basicinfo['headimg'])) {
				file_write('headimg_'.$acid.'.jpg', $basicinfo['headimg']);
			}
			if (!empty($basicinfo['qrcode'])) {
				file_write('qrcode_'.$acid.'.jpg', $basicinfo['qrcode']);
			}
			if (!empty($loginstatus)) {
								if ($type == 'wechat') {
					$result = account_weixin_interface($data['username'], $data['hash'], $data['token']);
					if (is_error($result)) {
						$error = $result['message'];
					}
				}
			}
		} else {
			if (!empty($_FILES['qrcode']['tmp_name'])) {
				$_W['uploadsetting'] = array();
				$_W['uploadsetting']['image']['folder'] = '';
				$_W['uploadsetting']['image']['extentions'] = array('jpg');
				$_W['uploadsetting']['image']['limit'] = $_W['config']['upload']['image']['limit'];
				$upload = file_upload($_FILES['qrcode'], 'image', "qrcode_{$acid}");
			}
			if (!empty($_FILES['headimg']['tmp_name'])) {
				$_W['uploadsetting'] = array();
				$_W['uploadsetting']['image']['folder'] = '';
				$_W['uploadsetting']['image']['extentions'] = array('jpg');
				$_W['uploadsetting']['image']['limit'] = $_W['config']['upload']['image']['limit'];
				$upload = file_upload($_FILES['headimg'], 'image', "headimg_{$acid}");
			}
		}
		
		message('更新子楼盘项目成功！', url('account/bind/post', array('acid' => $acid, 'uniacid' => $uniacid)), 'success');
	}
	$account = account_fetch($acid);
	template('account/bind');
}

if ($do == 'delete') {
	$account = account_fetch($acid);
	pdo_delete('account', array('acid' => $acid, 'uniacid' => $uniacid));
	if ($account['type'] == '1') {
		pdo_delete('account_wechats', array('acid' => $acid, 'uniacid' => $uniacid));
	} elseif ($account['type'] == '2') {
		pdo_delete('account_yixin', array('acid' => $acid, 'uniacid' => $uniacid));
	} else {
		pdo_delete('account_wechats', array('acid' => $acid, 'uniacid' => $uniacid));
		pdo_delete('account_yixin', array('acid' => $acid, 'uniacid' => $uniacid));
	}
	@unlink(IA_ROOT . '/attachment/qrcode_'.$acid.'.jpg');
	@unlink(IA_ROOT . '/attachment/headimg_'.$acid.'.jpg');
	message('删除子楼盘项目成功！', referer(), 'success');
}

if ($do == 'details') {
	load()->func('tpl');
	 	$account = account_fetch($acid);
		if(empty($account)) {
	 		message('楼盘项目不存在或已被删除', '', 'error');
	 	}
	 	
	 	$_W['page']['title'] = $account['name'] . ' - 楼盘项目详细信息';
	 	$uniaccount = pdo_fetchcolumn('SELECT name FROM ' . tablename('uni_account') . ' WHERE uniacid = :uniacid', array(':uniacid' => $account['uniacid']));
	 	$uid = pdo_fetchcolumn('SELECT uid FROM ' . tablename('uni_account_users') . ' WHERE uniacid = :uniacid', array(':uniacid' => $account['uniacid']));
	 	$username = pdo_fetchcolumn('SELECT username FROM ' . tablename('users') . ' WHERE uid = :uid', array(':uid' => $uid));
		
	 	
	 	$scroll = intval($_GPC['scroll']);
	    $add_num = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime >= :starttime AND followtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => strtotime(date('Y-m-d')) - 86400, ':endtime' => strtotime(date('Y-m-d')), ':follow' => 1));
	    $cancel_num = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND unfollowtime >= :starttime AND unfollowtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => strtotime(date('Y-m-d')) - 86400, ':endtime' => strtotime(date('Y-m-d')), ':follow' => 0));
	    $jing_num = $add_num - $cancel_num;
	    $total_num = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':endtime' => strtotime(date('Y-m-d')), ':follow' => 1));

	    $starttime = strtotime($_GPC['datelimit']['start']) ? strtotime($_GPC['datelimit']['start']) : strtotime('-30day');
		$endtime = strtotime($_GPC['datelimit']['end']) ? strtotime($_GPC['datelimit']['end']) : time();
		$day_num = intval(($endtime - $starttime) / 86400);
		$type = intval($_GPC['type']) ? intval($_GPC['type']) : 1;
	    		if($_W['isajax'] && $_W['ispost']) {
			$days = array();
			$datasets = array();
			for($i = $day_num; $i >= 1; $i--){
				$key = date('m-d', strtotime('-' . $i . 'day'));
				$days[$key] = 0;
				$datasets['flow1'][$key] = 0;
				$datasets['flow2'][$key] = 0;
				$datasets['flow3'][$key] = 0;
				$datasets['flow4'][$key] = 0;
			}

						$data = pdo_fetchall('SELECT * FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime >= :starttime AND followtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => $starttime, ':endtime' => $endtime, ':follow' => 1));
			foreach($data as $da) {
				$key = date('m-d', $da['followtime']);
				if(in_array($key, array_keys($days))) {
					$datasets['flow1'][$key]++;
				}
			}

						$data = pdo_fetchall('SELECT * FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND unfollowtime >= :starttime AND unfollowtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => $starttime, ':endtime' => $endtime, ':follow' => 0));
			foreach($data as $da) {
				$key = date('m-d', $da['unfollowtime']);
				if(in_array($key, array_keys($days))) {
					$datasets['flow2'][$key]++;
				}
			}

						$data0 = pdo_fetchall('SELECT * FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND unfollowtime >= :starttime AND unfollowtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => $starttime, ':endtime' => $endtime, ':follow' => 0));
			$data1 = pdo_fetchall('SELECT * FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime >= :starttime AND followtime <= :endtime', array(':acid' => $acid, ':uniacid' => $uniacid, ':starttime' => $starttime, ':endtime' => $endtime, ':follow' => 1));
			foreach($data1 as $da) {
				$key = date('m-d', $da['followtime']);
				if(in_array($key, array_keys($days))) {
					$day[date('m-d', $da['followtime'])] ++;
					$datasets['flow3'][$key]++;
				}
			}
			foreach($data0 as $da) {
				$key = date('m-d', $da['unfollowtime']);
				if(in_array($key, array_keys($days))) {
					$datasets['flow3'][$key]--;
				}
			}

						for($i = $day_num; $i >= 1; $i--){
				$key = date('m-d', strtotime('-' . $i . 'day'));
				$datasets['flow4'][$key] = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_mapping_fans') . ' WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime < ' . strtotime('-'.$i.'day'), array(':acid' => $acid, ':uniacid' => $uniacid, ':follow' => 1));;
			}

			$shuju['label'] = array_keys($days);
			$shuju['datasets'] = $datasets;
			exit(json_encode($shuju));
		} 
		template('account/details');
}
