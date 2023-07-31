<?php

if (empty($_GPC['__pid'])) {
    header('Location: ' . url('project/display'));
    exit;
}
if(isset( $_GPC['__pid'])) {
    $_W['project'] = biz_getProject($_GPC['__pid'],true);
    $_W['pid']=$_W['project']['id'];

}
if(empty($_W['project'])){
    message('无效的项目信息',url(''));
}

load()->web('right');
$_W['rights']=biz_getUserProjectRight();

$m = $_GPC['m'];
$m = in_array($m, array_keys($Modules)) ? $m : 'project';
if(in_array($m,array('chips','queuing'))&&($_W['project']['status']==9)){
    //检查项目状态，是否关闭
    message('当前项目已关闭，此功能禁止使用！');
}

load()->model('module');
$title = $Modules[$m]['title'];
$site = createModuleSite($m);// WeUtility::createModuleSite($m);
define('IN_SOLUTION', true);

if (!is_error($site)) {
    $method = 'NavMenu';
    $items = @$site->$method();
    $frames = array('nav' => array('title' => '导航菜单', 'items' => $items['menu']));

    $direct = false;
    if (empty($do)) {
        $do=empty($items['default'])?'welcome':$items['default'];
    }
    $method = 'doWeb' . ucfirst($do);
    if (!empty($items['menu'][$do]) && isset($items['menu'][$do]['direct'])) {
        $direct = $items['menu'][$do]['direct'];
    }
    unset($items);
    if (!$direct && empty($_W['user'])) {
        unset($site);
        message('抱歉，您无权进行该操作，请先登录！', url('user/login'), 'warning');
    }
    $welcome = @$site->$method();
    exit;
}
exit("访问的方法 {$method} 不存在.");

function createModuleSite($name)
{

    static $file;
    $classname = "{$name}ModuleSite";
    if (!class_exists($classname)) {
        $file = IA_ROOT . "/addons/{$name}/site.php";
        if (!is_file($file)) {
            trigger_error('ModuleSite Definition File Not Found ' . $file, E_USER_WARNING);
            return null;
        }
        require $file;
    }
    if (!class_exists($classname)) {
        trigger_error('ModuleSite Definition Class Not Found', E_USER_WARNING);
        return null;
    }
    $o = new $classname();
    $o->modulename = $name;
    $o->__define = $file;
    return $o;

}