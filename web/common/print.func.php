<?php

#region 打印接口功能

/**
 * 打印票据处理
 */
function biz_Print_ModuleRegister($data)
{
    $module = db_getPrintModule($data['computer'], $data['key']);
    $value = array('token' => md5(GUID()), 'createtime' => TIMESTAMP);
    if (empty($module)) {
        $value = array_merge($value, $data);
        pdo_insert('printmodule', $value);
        $module = db_getPrintModule($data['computer'], $data['key']);
    } else {
        $module['token'] = $value['token'];
        pdo_update('printmodule', $data, array('id' => $module['id']));
    }
    return $module;
}


/**
 * 获得打印任务列表
 * @param  $lasttime 最后创建时间
 * @return array
 */
function biz_Print_getTask($module)
{
 
    $sql = 'select id,title,copy,printtype,printid,printname,templateid,printdata,creator from ' . tablename('printtask');
    $params = array();

    $sql .= ' where moduleid=:moduleid and complate=0';
    $params[':moduleid'] = $module['id'];
    $list = pdo_fetchall($sql, $params);
    foreach ($list as &$item) {
        $item['printdata'] = iunserializer($item['printdata']);
    }
    unset($item);
    return $list;
}

/**
 * 打印机注册
 *    检查打印机是否注册，
 *    没有注册插入打印机信息，返回注册id
 * @param $data
 * @return array
 */
function biz_Print_Register($data, $module)
{
    $sql = " select * from " . tablename('printer');
    $where = ' where `moduleid`=:moduleid and `index`=:index';
    $param = array(':moduleid' => $module['id'], ':index' => $data['index']);
    $print = pdo_fetch($sql . $where, $param);
    if (empty($print)) {
        $insert = array('title' => $data['name'], 'index' => $data['index'],
            'moduleid' => $module['id'],
            'type' => $data['type'],
            'createtime' => TIMESTAMP,
            'reporttime' => TIMESTAMP,
            'status' => '创建',
        );
        pdo_insert('printer', $insert);
        $id = pdo_insertid();
        $print = pdo_fetch($sql . " where id=:id", array(':id' => $id));
    }
    //PrinterName,printerId,type
    return $print;
}


function biz_Print_Delete($data, $module)
{
    $sql = " SELECT * FROM " . tablename('printer') . " where  id=:id ";
    $print = pdo_fetch($sql, array(':id' => $data['index']));
    if (!empty($print) && ($print['title'] == $data['name']) && ($print['moduleid'] == $module['id'])) {
        pdo_delete('printer', array('id' => $print['id']));
        return true;
    }
    return false;
}

function biz_Print_getTemplateById($id)
{
    $sql = " select id,title,printtype,content from " . tablename('printtemplate');
    $sql .= ' where `id`=:id ';
    return pdo_fetch($sql, array('id' => $id));
}


/**
 * 打印机状态汇报
 * 更新对应打印机的状态
 * @param $data
 */
function biz_Print_StatusReport($data, $module)
{
    $printer = db_getPrinter($data['key']);
    if ($printer['moduleid'] == $module['id']) {
        pdo_update('printer', array(
            'status' => $data['status'],
            'reporttime' => TIMESTAMP,
        ), array('id' => $printer['id']));
        return true;
    } else {
        return false;
    }

}

/**
 * 打印任务汇报
 *  更新对应任务的状态
 * @param $data
 * @return array
 */
function biz_Print_TaskReport($data, $module)
{
    $task = db_getPrintTask($data['key']);
    if (($task['moduleid'] == $module['id']) && empty($task['complate'])) {
        $update = array('status' => $data['status'],
            'updatetime' => TIMESTAMP
        );
        if ($data['status'] == '完成') {
            $update['complate'] = 1;
        }
        pdo_update('printtask', $update, array('id' => $task['id']));
        return true;
    } else {
        return false;
    }
}

#endregion


#region 文件提示打印处理

function print_checkChipsEnable($chips, $printType, &$msg)
{
    global $_W;
    $enable = true;
    if (empty($chips)) {
        $enable = false;
        $msg = '无效的认筹单!';
    } else {
        if ($chips['deleted']) {
            $enable = false;
            $msg = '认筹单已做废!';
        }
    }
    if ($_W['isfounder']) {
        $enable = false;
        $msg = '操作人员必须为业务人员!';
    }
    if ($enable) {
        switch ($printType) {
            case 2:
                if ($chips['premoney'] <= 0 || $chips['pretype'] != 1) {
                    $enable = false;
                }
                break;
            case 3:
                if ($chips['premoney'] + $chips['ordermoney'] < $chips['shouldpay']) {
                    $msg = '未交足定金，无法打印!';
                    $enable = false;
                }
                break;
            case 4:
                if ($chips['premoney'] + $chips['ordermoney'] < $chips['shouldpay']) {
                    $msg = '未交足定金，无法打印!';
                    $enable = false;
                }
                if ($enable) {
                    $enable = biz_checkChipsStatus($chips, 8);
                    if (!$enable) {
                        $msg = '未确认认购，没有生成供款明细，无法打印!';
                    }
                }
                break;
        }
    }
    if ($enable && in_array($printType, array(2, 3))) {

        $set = biz_getInvoConfig($_W['project'], $printType - 1);

        if (empty($set) || empty($set['InvoGUID'])) {
            $msg = '未配置票据信息，无法打印!';
            $enable = false;
        }
    }
    if ($enable) {
        $task = db_getNoPrintTask($_W['project']['projguid'], $chips['qrcode'], $printType);
        if (!empty($task)) {
            $enable = false;
            $msg = date('Y-m-d H:i:s', $task['createtime']) . '创建的打印任务正在等待处理。。。<br/><br/>请等待打印完成后再打印！';
        }
    } else {
        if (empty($msg)) {
            $msg = '认筹单无法打印!';
        }
    }
    return $enable;
}

function print_Confirm($chips, $url, $printType)
{
    global $_W, $_GPC;
    disableWebCache();
    $templates = biz_getPrintTemplates($_W['project']['projguid'], $printType, 'id');
    if (empty($templates) || count($templates) == 0) {
        echo '无可用的打印模板，请找管理配置模板!';
        exit;
    }
    $prints = biz_getAllPrinter($_W['project']['projguid']);
    if (empty($prints) || count($prints) == 0) {
        echo '无可用的打印机，请找管理员确认打印机配置!';
        exit;
    }
    $maxcopy = 3;
    $copy = $printType == 4 ? 3 : 1;

    //读取上次使用的打印机
    if (!empty($_GPC['__print'])) {
        $lastPrintId = intval($_GPC['__print']);
    } else {
        $lastPrintId = $prints[0]['id'];
    }
    $titles = biz_getDictionary('printtype');
    $printTitle = $titles[$printType];
    if (empty($printTitle)) {
        echo '无效的打印调用!';
        exit;
    }
    //处理是否已打印过的提示
    $showMsg = biz_checkChipsStatus($chips, $printType, 'printstatus');
    //诚意金可多份（订金？）
    if ($showMsg && in_array($printType, array(2, 3))) {
        $bill = biz_getBill($chips['qrcode'], $printType - 1, false);
        $showMsg = empty($bill);
        unset($bill);
    }

    include template('common/print', TEMPLATE_INCLUDEPATH);
}


function print_addTask($chips, $backUrl, $printType)
{
    global $_W, $_GPC;
    $templates = biz_getPrintTemplates($_W['project']['projguid'], $printType, 'id');
    if (empty($templates) || count($templates) == 0) {
        message('无可用的打印模板，请找管理配置模板!', $backUrl, 'error');
    }
    $templateId = trim($_GPC['template']);
    if (!isset($templates[$templateId])) {
        message('无效的打印模板', $backUrl);
    }
    $prints = biz_getAllPrinter($_W['project']['projguid']);
    if (empty($prints) || count($prints) == 0) {
        message('无可用的打印机，请找管理员确认打印机配置!', $backUrl, 'error');
    }
    $titles = biz_getDictionary('printtype');
    $printTitle = $titles[$printType];
    if (empty($printTitle)) {
        message('无效的打印调用!', $backUrl, 'error');
    }
    $template = $templates[$templateId];
    $printId = intval($_GPC['printer']);
    //记录当前使用的打印机
    isetcookie('__print', $printId);
    $print = $prints[$printId];
    if (!biz_checkChipsStatus($chips, $printType, 'printstatus')) {
        db_updateChipsStatus($chips['id'], $printType, false, 'printstatus');
    }
    $copy_max = 9;
    $copy = intval($_GPC['copy']);
    if ($copy <= 0) {
        $copy = 1;
    }
    if ($copy > $copy_max) {
        $copy = $copy_max;
    }
    $printdata = biz_Print_getTemplateData($templateId, $chips);
    $task = array(
        'projguid' => $chips['projguid'],
        'title' => $chips['cname'] . '-' . $printTitle,
        'printid' => $printId,
        'moduleid' => $print['moduleid'],
        'templateid' => $templateId,
        'templatename' => $template['title'],
        'key' => $chips['qrcode'],
        'printname' => $print['title'],
        'printtype' => $printType,
        'printdata' => iserializer($printdata),
        'createid' => $_W['uid'],
        'creator' => $_W['username'],
        'copy' => $copy,
        'status' => '等待',
        'createtime' => TIMESTAMP
    );
    if (pdo_insert('printtask', $task)) {
        message('数据已进入排队打印中，请稍候...', $backUrl);
    } else {
        message('写入打印任务数据出错', $backUrl);
    }
}

#endregion

#region 票据处理


function biz_getInvoConfig($project, $BillType)
{
    if (empty($project)) {
        global $_W;
        $project = $_W['project'];
    }
    $set = biz_unserializer($project, 'finance');
    if ($BillType == 1) {
        $set = $set['prepay'];
    } else {
        $set = $set['order'];
    }
    return $set;
}


/**
 *获得打印票据信息
 *  更新票据总信息
 *  增加票据明细
 */
function biz_getPrintInvoNo($project, $BillType)
{
    $set = biz_getInvoConfig($project, $BillType);
    //并发处理方式？一次生成多个号码？，单次加锁
    $res = array('BatchNo' => $set['BatchNo'], 'InvoNo' => '');
    $key = 'Bill_' . $set['InvoGUID'];
    $invoice = biz_getInvoice($set['InvoGUID']);
    if (memcached_addKey($key)) {
        $max = biz_getLastBillNo($set['InvoGUID']);
        $detail = biz_insertInvoiceDetail($invoice, $max);
        $res['InvoNo'] = "{$invoice['Prefix']}{$max}";
        $res['InvoDetailGUID'] = $detail['InvoDetailGUID'];
        memcached_delete($key);
    }
    return $res;
}

/**
 * 生成打印协议序号
 * @param $chips
 */
function biz_updateChipsProtocolNo(&$chips)
{

    //从项目配置获取前缀配置
    $project = biz_getProject($chips['projguid']);
    $set = biz_unserializer($project, 'finance');
    $prefix = '';
    if (!empty($set['Prefix'])) {
        $prefix = $set['Prefix'];
    }
    $key = 'pro_' . $project['projguid'];
    if (memcached_addKey($key)) {
        $index = pdo_fetchcolumn('select count(*) from ims_chips where QSDate>:time and projguid=:projguid',
            array(':time' => strtotime(date('Y-m-d')), ':projguid' => $chips['projguid']));
        $no = $prefix . date('ymd', TIMESTAMP) . sprintf('%04d', $index + 1);
        $chips['ProtocolNO'] = $no;
        $chips['ordertime'] = TIMESTAMP;
        $chips['QSDate'] = TIMESTAMP;
        pdo_update('chips', array('ProtocolNO' => $no, 'QSDate' => TIMESTAMP,'ordertime'=>TIMESTAMP), array('id' => $chips['id']));
        memcached_delete($key);
    };

}

function biz_updateChipsNo(&$chips)
{

    //从项目配置获取前缀配置
    $project = biz_getProject($chips['projguid']);
    $set = biz_unserializer($project, 'finance');
    $prefix = '';
    if (!empty($set['ChipsPre'])) {
        $prefix = $set['ChipsPre'];
    }
    $key = 'chipsNo_' . $project['projguid'];
    if (memcached_addKey($key)) {
        $index = pdo_fetchcolumn('select count(*) from ims_chips where printdate>:time and projguid=:projguid',
            array(':time' => strtotime(date('Y-m-d')), ':projguid' => $chips['projguid']));
        $no = $prefix . date('ymd', TIMESTAMP) . sprintf('%04d', $index + 1);
        $chips['ChipsNo'] = $no;
        $chips['printdate'] = TIMESTAMP;
        pdo_update('chips', array('ChipsNo' => $no, 'printdate' => TIMESTAMP), array('id' => $chips['id']));
        memcached_delete($key);
    };

}


function biz_getInput2Bill(&$bill)
{
    //检查金额是否允许修改
    $result = false;
    global $_GPC;
    $money = 0;
    $f = $_GPC['finance'];
    //转换成组对象
    $c = count($f['money']) - 1;
    $finance = array();
    while ($c > -1) {
        $m = floatval($f['money'][$c]);
        if ($m > 0) {
            $money += $m;
            array_unshift($finance, array('money' => $m, 'bank' => $f['bank'][$c], 'note' => $f['note'][$c], 'FsettleNo' => $f['FsettleNo'][$c]));
            $c--;
        }
    }
    $result = $bill['Money'] != $money;
    $bill['Money'] = $money;
    $bill['finance'] = $finance;
    return $result;
}

function biz_addBill($chips, $bill, $billType = 1)
{
    global $_W;
    $jkr = !empty($chips['holdername']) ? $chips['cname'] . ',' . $chips['holdername'] : $chips['cname'];
    $pj = array(
        'BillGUID' => $bill['BillGUID'],
        'Qrcode' => $chips['qrcode'],
        'ProjGUID' => $chips['projguid'],
        'Money' => $bill['Money'],
        'Jkr' => $jkr,
        'BillType' => $billType,
        'Details' => iserializer($bill['finance']),
        'createid' => $_W['uid'],
        'createtime' => TIMESTAMP,
    );
    if (isset($bill['Printed'])) {
        $pj ['Printed'] = $bill['Printed'];
    }
    return pdo_insert('bill', $pj);
}

function biz_updateBillOfInvoNo($bill)
{
    $result = false;
    global $_W;
    if (empty($bill['InvoNo'])) {
        $project = $_W['project'];
        $invoice = biz_getPrintInvoNo($project, $bill['BillType']);
        if (!empty($invoice['InvoNo'])) {
            $data = array(
                'BatchNo' => $invoice['BatchNo'],
                'InvoNo' => $invoice['InvoNo'],
                'InvoDetailGUID' => $invoice['InvoDetailGUID'],
                'Kpr' => $_W['username'],
                'KpDate' => date('Y-m-d H:i:s', TIMESTAMP),
                'Printed' => 1,
                'createtime' => TIMESTAMP
            );
            $result = pdo_update('bill', $data, array('BillGUID' => $bill['BillGUID']));
        }
    } else {
        $result = pdo_update('bill', array('Printed' => 1), array('BillGUID' => $bill['BillGUID']));
    }
    return $result !== false;
}

function biz_getInvoice($InvoGUID)
{
    $key = 'Invo_' . $InvoGUID;
    $callback = function () use ($InvoGUID) {
        return db_getInvoiceById($InvoGUID);
    };
    return cache_GetData($key, $callback);
}

function db_getInvoGUID($bitchNo, $prefix, $projGUID)
{
    //在_p_invoice增加ProjGUID字段
    $sql = 'select * from ' . tablename('p_invoice') . ' where BatchNo=:BatchNo and Prefix=:Prefix and ProjGUID=:ProjGUID';
    return pdo_fetch($sql, array(':BatchNo' => $bitchNo, ':Prefix' => $prefix, ':ProjGUID' => $projGUID));
}

function biz_getInvoiceByBatchNo($batchNo, $prefix, $project)
{
    global $_W;
    $invoice = db_getInvoGUID($batchNo, $prefix, $project['projguid']);
    if (empty($invoice)) {
        $invoice = array(
            'InvoGUID' => GUID(),
            'BatchNo' => $batchNo,
            'Prefix' => $prefix,
            'ProjGUID' => $project['projguid'],
            'InvoType' => '收据',
            'BUGUID' => $project['BUGUID'],
            'DjDate' => Date('Y-m-d H:n:s', TIMESTAMP),
            'Djr' => $_W['username'],
            'BgnNo' => '1',
            'Application' => '售楼业务',

        );
        pdo_insert('p_invoice', $invoice);
        //pdo_insert('p_invoice2proj',array('InvoGUID'));
    }
    return $invoice;
}

function db_getInvoiceById($invoGUID)
{
    $sql = 'select * from ' . tablename('p_invoice') . ' where InvoGUID=:InvoGUID';
    return pdo_fetch($sql, array(':InvoGUID' => $invoGUID));
}

function biz_getLastBillNo($invoGUID)
{
    //最下一个票据号码？，使用查最大值？或者用Invoice对应的endno号？
    $sql = 'select max(InvoNO) from ' . tablename('p_invoicedetail') . ' where InvoGUID=:InvoGUID';
    $max = pdo_fetchcolumn($sql, array(':InvoGUID' => $invoGUID));
    if (empty($max)) {
        $max = 1;
    } else {
        $max = intval($max) + 1;
    }
    return sprintf('%06d', $max);
}

function biz_insertInvoiceDetail($invoice, $invoNo)
{
    global $_W;
    $insert = array(
        'InvoDetailGUID' => GUID(),
        'InvoGUID' => $invoice['InvoGUID'],
        'Invotype' => $invoice['Invotype'],
        'Lyr' => $_W['username'],
        'LyDate' => Date('Y-m-d H:n:s', TIMESTAMP),
        'InvoNO' => $invoNo,
        'Status' => '已开'
    );
    //未开
    if (pdo_insert('p_invoicedetail', $insert)) {
        return $insert;
    } else {
        return false;
    }

}


#endregion

#region 模板数据处理


function biz_Print_getDataField($templateType)
{
    //认筹单公用字段
    static $baseField = array(
        '项目名称', '客户名称', '权益人', '权益人证件', '国籍', '客户性别', '证件号码', '证件类型', '手机号码', '通讯地址', '邮编',
        '家庭电话', '二维码','代理公司', '业务员', '打印日期', '打印日期-年', '打印日期-月', '打印日期-日', '空','零'
    );
    static $typeFields = array(
        1 => array(
            '认筹书编号',  '辅助代理', '具体意向1', '具体意向2', '具体意向3', '备注'
        ),
        2 => array(
            '诚意金', '诚意金大写', '收款人', '票据批次号', '票据编号'
        ),
        3 => array(
            '定金', '定金大写', '收款人', '票据批次号', '票据编号', '楼栋名称', '房间号', '房间名'
        ),
        4 => array(
            '楼栋名称', '房间号', '房间名', '建筑面积', '套内面积', '价格', '成交价', '成交价大写', '付款方式', '折扣', '建筑单价', '套内单价',
            '定金', '定金大写', '定金+首期', '首期房款日期', '支付日期限制', '首期房款金额', '按揭金额', '按揭百分比',
            '维修基金', '预售证号', '预售证日期大写', '认购书编号', '备注'
        )
    );

    return array_merge($baseField, $typeFields[$templateType]);
}


/**
 * 获取认筹单不同模板类型的打印原始数据
 * @param $chips
 * @param $templateType
 * @return array
 */
function biz_Print_getChipsData($chips, $templateType)
{
    $user = $chips['user'];
    if (!is_array($user)) {

    }
    $data = array(
        '客户名称' => $chips['cname'],
        '二维码' => $chips['qrcode'],
        '客户性别' => $user['Gender'],
        '证件号码' => $user['CardID'],
        '手机号码' => $user['MobileTel'],
        '通讯地址' => $user['Address'],
        '邮编' => $user['PostCode'],
        '家庭电话' => $user['HomeTel'],
        '证件类型' => $user['CardType'],
        '国籍' => $user['Country'],
        '空' => '',
        '零'=>'0'
    );
    if(empty($data['国籍'])){
        $data['国籍']='中国';
    }
    $res = biz_getChipsCustomerInfo($chips, true);
    $data['权益人'] = $res['name'];
    $data['权益人证件'] = $res['card'];

    $project = biz_getProject($chips['projguid']);
    $data['项目名称'] = $project['projname'];

    $printDate = TIMESTAMP;
    if ($templateType > 2) {
        $room = db_getRoomByGUID($chips['roomguid']);
        $build = db_getBuildingById($room['BldGUID']);
        $data['楼栋名称'] = $build['BldName'];
        $data['预售证号'] = $build['PreSaleNo'];

        $data['预售证日期大写'] = Date2Cn($build['PreSaleDate']);
        $data['房间名'] = $room['ShowCode'];
        $data['房间号'] = $room['Room'];
        $data['定金'] = number_format($chips['premoney'] + $chips['ordermoney'], 2);
        $data['定金大写'] = Num2Cny($chips['premoney'] + $chips['ordermoney']);
    }
    $data['代理公司'] = $chips['agency'];
    $data['业务员'] = $chips['salesman'];
    switch ($templateType) {
        case 1:
            $data['认筹书编号']=$chips['ChipsNo'];

            $data['辅助代理'] = $chips['agencychild'];
            $intend = explode(',', $chips['intendroom']);
            $data['具体意向1'] = empty($intend[0]) ? '' : $intend[0];
            $data['具体意向2'] = empty($intend[1]) ? '' : $intend[1];
            $data['具体意向3'] = empty($intend[2]) ? '' : $intend[2];
            $data['备注'] = $chips['remark'];
            $printDate = $chips['printdate'];
            break;
        case 2:
            //只打印最后一次
            $item = biz_getBill($chips['qrcode'], 1, 1);
            $data['诚意金'] = number_format($item['Money'], 2);
            $data['诚意金大写'] = Num2Cny($item['Money']);
            $data['交款人'] = $item['Jkr'];
            $data['收款人'] = $item['Kpr'];
            $data['票据批次号'] = $item['BatchNo'];
            $data['票据编号'] = $item['InvoNo'];
            $printDate = $item['createtime'];
            break;
        case 3:
            $item = biz_getBill($chips['qrcode'], 2, 1);
            $data['交款人'] = $item['Jkr'];
            $data['收款人'] = $item['Kpr'];
            $data['票据批次号'] = $item['BatchNo'];
            $data['票据编号'] = $item['InvoNo'];
            $printDate = $item['createtime'];
            break;
        case 4:
            $data['建筑面积'] = $room['BldArea'];
            $data['套内面积'] = $room['TnArea'];
            $data['建筑单价'] = number_format($room['Price'], 2);
            $data['套内单价'] = number_format($room['TnPrice'], 2);
            $data['价格'] = number_format($room['Total'], 2);
            $data['成交价'] = number_format($room['RoomTotal'], 2);
            $data['成交价大写'] = Num2Cny($room['RoomTotal']);
            $data['折扣'] = $room['DiscntValue'];
            $data['认购书编号'] = $chips['ProtocolNO'];
            $pay = biz_unserializer($project, 'payform');
            $data['付款方式'] = $pay['Payform']['PayformName'];

            $sql = " select * from " . tablename('s_Fee') . " where TradeGUID =:qrcode ";
            $fees = pdo_fetchall($sql, array(':qrcode' => $chips['qrcode']), 'ItemName');
            if (isset($fees['首期'])) {
                $data['首期房款日期'] = date('Y年m月d日', strtotime($fees['首期']['lastDate']));
                $data['首期房款金额'] = number_format($fees['首期']['Amount'], 2);
                $data['首期房款百分比'] = $fees['首期']['Rate'];

                $data['定金+首期'] = number_format($fees['首期']['Amount'] + $chips['shouldpay'], 2);
            }
            if (isset($fees['银行按揭'])) {
                $data['按揭金额'] = number_format($fees['银行按揭']['Amount'], 2);
                $data['按揭百分比'] = $fees['银行按揭']['Rate'];
            }
            if (isset($fees['维修基金'])) {
                $data['维修基金'] = number_format($fees['维修基金']['Amount'], 2);
            }
            $data['备注'] = $chips['remark'];
            $printDate = $chips['QSDate'];
            break;
    }

    $date = getdate($printDate);
    $data['打印日期'] = date('Y年m月d日', $printDate);
    $data['打印日期-年'] = $date['year'];
    $data['打印日期-月'] = $date['mon'];
    $data['打印日期-日'] = $date['mday'];
    return $data;
}

function Date2Cn($date, $fanti = true)
{
    $_date = $date;
    if (!is_numeric($date)) {
        $_date = strtotime($date);
    }

    $result = date('Y', $_date) . '年';
    $result .= implode('十', str_split(date('n', $_date))) . '月';
    $result .= implode('十', str_split(date('j', $_date))) . '日';
    $result = str_replace(array('1十', '十0'), '十', $result);
    $char = str_split('零一二三四五六七八九', 3);
    if (!empty($fanti)) {
        $result = str_replace('十', '拾', $result);
        $char = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
    }
    $result = str_replace(str_split('0123456789'), $char, $result);
    return $result;
}

function Num2Cny($num)
{
    $return = '';
    $unit = array('分', '角', '元', '整');
    $dw = array('', '拾', '佰', '仟', '', '万', '亿', '兆');
    $char = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
    preg_match_all("/(\d*)\.?(\d*)/", $num, $ar);
    if ($ar[2][0] != '') {
        $return .= $ar[2][0][0] == 0 ? '' : $char[$ar[2][0][0]] . $unit[1];
        if (isset($ar[2][0][1])) $return .= $char[$ar[2][0][1]] . $unit[0];
    } else {
        $return .= $unit[3];
    }
    if ($ar[1][0] != '') {
        $str = strrev($ar[1][0]);
        $len = strlen($str);
        $return = $unit[2] . $return;
        for ($i = 0; $i < $len; $i++) {
            $out[$i] = $char[$str[$i]];
            $out[$i] .= $str[$i] != '0' ? $dw[$i % 4] : '';
            if ($str[$i] + $str[$i - 1] == 0)
                $out[$i] = '';
            if ($i % 4 == 0)
                $out[$i] .= $dw[4 + floor($i / 4)];
        }
        $return = join('', array_reverse($out)) . $return;
    }
    return $return;
}

function num2cn($num, $mode = true)
{
    $char = array("零", "壹", "贰", "叁", "肆", "伍", "陆", "柒", "捌", "玖");
    $dw = array("", "拾", "佰", "仟", "", "萬", "億", "兆");
    $dec = "點";
    $retval = "";
    if ($mode)
        preg_match_all("/^0*(d*).?(d*)/", $num, $ar);
    else
        preg_match_all("/(d*).?(d*)/", $num, $ar);

    if ($ar[2][0] != "")
        $retval = $dec . ch_num($ar[2][0], false); //如果有小数，先递归处理小数 
    if ($ar[1][0] != "") {
        $str = strrev($ar[1][0]);
        for ($i = 0; $i < strlen($str); $i++) {
            $out[$i] = $char[$str[$i]];
            if ($mode) {
                $out[$i] .= $str[$i] != "0" ? $dw[$i % 4] : "";
                if ($str[$i] + $str[$i - 1] == 0)
                    $out[$i] = "";
                if ($i % 4 == 0)
                    $out[$i] .= $dw[4 + floor($i / 4)];
            }
        }
        $retval = join("", array_reverse($out)) . $retval;
    }
    return $retval;
}

/**
 * 获取模板对应的打印数据
 * @param $templateId
 * @param $chips
 * @return mixed
 */
function biz_Print_getTemplateData($templateId, $chips)
{
    $template = db_getPrintTemplate($templateId);
    $base = biz_Print_getChipsData($chips, $template['printtype']);
    $data = biz_unserializer($template, 'datamap');
    foreach ($data as $k => $v) {
        if (isset($base[$v])) {
            $data[$k] = $base[$v];
        } else {
            $data[$k] = '';
        }
    }
    return $data;
}


#endregion

#region 上传模板文件处理

function  GetUploadFile($fileTag)
{
    global $_W;
    $filename = $_FILES[$fileTag]['tmp_name'];
    $dest = IA_ROOT . '/' . $_W['config']['upload']['attachdir'] . '/' . GUID() . '.tmp';
    @move_uploaded_file($filename, $dest);
    return file_exists($dest) ? $dest : false;
}

function  GetTagsOfXml($filename)
{
    $content = '';
    if (!$filename || !file_exists($filename)) return false;
    $zip = zip_open($filename);
    if (!$zip || is_numeric($zip)) return false;
    while ($zip_entry = zip_read($zip)) {
        if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
        if (zip_entry_name($zip_entry) != 'word/document.xml') continue;
        $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
        zip_entry_close($zip_entry);
    }// end while
    zip_close($zip);

    preg_match_all('/w:name=\"([^\"]+)\"/', $content, $match);
    if (!empty($match)) {
        $tags = $match[1];
        foreach ($tags as $k => $v) {
            if (strpos($v, '_Go') !== false) {
                unset($tags[$k]);
            }
        }
        return $tags;
    } else {
        return false;
    }
}

#endregion





