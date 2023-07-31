<?php

if (!in_array($op, array('qrcode', 'query', 'pay', 'confirm', 'print'))) {
    $op = 'qrcode';
}
if (in_array($op, array('qrcode', 'query'))) {

    
    if ($op == 'qrcode') {

        if (checksubmit()&&($_GPC['submit'] == 'pay')) {
        
            $keyword = getInputGUID($_GPC['keyword']);
            if (empty($keyword) ) {
                
                message('请输入有效的认筹单号！'."[{$_GPC['keyword']}]", $this->createWebUrl($do), 'warning');
            }

            $chips = biz_getChipsByQrcode($keyword, 'id');
            if (empty($chips)) {
                message("无效的认筹单![{$keyword}]", $this->createWebUrl($do), 'error');
            } else {
                header('location:' . $this->createWebUrl($do, array('id' => $chips['id'], 'op' => 'pay')));
                exit;
            }
           
        }
    }

    $status = array(0 => '登记', 1 => '付款', 2 => '无交款');

    $condition = " `projguid`=:projguid ";
    $pars = array(':projguid' => $_W['project']['projguid']);
    if (!$_W['isfounder']) {
        $condition .= ' and deleted=0 ';
        $condition .= ' and StationCode Like :code ';
        $pars[':code'] = "%{$_W['rights']['StationCode']}%";
    }


    if ($op == 'qrcode') {
        $condition .= " and pretype>0 ";
        // 导出诚意金交款
        if ($_GPC['submit'] == '导出') {

           
            $list = pdo_fetchall("SELECT * FROM " . tablename('chips') . " WHERE {$condition}", $pars);
            load()->web('down');
            down_PrepayInfo($list);
            exit;
  
        }
    } else {
        if (!empty($keyword)) {
            $types = array('cname', 'cardid', 'mobile');
            $tindex = intval($_GPC['query']);
            $condition .= " and `{$types[$tindex]}` like :keyword ";
            $pars[':keyword'] = "%{$keyword}%";
        };
        $selstatus = 0;
        if (isset($_GPC['status'])) {
            $selstatus = intval($_GPC['status']);

        }
        if ($selstatus > -1) {
            $condition .= " AND `pretype`=:pretype ";
            $pars[':pretype'] = $selstatus;
        }
    }


    $order = ' ORDER BY `pretype`,`createtime` ';

    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;
    $start = ($pindex - 1) * $psize;
    $sql = "SELECT COUNT(*) FROM " . tablename('chips') . " WHERE {$condition}";
    $total = pdo_fetchcolumn($sql, $pars); //
    $sql = "SELECT * FROM " . tablename('chips') . " WHERE {$condition} {$order} DESC LIMIT {$start}, {$psize}";
    $pager = pagination($total, $pindex, $psize);
    $list = pdo_fetchall($sql, $pars);
    foreach ($list as &$item) {
        $bill = biz_getBills($item['qrcode'], 1, true);
        $item['InvoNo1'] = $bill[0]['InvoNo'];
        $item['Money1'] = $bill[0]['Money'];
        $item['InvoNo2'] = $bill[1]['InvoNo'];
        $item['Money2'] = $bill[1]['Money'];
    }
    unset($item);
    include $this->template('chips_prepay');
    exit;
};

if ($op == 'pay') {
    $this->CheckRight('prepay');
    load()->web('print');
    $billType = 1;
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id);
    $this->CheckDataRight($chips);
    $enable = !empty($chips) && (!$chips['deleted']);

    $url = $this->createWebUrl($do);
    if (!empty($_GPC['back'])) {
        $url = $this->createWebUrl($_GPC['back'], array('id' => $chips['id']));
    }
    $urlEdit=$this->createWebUrl($do,array('id'=>$id,'op'=>'pay','edit'=>true));
    if (!$enable) {
        message('无效数据或认筹单不允许修改！', $url);
    }

    $user_info = $chips['user'];

    $printed_Bills = biz_getBills($chips['qrcode'], $billType, true);
    $inputBill = biz_getBill($chips['qrcode'], $billType, false);
    $enable = in_array($chips['pretype'], array(0,1));
    //$modifyLast=isset($_GPC['modi'])?intval($_GPC['modi']):false;
    //允许输入
    $maxInput=2;
    $inputed= count($printed_Bills);
    //禁止修改或增加票据，必须加参数edit,才显示输入界面
    $disableEdit=!isset($_GPC['edit'])||($_GPC['edit']!=1);
    if(empty($inputBill)) {
        if($inputed==0||!$disableEdit) {
            $inputBill = array('Printed' => 0, 'finance' => array(0 => array()));
            $disableEdit = false;
        }
    }
    
    if (!empty($inputBill)) {
        $project = $_W['project'];
        $banks = biz_getBanks($project['BUGUID']);
        $project = biz_getProject($project['projguid']);
        $bank = biz_unserializer($project, 'bank');
    }
    //是否允许修改

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
                if (!biz_addBill($chips, $inputBill)) {
                    $updateMoney = false;
                    message('认筹单诚意金交款，保存数据失败！', $url, 'error');
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
            $premoney = $inputBill['Money'];
            foreach ($printed_Bills as $b) {
                $premoney += $b['Money'];
            }
            $data = array(
                'status' => $status['付款'],
                'changetime' => TIMESTAMP,
                'pretype' => 1,
                'premoney' => $premoney,
                'preattach' => $inputBill['BillGUID']
            );
            pdo_update('chips', $data, array('id' => $chips['id']));
        }
        header('location:' . $this->createWebUrl($do,array('id'=>$id,'op'=>'pay')));
    }
    include $this->template('chips_pay');
    exit;
}

if ($op == 'confirm') {
    disableWebCache();
    $this->CheckRight('confirm');
    $id = intval($_GPC['id']);
    if ($id > 0) {
        $chips = biz_getChips($id);
        $this->CheckDataRight($chips);
    }
    $status = biz_getDictionary('chipstatus', true);
    $enable = !empty($chips) && (!$chips['deleted']);
    $enable &= ($chips['status'] == $status['登记'] || $chips['status'] == $status['付款']);
    $url = $this->createWebUrl($do, array('op' => 'confirm'));
    if ($enable) {
        $user_info = $chips['user'];
        if (!empty($chips['preattach'])) {
            $item = iunserializer($chips['preattach']);
        }
        if (empty($item)) {
            $item = array(
                'operator' => $_W['username'],
            );
        }
        if ($_W['isajax']) {
            include $this->template('chips_confirm');
            exit();
        }

    } else {
        message('无效数据或认筹单不允许修改！', $this->createWebUrl($do));
    }
    if ($_W['token'] == $_GPC['token']) {
        $id = $_GPC['id'];
        $status = biz_getDictionary('chipstatus', true);
        $pre = array(
            'source' => $_GPC['source'],
            'operator' => $_W['username'],
        );

        $data = array(
            'status' => $status['确认'],
            'changetime' => TIMESTAMP,
            'pretype' => 2,
            'premoney' => 0,
            'preattach' => iserializer($pre)
        );
        pdo_update('chips', $data, array('id' => $id));
        message('认筹单已确认无诚意金，数据更新成功！', $this->createWebUrl($do, array('op' => 'query')));
    }
}

if ($op == 'print') {
    disableWebCache();
    load()->web('print');
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    
    $printTitle = '诚意金交款';
    $type = 2;
    $msg = '';
    $enable = print_checkChipsEnable($chips, $type, $msg);
    if ($enable) {
        if ($_W['token'] == $_GPC['token']) {
            $bill = biz_getBill($chips['qrcode'], 1, false);
            //更新没有打印生成票据的数据
            if ((!empty($bill)) && empty($bill['Printed'])) {
                if (!biz_updateBillOfInvoNo($bill)) {
                    message('生成票据单号失败，请重新打印！', $this->createWebUrl($do), 'error');
                }
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
