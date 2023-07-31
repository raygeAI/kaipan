<?php
/**
 *定义系统业务接口功能及常用数据接口
 *
 */


/**
 *定义ERP接口是否可用
 */
define('ERP_ENABLE', defined('ERP'));

load()->func('cache.memcache');

#region 公共函数
/**
 * 生成guid
 * @return string
 */
function GUID()
{
    if (function_exists('com_create_guid')) {
        return com_create_guid();
    } else {
        mt_srand((double)microtime() * 10000); //optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45); // "-" chr(123)"{"chr(125);// "}"
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }
}

function object_to_array($obj)
{
    $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
    foreach ($_arr as $key => $val) {
        $val = (is_array($val)) || is_object($val) ? object_to_array($val) : $val;
        $arr[$key] = $val;
    }
    return $arr;

}

function getInputGUID($input)
{
    if (empty($input)) {
        return false;
    }
    $input = strtoupper($input);
    preg_match('/[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}/', $input, $m);
    if (count($m) > 0) {
        return $m[0];
    } else {
        return false;
    }
}

function logging_implode($array)
{
    $return = '';
    if (is_array($array) && !empty($array)) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return .= $key . "={" . logging_implode($value) . "}; ";
            } else {
                $return .= "$key=$value; ";
            }
        }
    }
    return $return;
}

function logging($level = 'info', $message = '')
{
    $filename = IA_ROOT . '/data/logs/' . date('Ymd') . '.log';
    load()->func('file');
    mkdirs(dirname($filename));
    $content = date('Y-m-d H:i:s') . " {$level} :\n------------\n";
    if (is_string($message)) {
        $content .= "String:\n{$message}\n";
    }
    if (is_array($message)) {
        $content .= logging_implode($message);

    }
    if ($message == 'get') {
        $content .= "GET:\n";
        foreach ($_GET as $key => $value) {
            $content .= sprintf("%s : %s ;\n", $key, $value);
        }
    }
    if ($message == 'post') {
        $content .= "POST:\n";
        foreach ($_POST as $key => $value) {
            $content .= sprintf("%s : %s ;\n", $key, $value);
        }
    }
    $content .= "\n";

    $fp = fopen($filename, 'a+');
    fwrite($fp, $content);
    fclose($fp);
}

function checkIsGUID($guid)
{
    return preg_match(REGULAR_GUID, $guid);
}

/**
 * json 输出，支付pjson
 * @param $data
 */
function returnJson($data)
{
    header("Access-Control-Allow-Origin:*");
    header('content-type: application/json; charset=utf-8');
    $json = json_encode($data);
    die(isset($_GET['callback']) ? "{$_GET['callback']}({$json})" : $json);
}

function disableWebCache()
{
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pramga: no-cache");
}

/**
 * 同步数据表
 * @param $sync
 * @param bool $clear
 * @return array
 */
function sync_table($sync, $clear = false)
{
    $table = $sync['table'];
    $rows = $sync['rows'];
    $key = $sync['key'];
    $res = array('table' => $table, 'del' => 0, 'update' => 0, 'insert' => 0, 'total' => count($rows));
    //检查是否指定了clear,指定了，以其为参数，清除数据
    if ($clear && isset($sync['clear']) && (is_array($sync['clear']))) {
        $res['del'] = pdo_delete($table, $sync['clear']);
    }
    //检查是否清除子表，以当前key
    $clearChild = false;
    if ($clear&&isset($sync['clearchild']) && (count($sync['clearchild']) > 0)) {
        $table_child = $sync['clearchild'];
        $clearChild = true;
    }
    foreach ($rows as $item) {
        if ($clearChild) {
            foreach ($table_child as $t) {
                pdo_delete($t, array($key => $item[$key]));
            }
        }
        if ($clear) {
            $res['del'] += pdo_delete($table, array($key => $item[$key]));
            if (pdo_insert($table, $item)) {
                $res['insert'] += 1;
            }
        } else {

            $sql = 'select * from ' . tablename($table) . " where {$key}=:key";
            $row = pdo_fetch($sql, array(":key" => $item[$key]));
            if (empty($row)) {
                if (pdo_insert($table, $item)) {
                    $res['insert'] += 1;
                }
            } else {
                if (pdo_update($table, $item, array("{$key}" => $item[$key]))) {
                    $res['update'] += 1;
                }
            }
        }
    }
    return $res;
}

function cache_GetData($key, $callback, $expiration = 3600)
{
    $data = cache()->get($key);
    if (empty($data)) {
        $data = $callback();
        if (memcached_addKey('Add_' . $key)) {
            memcached_set($key, $data, $expiration);
            memcached_delete('Add_' . $key);
        }
    }
    return $data;
}

function cache_UpdateData($key, $callback, $expiration = 3600)
{
    $result = false;
    if (memcached_addKey('Update_' . $key)) {
        $data = cache()->get($key);
        if ($callback($data)) {
            $result = memcached_set($key, $data, $expiration);
        }
        memcached_delete('Update_' . $key);
    }
    return $result;
}

#endregion

#region 用户登录


/**
 * 用户登录处理
 * @param $usercode 用户代码
 * @param $password 密码
 * @return bool
 */
function biz_login($usercode, $password)
{
    $sql = 'SELECT UserGUID,UserCode,UserName,Password,BUGUID,ParentGUID  FROM ' . tablename('myuser');
    $sql .= " where UserCode=:usercode";
    $user = pdo_fetch($sql, array(':usercode' => $usercode));
    $result = false;
    if (!empty($user) && (strtolower($user['Password']) == md5($password))) {
        //$result = strtolower($user['Password']) == md5($password);
        $user['Token'] = md5($user['UserGUID'] + date('-md'));
        memcached_set('u_' . $user['Token'], $user, 86400);
        $result = $user;
    }
    return $result;
}


/**
 * 通过token获取用户信息
 * @param $token
 * @return array|bool|mixed|string
 */
function biz_getUser($token)
{
    if (empty($token)) {
        return false;
    } else {
        $user = memcached_get('u_' . $token);
        return $user;
    }
}


/**
 * 获得岗位员工数据
 * @param $projGUID
 * @param $onlySale 只获取销售人员
 * @return array
 */
function biz_getStationUser($projGUID, $onlySale = false, $keyfield = 'UserGUID')
{
    $sql = "select a.StationGUID,a.StationName,c.UserGUID,c.UserName from ims_mystation a,ims_mystationuser b,ims_myuser c ";
    $sql .= "where b.UserGUID=c.UserGUID and a.StationGUID=b.StationGUID  and a.ProjGUID=:ProjGUID";
    if ($onlySale) {
        $sql .= ' and c.IsSaler=1';
    }
    return pdo()->fetchall($sql, array(':ProjGUID' => $projGUID), $keyfield);
}

/**
 * 获取项目的所有部门
 * @param $projGUID
 */
function db_getAllStation($projGUID, $keyfield)
{
    $sql = "select * from ims_mystation  ";
    $sql .= " where  ProjGUID=:ProjGUID order by HierarchyCode";
    return pdo()->fetchall($sql, array(':ProjGUID' => $projGUID), $keyfield);
}


/**
 * 获得部门下的所有用户信息
 * @param $StationGUID
 * @return array
 */
function db_getUserOfStation($StationGUID)
{
    $sql = "SELECT b.* FROM ims_mystationuser a INNER JOIN ims_myuser b ON a.UserGUID = b.UserGUID  ";
    $sql .= " where  a.StationGUID=:StationGUID";
    return pdo()->fetchall($sql, array(':StationGUID' => $StationGUID));
}


function db_getStation($ids)
{
    $sql = "SELECT * FROM " . tablename('mystation');
    $params = array();
    if (is_array($ids)) {
        $sql .= " where StationGUID in ('" . explode("','", $ids) . "')";
        return pdo_fetchall($sql);
    } else {
        $sql .= " where StationGUID=:StationGUID ";
        $params[':StationGUID'] = $ids;

    }

}


#endregion

#region 认筹处理

/**
 * 从erp中的客户数据导入到系统数据表
 * 标记来源及状态
 * @param $data
 * @return array
 */
function import_Customer($data)
{
    //erp原始数据导入，并转化成本地数据返回
    $keys = pdo_fetchallfields(tablename('p_customer'));
    unset($keys['status']);
    unset($keys['erp']);
//    $keys = array('CstGUID', 'CstName', 'CardType', 'CardID', 'Gender', 'MobileTel', 'HomeTel', 'OfficeTel',
//        'Address', 'PostCode', 'CstType','KhFl', 'HKCountry', 'HKProvince', 'HKRegional', 'CreatedOn', 'CreatedBy');
    $info = array_elements($keys, $data);
    $info['status'] = '0';
    $info['erp'] = '1';
    $state = pdo_insert('p_customer', $info);
    return $info;
}


/**
 * 获得客户信息
 * @param $query
 * @return bool
 */
function biz_getCustomerInfo($query)
{
    $sql = 'select * from ' . tablename('p_customer');
    $param = array();
    if (is_array($query)) {
        $where = '';
        if (isset($query['CstGUID'])) {
            $where .= " and CstGUID=:CstGUID";
            $param[':CstGUID'] = $query['CstGUID'];
        }
        if (isset($query['CstName'])) {
            $where .= " and CstName like '%" . $query['CstName'] . "%'";
        }
        if (isset($query['CardID'])) {
            $where .= " and CardID=:CardID";
            $param[':CardID'] = $query['CardID'];
        }
        $sql .= " where 1=1 {$where}";
    } else {
        $sql .= " where CardID:=CardID";
        $param[':CardID'] = $query;
    }
    return pdo_fetch($sql, $param);
}

function db_getCustomer($CstGUID)
{
    $sql = 'select * from ' . tablename('p_customer');
//    $sql.=" where CstGUID='{$CstGUID}'";
//    $data = pdo_fetch($sql);
    //bug?:使用查询参数，查询数据为空
    $sql .= ' where CstGUID=:CstGUID';
    $data = pdo_fetch($sql, array(':CstGUID' => $CstGUID));
    return $data;
}

/**
 * 通过证件号码查询客户信息
 *  如不存在则查询erp数据，查询数据导入到表中，并返回
 * @param $CardID
 * @return array|bool
 */
function biz_getCustomerByCardId($CardID, $projGUID)
{
    $info = biz_getCustomerInfo(array('CardID' => $CardID));
//    if (empty($info) && ERP_ENABLE) {
//        $data = erp_getCustomerInfo($CardID, $projGUID);
//        if (!empty($data)) {
//            $info = import_Customer($data);
//        }
//    }
    return $info;
}

/**
 * 保存用户信息
 *  无id增加，有id更新
 * @param $user
 * @return mixed
 */
function biz_saveCustomer(&$user, $project)
{
    //如果有CstGUID，status为0，为erp导入数据，修改后应使用新的GUID？
    $state = false;
    if (empty($user['CstGUID'])) {
        $user['CstGUID'] = GUID();
        $user['status'] = 1;
        biz_insertCstAttach($user['CstGUID'], $project);
        $state = pdo_insert('p_customer', $user);
    } else {
        $params = array('CstGUID' => $user['CstGUID']);
        $state = pdo_update('p_customer', $user, $params);
    }
    return $state;
}

function biz_getAllCustomerField($guids, $field = 'CardID')
{
    $list = explode(',', $guids);
    $result = array();
    foreach ($list as $id) {
        $c = biz_getCustomerInfo(array('CstGUID' => $id));
        if (!empty($c)) {
            $result[] = $c[$field];
        }
    }
    return implode(',', $result);
}

/**
 * 插入客户项目关联表
 * @param $CstGUID
 * @param $project
 * @return bool
 */
function biz_insertCstAttach($CstGUID, $project)
{
    global $_W;
    $item = db_getCstAttach($project['projguid'], $CstGUID);
    if (empty($item)) {
        $data = array(
            'CstGUID' => $CstGUID,
            'ProjGUID' => $project['projguid'],
            'BUGUID' => $project['BUGUID'],
            'USERGUID' => $_W['uid'],
            'CstAttachGUID' => GUID(),
        );
        $state = pdo_insert('p_cstattach', $data);
        return $state;
    } else {
        return false;
    }
}


function biz_getChipsCustomerInfo($chips, $hasCard = false)
{
    $res = array('guid' => $chips['cid'], 'name' => $chips['cname'], 'card' => $chips['cardid']);
    if (!empty($chips['holderguid'])) {
        $res['guid'] .= "," . $chips['holderguid'];
        $res['name'] .= "," . $chips['holdername'];
        if ($hasCard) {
            $res['card'] .= "," . biz_getAllCustomerField($chips['holderguid'], 'CardID');
        }
    }
    return $res;
}

/**
 * 保存认筹信息，分开保存用户信息并新更
 * @param $data
 * @return array
 */
function biz_saveChips($data)
{
    global $_W;
    $result = array('result' => false, 'msg' => '');
    if (isset($data['user'])) {
        $user = $data['user'];
        unset($data['user']);
        biz_saveCustomer($user, $_W['project']);
        $data['cid'] = $user['CstGUID'];
        $data['cname'] = $user['CstName'];
        $data['grender'] = $user['Gender'];
        $data['mobile'] = $user['MobileTel'];
        $data['cardid'] = $user['CardID'];
    }
    $state = false;
    if (empty($data['id'])) {
        $data['createid'] = $_W['uid'];
        $data['creator'] = $_W['username'];
        //记录操作用户部门ID？
        $data['StationCode'] = $_W['rights']['HierarchyCode'];
        $data['changetime'] = TIMESTAMP;
        $data['createtime'] = TIMESTAMP;
        $state = pdo_insert('chips', $data);

    } else {
        $data['changetime'] = TIMESTAMP;
        $params = array('id' => $data['id']);
        unset($data['id']);
        $state = pdo_update('chips', $data, $params);
    }

    $result['result'] = $state > 0;
    return $result;
}

//保存权益人信息
function biz_saveHolder($chips, $user)
{
    global $_W;
    $result = array('result' => false, 'msg' => '');

    $state = false;
    $holdguid = $user['CstGUID'];
    $holdnames = empty($chips['holdername']) ? array() : explode(';', $chips['holdername']);
    $holdguids = empty($chips['holderguid']) ? array() : explode(';', $chips['holderguid']);
    // 首次附属权益人
    if (($chips['cid'] != $holdguid) && !in_array($holdguid, $holdguids)) {
        array_push($holdguids, $holdguid);
        array_push($holdnames, $user['CstName']);
        $state = pdo_update('chips',
            array('holdername' => implode(';', $holdnames), 'holderguid' => implode(';', $holdguids)),
            array('id' => $chips['id']));

    } else {
        $result['msg'] = '当前客户已经是权益人，不需添加！';
    }
    $result['result'] = $state > 0;
    return $result;

}

/**
 * 保存权益人的名字，冗余字段
 * @param $qrcode 认筹单二维码
 * @认筹单的holdguid + Cstid，更新holdname
 */
function biz_saveHolderName($chips)
{
    global $_W;
    $save = false;
    $holders = explode(';', $chips['holderguid']);
    $name = array();
    foreach ($holders as $h) {
        if (!empty($h)) {
            $sql = "SELECT CstName from " . tablename('p_customer') . " where CstGUID =:cstguid";
            $name[] = pdo_fetchcolumn($sql, array(':cstguid' => $h));
        }
    }
    if (!empty($chips['id'])) {
        $holders = '';
        if (count($name) > 0) {
            $holders = implode(';', $name);
        }
        $save = pdo_update('chips', array('holdername' => $holders), array('id' => $chips['id']));
    }
    return $save;
}

/**
 * @param $id
 * @param bool $showCustomer
 * @return array|bool
 */
function biz_getChips($id, $showCustomer = true)
{
    $sql = 'select * from ' . tablename('chips') . ' where id=:id';
    $data = pdo_fetch($sql, array(':id' => $id));
    if (is_array($data)) {
        $data['user'] = db_getCustomer($data['cid']);
    }
    return $data;
}


/**
 * @param $code
 * @param bool $showCustomer
 * @return bool
 */
function biz_getChipsByQrcode($code, $fields = '*')
{
    $code = trim($code);
    //select * from `ims_chips` where qrcode=:qrcode
    $sql = "select {$fields} from " . tablename('chips') . ' where `qrcode`=:qrcode';
    $data = pdo_fetch($sql, array(':qrcode' => $code));
    return $data;
}


/**
 * 获取指定类型的数据字典
 * 开盘相关的数据字典都在此定义
 * @param $type
 * @param $param
 * @return array
 */
function biz_getDictionary($type, $param = null)
{
    static $_DICT = array();
    $type = strtolower($type);

    if (isset($_DICT[$type])) {
        $data = $_DICT[$type];
    } else {
        switch ($type) {
            case 'cardtype' :
                $data = explode('/', '身份证/军官证/护照/港澳身份证/学生证/工作证/台胞证/港澳通行证/营业执照');
                break;
            case 'kehutype' :
                $data = explode('/', '普通客户/内部员工/业主介绍');
                break;
            case 'chipstatus' :
                $data = array(1 => '登记', 2 => '付款', 3 => '无交款', 4 => '签到', 5 => '中签', 6 => '选房', 7 => '补定', 8 => '确认');
                break;
            case 'projstatus':
                $data = array(0 => '导入', 1 => '在线认筹', 2 => '在线开盘', 3 => '离线开盘', 4 => '开盘结束', 9 => '关闭项目');
                break;
            case 'printtype':
                $data = array(1 => '认筹书', 2 => '诚意金交款', 3 => '定金交款', 4 => '认购书');
                break;
            default:
                $data = array();
                break;
        }
        if (empty($data)) {
            $_DICT[$type] = $data;
        }
    }
    if ($param) {
        $data = array_flip($data);
    }
    return $data;
}

#endregion


#region 楼盘项目


function biz_unserializer(&$object, $attribute)
{
    if (is_array($object)) {
        if (!empty($object[$attribute]) && !is_array($object[$attribute])) {
            $object[$attribute] = iunserializer($object[$attribute]);
        }
    }
    return $object[$attribute];
}

/**
 * 解压项目压缩属性
 * @param $project
 */
function projectUnProperty(&$project)
{
    biz_unserializer($project, 'product');
    biz_unserializer($project, 'finance');
    biz_unserializer($project, 'housetype');
    biz_unserializer($project, 'signset');
}


function biz_getProjectSet($set)
{
    global $_W;
    $data = array();
    if (in_array($set, array('finance', 'signset', 'syncset'))) {
        if (is_array($_W['project'][$set])) {
            $data = $_W['project'][$set];
        } else {
            $data = iunserializer($_W['project'][$set]);
        }
    }
    return $data;
}

/**
 * 通过id获得楼盘数据
 *  查询结果反序列化产品及房型
 * @param $id
 * @param $isGuid  id是否为GUID
 * @param $unzip  是否解压属性
 */
function biz_getProject($id, $isGuid = true, $unzip = false)
{
    $key = 'P_' . $id;
    $info = cache()->get($key);
    if (empty($info)) {
        if (memcached_addKey('Add_' . $key)) {
            $info = db_getProject($id, $isGuid, $unzip);
            memcached_set($key, $info);
            memcached_delete('Add_' . $key);
        }
    }
    return $info;
}

function biz_mem_clearProject($project = '')
{
    if (empty($project)) {
        global $_W;
        $project = $_W['project'];
    }
    memcached_delete('P_' . $project['id']);
    memcached_delete('P_' . $project['projguid']);
}

/**
 * 删除项目信息
 * @param $projGUID
 */
function biz_deleteProject($project)
{
    if (!empty($project)) {
        pdo_delete('project', array('id' => $project['id']));
        biz_mem_clearProject($project);
    }
}


/**
 * 获取用户有权的楼盘
 * @param $user 用户数据
 * @return array
 */
function biz_getProjects($user)
{
    $sql = 'select projguid,projname from ' . tablename('project');
    $projects = biz_getUserProject($user);
    if (is_array($projects)) {
        $sql .= ' where projguid in (\'' . implode("','", $projects) . '\')';
    }
    return pdo_fetchall($sql);
}

/**
 * 获取用户有权的楼盘GUID列表
 * @param $user
 * @return array 楼盘GUID列表
 */
function biz_getUserProject($user)
{
    $projects = false;
    if (!empty($user['UserGUID'])) {
        $sql = 'SELECT c.ProjGUID FROM ims_myuser a  ';
        $sql .= ' INNER JOIN ims_mystationuser b ON a.UserGUID = b.UserGUID  ';
        $sql .= ' INNER JOIN ims_mystation c ON b.StationGUID = c.StationGUID ';
        $sql .= ' WHERE a.UserGUID = :userGUID ';
        $params[':userGUID'] = $user['UserGUID'];
        $data = pdo_fetchall($sql, $params, 'ProjGUID');
        $projects = array_keys($data);
    }
    return $projects;
}


/**
 * 计算房间价格
 * @param $room
 * @param $calc
 */
function biz_calcRoomPrice(&$room, $calc, $update = false)
{
    if ($room['Total'] <= 0) {
        return;
    }
    $room['RoomTotal'] = round(($room['Total'] * $calc['pay_discount'] - $calc['dec_money']) * $calc['discount']);
    $room['BldCjPrice'] = round($room['RoomTotal'] / $room['BldArea']);
    $room['TnCjPrice'] = round($room['RoomTotal'] / $room['TnArea']);
    $room['XyTotal'] = $room['RoomTotal'];// *rate 1
    $room['DiscntValue'] = round(100.00 * $room['XyTotal'] / $room['Total'], 2);
    if ($update) {
        $data = array(
            'RoomTotal' => $room['RoomTotal'],
            'BldCjPrice' => $room['BldCjPrice'],
            'TnCjPrice' => $room['TnCjPrice'],
            'XyTotal' => $room['XyTotal'],
            'DiscntValue' => $room['DiscntValue']);
        pdo_update('p_room', $data, array('RoomGUID' => $room['RoomGUID']));
    }
}

/**
 * 获得房价计算方式
 * @param $set
 * @param $discount
 * @param $payform
 * @return array
 */
function biz_getRoomPriceCalc($set, $discount, $payform)
{
    $calc = array('pay_discount' => 1.00, 'dec_money' => 0, 'discount' => 1.00,'remark'=>"以标准总价为基础计算：");
    
    if (isset($payform[$set['pay_id']])) {
        $pay=$payform[$set['pay_id']];
        $calc['pay_discount'] = $pay['DisCount'] / 100.00;
        $calc['remark'].=$pay['PayformName']. "{$pay['DisCount']}%";
    }
    foreach ($set['dis_id'] as $d) {
        if (isset($discount[$d])) {
            $disc=$discount[$d];
            if ($discount[$d]['DiscntValue'] > 0) {
                $calc['discount'] *= $disc['DiscntValue'] / 100.0;
                $calc['remark'].=" × {$disc['DiscntName']}{$disc['DiscntValue']}%";
            }
            if ($discount[$d]['PreferentialPrice'] > 0) {
                $calc['dec_money'] += $discount[$d]['PreferentialPrice'];
                $calc['remark'].=" - {$calc['dec_money']}";
            }
        }
    }
    //todo:不同的类型 要加（），以标准总价为基准计算: (商业按揭 100.00% × 诚意优惠 84.00% - 诚意登记优惠 50000.00) × 特批优惠 95.00%
    //$calc['discount'] =round($calc['discount'],4);
    return $calc;
}

#endregion

#region 票据


/**
 * @param $qrcode
 * @param $billtype
 * 得到票据信息
 */
function biz_getBills($qrcode, $billtype = 1, $printed = false)
{
    $sql = "select * from " . tablename('bill');
    $sql .= " where Qrcode =:Qrcode ";
    $params = array(':Qrcode' => $qrcode);
    if (isset($billtype)) {
        $sql .= ' and BillType=:billtype';
        $params[':billtype'] = $billtype == 1 ? 1 : 2;
    }
    if (isset($printed)) {
        $sql .= ' and Printed=:printed';
        $params[':printed'] = $printed == 1 ? 1 : 0;
    }
    $sql .= ' order by Printed desc,createtime ';
    $bills = pdo_fetchall($sql, $params);
    foreach ($bills as &$b) {
        if (!empty($b['Details'])) {
            $b['finance'] = iunserializer($b['Details']);
        }
    }
    unset($b);

    return $bills;
}


function biz_getBill($qrcode, $billtype, $printed = 1)
{
    $sql = "select * from " . tablename('bill');
    $sql .= " where Qrcode =:Qrcode and BillType=:billtype ";
    $params = array(':Qrcode' => $qrcode, ':billtype' => $billtype);
    if (isset($printed)) {
        $sql .= ' and Printed=:printed';
        $params[':printed'] = $printed == 1 ? 1 : 0;
    }
    $sql .= ' order by createtime desc limit 1';
    $bill = pdo_fetch($sql, $params);
    if (!empty($bill['Details'])) {
        $bill['finance'] = iunserializer($bill['Details']);
    }
    return $bill;
}

function db_getBillByGUID($billGUID)
{
    $sql = "select * from " . tablename('bill');
    $sql .= ' where BillGUID=:BillGUID';
    return pdo_fetch($sql, array(':BillGUID' => $billGUID));
}

#endregion

#region 打印处理


function biz_getPrintModule($keyField = 'id')
{
    $sql = 'select * from ' . tablename('printmodule');
    return pdo_fetchall($sql, array(), $keyField);
}

/**
 * 获得打印任务列表
 * @param  $lasttime 最后创建时间
 * @return array
 */
function db_getPrintTaskOfProj($projGUID)
{
    $sql = 'select * from ' . tablename('printtask');
    $params = array();
    if (!empty($lasttime)) {
        $sql .= ' where projguid=:projguid';
        $params[':projguid'] = $projGUID;
    }
    $sql .= ' order by createtime desc';
    return pdo_fetchall($sql, $params);
}

function db_getPrintTask($id)
{
    $sql = 'select * from ' . tablename('printtask') . ' where id=:id';
    return pdo_fetch($sql, array(':id' => $id));
}

function biz_getPrintTaskByProject($guid)
{
    $sql = 'select * from ' . tablename('printtask') . ' where projguid=:projguid';
    return pdo_fetch($sql, array(':projguid' => $guid));
}

function db_getPrinter($id)
{
    $sql = 'select * from ' . tablename('printer') . ' where id=:id';
    return pdo_fetch($sql, array(':id' => $id));
}


/**
 * 获得打印模板
 * @param $id
 * @param $unzip
 * @return bool
 */
function db_getPrintTemplate($id, $unzip = false)
{
    $sql = 'select * from ' . tablename('printtemplate');
    $sql .= ' where id=:id ';
    $template = pdo_fetch($sql, array(':id' => $id));
    if (!empty($template) && $unzip) {
        if (!empty($template['tags']) && !is_array($template['tags'])) {
            $template['tags'] = iunserializer($template['tags']);
        }
        if (!empty($template['datamap']) && !is_array($template['datamap'])) {
            $template['datamap'] = iunserializer($template['datamap']);
        }
    }
    return $template;
}

/**
 * 获取项目可用的打印模板
 * @param $projGUID
 * @param string $select
 */
function biz_getPrintTemplates($projGUID, $type, $keyfield = '', $select = '')
{
    if ($select == '') {
        $select = 'id,title,printtype,status';
    }
    $sql = "select {$select} from " . tablename('printtemplate');
    $sql .= " where printtype=:type and  ( project='' or project=:projGUID )";
    return pdo_fetchall($sql, array(':type' => $type, ':projGUID' => $projGUID), $keyfield);
}


/**
 * 获得指定key没有打印完成的任务
 * @param $projguid
 * @param $key
 * @param $type
 * @return array
 */
function db_getNoPrintTask($projguid, $key, $type)
{
    $sql = 'select * from ' . tablename('printtask');
    $sql .= ' where `key`=:key and `projguid`=:projguid and `printtype`=:type and `complate`=0';
    return pdo_fetch($sql, array(':key' => $key, ':projguid' => $projguid, ':type' => $type));
}

/**
 * 获得项目所有可用打印机
 * @param $id
 */
function biz_getAllPrinter($id)
{
    $sql = 'select * from ' . tablename('printer');//' where id=:id';
    return pdo_fetchall($sql, array(), 'id');
}


function db_getPrintModule($name, $key)
{
    $sql = 'select * from ' . tablename('printmodule');
    $sql .= ' where `computer`=:computer and `key`=:key';
    return pdo_fetch($sql, array(':computer' => $name, ':key' => $key));
}

#endregion

#region 楼盘房间处理


function biz_getRoomName($room, $build)
{
    $name = $build['BldName'];
    $unit = trim(trim($room['Unit']));
    $unit = str_replace('  ', '', $unit);
    if (!empty($unit)) {
        $name .= '-' . $room['Unit'];
    }
    $name .= '-' . $room['Room'];
    return $name;
}


/**
 * @param $BldGUID
 * @return array
 */
function biz_getRoomStatus($BldGUID)
{
    $sql = 'select RoomGUID,BldGUID,RoomCode,Status,ChooseRoom,ChooseRoomCstName from ' . tablename('p_room');
    $sql .= ' WHERE  BldGUID=:BldGUID';
    return pdo_fetchall($sql, array(':BldGUID' => $BldGUID), 'RoomGUID');
}


/**
 * 获得楼栋的房间信息
 * @param $BldGUID
 * @return array
 */
function biz_getRoomInfo($BldGUID, $useKey = false)
{
    $sql = 'select BldGUID,RoomGUID,RoomCode,Status,BProductTypeCode,Floor,Unit,No,Room,HuXing,BldArea,TnArea';
    $sql .= ',ShowCode,`Price`,`TnPrice`,`Total`,`CstName`,`ChooseRoom`,`ChooseRoomCstName`,`SelectTime` ';
    $sql .= ',`RoomTotal`,`DiscntValue`,`BldCjPrice`,`TnCjPrice`,`XyTotal` ';
    $sql .= ' from ' . tablename('p_room');
    $sql .= ' WHERE  BldGUID=:BldGUID';
    $sql .= ' order by Floor desc ,Unit,No,Room';
    return pdo_fetchall($sql, array(':BldGUID' => $BldGUID), $useKey ? 'RoomGUID' : '');
}

/**
 * 更新选房状态
 *  认筹单状态，房间信息
 * @param $data
 * @type 0取消选房，1选房
 */
function biz_updateRoomStatus($room, $chips, $type)
{
    //$data = array('CstName' => $chips['cname'], 'RoomGUID' => $room['RoomGUID'], 'qrcode' => $chips['qrcode']);

    $update = array();
    if ($type == 0) {
        $update['ChooseRoomCstName'] = '';
        $update['ChooseRoom'] = 0;
        $update['ChooseRoomDate'] = '';
        $update['SelectTime'] = 0;
    }
    if ($type == 1) {
        $update['ChooseRoomCstName'] = $chips['cname'];
        $update['ChooseRoom'] = 1;
        $update['ChooseRoomDate'] = date('Y-m-d H:i:s', TIMESTAMP);
        $update['SelectTime'] = TIMESTAMP;
    }
    if ($type == 2) {
        $update['CstName'] = $chips['cname'];
    }
    $update['Status'] = $room['NewStatus'];
    $status = pdo_update('p_room', $update, array('RoomGUID' => $room['RoomGUID']));
    return $status !== false;
}


/**
 * 更新认筹单选房相关信息
 *    +对应状态更新
 * @param $chips
 * @param $state
 * @param null $room
 */
function biz_updateChipsRoomStatus($chips, $state, $room = null)
{
    $update = array();
    if ($state == 0) {
        $update = array('roomguid' => '', 'roomcode' => '', 'roomstatus' => '0', 'shouldpay' => 0);
    }
    if ($state == 1) {
        $update['roomguid'] = $room['RoomGUID'];
        $update['roomcode'] = empty($room['ShowCode']) ? $room['RoomCode'] : $room['ShowCode'];
        $update['roomstatus'] = 1;
        $update['shouldpay'] = biz_getRoomOrderPay($room);
    }
    if ($state == 2) {
        //预留转认购，设置状态为确认
        //db_updateChipsStatus($chips['id'], 8);
    } else {

        pdo_update('chips', $update, array('id' => $chips['id']));
        //内选退房，去除选房状态,---$state为空，去掉
        db_updateChipsStatus($chips['id'], 6, empty($state));
    }

}

/**
 * @param $BldGUID
 * @return array
 */
function biz_getRoomStat($BldGUID)
{
    $sql = 'select count(Status) as num, Status from ' . tablename('p_room');
    $sql .= ' WHERE  BldGUID=:BldGUID';
    $sql .= ' GROUP BY `Status` ';
    return pdo_fetchall($sql, array(':BldGUID' => $BldGUID), 'Status');
}


function biz_getRoomStatusStat($BldGUID)
{
    $base = biz_getRoomStat($BldGUID);
    $status = explode(',', '待售,签约,认购,销控,预留');
    $c = 0;
    foreach ($base as $s) {
        $c += $s['num'];
    }
    $stat = array('总数' => $c);
    foreach ($status as $s) {
        if (isset($base[$s])) {
            $stat[$s] = intval($base[$s]['num']);
        } else {
            $stat[$s] = 0;
        }
    }
    return $stat;
}


#endregion

#region 签到处理


/**
 * 获得所有签到组信息
 * @param $pid  项目id
 * @param $keyfield key字段
 * @return 组信息数组
 */
function biz_getAllSignGroup($pid, $keyfield = 'dispnum')
{
    $sql = 'select * from ' . tablename('call_group') . ' where  pid=:pid';
    $sql .= ' order by dispnum';
    return pdo_fetchall($sql, array(':pid' => $pid), $keyfield);
}

function biz_getCalledGroup($pid, $keyfield = 'dispnum')
{
    $sql = 'select * from ' . tablename('call_group') . ' where  pid=:pid and called=1';
    $sql .= ' order by  calltime desc';
    return pdo_fetchall($sql, array(':pid' => $pid), $keyfield);
}


/**
 * @param $data
 * @param string $key
 * @return array
 */
function biz_getWillCallGroup($pid, $key = 'dispnum')
{
    $sql = 'select * from ' . tablename('call_group') . ' where  pid=:pid and called=0';
    $sql .= ' order by dispnum';
    return pdo_fetchall($sql, array(':pid' => $pid), $key);
}

/**
 * 获得组详细信息
 * @param $num
 * @param $pid
 * @return mix
 */
function biz_getSignGroupByNum($num, $pid)
{
    $sql = 'select * from ' . tablename('call_group') . ' where dispnum=:dispnum and pid=:pid';
    $group = pdo_fetch($sql, array(':dispnum' => $num, ':pid' => $pid));
    if (!empty($group)) {
        $stats = db_getSignStats($num, $pid);
        if ($statsstats['maxnum'] != null) {
            $group['maxnum'] = $stats['maxnum'];
        }
    }
    return $group;
}


/**
 * 获得组详细信息
 *  不存在数据则增加
 * @param $index
 * @param $pid
 */
function Sign_GetGroup($index, $pid)
{
    $info = biz_getSignGroupByNum($index, $pid);
    if (empty($info)) {
        biz_addSignGroup($index, $pid);
        $info = biz_getSignGroupByNum($index, $pid);
    }
    return $info;
}

/**
 * @param $index
 * @param $pid
 * @return bool
 */
function biz_addSignGroup($index, $pid)
{
    $data = array('dispnum' => $index, 'pid' => $pid, 'createtime' => TIMESTAMP);
    return pdo_insert('call_group', $data);
}

/**
 * @param $index
 * @param $maxnum
 * @param $pid
 * @return mix
 */
function Sign_GetEmptyGroup($index, $maxnum, $pid)
{
    do {
        $info = Sign_GetGroup($index, $pid);
        $index += 1;
        if ($index > 900) {
            break;
        }
    } while ($info['called'] || $info['maxnum'] >= $maxnum);
    return $info;
}

/**
 * 签到处理，增加组签到统计信息
 * @param $group
 */
function Sign_updateGroup($group, $bySign = false)
{
    if ($bySign) {
        //通过签到信息更新
        $update = db_getSignStats($group['gid'], $group['pid']);
        $group = biz_getSignGroupByNum($group['gid'], $group['pid']);
    } else {
        //通过组信息更新
        $update = db_getSignStats($group['dispnum'], $group['pid']);
    }
    if (!empty($group)) {
        pdo_update('call_group', $update, array('id' => $group['id']));
    }

}

function db_getSignStats($gid, $pid)
{
    $sql = 'select count(*) as maxnum, sum(signed) as signednum ,sum(preset) as presetnum from ims_sign';
    $sql .= ' where gid=:gid and pid=:pid';
    return pdo_fetch($sql, array(':gid' => $gid, ':pid' => $pid));
}

/**
 * 获得签到信息
 * @param $key qrcode
 * @param $pid 项目id
 * @return bool
 */
function biz_getSignInfoByQrcode($key, $pid)
{
    $sql = 'select * from ' . tablename('sign') . ' where qrcode=:qrcode and pid=:pid';
    return pdo_fetch($sql, array(':qrcode' => $key, ':pid' => $pid));
}

/**
 * 获取签到组成员信息
 * @param $gid 组显示序号
 * @param $pid 项目id
 * @return array 返回组下所有成员信息
 */
function biz_getSignsByGroup($gid, $pid)
{
    $sql = 'select * from ' . tablename('sign') . ' where gid=:gid and pid=:pid';
    return pdo_fetchall($sql, array(':gid' => $gid, ':pid' => $pid));
}

/**
 * 插入插到信息
 * @param $chips
 * @param $gid 组显示号码dispnum
 * @param $pid
 * @param preset 预设，false为预设
 */
function biz_insertSignInfo($chips, $gid, $pid, $preset = false, $sign = false)
{
    if (empty($chips)) {
        return false;
    }
    $data = array(
        'pid' => $pid,
        'qrcode' => $chips['qrcode'],
        'gid' => $gid,
        'cname' => $chips['cname'],
        'mobile' => $chips['mobile'],
        'cardid' => $chips['cardid'],
    );
    if ($preset) {
        $data['preset'] = 1;
    }
    if ($sign) {
        $data['signed'] = 1;
        $data['signtime'] = TIMESTAMP;
    }
    if (pdo_insert('sign', $data)) {
        return $data;
    } else {
        return false;
    }
    //更新chips,签到状态
    //pdo_update('chips',array('signed'=>1),array('id'=>$chips['id']));
}


/**
 * 叫号处理，更新叫号状态
 * @param $gid
 * @param $pid
 */
function biz_callSignGroup($gid, $pid)
{
    $group = biz_getSignGroupByNum($gid, $pid);
    if (!empty($group) && (empty($group['called']))) {
        pdo_update('call_group', array('called' => 1, 'calltime' => TIMESTAMP), array('id' => $group['id']));
    }
}


#endregion

#region 参数


/**
 * 获取支付银行列表
 * @param $ScopeGUID
 * @return array
 */
function biz_getBanks($ScopeGUID)
{
    return db_getParams('s_RzBank', $ScopeGUID);
}

/**
 * 获取代理公司列表
 * @param $projGUID
 * @return array
 */
function biz_getBizDLGS($project)
{
    $guid = $project['projguid'];
    if ($project['Level'] == 3) {
        $guid = $project['ParentGUID'];
    }
    return db_getParams('s_DLGS', $guid);
}

#endregion

#region 数据基础数据查询处理


/**
 * 获得项目所有楼幢信息
 * @param $projGUID
 * @param $onlySale 只返回已配置的可售楼栋
 * @return array
 */
function db_getBuilds($projGUID, $keyfield = '', $onlySale = false)
{
    $sql = 'select * from ' . tablename('p_building');
    $sql .= " WHERE  ProjGUID = :ProjGUID";
    if ($onlySale) {
        $sql .= ' AND Status=1';
    }
    $sql .= "  order by BldCode";
    return pdo_fetchall($sql, array(':ProjGUID' => $projGUID), $keyfield);
}

function db_getBuildingById($bldguid)
{
    $sql = 'select * from ' . tablename('p_building');
    $sql .= " WHERE  BldGUID = :BldGUID";
    $sql .= "  order by BldCode";
    return pdo_fetch($sql, array(':BldGUID' => $bldguid));
}

/**
 * 获得系统配置的可用楼栋
 * @param null $project
 */
function biz_getEnableBuilds($project = null)
{
    $builds = array();
    if (empty($project)) {
        global $_W;
        $project = $_W['project'];
    }
    $set = biz_unserializer($project, 'builds');
    if (!empty($set)) {
        $builds = array_merge($builds, $set['build']);
    }
    return $builds;
}

/**
 * 获得单元信息
 * @param $BldGUID
 * @return array
 */
function db_getBuildUnit($BldGUID)
{
    $sql = 'select * from ' . tablename('p_buildunit');
    $sql .= ' WHERE  BldGUID=:BldGUID';
    return pdo_fetchall($sql, array(':BldGUID' => $BldGUID));
}


function db_getCstAttach($projGUID, $CstGUID = '')
{
    $sql = 'select * from ' . tablename('p_cstattach');
    $sql .= ' WHERE  ProjGUID=:ProjGUID';
    $params = array(':ProjGUID' => $projGUID);
    if (!empty($CstGUID)) {
        $sql .= ' and CstGUID=:CstGUID';
        $params[':CstGUID'] = $CstGUID;
        return pdo_fetch($sql, $params);
    } else {

        return pdo_fetchall($sql, $params);
    }

}

/**
 * 获得项目中所有的客户信息
 * @param $projGUID
 * @return array
 */
function db_getCustomers($projGUID)
{
    $sql = 'SELECT b.*';
    $sql .= ' FROM ims_p_cstattach a INNER JOIN ims_p_customer b ON a.CstGUID = b.CstGUID';
    $sql .= ' where a.ProjGUID=:projGUID';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID));
}

/**
 * 获得项目票据信息
 * @param $projGUID
 * @param $keyfield
 * @return array
 */
function db_getInvoices($projGUID, $keyfield = '')
{
    //array_unique($projects);ims_p_invoice2proj唯一性处理？加唯一索引
//    $sql = 'SELECT b.*';
//    $sql .= ' FROM ims_p_invoice2proj a INNER JOIN ims_p_invoice b ON a.InvoGUID = b.InvoGUID';
//   $sql .= ' where a.ProjGUID=:projGUID';
    $sql = 'select * from ' . tablename('p_invoice') . ' where ProjGUID=:projGUID';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID), $keyfield);
}

function db_getFees($projGUID)
{
    $sql = 'SELECT * FROM ' . tablename('s_fee');
    $sql .= ' where ProjGUID=:projGUID';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID));
}

function db_getInvoice2proj($projGUID)
{
    //array_unique($projects);ims_p_invoice2proj唯一性处理？加唯一索引
    $sql = 'SELECT * FROM ' . tablename('p_invoice2proj');
    $sql .= ' where ProjGUID=:projGUID';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID));
}

function db_getInvoiceDetails($invoGUID)
{

    $sql = 'SELECT * FROM ' . tablename('p_invoicedetail');
    $sql .= ' where InvoGUID=:InvoGUID';
    return pdo_fetchall($sql, array(':InvoGUID' => $invoGUID));
}

function db_getProject($id, $isGuid = false, $unzip = true)
{
    $sql = 'select * from ' . tablename('project');
    $sql .= $isGuid ? ' where projguid=:id' : ' where id=:id';
    $project = pdo_fetch($sql, array(':id' => $id));
    if ($unzip) {
        projectUnProperty($project);
    }
    return $project;
}

function db_getChipsByProj($guid)
{
    $sql = "select * from " . tablename('chips') . ' where projguid=:projguid';
    return pdo_fetchall($sql, array(':projguid' => $guid));

}

function biz_checkChipsStatus($chips, $status, $field = 'status')
{
    //$dict=biz_getDictionary('chipstatus',true);
    $enable = false;
    $value = 1 << $status;
    $enable = ($chips[$field] & $value) == $value;
    return $enable;
}


/**
 * 更新认筹单状态
 * @param $chipsId
 * @param $statusIndex 状态值索引序号
 */
function db_updateChipsStatus($chipsId, $statusIndex, $remove = false, $field = 'status')
{
    if (is_array($chipsId)) {
        $chipsId = $chipsId['id'];
    }
    //status = status ^ 4;
    $value = 1 << $statusIndex;
    $sql = "update ims_chips set {$field} = {$field}";
    if ($remove) {
        $sql .= ' ^ ';
    } else {
        $sql .= ' | ';
    }
    $sql .= $value . ' where id=' . $chipsId;
    return pdo_run($sql);
}

/**
 * 获取房间信息
 * @param string $ProjGUID
 * @param string $BldGUID
 * @return array
 */
function db_getRooms($ProjGUID, $BldGUID = '')
{
    $sql = 'select *  from ' . tablename('p_room');
    $sql .= ' WHERE  ProjGUID=:ProjGUID ';
    $param = array(':ProjGUID' => $ProjGUID);
    if (!empty($BldGUID)) {
        $sql .= ' and BldGUID=:BldGUID ';
        $param[':BldGUID'] = $BldGUID;
    }
    $sql .= ' order by Floor,No';
    return pdo_fetchall($sql, $param);
}


/**
 * 获得项目可售房间信息
 * @param $ProjGUID
 * @return array
 */
function db_getCanSaleRooms($ProjGUID)
{
    $sql = "select b.* FROM ims_p_building a INNER JOIN ims_p_room b ON  a.BldGUID = b.BldGUID ";
    $sql .= ' WHERE  a.ProjGUID=:ProjGUID and a.Status=1 ';
    $sql .= " and b.Status='待售' ";
    $sql .= ' order by b.Floor,b.No';
    $param = array(':ProjGUID' => $ProjGUID);
    return pdo_fetchall($sql, $param);
}

/**
 * 获取房间信息通过GUID
 * @param $roomGUID
 * @return bool
 */
function db_getRoomByGUID($roomGUID)
{
    $sql = " select * from " . tablename('p_room') . " where RoomGUID=:roomguid ";
    return pdo_fetch($sql, array(':roomguid' => $roomGUID));
}

/**
 * 获得参数配置
 * @param $ParamName 类别名称
 * @param $ScopeGUID
 * @return array
 *  Paramvalue as 'name' ,ParamGUID as 'id'
 */
function db_getParams($ParamName, $ScopeGUID)
{
    $sql = "SELECT Paramvalue as 'name' ,ParamGUID as 'id' FROM " . tablename('mybizparamoption');
    $sql .= " WHERE ParamName=:ParamName AND ScopeGUID=:ScopeGUID";
    return pdo()->fetchall($sql, array(":ParamName" => $ParamName, ":ScopeGUID" => $ScopeGUID));
}

function db_getAllParams($ParamName, $ScopeGUID)
{
    $sql = "SELECT * FROM " . tablename('mybizparamoption');
    $sql .= " WHERE ParamName=:ParamName AND ScopeGUID=:ScopeGUID";
    return pdo()->fetchall($sql, array(":ParamName" => $ParamName, ":ScopeGUID" => $ScopeGUID));
}

/**
 * @param $id
 * 返回预设银行
 */
function db_getPreBank($id)
{
    $sql = "SELECT Paramvalue as 'name' ,ParamGUID as 'id' FROM " . tablename('mybizparamoption');
    $sql .= " WHERE ParamGUID =:ParamGUID";
    return pdo()->fetch($sql, array(":ParamGUID" => $id));
}

/**
 * 获得项目所有拆扣
 * @param $projGUID
 */
function db_getDiscount($projGUID, $keyfield = '')
{
    $sql = 'select * from ' . tablename('s_discountdefine') . ' where ProjGUID=:projGUID ';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID), $keyfield);
}

/**
 * 获得项目所有支付方式
 * @param $projGUID
 */
function db_getPayForm($projGUID, $keyfield = '')
{
    $sql = 'select * from ' . tablename('s_payform') . ' where ProjGUID=:projGUID ';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID), $keyfield);
}

function db_getMyStation($projGUID, $keyfield = '')
{
    $sql = 'select * from ' . tablename('mystation') . ' where ProjGUID=:ProjGUID ';
    return pdo_fetchall($sql, array(':ProjGUID' => $projGUID), $keyfield);
}

function db_getMyStationUser($projGUID)
{
    $sql = "SELECT b.* FROM ims_mystation a INNER JOIN ims_mystationuser b ON a.StationGUID = b.StationGUID  ";
    $sql .= " where  a.ProjGUID=:ProjGUID";
    return pdo()->fetchall($sql, array(':ProjGUID' => $projGUID));
}

function db_getMyUser($projGUID, $keyfield = '')
{
    $sql = 'SELECT c.* FROM ims_mystation a INNER JOIN ims_mystationuser b ON  a.StationGUID = b.StationGUID ';
    $sql .= ' INNER JOIN ims_myuser c ON b.UserGUID = c.UserGUID ';
    $sql .= ' where a.ProjGUID=:projGUID ';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID), $keyfield);
}

/**
 * 获得项目支付guid
 * @param $projGUID
 * @return bool
 */
function biz_getPayFormGUID($projGUID)
{
    $project = biz_getProject($projGUID);
    $discount = biz_unserializer($project, 'payform');
    if (empty($discount) || empty($discount['pay_id'])) {
        return false;
    } else {
        return $discount['pay_id'];
    }
}

/**
 * 获得房间定金
 * @param $room
 * @return float|int
 */
function biz_getRoomOrderPay($room)
{

    $PayFormGUID = biz_getPayFormGUID($room['ProjGUID']);
    if (!$PayFormGUID) {
        return 0;
    }
    //获得支付方式中的定金条目
    $details = db_getPaydetail($PayFormGUID);
    foreach ($details as $p) {
        if ($p['ItemName'] == '定金') {
            $pay = $p;
            break;
        }
    }
    $OrderPay = 0;
    if (!empty($pay)) {
        if ($pay['Rate'] > 0) {
            $OrderPay = $room['RoomTotal'] * $pay['Rate'] / 100;
        } else {
            $OrderPay = $pay['Amount'];
        }
    }
    return $OrderPay;
}

/**
 * 获取项目所有支付细节
 * @param $projGUID
 * @param string $keyfield
 * @return array
 */
function db_getAllPayDetail($projGUID, $keyfield = '')
{
    $sql = 'SELECT b.* FROM ims_s_payform AS a INNER JOIN ims_s_paydetail AS b ON a.PayFormGUID = b.PayFormGUID ';
    $sql .= ' WHERE a.ProjGUID =:projGUID ';
    return pdo_fetchall($sql, array(':projGUID' => $projGUID), $keyfield);
}


/**
 * 获得支付类别的支付细节
 * @param $PayFormGUID
 * @param string $keyfield
 * @return array
 */
function db_getPayDetail($PayFormGUID, $keyfield = '')
{
    $sql = 'select * from ' . tablename('s_paydetail') . ' where PayFormGUID=:PayFormGUID order by Sequence';
    return pdo_fetchall($sql, array(':PayFormGUID' => $PayFormGUID), $keyfield);
}


/**
 * 获得项目所有已开的票据信息
 * @param $projGUID
 * @param string $type 票据类型：1诚意金，2定金
 * @param bool $onlyErp 只返回要导入Erp
 * @return bool
 */
function db_getBills($projGUID, $type = '', $onlyErp = false)
{
    $sql = " select * from " . tablename('bill') . " where ProjGUID=:ProjGUID ";
    $params = array(':ProjGUID' => $projGUID);
    if (!empty($type)) {
        $sql .= ' and BillType=:BillType';
        $params[':BillType'] = $type;
    }
    if ($onlyErp) {
        $sql .= ' and ErpSync=0';
    }
    return pdo_fetchall($sql, $params);

}

function biz_getPayFromDetail($projGUID)
{
    $project = biz_getProject($projGUID, true);
    $pay = biz_unserializer($project, 'payform');
    //$pay['details'] = db_getPaydetail($pay['pay_id']);
    return $pay;
}

/**
 * 生成认筹单对应的供款明细
 *
 * @param $chips
 */
function biz_insertFee($chips, $room)
{
    $result = false;
    $pay = biz_getPayFromDetail($chips['projguid']);
    if ((!empty($pay['details'])) && (!empty($chips['roomguid']))) {
        $reset = array();
        foreach ($pay['details'] as $p) {
            $fee = get_s_feearray($p, $room, $chips);
            if ($fee['ItemName'] == '首期') {
                //记录首期，金额+银行按揭取W剩余金额
                $reset['item'] = $fee;
            }
            if ($fee['ItemName'] == '银行按揭') {
                $reset['add'] = $fee['RmbAmount'] - $fee['Amount'];
                $fee['RmbAmount']=$fee['Amount'];
                $fee['Rate'] = $fee['Amount'] / $room['RoomTotal'] * 100;
            }
            $result = pdo_insert('s_fee', $fee);
        }
        if (!empty($reset['item']) && !empty($reset['add'])) {
            $fee = $reset['item'];
            $fee['Amount'] += $reset['add'];
            $fee['RmbAmount'] = $fee['Amount'];
            $fee['Ye'] = $fee['Amount'];
            $fee['RmbYe'] = $fee['Ye'];

            pdo_update('s_fee', $fee, array('FeeGUID' => $fee['FeeGUID']));
        }
    }
    return $result;
}

/**
 * $value: 付款明细，$room:房间信息，$chips: 认筹信息，$id :tradeGUID,
 * 获得供款明细的插入规则
 * 返回 array() $fee,
 */
function get_s_feearray($value, $room, $chips)
{
    $fee = array(
        'FeeGUID' => GUID(),
        'ProjGUID' => $chips['projguid'],
        'TradeGUID' => $chips['qrcode'],
        'ItemName' => $value['ItemName'],
        'ItemType' => $value['FeeSort'],
        'Sequence' => $value['Sequence'],
        'Bz' => '人民币',
        'ExRate' => 1.00,
        'JmLateFee' => 0,
        'IsChg' => 0,
        'OutAmount' => 0,
        'OutRmbAmount' => 0,
        'DsAmount' => 0,
        'RmbDsAmount' => 0,
        //'signguid'=>$projGUID,
        'IsBcXyKx' => 0,
    );

    if ($value['Action'] == "签署认购书") {
        $add = '+' . (empty($value['ActiDays']) ? '' : "{$value['ActiDays']} days") . (empty($value['ActiMonth']) ? '' : "{$value['ActiMonth']} months");
        $fee['lastDate'] = date("Y-m-d", strtotime($add, TIMESTAMP));
        $fee['lastDate'] = date("Y-m-d", strtotime('-1 days', strtotime($fee['lastDate'])));
    } else {
        $fee['lastDate'] = null;
    }
    //增加比例存储
    $fee['Rate'] = $value['Rate'];
    // 是否扣除定金交款
    if ($value['IsDeductEarnest'] == 1) {
        // 判断是打折，还是金额，
        if ($value['Rate'] > 0) {
            $fee['Amount'] = $room['RoomTotal'] * $value['Rate'] / 100.0 - $chips['ordermoney'] - $chips['premoney'];
        } else {
            $fee['Amount'] = $value['Amount'] - $chips['ordermoney'] - $chips['premoney'];
        }
    } else {
        if ($value['Rate'] > 0) {
            $fee['Amount'] = $room['RoomTotal'] * $value['Rate'] / 100.0;
        } else {
            $fee['Amount'] = $value['Amount'];
        }
    }

    $fee['RmbAmount'] = $fee['Amount'];
    if ($fee['ItemName'] == "银行按揭") {
        //取万元整数
        $fee['Amount'] = intval($fee['RmbAmount'] / 10000) * 10000;

    }

    // 计算余额
    if ($fee['ItemName'] == '定金') {
        $fee['Ye'] = $fee['Amount'] - $chips['ordermoney'] - $chips['premoney'];

    } else {
        $fee['Ye'] = $fee['Amount'];
    }
    // 计算RmbYe
    $fee['RmbYe']=$fee['Ye'];

//    if ($fee['Ye'] == 0) {
//        $fee['Flag'] = '√';
//    } else {
//        $fee['Flag'] = null;
//    }

    return $fee;
}

/**
 * 从s_fee 获取银行按揭数据的金额数据，
 * retrurn array
 */
function AjTotal($projGUID, $qrcode)
{
    $sql = "select * from " . tablename('s_fee') . " where ProjGUID=:ProjGUID and TradeGUID=:TradeGUID  and ItemName=:ItemName ";
    return pdo_fetch($sql, array(':ProjGUID' => $projGUID, ':TradeGUID' => $qrcode, ':ItemName' => '银行按揭'));
}
#endregion
