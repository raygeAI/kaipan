<?php

$_W['page']['title'] = '更新缓存 - 系统管理';
//load()->model('cache');
//load()->model('setting');
if (checksubmit('submit')) {
	cache_build_template();
	//cache_build_modules();
	//cache_build_setting();
    cache()->flush();
	message('缓存更新成功！', url('system/updatecache'));
} else {
	template('system/updatecache');
}

















