<?php


if($controller == 'profile' && $action == 'notify') { 
	define('FRAME', 'mc');
} elseif(empty($_GPC['m']) && $action != 'module') {
	define('FRAME', 'setting');
} else {
	define('FRAME', 'ext');
}
$frames = buildframes(array(FRAME));
$frames = $frames[FRAME];

