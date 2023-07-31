<?php

defined('IN_IA') or exit('Access Denied');


function url($segment, $params = array())
{
    return wurl($segment, $params);
}


function message($msg, $redirect = '', $type = '')
{
    global $_W;
    if ($redirect == 'refresh') {
        $redirect = $_W['script_name'] . '?' . $_SERVER['QUERY_STRING'];
    }
    if ($redirect == 'referer') {
        $redirect = referer();
    }
    if ($redirect == '') {
        $type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'info';
    } else {
        $type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'success';
    }
    if ($_W['isajax'] || !empty($_GET['isajax'])) {
        exit("<script type=\"text/javascript\">
					parent.require(['jquery', 'util'], function($, util){
						var url = " . (!empty($redirect) ? 'parent.location.href' : "''") . ";
						var modalobj = util.message('" . $msg . "', '', '" . $type . "');
						if (url) {
							modalobj.on('hide.bs.modal', function(){\$('.modal').each(function(){if(\$(this).attr('id') != 'modal-message') {\$(this).modal('hide');}});top.location.reload()});
						}
					});
			</script>");
    } elseif ($type == 'ajax') {
        $vars = array();
        $vars['message'] = $msg;
        $vars['redirect'] = $redirect;
        $vars['type'] = $type;
        exit(json_encode($vars));
    }
    if (empty($msg) && !empty($redirect)) {
        header('location: ' . $redirect);
    }
    $label = $type;
    if ($type == 'error') {
        $label = 'danger';
    }
    if ($type == 'ajax' || $type == 'sql') {
        $label = 'warning';
    }
    include template('common/message', TEMPLATE_INCLUDEPATH);
    exit();
}


function checklogin()
{
    global $_W;
    if (empty($_W['user'])) {
        message('抱歉，您无权进行该操作，请先登录！', url('user/login'), 'warning');
    }
    return true;
}


function checkaccount()
{
    global $_W;
    if (empty($_W['uniacid'])) {
        message('这项功能需要你选择特定楼盘项目才能使用！', url('account/display'), 'info');
    }
}

function buildframes($types = array('platform'), $modulename = '')
{
    global $_W;
    $ms = include IA_ROOT . '/web/common/frames.inc.php';
    $ms = array_elements($types, $ms);

    if (in_array('ext', $types)) {
        load()->model('module');
        $frames = array();
        $modules = uni_modules();
        if (!empty($modules)) {
            foreach ($modules as $m) {
                if (in_array($m['name'], array('basic', 'news', 'music', 'userapi'))) {
                    continue;
                }
                $frames[$m['type']][] = $m;
            }
        }
        $types = module_types();
        if (!empty($frames)) {
            foreach ($frames as $type => $fs) {
                $items = array();
                if (!empty($fs)) {
                    foreach ($fs as $m) {
                        $items[] = array(
                            'title' => $m['title'],
                            'url' => url('home/welcome/ext', array('m' => $m['name']))
                        );
                    }
                }
                $ms['ext'][] = array(
                    'title' => $types[$type]['title'],
                    'items' => $items
                );
            }
        }
    }
    if (in_array('solution', $types)) {
        load()->model('module');
        $module = module_fetch($modulename);
        $entries = module_entries($modulename, array('menu'));
        if ($_W['role'] == 'operator') {
            foreach ($entries as &$entry1) {
                foreach ($entry1 as $index2 => &$entry2) {
                    $url_arr = parse_url($entry2['url']);
                    $url_query = $url_arr['query'];
                    parse_str($url_query, $query_arr);
                    $eid = intval($query_arr['eid']);
                    $data = pdo_fetch('SELECT * FROM ' . tablename('modules_bindings') . ' WHERE eid = :eid', array(':eid' => $eid));
                    $ixes = pdo_fetchcolumn('SELECT id FROM ' . tablename('solution_acl') . ' WHERE uid = :uid AND module = :module AND do = :do AND state = :state', array('uid' => $_W['uid'], ':module' => $modulename, ':do' => $data['do'], 'state' => $data['state']));
                    if (empty($ixes)) {
                        unset($entry1[$index2]);
                    }
                }
            }
        }
        if ($entries['menu']) {
            $menus = array('title' => $module['title']);
            foreach ($entries['menu'] as $menu) {
                $menus['items'][] = array('title' => $menu['title'], 'url' => $menu['url']);
            }
            $ms['solution'][] = $menus;
        }
    }
    return $ms;
}
