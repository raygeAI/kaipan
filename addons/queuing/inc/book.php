<?php
if (!in_array($op, array('qrcode','query', 'change', 'print', 'disp', 'dispfee'))) {
    $op = 'qrcode';
}

if (in_array($op, array('qrcode', 'query'))) {
    $keyword = trim($_GPC['keyword']);
    if (checksubmit()) {
        if ($_GPC['submit'] == 'pay') {
            $keyword = getInputGUID($keyword);
            if (empty($keyword) ) {
                message('请输入有效的认筹单号！', $this->createWebUrl($do), 'warning');
            }
            $chips = biz_getChipsByQrcode($keyword);
            if (empty($chips)) {
                message('无效的认筹单！', $this->createWebUrl($do), 'error');
            } else {
                $confirmed = biz_checkChipsStatus($chips, 8);
                if(!$confirmed){
                    header('location:' . $this->createWebUrl($do, array('id' => $chips['id'], 'op' => 'disp')));
                }else{
                    header('location:' . $this->createWebUrl($do, array('id' => $chips['id'], 'op' => 'dispfee')));
                }

            }
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
    }else{
        $condition .= ' and status>=256 ';
    }

    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;
    $start = ($pindex - 1) * $psize;
    $sql = "SELECT COUNT(*) FROM " . tablename('chips') . " WHERE {$condition}";
    $total = pdo_fetchcolumn($sql, $pars); //
    $sql = "SELECT * FROM " . tablename('chips') . " WHERE {$condition} ORDER BY `QSDate` DESC,`createtime` DESC  LIMIT {$start}, {$psize}";
    $pager = pagination($total, $pindex, $psize);
    $list = pdo_fetchall($sql, $pars);
    //金额票据处理
    foreach ($list as &$item) {
        $item['_money'] = $item['ordermoney'] + $item['premoney'];
        $item['_need'] = 0;
        if ($item['_money'] < $item['shouldpay']) {
            $item['_need'] = $item['shouldpay'] - $item['_money'];
        }
    }
    unset($item);
    include $this->template('book_list');
    exit;
}
// 显示供款明细
if ($op == 'dispfee') {
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    $confirmed = biz_checkChipsStatus($chips, 8);
    if(!$confirmed){
        header('location:' . $this->createWebUrl($do, array('id' => $chips['id'], 'op' => 'disp')));
    }
    $user_info = $chips['user'];
    $sql = " select * from " . tablename('s_fee') . " where TradeGUID=:guid order by Sequence";
    $list = pdo_fetchall($sql, array(':guid' => $chips['qrcode']));
    // 点击确认 相应事件？？
    include $this->template('book_dispfee');
}
// 显示认购书信息
if ($op == 'disp') {
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    $this->CheckDataRight($chips);
    if(!biz_checkChipsStatus($chips, 7)){
        message('未打印订金不能确认！', $this->createWebUrl($do),'info');
    }
    //定单是否已确认
    $confirmed = biz_checkChipsStatus($chips, 8);
    
    $user_info = $chips['user'];
    if (checksubmit() && !$confirmed) {
        //定单没有确认，
        load()->web('app');
        
        $room = db_getRoomByGUID($chips['roomguid']);
        if (!empty($room)) {
            if( $room['Status']=='交款') {
                $room['NewStatus'] = '认购';
                //4强制更新状态？
                APP_updateRoomStatus($room, $chips, 2);
            }
            if(empty($chips['ProtocolNO'])){
                load()->web('print');
                biz_updateChipsProtocolNo($chips);
            }
            if (biz_insertFee($chips, $room)) {
                //更新状态为确认
                db_updateChipsStatus($chips['id'], 8);
                message('认购确定，生成供款明细成功！', $this->createWebUrl($do));
            } else {
                message('生成供款明细失败！', $this->createWebUrl($do));
            }

        } else {
            message('无效的房间数据！', $this->createWebUrl($do));
        }
    }
    include $this->template('book_disp');
}

if ($op == 'print') {
    load()->web('print');
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    $printTitle = '认购书';
    $type = 4;
    $msg = '';
    $enable = print_checkChipsEnable($chips, $type, $msg);
    if ($enable) {
        if ($_W['token'] == $_GPC['token']) {
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

