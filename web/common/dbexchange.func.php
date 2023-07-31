<?php
/**
 * 数据导入、导出处理
 */


#region 导入处理

function process_Exception_handler($exception)
{
    showLog("出错异常: ", $exception->getMessage());
}

function importFromErp_handler($guid)
{
    global $_W;
    //set_error_handler('process_Exception_handler',E_ALL ^ ~E_NOTICE ^ E_WARNING );
    $progress = 10;
    $error = false;
    updateProgress($progress, '导入项目信息');

    if (importErp_Project($guid)) {
        showLog('导入项目信息成功');
        updateProgress(10, '导入出错');
    } else {
        showLog('导入出错');
        updateProgress(-1, '导入出错');
        return false;
    }
    $calls = array(
        'AllOption' => '主体项目',
        'BuildInfo' => '楼盘信息',
        'UserInfo' => '用户信息',
        'ProjCustomer' => '客户信息',
        'ProjPayAndDiscount' => '支付及折扣信息',
        'ProjInvoice' => '票据信息',
    );
    $step = 15;
    $clear = false;
    pdo()->setShowDebugExcept(true);
    foreach ($calls as $c => $m) {
        $progress += $step;
        updateProgress($progress, '正在获取' . $m);
        $function = 'importErp_' . $c;
        try {
            $function($guid, true);
        } catch (Exception $e) {
            showLog('导入' . $m . '出错：' . $e->getMessage() . '<br>');
            $error = true;
        }
        sleep(1);
    }
    //更新项目状态
    if (!$error) {
        pdo_update('project', array('status' => 1), array('projguid' => $guid));
    }
    showLog('导入完成');
    updateProgress(100, '导入完成');
    pdo()->setShowDebugExcept(false);

}

/**
 * 通过ProjGUID从erp中导入数据
 * 序列化存储产品、房型数据
 * @param $guid
 * @return bool
 */
function importErp_Project($guid)
{
    $state = false;
    $project = erp_getProject($guid);
    $housetype = erp_getBuildHuXing($guid);
    $product = erp_getBuildProduct($guid);
    $data = array(
        'projguid' => $project['ProjGUID'],
        'projname' => $project['ProjShortName'],
        'ProjCode' => $project['ProjCode'],
        'ParentCode' => $project['ParentCode'],
        'Level' => $project['Level'],
        'BUGUID' => $project['BUGUID'],
        'product' => iserializer($product),
        'housetype' => iserializer($housetype),
        'createtime' => TIMESTAMP,
        'changetime' => TIMESTAMP,
        'status' => 9
    );
    //状态为关闭
    if (!empty($data['ParentCode'])) {
        $p = erp_getProjectByCode($data['ParentCode']);
        if (!empty($p)) {
            $data['ParentGUID'] = $p['ProjGUID'];
            unset($p);
        }
    }
    if (is_array($project)) {
        $state = pdo_insert('project', $data, true);
    }
    return $state;
}


/**
 * 导入项目所有相关参数
 * @param $guid
 * @param bool $clear
 */
function importErp_AllOption($guid, $clear = true)
{
    try {
        $project = biz_getProject($guid, true, false);
        if (!empty($project)) {
            //银行取的楼盘的BUGUID
            importErp_ParamOption('s_RzBank', $project['BUGUID'], true);
            if ($project['Level'] == 3) {
                $guid = $project['ParentGUID'];
            }
            importErp_ParamOption('s_DLGS', $guid, true);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * 导入指定类别范围的参数
 * @param $ParamName
 * @param $ScopeGUID
 * @param bool $clear
 */
function importErp_ParamOption($ParamName, $ScopeGUID, $clear = true)
{
    try {
        if ($clear) {
            pdo_delete('mybizparamoption', array("ParamName" => $ParamName, "ScopeGUID" => $ScopeGUID));
        }
        $list = erp_getAllParams($ParamName, $ScopeGUID);
        foreach ($list as $item) {
            pdo_insert('mybizparamoption', $item);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * 导入项目客户信息
 *   p_cstattach 客户附加表
 *   p_customer  客户信息表
 * @param $guid
 * @param bool $clear
 */
function importErp_ProjCustomer($guid, $clear = true)
{
    try {
        if ($clear) {
            pdo_delete('p_cstattach', array('ProjGUID' => $guid));
        }
        //要过滤无效客户信息，有cardid才导入
        $list = erp_getCstAttach($guid);
        foreach ($list as $item) {
            pdo_insert('p_cstattach', $item);
        }
        $list = erp_getAllCustomers($guid);
        foreach ($list as $item) {
            pdo_insert('p_customer', $item, true);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * 导入项目楼盘信息
 * @param $guid
 */
function importErp_BuildInfo($guid, $clear = true)
{
    try {
        if ($clear) {
            pdo_delete('p_building', array('ProjGUID' => $guid));
        }
        $builds = erp_getBuilds($guid);
        foreach ($builds as $item) {
            pdo_insert('p_building', $item);
            $bldGUID = $item['BldGUID'];
            $units = erp_getBuildUnit($bldGUID);
            if ($clear) {
                pdo_delete('p_buildunit', array('BldGUID' => $bldGUID));
            }
            foreach ($units as $u) {
                pdo_insert('p_buildunit', $u);
            }
            if ($clear) {
                pdo_delete('p_room', array('BldGUID' => $bldGUID));
            }
            $rooms = erp_getBuildRoom($bldGUID);
            foreach ($rooms as $r) {
                $r['ShowCode'] = biz_getRoomName($r, $item);
                pdo_insert('p_room', $r);
            }
        }
    } catch (Exception $e) {
        throw $e;
    }
}


function importErp_ProjPayAndDiscount($guid, $clear = true)
{
    if ($clear) {
        pdo_delete('s_discountdefine', array('ProjGUID' => $guid));
    }
    $discount = erp_getDiscountDefine($guid);
    foreach ($discount as $i) {
        pdo_insert('s_discountdefine', $i, true);
    }
    if ($clear) {
        pdo_delete('s_payform', array('ProjGUID' => $guid));
    }
    $list = erp_get_PayForm($guid);
    foreach ($list as $i) {
        pdo_insert('s_payform', $i, true);
        if ($clear) {
            pdo_delete('s_paydetail', array('PayFormGUID' => $i['PayFormGUID']));
        }
        $details = erp_get_PayDetail($i['PayFormGUID']);
        foreach ($details as $d) {
            pdo_insert('s_paydetail', $d, true);
        }
    }

}


/**
 * 删除项目所有相数据
 * @param $guid
 */
function import_deleteProjAllInfo($guid)
{
    //删除主表
    pdo_delete('project', array('projguid' => $guid));
    //
}

/**
 * 导入项目票据信息
 * @param $guid
 */
function importErp_ProjInvoice($guid, $clear = true)
{

    if ($clear) {
        pdo_delete('p_Invoice2Proj', array('ProjGUID' => $guid));
    }
    $i2p = erp_getInvoice2Proj($guid);
    foreach ($i2p as $i) {
        pdo_insert('p_Invoice2Proj', $i, true);
        $invoice = erp_getInvoice($i['InvoGUID']);
        if (!empty($invoice)) {
            //增加项目冗余字符
            $invoice['ProjGUID'] = $guid;
            pdo_insert('p_Invoice', $invoice, true);
            if ($clear) {
                pdo_delete('p_InvoiceDetail', array('InvoGUID' => $i['InvoGUID']));
            }
            $details = erp_getInvoiceDetail($i['InvoGUID']);
            foreach ($details as $d) {
                pdo_insert('p_InvoiceDetail', $d);
            }
        }
    }
}


/**
 * 导入项目相关用户信息
 * @param $projGUID
 */
function importErp_UserInfo($projGUID, $clear = true)
{

    if ($clear) {
        pdo_delete('mystation', array('ProjGUID' => $projGUID));
    }
    $stations = erp_getStation($projGUID);
    foreach ($stations as $s) {
        pdo_insert('mystation', $s, true);
        if ($clear) {
            pdo_delete('mystationuser', array('StationGUID' => $s['StationGUID']));
        }
        $station_user = erp_getStation_User($s['StationGUID']);
        if (!empty($station_user)) {
            foreach ($station_user as $s_u) {
                pdo_insert('mystationuser', $s_u, true);
            }
            unset($station_user);
            $users = erp_getUsersByStation($s['StationGUID']);
            foreach ($users as $u) {
                pdo_insert('myuser', $u, true);
            }
            unset($users);
        }
    }
}


#endregion 

#region 导出数据至erp

function updateToErp_handler($projGUID, $option)
{
    //导出客户信息
    //export_customer($projGUID);
    $progress = 5;
    updateProgress($progress, '正在准备...');

    $cfg=MsSql()->getConfig();
    showLog("正在连接数据库{$cfg['host']}[{$cfg['database']}]...");
    unset($cfg);
    if(MsSql()->IsConnected()){
        showLog("连接数据库成功<br/>");
    }else{
        showLog(MsSql()->getErrors()[0]);
        updateProgress(100, '连接出错');
        exit;    
    }
    
    $importInvo=false;
    $step_item = intval(90 / count($option));
    $step = $step_item / 10;
    //分十次调用，每次1？
    $progressFunc = function ($value) use (&$progress, &$step) {
        $p = $progress + intval($step * $value);
        updateProgress($p, '');
        sleep(1);
    };
    foreach ($option as $item) {
        if ($item == 'voucher') {
            updateProgress($progress, '开始导出诚意金数据...');
            $res = export_Voucher($projGUID, $progressFunc);
            showLog("导出诚意金数据完成，{$res['total']}条数据，{$res['update']}条更新，{$res['failed']}条出错<br/>");
            $importInvo=true;
        }
        if ($item == 'order') {
            updateProgress($progress, '开始导出订金、订单数据...');
            $res = export_chips($projGUID, $progressFunc);
            showLog("导出订金数据完成，{$res['total']}条数据，{$res['update']}条更新，{$res['failed']}条出错<br/>");
            $importInvo=true;
        }

        if ($item == 'discount') {

            updateProgress($progress, '开始导入折扣数据...');
            importErp_ProjPayAndDiscount($projGUID);
            showLog("导入折扣数据完成<br/>");
        }
        if ($item == 'room') {
            updateProgress($progress, '开始导入房间数据...');
            importErp_BuildInfo($projGUID);
            showLog("导入房间数据完成<br/>");
        }
        if ($item == 'user') {
            updateProgress($progress, '开始导入用户数据...');
            importErp_UserInfo($projGUID);
            showLog("导入用户数据完成<br/>");
        }
        sleep(1);
        $progress += $step_item;
    }
    if($importInvo){
        updateProgress($progress, '开始导入票据信息...');
        $res = export_Invoice($projGUID, $progressFunc);
        showLog("导入票据完成，{$res['total']}条数据，{$res['update']}条更新，{$res['failed']}条出错<br/>");
    }
    updateProgress(100, '更新完成');
    showLog('更新数据完成<br/>');
    //记录日志
    pdo_update('project', array('changetime' => TIMESTAMP), array('projguid' => $projGUID));

}

// 在本地customer表中 && 不在erp数据表中的数据插入到erp
function export_customer($projGUID)
{
    $lis = local_getCustomer($projGUID);
    $items = erp_getAllCustomers($projGUID);
    //$keys = array_keys($lists);
    //$erpkeys = array_keys($items);
    $lists = array_diff($lis, $items);
    foreach ($lists as $list) {
        //MsSql()->insert('customer', $list);
        $sql = "INSERT INTO customer(CstGUID, CstName, CardID, MobileTel) ";
        $sql .= "values ('{$list['CstGUID']}', '{$list['CstName']}','{$list['CardID']}','{$list['MobileTel']}')";
        MsSql()->query($sql);
        // 插入客户特征表 CstAttribute（只插入guid）
        $sql2 = "INSERT INTO CstAttribute(CstGUID) ";
        $sql2 .= "values ('{$list['CstGUID']}')";
        MsSql()->query($sql2);
    }

    // 直接在erp 中插入 CstAttach
    $cstattach = local_getCstAttach($projGUID);
    foreach ($cstattach as $cst) {
        $sql = "INSERT INTO CstAttach(BUGUID, CstGUID, ProjGUID, UserGUID, CstAttachGUID, signguid) ";
        $sql .= "values ('{$cst['BUGUID']}','{$cst['CstGUID']}', '{$cst['ProjGUID']}','{$cst['UserGUID']}','{$cst['CstAttachGUID']}','{$cst['signguid']}')";
    }

}

function local_getCustomer($projGUID)
{
    $sql = 'select b.* from p_cstattach a,p_customer b where a.CstGUID=b.CstGUID ';
    $sql .= " and a.ProjGUID='{$projGUID}'";
    return pdo_fetchall($sql, 'CstGUID');

}

function local_getCstAttach($projGUID)
{
    $sql = 'select * from p_cstattach ' . " where ProjGUID='{$projGUID}'";
    return pdo_fetchall($sql);
}


/**
 * 导出诚意金信息至erp
 * 生成票据信息规则
 * return array()
 */
function export_Voucher($projGUID, $progressFunc)
{
    //生成财务单据表，s_Voucher
    $res = array('total' => 0, 'update' => 0, 'failed' => 0);
    $bills = db_getBills($projGUID, 1, true);
    $count = count($bills);
    $progress = 0;
    $index = 0;
    $showProgress = function () use (&$progress, $count, &$index, $progressFunc) {
        $index++;
        $value = intval($index * 5 / $count);
        if ($value > $progress) {
            $progress = $value;
            if (!empty($progressFunc)) {
                $progressFunc($value);
            }
        }
    };
    $project = biz_getProject($projGUID);
    $res['total'] = 0;
    foreach ($bills as $b) {
        $showProgress();
        if (($b['Money'] > 0) && ($b['ErpSync'] == 0) && ($b['Printed'] == 1)) {
            $res['total']++;
            $inserted=erp_write_Voucher($b,$project);
            if ($inserted) {
                //记录票据信息更新状态，标记为等待更新
                pdo_update('p_invoicedetail', array('ErpSync' => 2, 'signguid' => $projGUID),
                    array('InvoDetailGUID' => $b['InvoDetailGUID']));
                $res['update']++;
            } else {
                $res['failed']++;
                $error = MsSql()->getErrors();
                MsSql()->clearError();
                //记录出错信息
                logging('bill_'.$b['BillGUID'],$error);
            }
            

            pdo_update('bill', array('ErpSync' => $inserted ? 1 : 2, 'SyncTime' => TIMESTAMP),
                array('BillGUID' => $b['BillGUID']));
            
        }

    }

    return $res;
}


/**
 * 导入票据明细及关系表
 *    更新票据主表EndNo
 * @param $
 */
function export_Invoice($projGUID, $progressFunc)
{
    $res = array('total' => 0, 'update' => 0, 'failed' => 0);

    $invoKeys = array('InvoGUID', 'Invotype', 'BatchNo', 'Prefix', 'BgnNo', 'EndNo', 'Yzdw', 'Djr', 'DjDate', 'BuGUID', 'Remark', 'Application', 'signguid');
    $detailKeys = array('InvoDetailGUID', 'InvoGUID', 'Invotype', 'InvoNO', 'Lyr', 'LyDate', 'Status', 'signguid');
    $noteNo = function ($invo, $no) use (&$bgnNo, &$endNo) {
        $value = intval(str_replace($invo['Prefix'], '', $no));
        if ($value > $endNo) {
            $endNo = $value;
        }
        if ($value < $bgnNo) {
            $bgnNo = $value;
        }
    };
    $total = pdo_fetchcolumn('select count(*) from ' . tablename('p_invoicedetail') . ' where ErpSync=2');
    $progress = 0;
    $index = 0;
    $showProgress = function () use (&$progress, $count, &$index, $progressFunc) {
        $index++;
        $value = intval($index * 5 / $count);
        if ($value > $progress) {
            $progress = $value;
            if (!empty($progressFunc)) {
                $progressFunc($value);
            }
        }
    };

    $invoices = db_getInvoices($projGUID);
    foreach ($invoices as $invo) {
        $sql = 'select * from ' . tablename('p_invoicedetail') . ' where InvoGUID=:InvoGUID and ErpSync=2';
        $details = pdo_fetchall($sql, array(':InvoGUID' => $invo['InvoGUID']));

        $endNo = $invo['EndNo'];
        $bgnNo = $invo['BgnNo'];

        if (count($details) > 0) {
            $erp = MsSql()->fetch('select InvoGUID from p_Invoice ' . " where InvoGUID='{$invo['InvoGUID']}'");
            if (empty($erp)) {

                MsSql()->insertObject('p_Invoice2Proj', array('InvoGUID' => $invo['InvoGUID'], 'ProjGUID' => $invo['ProjGUID']));
                $invo['signguid'] = $projGUID;
                MsSql()->insertObject('p_Invoice', array_elements($invoKeys, $invo));
            }
            foreach ($details as $d) {
                $showProgress();
                $res['total']++;
                $noteNo($invo, $d['InvoNO']);
                $inserted = false;
                //检查是否存在？
                $data = MsSql()->fetch('select * from p_InvoiceDetail' . " where InvoDetailGUID='{$d['InvoDetailGUID']}'");
                if (empty($data)) {
                    $d['signguid'] = $projGUID;
                    $inserted = MsSql()->insertObject('p_InvoiceDetail', array_elements($detailKeys, $d));
                } else {
                    //处理存在？
                }
                if ($inserted) {
                    $res['update']++;
                } else {
                    $res['failed']++;
                }
                pdo_update('p_invoicedetail', array('ErpSync' => 1), array('InvoDetailGUID' => $d['InvoDetailGUID']));
            }
            $sql = "update p_Invoice set BgnNo={$bgnNo},EndNo={$endNo} where InvoGUID='{$invo['InvoGUID']}'";
            MsSql()->query($sql);
        }
    }
    return $res;
}



/**
 * 认筹数据转化为交易trade，trade2cst,s_Order，s_Fee，
 * @param $chips
 * @param $project
 */
function writeChipsToErp($chips, $payform)
{
    
    $result=false;
    try{
        //生成交易trade,再写入order
        $result= erp_Write_Trade($chips);
        if($result) {
            $result=  erp_Write_Order($chips, $payform);
        }
        if($result) {
            $result=erp_Write_fee($chips);
        }
        if($result) {
            erp_Write_Customer($chips);
        }
    }
    catch(Exception $e){
        logging("写入认筹数据[{$chips['qrcode']}]异常：",$e->getMessage());
        $result=false;
    }
 
    return $result;
}

function export_chips($projGUID, $progressFunc)
{
    $res = array('total' => 0, 'update' => 0, 'failed' => 0);

    $sql = "select * from " . tablename('chips') . " where projguid=:projguid and SyncTime=0 ";
    $list = pdo_fetchall($sql, array(':projguid' => $projGUID));


    $count = count($list);
    $progress = 0;
    $index = 0;
    $showProgress = function () use (&$progress, $count, &$index, $progressFunc) {
        $index++;
        $value = intval($index * 5 / $count);
        if ($value > $progress) {
            $progress = $value;
            if (!empty($progressFunc)) {
                $progressFunc($value);
            }
        }
    };
    $project = biz_getProject($projGUID);
    $payform = biz_unserializer($project, 'payform');
    $sql = "select * from " .tablename('s_discountdefine') ." where DiscntGUID=:DiscntGUID and ProjGUID=:ProjGUID ";
    foreach($payform['dis_id'] as $id){
        $payform['disc_Details'][]= pdo_fetch($sql,array(':DiscntGUID'=>$id,':ProjGUID'=>$projGUID));
    }
    foreach ($list as $chips) {
        $showProgress();
        if (!empty($chips['ProtocolNO'])) {
            $res['total']++;
            $result = writeChipsToErp($chips, $payform);
            if ($result) {
                pdo_update('chips', array('SyncTime' => TIMESTAMP), array('qrcode' => $chips['qrcode']));
                $res['update']++;
            } else {
                $error = MsSql()->getErrors();
                MsSql()->clearError();
                //记录出错信息
                logging($chips['qrcode'],$error);
                $res['failed']++;
            }
        }
    }
    return $res;
}




#endregion