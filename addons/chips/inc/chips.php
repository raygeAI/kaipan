<?php

if (!in_array($op, array('qrcode', 'query','add','delete','edit','display','print'))) {
    $op = 'qrcode';
}

if(in_array($op, array('qrcode', 'query'))) {

    $condition = " `projguid`=:projguid ";
    $pars = array(':projguid' => $_W['project']['projguid']);
    if (!$_W['isfounder']) {
        $condition .= ' and deleted=0 ';
        $condition .= ' and StationCode Like :code ';
        $pars[':code'] = "%{$_W['rights']['StationCode']}%";
    }

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
        if ($selstatus > -1) {
            $condition .= " AND `pretype`=:pretype ";
            $pars[':pretype'] = $selstatus;
        }
    }
    $status = array(0 => '登记', 1 => '付款', 2 => '无交款');
    if ($_GPC['submit'] == '导出') {
        load()->web('down');
        $list = pdo_fetchall("SELECT * FROM " . tablename('chips') . " WHERE {$condition}", $pars);
        down_BaseInfo($list);
        exit;
    }
    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;
    $start = ($pindex - 1) * $psize;
    $sql = "SELECT COUNT(*) FROM " . tablename('chips') . " WHERE {$condition}";
    $total = pdo_fetchcolumn($sql, $pars); //
    $sql = "SELECT * FROM " . tablename('chips') . " WHERE {$condition} ORDER BY `printdate` DESC,`createtime` DESC  LIMIT {$start}, {$psize}";
    $pager = pagination($total, $pindex, $psize);
    $list = pdo_fetchall($sql, $pars);

    include $this->template('chips_list');
    exit;
};

if($op=='add'){
    $this->CheckRight('add');

    $step = 1;
    if (isset($_GPC['step'])) {
        $step = intval($_GPC['step']);
    }

    if (checksubmit('submit')) {
        if ($step == 1) {
            //检查用户信息是否存在
            $user_info = biz_getCustomerByCardId($_GPC['cardid'], $_W['project']['projguid']);
            if (empty($user_info)) {
                $user_info = $_GPC['user'];
                $user_info['CardID'] = $_GPC['cardid'];
                $user_info['Country'] = '中国';
                $user_info['Gender'] = '男';
            }
            $cardid = $_GPC['cardid'];
            $step = 2;
        } else if ($step == 2) {
            $user_info = $_GPC['user'];
            $input_save = base64_encode(iserializer($user_info));
            $step = 3;
        } else if ($step == 3) {

            $data = $_GPC['chips'];
            //处理销售人员信息
            $salesman = biz_getStationUser($_W['project']['projguid'],false);
            $data['salesmanGUID']=$data['sales'];
            $data['salesman']=$salesman[$data['sales']]['UserName'];
            $data['user'] = iunserializer(base64_decode($_GPC['user']));
            $data['projguid'] = $_W['project']['projguid'];
            
            unset($data['sales']);
            $data['qrcode'] = GUID();
            //$data['qrimage']=$this->geneQrcodeImg($data['qrcode']);
            $data['status'] = 1;
            $result = biz_saveChips($data);
            if ($result['result']) {
                message('数据增加成功！', $this->createWebUrl($do));
            } else {
                message('数据保存出错：' . $result['msg'], $this->createWebUrl($do), 'error');
            }
        }
    }
    if ($step == 2) {
        $cardTypes = biz_getDictionary('CardType');
        $khTypes = biz_getDictionary('kehuType');
    }
    if ($step == 3) {
        $housetype = biz_unserializer($_W['project'], 'housetype');
        $products = biz_unserializer($_W['project'], 'product');
        $agency = biz_getBizDLGS($_W['project']);
        $salesman = biz_getStationUser($_W['project']['projguid'],false);

    }

    include $this->template('chips_add');
    exit;
}

if($op=='edit'){
    $this->CheckRight('edit');
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id);
    $this->CheckDataRight($chips);
    $status = biz_getDictionary('chipstatus', true);
    //$enable = !empty($chips) && (!$chips['deleted']) && ($chips['status'] == $status['付款']);
    $enable = true;
    if ($enable) {
        $user_info = $chips['user'];
        $cardTypes = biz_getDictionary('CardType');
        $khTypes = biz_getDictionary('kehuType');
        $housetype = biz_unserializer($_W['project'], 'housetype');
        $products = biz_unserializer($_W['project'], 'product');
        $agency = biz_getBizDLGS($_W['project']);
        $salesman = biz_getStationUser($_W['project']['projguid']);
    } else {
        if (!$enable) {
            message('无效数据或认筹单不允许修改！', $this->createWebUrl($do));
        }
    }
    if (checksubmit()) {
        $chip = $_GPC['chips'];
        $user = $_GPC['user'];
        $data = array(
            'changetime' => TIMESTAMP,
            'product' => $chip['product'],
            'housetype' => $chip['housetype'],
            'local' => $chip['local'],
            'housenum' => $chip['housenum'],
            'agency' => $chip['agency'],
            'salesman' => $salesman[$chip['sales']]['UserName'],
            'intendroom' => $chip['intendroom'],
            'mobile'=>$user['MobileTel'],
            'salesmanGUID'=>$salesman[$chip['sales']]['UserGUID'],
            'remark'=>$chip['remark'],
            'agencychild'=>$chip['agencychild'],
        );
        $userinfo = array(
            //'CardID' => $user['CardID'],
            'Address' => $user['Address'],
            'MobileTel' => $user['MobileTel'],
            'HomeTel' => $user['HomeTel'],
            'Country' => $user['Country'],
            'PostCode' => $user['PostCode'],
        );
        pdo_update('p_customer', $userinfo, array('CstGUID' => $chips['user']['CstGUID']));
        pdo_update('chips', $data, array('id' => $chips['id']));
        message('认筹单数据更新成功！', $this->createWebUrl($do));
    }
    include $this->template('chips_edit');
    exit;
}

if ($op == 'print') {
    disableWebCache();
    load()->web('print');
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id, true);
    $this->CheckDataRight($chips);
    $type = 1;
    $msg = '';
    $enable = print_checkChipsEnable($chips, $type, $msg);
    if ($enable) {
        if($_W['token'] == $_GPC['token']) {
            if(empty($chips['ChipsNo'])){
                load()->web('print');
                biz_updateChipsNo($chips);
            }
            print_addTask($chips,$this->createWebUrl($do),$type);
        }else{
            $url = $this->createWebUrl($do, array('op' => 'print', 'id' => $id));
            print_Confirm($chips, $url, $type);
        }
    } else {
        echo $msg;
    }
    exit;
}


if($op=='confirm'){
    disableWebCache();
    $this->CheckRight('confirm');
    $id = intval($_GPC['id']);
    if ($id > 0) {
        $chips = biz_getChips($id);
        $this->CheckDataRight($chips);
    } 
    $status = biz_getDictionary('chipstatus', true);
    $enable = !empty($chips) && (!$chips['deleted']);
    $enable &= ($chips['status'] == $status['登记']);
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
    } else {
        message('无效数据或认筹单不允许修改！', $this->createWebUrl($do));
    }
    
    if (checksubmit()) {
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
        pdo_update('chips', $data, array('id' => $chips['id']));
        message('认筹单已确认无诚意金，数据更新成功！', $this->createWebUrl($do));
    }

    include $this->template('chips_confirm');
    exit;
}

if ($op == 'display') {
    disableWebCache();
    $id = intval($_GPC['id']);
    $chips = biz_getChips($id);
    if(empty($chips)){
        echo '无效的参数';
        exit;
    }
    if(!$this->CheckDataRight($chips)){
        echo '无权查看数据';
        exit;  
    }
    if($chips['pretype']==2) {
        $preattach = biz_unserializer($chips, 'preattach');
    }
    $user_info = $chips['user'];
    $pre_bills = biz_getBills($chips['qrcode'], 1,null);
    foreach($pre_bills as &$bill){
        if(empty($bill['Printed'])){
            $bill['Printed']=1;
            $bill['InvoNo']='未打印生成' ;
        }
    }
    unset($bill);
    $order_bills = biz_getBills($chips['qrcode'], 2,null);
    foreach($order_bills as &$bill){
        if(empty($bill['Printed'])){
            $bill['Printed']=1;
            $bill['InvoNo']='未打印生成' ;
        }
    }
    unset($bill);
    //$qrImage = $this->geneQrcodeImg($chips['qrcode']);
    include $this->template('chips_disp');
    exit;
}
