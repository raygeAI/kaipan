<?php

#region 部分权限存储

/**
 * 获得用户在项目中的权限信息
 * @param $user
 * @param $project
 */
function biz_getUserProjectRight($userGUID='', $projGUID='')
{
    global $_W;
    if (empty($userGUID)) {
        $userGUID = isset($_W['user']['UserGUID'])?$_W['user']['UserGUID']:$_W['uid'];
    }
    if (empty($projGUID)) {
        $projGUID = $_W['project']['projguid'];
    }
    $callback=function()use($userGUID, $projGUID){
        return db_getUserProjectRight($userGUID, $projGUID);
    };
    $key=$projGUID.'_'.$userGUID;
    return cache_GetData($key,$callback,1800);
}

function db_getUserProjectRight($userGUID, $projGUID)
{
    $rights = array('DataCode' => '', 'Permission' => array(), 'SubStation' => array(), 'StationGUID' => '', 'Level' => -1);
    //先取用户的部门
    $stations = db_getUserProjectStations($userGUID, $projGUID);
    foreach ($stations as $s) {
        $level = substr_count( $s['HierarchyCode'],'.');
        if ($level > $rights['Level']) {
            $rights['Level'] = $level;
            $rights['StationCode'] = $s['StationCode'];
            $rights['StationGUID'] = $s['StationGUID'];
            $rights['HierarchyCode'] = $s['HierarchyCode'];
        }
        $modules = iunserializer(base64_decode($s['modules']));
        if (!empty($modules) && is_array($modules)) {
            $rights['Permission'] = array_merge($rights['Permission'], $modules);
        }
//        $sub=biz_getSubStations($s,'StationCode');
//        if(!empty($sub)&&is_array($sub)){
//            $rights['SubStation']=array_merge($rights['SubStation'],array_keys($sub));
//        }

    }
    return $rights;
}

/**
 * 获得用户在项目中的职位信息
 * @param $userGUID
 * @param $projGUID
 * @return bool
 */
function db_getUserProjectStations($userGUID, $projGUID)
{
    $sql = 'SELECT a.* from ims_mystation a ';
    $sql .= ' INNER JOIN ims_mystationuser b ON a.StationGUID = b.StationGUID';
    $sql .= ' where b.UserGUID=:userGUID and a.ProjGUID=:projGUID';
    return pdo_fetchall($sql, array(':userGUID' => $userGUID, ':projGUID' => $projGUID));
}

/**
 * 获得所属部门的下属部门
 * @param $user
 * @return array
 */
function biz_getSubStations($station, $keyfield = '')
{
    if (empty($station) || empty($station['HierarchyCode'])) {
        return false;
    }
    $code = $station['HierarchyCode'];
    $sql = "SELECT * FROM " . tablename('mystation') . " where HierarchyCode like :code ";
    return pdo_fetchall($sql, array(':code' => $code . '%'), $keyfield);
}


#endregion


#region 权限处理

function getModuleOperate($module = '')
{
    static $MODULE_OPERATES = array(
        'project' => array(
            'name' => '项目管理',
            'code' => 'chips',
            'operate' => array(
                'entry' => array(
                    'name' => 'entry',
                    'title' => '允许进入',
                    'icon' => 'manage',
                    'admin' => true
                ),
                'set' => array(
                    'name' => 'set',
                    'title' => '项目配置',
                    'icon' => 'manage',
                    'admin' => true
                ),
                'upload' => array(
                    'name' => 'upload',
                    'title' => '上传数据',
                    'icon' => 'upload',
                    'admin' => true
                )
            )
        ),
        'chips' => array(
            'name' => '认筹管理',
            'code' => 'chips',
            'operate' => array(
                'entry' => array(
                    'name' => 'entry',
                    'title' => '允许进入',
                    'icon' => 'manage',
                    'admin' => true
                ),
                'list' => array(
                    'name' => 'list',
                    'title' => '认筹单列表',
                    'icon' => 'add',
                    'admin' => true
                ),
                'add' => array(
                    'name' => 'add',
                    'title' => '增加认筹单',
                    'icon' => 'add',
                    'admin' => false
                ),
                'delete' => array(
                    'name' => 'delete',
                    'title' => '作废认筹单',
                    'icon' => 'remove'
                ),
                'edit' => array(
                    'name' => 'edit',
                    'title' => '修改认筹单',
                    'icon' => 'edit',
                    'admin' => false
                ),
                'disp' => array(
                    'name' => 'disp',
                    'title' => '查看所有认筹单',
                    'icon' => 'search',
                    'admin' => true
                ),
                'prepay' => array(
                    'name' => 'prepay',
                    'title' => '诚意金交款',
                    'icon' => 'search',
                    'admin' => false
                ),
                'confirm' => array(
                    'name' => 'confirm',
                    'title' => '无交款确认',
                    'icon' => 'search',
                    'admin' => false
                )
            )
        ),
        'printer' => array(
            'name' => '打印管理',
            'code' => 'printer',
            'operate' => array(
                'entry' => array(
                    'name' => 'entry',
                    'title' => '允许进入',
                    'icon' => 'manage',
                    'admin' => true
                ),
                'deltask' => array(
                    'name' => 'delete',
                    'title' => '删除他人打印任务',
                    'icon' => 'remove',
                     'admin' => true
                ),
                'addtemp' => array(
                    'name' => 'addtemp',
                    'title' => '增加模板',
                    'icon' => 'add',
                    'admin' => true
                ),
                'deltemp' => array(
                    'name' => 'deltemp',
                    'title' => '删除模板',
                    'icon' => 'remove', 
                    'admin' => true
                ),
                'tag' => array(
                    'name' => 'tag',
                    'title' => '模板标签配置',
                    'icon' => 'remove',
                    'admin' => true
                )
            )
        ),
        'queuing' => array(
            'name' => '开盘管理',
            'code' => 'queuing',
            'operate' => array(
                'entry' => array(
                    'name' => 'entry',
                    'title' => '允许进入',
                    'icon' => 'manage',
                    'admin' => true
                ),
                'order' => array(
                    'name' => 'order',
                    'title' => '认购交款',
                    'icon' => 'search',
                    'admin' => false
                ),
                'book' => array(
                    'name' => 'prepay',
                    'title' => '认购书列表',
                    'icon' => 'search',
                    'admin' => false
                ),
                'change' => array(
                    'name' => 'change',
                    'title' => '认购书换名',
                    'icon' => 'change',
                    'admin' => false
                ),
                'add' => array(
                    'name' => 'add',
                    'title' => '增加签到组',
                    'icon' => 'add',
                    'admin' => true
                ),
                'delete' => array(
                    'name' => 'delete',
                    'title' => '删除签到组',
                    'icon' => 'remove',
                    'admin' => true
                ),
                'preset' => array(
                    'name' => 'edit',
                    'title' => '预设',
                    'icon' => 'edit',
                    'admin' => true
                ),
                'call' => array(
                    'name' => 'edit',
                    'title' => '叫号',
                    'icon' => 'edit',
                    'admin' => true
                )
            )
        ),
        'app' => array(
            'name' => 'APP应用',
            'code' => 'app',
            'operate' => array(
                '1' => array(
                    'name' => '1',
                    'title' => '客户签到',
                    'icon' => 'add',
                    'admin' => false
                ),
                '2' => array(
                    'name' => '2',
                    'title' => '客户选房',
                    'icon' => 'remove'
                ),
                '3' => array(
                    'name' => '3',
                    'title' => '报表',
                    'icon' => 'edit',
                    'admin' => false
                ),
                '4' => array(
                    'name' => '4',
                    'title' => '内控图表',
                    'icon' => 'edit',
                    'admin' => false
                ),               
                '5' => array(
                    'name' => '5',
                    'title' => '中签',
                    'icon' => 'edit',
                    'admin' => false
                )
            )
        )
    );
    if (!empty($module)) {
        return $MODULE_OPERATES[$module]['operate'];
    } else {
        return $MODULE_OPERATES;
    }
}

function getModules()
{
    $_MODULES = array(
        'project' => array('name' => 'project',
            'title' => '楼盘管理',
            'url' => url('project/module', array('m' => 'project')),
            'icon' => 'fa-building',
            'menu' => array(
                'manage' => array('title' => '开盘管理', 'url' => url('project/module/manage',array('m'=>'project'))),
                'list' => array('title' => '售房管理', 'url' => url('project/module/list',array('m'=>'project'))),
            ),
            'default' => 'welcome'
        ),

        'chips' => array('name' => 'chips',
            'title' => '认筹管理',
            'url' => url('project/module', array('m' => 'chips')),
            'icon' => 'fa-clock-o',
            'menu' => array(
                'list' => array('title' => '认筹单列表', 'url' => url('project/module/list',array('m'=>'chips'))),
                'prepay' => array('title' => '诚意金交款', 'url' => url('project/module/prepay',array('m'=>'chips'))),
            ),
            'default' => 'welcome'
        ),
        'queuing' => array('name' => 'queuing',
            'title' => '开盘管理',
            'url' => url('project/module', array('m' => 'queuing')),
            'icon' => 'fa-sign-in',
            'menu' => array(
                'sale' => array('title' => '认购交款', 'url' => url('project/module/sale',array('m'=>'chips'))),
                'salebook' => array('title' => '认购书列表', 'url' =>url('project/module/salebook',array('m'=>'chips'))),
                'sign' => array('title' => '签到分配',  'url' =>url('project/module/sign',array('m'=>'queuing'))),
                'call' => array('title' => '选房叫号','url' =>url('project/module/call',array('m'=>'queuing'))),
                'screen' => array('title' => '叫号大屏幕', 'url' =>url('project/module/screen',array('m'=>'queuing'))),
            ),
            'default' => 'sign'
        ),
        'printer' => array('name' => 'printer',
            'title' => '打印管理',
            'url' => url('project/module', array('m' => 'printer')),
            'icon' => 'fa-print',
            'menu' => array(
                'task' => array('title' => '打印任务列表', 'url' =>url('project/module/task',array('m'=>'printer'))),
                'printer' => array('title' => '打印机列表', 'url' =>url('project/module/printer',array('m'=>'printer'))),
                'template' => array('title' => '打印模板管理', 'url' =>url('project/module/template',array('m'=>'printer'))),
            ),
            'default' => 'printer'
        )

    );
    return $_MODULES;
}


function biz_getStationRight($StationGUID, $projGUID)
{
    $modules = getModuleOperate();

    $rights = db_getStationRight($StationGUID, $projGUID);
    if (!empty($rights)) {
        foreach ($modules as $k => &$m) {
            $r = $rights[$k];
            if (!empty($r)) {
                foreach ($m['operate'] as &$o) {
                    $o['enable'] = in_array($o['name'], $r);
                }
            }
        }
        unset($m);
    }
    return $modules;
}

function db_getStationRight($StationGUID, $projGUID = null)
{
    $sql = 'select * from ' . tablename('mystation') . ' where StationGUID=:StationGUID ';
    $params = array(':StationGUID' => $StationGUID);
    if (!empty($projGUID)) {
        $sql .= ' and ProjGUID=:ProjGUID';
        $params[':ProjGUID'] = $projGUID;
    }
    $station = pdo_fetch($sql, $params);
    $rights = array();
    if (!empty($station['modules'])) {
        $rights = iunserializer(base64_decode($station['modules']));
    }
    return $rights;
}

function biz_updateStationRight($rights, $StationGUID, $projGUID)
{
    $update = array(
        'modules' => base64_encode(iserializer($rights))
    );
    return pdo_update('mystation', $update, array('StationGUID' => $StationGUID, 'ProjGUID' => $projGUID));

}


function checkRecodeRight($recode, $user, $module, $op, $tip = true)
{
    $operates = getUserOperate($user, $module, $recode);
    $enable = in_array($op, $operates);
    if (!$enable && $tip) {
        message('没有相应权限，数据禁止访问！');
    }
    return $enable;
}

function checkModuleRight($module, $op, $tip = true, $user = null)
{
    global $_W;
    if (empty($user)) {
        $user = $_W['user'];
    }

    $enable = false;
    $rights = $_W['rights']['Permission'];
    if ($_W['isfounder']) {
        $operates = getModuleOperate($module);
        $operate = $operates[$op];
        if (!empty($operate)) {
            $enable = !empty($operate['admin']);
        }
    } else {
        //检查用户操作权限permission
        $operates = $rights[$module];
        $enable = in_array($op, $operates);
    }
    if (!$enable && $tip) {
        message('没有模块相应权限，禁止访问！');
    }
    return $enable;
}

function getUserOperate($user, $module, $recode = null)
{
    $operates = getOperate(true);
    $enable = array();
    foreach ($operates as $op) {
        if (checkModuleRight($user, $module, $op, false)) {
            $enable[] = $op;
        }
    }
    //处理数据可用权限
    if (is_array($recode)) {
        $operates = getRecodeOperates($recode, $user);
        //处理删除：_delete表示数据可以删除，需有用户删除权限
        if (in_array('_delete', $operates) && (in_array('delete', $enable))) {
            if (!in_array('delete', $operates)) {
                $operates[] = 'delete';
            }
        }
        $enable = array_intersect($enable, $operates);

    }
    return $enable;
}


function getRecodeOperates($recode, $user)
{
    $op = array();

    return $op;

}

#endregion