<?php

if(!empty($_GPC['multiid']) || (!empty($_GPC['mtid']) && $_GPC['mtid'] != -1)) {
	define('ACTIVE_FRAME_URL', url('site/multi/display'));
}
if(!empty($_GPC['styleid'])) {
	define('ACTIVE_FRAME_URL', url('site/style/styles'));
}

if($action != 'entry' && $action != 'nav') {
    define('FRAME', 'site');
}
$frames = buildframes(array(FRAME));
$frames = $frames[FRAME];
