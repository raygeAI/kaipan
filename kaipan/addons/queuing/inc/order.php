<?php

if (!in_array($op, array('qrcode', 'query', 'pay', 'print'))) {
    $op = 'qrcode';
}
if (in_array($op, array('qrcode', 'query'))) {
     
    if (checksubmit() && ($_GPC['submit'] == 'pay')) {
        $keyword = getInputGUID($_GPC['keyword']);
        if (empty($keyword) ) {
            message('请输入有效的认筹单号！'."[{$_GPC['keyword']}]", $this->createWebUrl($do), 'warning');
        }

        $chips = biz_getChipsByQrcode($keyword, 'id');
        if (empty($chips)) {
            message("无效的认筹单![{$keyword}]", $this->createWebUrl($do), 'error');
        } else {
            header('location:' . $this->createWebUrl($do, array('op' => 'pay', 'id' => $chips['id'])));
            exit;
        }

    }

    $status = array(1 => '需补定', 2 => '已足定');
    $condition = " `projguid`=:projguid and roomstatus>0 ";
    $pars = array(':projguid' => $_W['project']['projguid']);
    if (!$_W['isfounder']) {
        $condition .= ' and deleted=0 ';
        $condition .= ' and StationCode Like :code ';
        $pars[':code'] = "%{$_W['rights']['StationCode']}%";
    }

    if ($op == 'query') {
        $keyword = trim($_GPC['keyword']);

        if (!empty($keyword)) {
            $types = array('cname', 'cardid', 'mobile');
            $tindex = intval($_GPC['query']);
            $condition .= " AND `{$types[$tindex]}` LIKE :keyword";
            $pars[':keyword'] = "%{$keyword}%";
        }
        $selstatus = -1;
        if (isset($_GPC['status'])) {
            $selstatus = intval($_GPC['status']);
            if ($selstatus == 2) {
                $condition .= " AND (premoney+ordermoney>=shouldpay) ";
            }
            if ($selstatus == 1) {
                $condition .= " AND (premoney+ordermoney<shouldpay) ";
            }
        }
    } else {
        // 导出定金交款
        if ($_GPC['submit'] == '导出') {
            $list = pdo_fetchall("SELECT * FROM " . tablename('chips') . " WHERE {$condition}", $pars);
            load()->web('down');
            down_OrderInfo($list);
            exit;
        }
    }
    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;
    $start = ($pindex - 1) * $psize;
    $sql = "SELECT COUNT(*) FROM " . tablename('chips') . " WHERE {$condition}";
    $total = pdo_fetchcolumn($sql, $pars); //
    $sql = "SELECT * FROM " . tablename('chips') . " WHERE {$condition} ORDER BY `createtime` DESC  LIMIT {$start}, {$psize}";
    $pager = pagination($total, $pindex, $psize);
    $list = pdo_fetchall($sql, $pars);
    //金额票据处理
    foreach ($list as &$item) {
        $item['_money'] = $item['ordermoney'] + $item['premoney'];
        $item['_need'] = 0;
        if ($item['_money'] < $item['shouldpay']) {
            $item['_need'] = $item['shouldpay'] - $item['_money'];
        }
        //票据信息
        if (biz_checkChipsStatus($item, 3, 'printstatus')) {
            $bill = biz_getBill($item['qrcode'], 2, true);
            if (!empty($bill)) {
                $item['InvoNo'] = $bill['InvoNo'];
            }
        }
    }

    unset($item);
    include $this->template('order_list');
    exit;
};
if ($op == 'pay') {

    load()->web('print');
    $billType = 2;
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id);
    $this->CheckDataRight($chips);
    $enable = !empty($chips) && (!$chips['deleted']);
    $status = biz_getDictionary('chipstatus', true);
    $url = $this->createWebUrl($do);
    if (!empty($_GPC['back'])) {
        $url = $this->createWebUrl($_GPC['back'], array('id' => $chips['id']));
    }
    $urlEdit = $this->createWebUrl($do, array('id' => $id, 'op' => 'pay', 'edit' => true));

    if (!$enable) {
        message('无效数据或认筹单不允许修改！', $url);
    }
    if (empty($chips['roomstatus']  )) {
        message('认筹单未选房，不能录入订金信息！', $url);
    }
    $user_info = $chips['user'];
    $printed_Bills = biz_getBills($chips['qrcode'], 1, true);
    $inputBill = biz_getBill($chips['qrcode'], $billType, false);

    $totalMoney=$chips['ordermoney'] + $chips['premoney'];
    $enable=$totalMoney >= $chips['shouldpay'];
    $disableEdit = !isset($_GPC['edit']) || ($_GPC['edit'] != 1);
 
    if (!$enable&&empty($inputBill)  ) {
        $inputBill = array('printed' => 0, 'finance' => array(0 => array()));
        $disableEdit=false;
    }
 
    if (!empty($inputBill)) {
        $project = $_W['project'];
        $banks = biz_getBanks($project['BUGUID']);
        $project = biz_getProject($project['projguid']);
        $bank = biz_unserializer($project, 'bank');
    }
    if (checksubmit()) {
        if (empty($inputBill)) {
            message('认筹单无效数据，不能交款！', $url, 'error');
        }
        //保存中
        $updateMoney = biz_getInput2Bill($inputBill);
        if (empty($inputBill['BillGUID'])) {
            // 插入票据单相关信息
            if ($inputBill['Money'] > 0) {
                $inputBill['BillGUID'] = GUID();
                if (!biz_addBill($chips, $inputBill, 2)) {
                    $updateMoney = false;
                    message('认筹单定金交款，保存数据失败！', $url, 'error');
                }
            }
        } else {
            $update = array(
                'Money' => $inputBill['Money'],
                'Details' => iserializer($inputBill['finance']),
            );
            $updateMoney = pdo_update('bill', $update, array('BillGUID' => $inputBill['BillGUID']));
        }
        if ($updateMoney !== false) {
            $data = array(
                'ordermoney' => $inputBill['Money'],
            );
            pdo_update('chips', $data, array('id' => $chips['id']));

        }
        header('location:' . $this->createWebUrl($do, array('id' => $id, 'op' => 'pay')));
    }
    include $this->template('order_pay');
    exit;
}

if ($op == 'print') {
    disableWebCache();
    load()->web('print');
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    $printTitle = '定金交款';
    $type = 3;
    $msg = '';
    $enable = print_checkChipsEnable($chips, $type, $msg);
    if ($enable) {
        if ($_W['token'] == $_GPC['token']) {

            $bill = biz_getBill($chips['qrcode'], 2, null);
            if ($chips['ordermoney'] == 0) {
                //已足定，没有票据，生成票据0
                if (empty($bill)) {
                    $inputBill = array('BillGUID' => GUID(), 'Money' => 0, 'finance' => array(
                        array('money'=>0,'note'=>'足定换票')));
                    if (!biz_addBill($chips, $inputBill, 2)) {
                        message('生成票据，保存数据失败！', $url, 'error');
                        
                    }
                    $bill = biz_getBill($chips['qrcode'], 2, 0);
                }
            }


            //更新没有打印生成票据的数据
            if ((!empty($bill)) && empty($bill['Printed'])) {
                if (!biz_updateBillOfInvoNo($bill)) {
                    message('生成票据单号失败，请重新打印！', $this->createWebUrl($do), 'error');
                }
            }

            //未改变状态
            if (!biz_checkChipsStatus($chips, 7)) {
                load()->web('app');
                $room = db_getRoomByGUID($chips['roomguid']);
                $room['NewStatus'] = '交款';
                if (APP_updateRoomStatus($room, $chips, 2)) {
                    //更新补定状态

                }
                db_updateChipsStatus($chips['id'], 7);
            }
            print_addTask($chips, $this->createWebUrl($do), $type);

        } else {
            $url = $this->createWebUrl($do, array('op' => 'print', 'id' => $id));
            print_Confirm($chips, $url, $type);
        }
    } else {
        echo $msg;
    }
    exit;
}

