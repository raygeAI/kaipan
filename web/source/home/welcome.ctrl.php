<?php

defined('IN_IA') or exit('Access Denied');

$dos = array('project', 'printer', 'ext', 'solution');
$do = in_array($do, $dos) ? $do : 'project';

define('FRAME', $do);
$frames = buildframes(array(FRAME), $_GPC['m']);
$frames = $frames[FRAME];

if ($do != 'solution') {
    if ($_W['role'] == 'operator') {
        header('location: ' . url('home/welcome/solution'));
        exit;
    }

    $modules = uni_modules();
    $settings = uni_setting($_W['uniacid'], array('shortcuts'));
    $shorts = $settings['shortcuts'];
    if (!is_array($shorts)) {
        $shorts = array();
    }
}


if ($do == 'solution') {
    $solutions = array();
    $modules = uni_modules();
//    foreach ($modules as $modulename => $module) {
//        if (!is_error(module_solution_check($modulename))) {
//            if ($_W['role'] == 'operator') {
//                $sql = 'SELECT COUNT(*) FROM ' . tablename('solution_acl') . ' WHERE `uid`=:uid AND `module`=:module';
//                $pars = array();
//                $pars[':uid'] = $_W['uid'];
//                $pars[':module'] = $modulename;
//                if (pdo_fetchcolumn($sql, $pars) > 0) {
//                    $solutions[] = $module;
//                }
//            } else {
//                $solutions[] = $module;
//            }
//        }
//    }

    $m = $_GPC['m'];
    if (!empty($m)) {
        load()->model('module');
//        $error = module_solution_check($m);
//        if (is_error($error)) {
//            message($error['message']);
//        }
        $solution = module_fetch($m);
        $title = $solution['title'];
        $site = WeUtility::createModuleSite($m);
        if (!is_error($site)) {
            $method = 'doWebWelcome';
            $welcome = @$site->$method();
            exit;
        }
        if (empty($welcome)) {
            $entries = module_entries($m, array('menu', 'home', 'profile', 'shortcut', 'cover'));
            if ($_W['role'] == 'operator') {
                foreach ($entries as $index1 => &$entry1) {
                    if ($index1 == 'cover') {
                        continue;
                    }
                    foreach ($entry1 as $index2 => &$entry2) {
                        $url_arr = parse_url($entry2['url']);
                        $url_query = $url_arr['query'];
                        parse_str($url_query, $query_arr);
                        $eid = intval($query_arr['eid']);
                        $data = pdo_fetch('SELECT * FROM ' . tablename('modules_bindings') . ' WHERE eid = :eid', array(':eid' => $eid));
                        $ixes = pdo_fetchcolumn('SELECT id FROM ' . tablename('solution_acl') . ' WHERE uid = :uid AND module = :module AND do = :do AND state = :state', array('uid' => $_W['uid'], ':module' => $m, ':do' => $data['do'], 'state' => $data['state']));
                        if (empty($ixes)) {
                            unset($entry1[$index2]);
                        }
                    }
                }
            }
        }
    } else {
        if (empty($solutions)) {
            message('没有您可以使用的功能, 请联系系统管理员.');
        } else {
            header('location: ' . url('home/welcome/solution', array('m' => $solutions[0]['name'])));
        }
        exit;
    }
    define('IN_SOLUTION', true);
}

template('home/welcome');
