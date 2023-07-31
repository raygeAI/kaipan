<?php
/**
 * app相关处理
 */


/**
 * 获得房间状态
 * @param $BldGUID
 * @param $RoomGUID
 * @return bool
 */
function APP_getRoomStatus($projGUID, $BldGUID, $RoomGUID)
{
    $room = false;
    $info = APP_getRoomInfo($projGUID, $BldGUID);
    if (!empty($info) && isset($info[$RoomGUID])) {
        $room = $info[$RoomGUID];
        $room['ProjGUID'] = $projGUID;
    }
    return $room;
}

function db_getChipsByRoom($room)
{
    $sql = 'select * from ' . tablename('chips') . ' where roomstatus=1 ';
    $sql .= ' and  projguid=:projguid and roomguid=:roomguid';
    return pdo_fetch($sql, array(':projguid' => $room['ProjGUID'], ':roomguid' => $room['RoomGUID']));
}


function  Mem_updateRoomStatus($update, $select, $check = true)
{
    $result = false;
    $projGUID = $update['ProjGUID'];
    $roomGUID = $update['RoomGUID'];
    $BldGUID = $update['BldGUID'];

    if (memcached_addKey('Update_' . $roomGUID)) {
        $data = APP_getRoomInfo($projGUID, $BldGUID);
        if (!empty($data)) {
            if (isset($data[$roomGUID])) {
                $room = &$data[$roomGUID];
                $set = $check ? $room['ChooseRoom'] != $select : true;
                $set=$set && (!empty($update['NewStatus'])) && ($room['Status'] == $update['Status']);
                if ($set) {
                    if($select<2){
                        $room['ChooseRoom'] = $select > 0 ? 1 : 0;
                        $room['ChooseRoomCstName'] = $select == 1 ? $update['CstName'] : '';
                        $room['SelectTime'] = $select == 1 ? TIMESTAMP : 0;
                    }
                    if($select==2&&($update['NewStatus']=='待售')){
                        $room['ChooseRoom'] =  0 ;
                    }
                    $room['Status'] = $update['NewStatus'];
                    memcached_set('R_I_' . $BldGUID, $data);
                    $result = true;
                }
            }
        }
        memcached_delete('Update_' . $roomGUID);
    }


    return $result;
}

/**
 * 更新状态
 * @param $update
 * @return bool
 */
function APP_updateRoomStatus($room, $chips, $select)
{
    if ($select&&(!empty($chips))) {
        $room['CstName'] = $chips['cname'];
    }
    $result = Mem_updateRoomStatus($room, $select);
    if ($result) {
        $result = biz_updateRoomStatus($room, $chips, $select);
        if (isset($chips)) {
            biz_updateChipsRoomStatus($chips, $select, $room);
        }
    }
    return $result;
}

function App_changeRoomStatus($room, $newStatus)
{
    $room['NewStatus']=$newStatus;
    $room['ChooseRoom']=0;
    $result = Mem_updateRoomStatus($room, 2);
    if ($result) {
        $result = biz_updateRoomStatus($room, null, 3);
    }
    return $result;
}

/**
 * @param $projGuid
 * @return array|mixed|string
 * @throws Exception
 */
function APP_getRoomInfo($projGuid, $bldGUID)
{
    $key = 'R_I_' . $bldGUID;
    if (empty($bldGUID)) {
        $key = 'R_I_GLOBAL';
    }
    $callback = function () use ($bldGUID) {
        return biz_getRoomInfo($bldGUID, true);
    };
    $rooms = cache_GetData($key, $callback);
    $waitTime =10 * 60;
    $project=biz_getProject($projGuid);
    $set=biz_unserializer($project,'builds');
    if(!empty($set['timeout'])){
        $waitTime= $set['timeout']*60; 
    }
    //处理超时

    foreach ($rooms as &$room) {
        if ($room['Status'] == '选房' && !empty($room['SelectTime']) && ($room['SelectTime'] + $waitTime < TIMESTAMP)) {
            $room['Status'] = '超时';
        }
    }
    unset($room);
    return $rooms;

}




/**
 * 获取用户有权的楼盘
 * @param $user 用户数据
 * @param $hasBuild 是否包含楼盘信息
 * @return array
 */
function App_getProjects($user, $hasBuild = false)
{
    $sql = 'select projguid,projname from ' . tablename('project');
    $projects = biz_getUserProject($user);
    if (is_array($projects)) {
        $sql .= ' where projguid in (\'' . implode("','", $projects) . '\')';
    }
    $list = pdo_fetchall($sql);
    if ($hasBuild) {
        foreach ($list as &$item) {
            $item['build'] = App_db_GetBuildInfo($item['projguid']);
        }
        unset($item);
    }
    return $list;
}

function App_getBuilds($projGUID)
{
    $callback = function () use ($projGUID) {
        return App_db_Builds($projGUID);
    };
    $key = 'builds_' . $projGUID;
    return cache_GetData($key, $callback);
}


function App_db_Builds($projGUID)
{
    $sql = 'select projguid,projname from ' . tablename('project');
    $sql .= ' where projguid =:projguid';
    $list = pdo_fetchall($sql, array(':projguid' => $projGUID));
    foreach ($list as &$item) {
        $item['build'] = App_db_GetBuildInfo($item['projguid']);
    }
    unset($item);
    return $list;
}

/**
 * 获得认筹单状态，当前流程状态
 * @param $qrcode
 * @return bool
 */
function App_getChipsStatus($qrcode)
{

    $chips = biz_getChipsByQrcode($qrcode);
    if (empty($chips)) {
        return false;
    }
    $data = array_elements(array('cname', 'grender', 'mobile', 'cardid', 'agency', 'salesman', 'premoney'), $chips);;
    //状态检查
    if (is_array($data)) {
        $data['step'] = '登记';
        if ($chips['pretype'] == 1) {
            $data['premoney'] = $chips['premoney'];
            $data['step'] = '诚意金交款';
        }
        if ($chips['pretype'] == 2) {
            $data['step'] = '诚意金无交款确认';
        }
        if ($chips['signed'] == 1) {
            $data['step'] = '已签到';
        }
        if ($chips['lucky'] == 1) {
            $data['step'] = '已中签';
        }
        if ($chips['roomstatus'] == 1) {
            $data['shouldpay'] = $chips['shouldpay'];
            $data['step'] = '已选房';
        }
        if ($chips['shouldpay'] > 0) {
            $data['ordermoney'] = $chips['ordermoney'];
        }
        if ($data['printstatus'] > 4) {
            $data['step'] = '已认购';
        }
        $data['roomcode'] = $chips['roomcode'];
    }
    return $data;
}

/**
 * 获得项目下的楼盘列表
 * @param $projGUID
 * @param bool $hasUnit
 * @return array
 */
function App_db_GetBuildInfo($projGUID, $hasUnit = true)
{
    $sql = 'select `ProjGUID`,`BldGUID`,`BldName`,`BldFullName`';
    $sql .= ',`UnitNum`,`FloorNum`,`FloorList`,`BldProPerty`';
    $sql .= ' from ' . tablename('p_building') . " WHERE  (`IsBld`=1) and (`Status`=1) ";

    $params = array();
    if (!empty($projGUID)) {
        $sql .= ' and (ProjGUID = :projGUID) ';
        $params[':projGUID'] = $projGUID;
    }
    $sql .= ' order by BldCode  ';
    $builds = pdo_fetchall($sql, $params);
    if ($hasUnit) {
        foreach ($builds as &$b) {
            $b['Unit'] = App_getBuildUnit($b['BldGUID']);
        }
        unset($b);
    }
    return $builds;
}

/**
 * 获得楼幢下单元信息
 * @param $BldGUID
 * @return array
 */
function App_getBuildUnit($BldGUID)
{
    $sql = 'select UnitGUID,DoorNum,UnitNo,RoomNoList from ' . tablename('p_buildunit');
    $sql .= ' WHERE  BldGUID=:BldGUID';
    $sql .= ' order by UnitNo ';
    return pdo_fetchall($sql, array(':BldGUID' => $BldGUID));
}


function App_getStats($projGUID)
{
    $key = 'stats_' . $projGUID;
    $callback = function () use ($projGUID) {
        return App_db_getStats($projGUID);
    };
    return cache_GetData($key, $callback, 10);
}


function biz_getCalledGroupNum($project){
    $key = 'call_' . $project['projguid'];
    $callback = function () use ($project) {
        $groups= biz_getCalledGroup($project['id']);
        return array_keys($groups);
    };
    return cache_GetData($key, $callback, 5);   
    
}

function App_db_getStats($projGUID)
{
    $project=biz_getProject($projGUID);
    $set = biz_unserializer($project, 'builds');
    $stats = array();//'renchou'=>0,'qiandao'=>0,'zhongqian'=>0,'crj'=>0,'rengou'=>0,'salearea'=>0,'salemoney'=>0);
    $sql = " select count(*) as 'renchou', sum(case when signed>0 then 1 else 0 end) as 'qiandao',sum(roomstatus) as xuanfang";
    $sql .= " ,sum(lucky) as 'zhongqian'  ,sum(premoney) as 'crj'";
    $sql .= " ,sum(case when printstatus>=8 then 1 else 0 end) as jiaokuan";
    $sql .= " ,sum(case when printstatus>=16 then 1 else 0 end) as rgs";//认购数
    $sql .= " from ims_chips where projguid=:projguid and deleted=0";
    $params = array(':projguid' => $projGUID);
    $data = pdo_fetch($sql, $params);
    $stats = array_merge($stats, $data);
    $stats['roomtotal']=intval($set['roomnum']);
    //当天时间开始时间
    $sql = " select count(*) as salenum,sum(`Total`) as salemoney ,sum(`BldArea`) as  salearea  FROM ims_p_room where `Status`='认购' and ProjGUID=:projguid ";
    $data = pdo_fetch($sql, $params);
    $stats = array_merge($stats, $data);
    foreach ($stats as $k => $v) {
        if ($v == null) {
            $stats[$k] = 0;
        }
    }
    $stats['rgs']=$stats['salenum'];
    //格式化金额
//    if (isset($stats['crj'])) {
//        $stats['crj'] = number_format($stats['crj'], 2);
//    }
//    if (isset($stats['salemoney'])) {
//        $stats['salemoney'] = number_format($stats['salemoney'], 2);
//    }
    return $stats;
}


/**
 *获得签到全局表信息
 * 自动增加组
 * @param $pid 项目id
 */
function getGlobalSign($project)
{
    global $_W;
    $key = 'sign_' . $project['projguid'];
    $singset = biz_unserializer($project, 'signset');
    $max = empty($singset['num']) ? 10 : $singset['num'];
    $info = memcached_get($key);
    $reset=empty($info)||($info['group']['maxnum']>=$max);
    if ($reset) {
        memcached_delete($key);
        $index=1;
        if(!empty($info['group']['dispnum'])){
            $index=$info['group']['dispnum'];
        }

        //获取组号
        $callback = function () use ($index,$max,$project) {
            $info['maxnum']=$max;
            //获取未签到组列表
            $info['group'] = Sign_GetEmptyGroup($index, $max, $project['id']);
            $info['group']['maxnum']++;
            return $info;
        };
        $info = cache_GetData($key, $callback, 1800);
    }else{
        $info['group']['maxnum']++;
        memcached_set($key, $info, 1800);
    }
    return $info;
}

/**
 *获得签到全局表信息
 * 自动增加组
 * @param $key 认筹单二维码key
 * @param $project 项目
 */
function App_Sign_getGroup($key, $project)
{
    $res = array('signed' => false, 'msg' => '');
    $pid = $project['id'];
    //查询是否已有分配的签到
    $sign = biz_getSignInfoByQrcode($key, $pid);
    $chips = biz_getChipsByQrcode($key);
    if (empty($chips)) {
        $res['msg'] = '无效认筹单';
        return $res;
    }
    if (empty($sign) || ($sign['preset'] == 1)) {

        if ($chips['projguid'] != $project['projguid']) {
            $res['msg'] = '认筹单所属非当前项目';
            return $res;
        }
        //处理已签到信息
        if ($chips['singed'] == 1) {
            $res['msg'] = '认筹单已签到';
            return $res;
        }
    }

    if (isset($chips) || !$sign['signed']) {
        $cachekey = 'lock_sign_' . $pid;
        //写入签到信息
        if (memcached_addKey($cachekey, 30)) {
            
            if (empty($sign)) {
                $info = getGlobalSign($project);
                $sign = biz_insertSignInfo($chips, $info['group']['dispnum'], $pid, false, true);
                if (!empty($sign)) {
                    // $sign = biz_getSignInfoByQrcode($key, $pid);
                    $res['signed'] = true;
                    $res['group'] = $info['group']['dispnum'];
                }
            } else if (empty($sign['signed'])) {
                $update['signed'] = 1;
                $update['signtime'] = TIMESTAMP;
                pdo_update('sign', $update, array('id' => $sign['id']));
                unset($update);
                $sign['signed'] = 1;
                $sign['signtime'] = TIMESTAMP;
                $res['signed'] = true;
                $res['group'] = $sign['gid'];
            }
            if (!empty($sign)) {
                //更新对应组信息
                Sign_updateGroup($sign,true);
                
            }
            if ($res['signed']) {
                //签到记录组号
                pdo_update('chips', array('signed' => $sign['gid']), array('id' => $chips['id']));
                db_updateChipsStatus($chips['id'], 4);
            }
            memcached_delete($cachekey);
        }
    } else {
        $res['signed'] = !empty($sign['signed']);
    }
    $res['sign'] = $sign;
    return $res;
}