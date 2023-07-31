<?php

// 认筹单 签到确认
if (!in_array($op, array('qrcode', 'query', 'disp'))) {
    $op = 'qrcode';
}
if (in_array($op, array('qrcode', 'query'))) {
    $condition = " `projguid`=:projguid  ";
    $pars = array(':projguid' => $_W['project']['projguid']);
    if (!$_W['isfounder']) {
        $condition .= ' and deleted=0 ';
    }
    $keyword = trim($_GPC['keyword']);
    if (checksubmit()) {
        if ($_GPC['submit'] == 'pay') {
            
            $chips = biz_getChipsByQrcode($keyword, 'id');
            if (empty($chips)) {
                message('无效的认筹单！', $this->createWebUrl($do), 'error');
            }
        }
    }

    if ($op == 'qrcode') {
        if (!empty($keyword)) {
            $condition .= " and qrcode like :keyword";
            $pars[':keyword'] = "%{$keyword}%";

        }
        $condition .= " and `lucky` = 1";
    } else {
        $keyword = trim($_GPC['keyword']);
        if (!empty($keyword)) {
            $types = array('cname', 'cardid', 'mobile');
            $tindex = intval($_GPC['query']);
            $condition .= " and `{$types[$tindex]}` like :keyword ";
            $pars[':keyword'] = "%{$keyword}%";
        };
        // 查询签到状态
        $signed = -1;
        if (isset($_GPC['signed'])) {
            $signed = intval($_GPC['signed']);
        }
        if($signed >-1) {
            if ($signed == 1) {
                $condition .= " and `signed` > 0 ";
                //$_GET['signed'] = $_GPC['signed'];
            } else {
                $condition .= " and `signed` = 0 ";
            }
            $_GET['signed'] = $_GPC['signed'];
        }
        
        // 查询中签状态
        $lucky = -1;
        if (isset($_GPC['lucky'])) {
            $lucky = intval($_GPC['lucky']);
        }
        if ($lucky > -1) {
            $condition .= " and `lucky` = {$lucky}";
            $_GET['lucky'] = $_GPC['lucky'];
        }
        if ($_GPC['submit'] == '导出') {
            $condition .= " order by salesman ";
            $list = pdo_fetchall("SELECT * FROM " . tablename('chips') . " WHERE {$condition}", $pars);
            load()->web('down');
            down_LuckyInfo($list);
            exit;
        }

    };
    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;
    $start = ($pindex - 1) * $psize;
    $sql = "SELECT COUNT(*) FROM " . tablename('chips') . " WHERE {$condition}";
    $total = pdo_fetchcolumn($sql, $pars); //
    $sql = "SELECT * FROM " . tablename('chips') . " WHERE {$condition} ORDER BY signed desc,lucky desc, `createtime` DESC  LIMIT {$start}, {$psize}";
    $pager = pagination($total, $pindex, $psize);
    $list = pdo_fetchall($sql, $pars);
    $status = biz_getDictionary('chipstatus');
    include $this->template('chips_signed');
}

if ($op == 'disp') {
    disableWebCache();
    $keyword = getInputGUID($_GPC['qrcode']);
    if (empty($keyword)) {
        message('请输入有效的认筹单号！', $this->createWebUrl($do), 'warning');
    }
    $chips = biz_getChipsByQrcode($keyword);

    if (empty($chips)) {
        message('无效的认筹单!', $this->createWebUrl($do), 'error');
    }
    $url = $this->createWebUrl($do, array('op' => 'disp','qrcode'=>$keyword));
    $ok = empty($chips['lucky']);
    if ($ok) {
        //未中签处理，1、未签处理，2已签到，未叫号、已叫号
        if(empty($chips['signed'])){
            $message='此单未签到、未中签，是否确认中签？';
            $ok=false;
        }else{
            $callGroup=biz_getCalledGroup($_W['project']['id']);
            if(in_array($chips['signed'],array_keys($callGroup))) {
                $message = "此单号签到组号{$chips['signed']}组，中签登记成功";
            }else{
                $message = "此单号签到组号{$chips['signed']}组、未中签<br/>是否确认中签？";
                $ok=false;
            }
        }
       
    } else {
        $message = '此单已中签，不能重复登记！';
    }
    if ($_W['isajax']) {
        include $this->template('lucky_info');
        exit;
    }
    if ($_W['token'] == $_GPC['token']) {
        if(empty($chips['lucky'])) {
            $data = array(
                'lucky' => 1,
                'signdate' => TIMESTAMP,
            );
            pdo_update('chips', $data, array('id' => $chips['id']));
           
        }
        message('中签确认信息已更新！', $this->createWebUrl($do), 'success');
    }
}
