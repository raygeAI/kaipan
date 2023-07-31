<?php

//增加接口调用
define('IN_SYS', true);
error_reporting(E_ALL ^ ~E_NOTICE ^ E_WARNING);


require './framework/bootstrap.inc.php';
load()->web('business');
load()->web('app');

disableWebCache();

$func = strtolower($_GPC['func']);
$params = $_GPC['params'];
if (is_array($params)) {
    $params = array_change_key_case($params, CASE_LOWER);
}
$res = array('result' => false, 'msg' => '无效的功能调用');
//检查调用IP？
//cache()->flush();

if ($func == 'calllnum') {
    $res['msg'] = '';
    $res['result'] = true;

    $num = memcached_get('callnum');
    $res['data'] = sprintf('%03d', intval($num));
    returnJson($res);
}

if ($func == 'login') {
    $user = biz_login($params['usercode'], $params['password']);
    if (!empty($user)) {
        unset($user['Password']);
        $res['msg'] = '登录成功';
        $res['result'] = true;
        $res['data']['user'] = $user;
    } else {
        $res['msg'] = '无效的用户名或密码！';
    }
    returnJson($res);
}

//检查用户是否存在
if (empty($_GPC['info'])) {
    returnJson($res);
}

$user = biz_getUser($_GPC['info']['token']);
if (empty($user)) {
    $res['msg'] = '用户登录过期';
    $res['code'] = -1;
    returnJson($res);
}

if ($func == 'sign') {
    //检查开盘状态
    if (empty($params['qrcode'])) {
        $res['msg'] = '空参数';
        returnJson($res);
    }
    $project = biz_getProject($params['projguid'], true);
    if (empty($project)) {
        $res['msg'] = '无效项目信息';
        returnJson($res);
    }
    $sign = App_Sign_getGroup(trim($params['qrcode']), $project);

    if (empty($sign) || empty($sign['sign'])) {
        $res['msg'] = $sign['msg'];
    } else {
        $res['result'] = true;
        $sign['sign']['signtime'] = date('Y-m-d H:i:s', $sign['sign']['signtime']);
        $res['data'] = $sign['sign'];
        $res['first'] = isset($sign['group']);
        $res['msg'] = $res['first'] ? '签到成功' : '此单已签到，不能重复签到';
    }
    returnJson($res);
}

if($func=='lucky'){
    
    if (empty($params['qrcode'])) {
        $res['msg'] = '空参数';
        returnJson($res);
    }
    $project=biz_getProject($params['projguid']);
    $chips = biz_getChipsByQrcode($params['qrcode']);
    if (empty($chips)||($chips['projguid']!=$project['projguid'])) {
        $res['msg'] = '无效认筹单';
        returnJson($res);
    }
    $res['data']=array_elements(array('cname','cardid','signed'),$chips);
    if (empty($chips['lucky'])) {
        //未中签处理，1、未签处理，2已签到，未叫号、已叫号
        if (empty($chips['signed'])) {
            //获取预设组
            $res['msg'] = '此单未签到、未中签!';
            $res['result'] = false;
        } else {
            $called = biz_getCalledGroupNum($project);
            if(in_array($chips['signed'],$called)){
                pdo_update('chips',  array('lucky' => 1), array('id' => $chips['id']));
                $res['msg'] ="此单号签到组号{$chips['signed']}组，中签登记成功";
                $res['result'] = true;
            }else{
                $res['msg'] ="此单号签到组号{$chips['signed']}组,未中签!";
            }
        }
    }else{
        $res['msg'] = '此单已经确认中签！';
        $res['result'] = true;
    }
    returnJson($res);

}
if ($func == 'projlist') {
    $res['result'] = true;
    $res['msg'] = '';
    $res['data'] = App_getProjects($user, false);
    returnJson($res);
}

if ($func == 'buildlist') {
    if (empty($params['projguid'])) {
        $res['result'] = false;
        $res['msg'] = '无效的项目参数';
    } else {
        load()->web('right');
        $res['data'] = App_getBuilds($params['projguid']);
        $right = biz_getUserProjectRight($user['UserGUID'], $params['projguid']);
        $res['right'] = isset($right['Permission']) ? $right['Permission']['app'] : array();
        $res['result'] = true;
        $res['msg'] = '';
    }
    returnJson($res);
}
if ($func == 'roominfo') {
    $res['result'] = true;
    $res['data'] = APP_getRoomInfo($params['projguid'], $params['bldguid']);
    $res['msg'] = '';
    returnJson($res);
}


if ($func == 'selectroom') {
    $room = APP_getRoomStatus($params['projguid'], $params['bldguid'], $params['roomguid']);
    if (!empty($room)) {
        if (($room['Status'] == '待售') && empty($room['ChooseRoom'])) {
            $chips = biz_getChipsByQrcode($params['qrcode']);
            if ($chips['projguid'] != $room['ProjGUID']) {
                $res['msg'] = '认筹单与所选房间非同一项目';
                returnJson($res);
            }
            //认筹单没有选房
            if (!empty($chips)) {
                if (empty($chips['roomstatus'])) {
                    $room['NewStatus'] = '选房';
                    if (APP_updateRoomStatus($room, $chips, 1)) {
                        $res['result'] = true;
                        $res['msg'] = '选房成功';
                    } else {
                        $res['msg'] = '选房失败';
                    }
                } else {
                    $res['msg'] = '此单不可再选房，如已选房请先退房';
                }
            } else {
                $res['msg'] = '无效的认筹单';
            }
        } else {
            $res['msg'] = '房间非待售状态';
        }
    } else {
        $res['msg'] = '无效房间参数';
    }
    returnJson($res);
}

if ($func == 'unselectroom') {
    $res['msg'] = '';
    $chips = biz_getChipsByQrcode($params['qrcode']);
    if (!empty($chips)) {
        $room = APP_getRoomStatus($params['projguid'], $params['bldguid'], $params['roomguid']);
        if (!empty($room) && ($chips['roomguid'] == $room['RoomGUID'])) {
            if (($chips['roomstatus'] == 1) &&($room['Status']=='选房')) {
                //认筹单没有选房
                $room['NewStatus'] = '待售';
                if (APP_updateRoomStatus($room, $chips, 0)) {
                    $res['result'] = true;
                    $res['msg'] = '退房成功';
                } else {
                    $res['msg'] = '退房失败';
                }
            } else {
                $res['msg'] = '此单非选房状态，不能退房';
            }
        } else {
            $res['msg'] = '无效的房间信息,与订单信息不匹配';
        }
    } else {
        $res['msg'] = '无效的认筹单';
    }
    returnJson($res);
}


if ($func == 'report') {

    if (!empty($params['projguid'])) {
        $data = App_getStats($params['projguid']);
        $res['msg'] = '';
        $res['result'] = true;
        $res['data'] = $data;
    } else {
        $res['msg'] = '无效参数';
    }

    //统计报表
    returnJson($res);
}

if ($func == 'turn_room') {
    $res['msg'] = '';
    $room = APP_getRoomStatus($params['projguid'], $params['bldguid'], $params['roomguid']);
    if (!empty($room)) {
        if (in_array($room['Status'], array('预留', '预约'))) {
            //empty($room['ChooseRoom'])
            $chips = biz_getChipsByQrcode($params['qrcode']);
            //认筹单没有选房
            if (!empty($chips)) {
                if (empty($chips['roomstatus'])) {
                    $room['NewStatus'] = '确认';
                    if (APP_updateRoomStatus($room, $chips, 1)) {
                        $res['result'] = true;
                        $res['msg'] = '转认购成功';
                    } else {
                        $res['msg'] = '转认购失败';
                    }
                } else {
                    $res['msg'] = '此单不可再选房，如已选房请先退房';
                }
            } else {
                $res['msg'] = '无效的认筹单';
            }
        } else {
            $res['msg'] = '房间非预留、预约状态';
        }
    } else {
        $res['msg'] = '无效参数';
    }
    returnJson($res);
    //$params['projguid'], $params['bldguid'], $params['roomguid']
    //直接选房
}

if ($func == 'nei_unselect_room') {
    $res['msg'] = '';
    //选房过期未交款；内控退房

    $room = APP_getRoomStatus($params['projguid'], $params['bldguid'], $params['roomguid']);
    //检查超时 ，状态为超时
    if (!empty($room) && !empty($room['ChooseRoomCstName'])) {
        $chips = db_getChipsByRoom($room);
//        if (!empty($chips) && ($chips['printstatus'] >= 4)) {
//            $res['msg'] = '房间对应认筹单已认购不能退房';
//            returnJson($res);
//        }
        if ($room['Status'] == '超时') {
            //认筹单没有选房
            $room['NewStatus']='待售';
            if (APP_updateRoomStatus($room, $chips, 0)) {
                $res['result'] = true;
                $res['msg'] = '退房成功';
            } else {
                $res['msg'] = '退房失败';
            }
        } else {
            $res['msg'] = '此单非超时状态，不能退房';
        }
    } else {
        $res['msg'] = '无效的房间信息';
    }
    returnJson($res);
}

if ($func == 'changestatus') {
    $res['msg'] = '';
    //要获取状态
    $status = trim($params['status']);
    if (!in_array($status,array('待售', '销控'))) {
        $res['msg'] = '无效的状态参数';
        returnJson($res);
    }
    $room = APP_getRoomStatus($params['projguid'], $params['bldguid'], $params['roomguid']);
    //选房时间大于10分钟以上
    if (empty($room)) {
        $res['msg'] = '无效的房间信息';
        returnJson($res);
    }
    if (($room['Status'] == $status)) {
        $status= $status=='待售'?'销控':'待售';
        $res['result']=App_changeRoomSTatus($room ,$status);
        
        $res['msg']='转换到'.$status.'状态'. ($res['result']?'成功':'失败');
       
    } else {
        $res['msg'] = '房间信息状态不匹配';
    }
    returnJson($res);
}

if ($func == 'chipsinfo') {
    $res['msg'] = '';
    //要获取状态
    $chips = App_getChipsStatus($params['qrcode']);
    if (!empty($chips)) {
        $res['result'] = true;
        $res['data'] = $chips;
    } else {
        $res['msg'] = '无效的认筹单';
    }
    returnJson($res);
}









