<?php

defined('IN_IA') or exit('Access Denied');

class ChipsModuleSite extends IndustryModule
{


    public function __Process($call)
    {
        global $_W, $_GPC;
        $do = strtolower(substr($call, 5));
        $op = strtolower($_GPC['op']);
        //inc目录存放业务处理
        include_once 'inc/' . $do . '.php';
    }


    public function doWebPrepay()
    {
        $this->__Process(__FUNCTION__);
    }

    public function doWebChips()
    {
        $this->__Process(__FUNCTION__);
    }





    public function doWebDelete()
    {
        global $_W, $_GPC;
        $this->CheckRight('delete');
        $id = intval($_GPC['id']);
        if ($id > 0) {
            $chips = biz_getChips($id);
        }
        $result = array('ok' => false, 'message' => '无效数据认筹单');
        if (!empty($chips)) {
            $data = array(
                'deleted' => 1,
                'changetime' => TIMESTAMP,
                'operator' => $_W['username']);
            if (pdo_update('chips', $data, array('id' => $chips['id']))) {
                //更新对应票据printed为2
                pdo_update('bill', array('Printed'=>2), array('Qrcode' => $chips['qrcode']));
                $result['ok'] = true;
                $result['message'] = '认筹单已做废';
            } else {
                $result['ok'] = false;
                $result['message'] = '认筹单做废，更新数据库失败';
            }
        }
        die(json_encode($result));
    }

    public function doWebWelcome()
    {
        global $_W;
        $sql = 'select pretype,count(*) as num from ' . tablename('chips');
        $sql .= ' where projguid=:projguid and deleted=0 group by pretype ';
        $cnt = pdo_fetchall($sql, array(':projguid' => $_W['project']['projguid']), 'pretype');
        $status = array(0 => '登记', 1 => '付款', 2 => '无交款');
        $count = array(0 => 0);
        foreach ($status as $k => $v) {
            $count[$k+1] = 0;
            if (isset($cnt[$k])) {
                $count[$k+1] = $cnt[$k]['num'];
            }
            $count[0] += $count[$k];
        }
        // 诚意金统计
        $sql = "select count(*) as num, sum(premoney) as total from " . tablename('chips');
        $sql .= ' where premoney>0 and projguid=:projguid and deleted=0';
        $premoney = pdo_fetch($sql, array(':projguid' => $_W['project']['projguid']));

        // 认购统计
        $sql = "select count(*) as num, coalesce(SUM(ordermoney),0) as total from " . tablename('chips');
        $sql .= ' where ordermoney>0 and projguid=:projguid and deleted=0';
        $ordermoney = pdo_fetch($sql, array(':projguid' => $_W['project']['projguid']));

        include $this->template('welcome');
    }

    public function doWebCustomer()
    {
        global $_W, $_GPC;
        $step = 1;
        if (isset($_GPC['step'])) {
            $step = intval($_GPC['step']);
        }
        $id = intval($_GPC['id']);
        $chips = biz_getChips($id);
        if (empty($chips) || $chips['deleted']) {
            message('无效认筹单', $this->createWebUrl('chips'));
        }
        $op = $_GPC['op'];
        if (!in_array($op, array('change', 'add', 'delete'))) {
            message('无效操作', $this->createWebUrl('chips'));
        }
        $url = $this->createWebUrl('customer', array('op' => $op, 'id' => $id));
        if ($op == 'change') {
            $title = '认筹单换名';
        }
        if ($op == 'add') {
            $title = '增加附属权益人';
        }
        if (checksubmit('submit')) {
            if ($step == 1) {
                $user_info = biz_getCustomerByCardId($_GPC['cardid'], $_W['project']['projguid']);
                if (empty($user_info)) {
                    $user_info = $_GPC['user'];
                    $user_info['CardID'] = $_GPC['cardid'];
                    $user_info['Country'] = '中国';
                    $user_info['Gender'] = '男';
                }
                $step = 2;
            } else if ($step == 2) {
                $user_info = $_GPC['user'];
                $customer = biz_getCustomerByCardId($user_info['CardID'], $_W['project']['projguid']);
                if ($op == 'change') {

                    if (empty($customer)) {
                        $customer = $user_info;
                        biz_saveCustomer($customer, $_W['project']);
                    }
                    // 插入的数据
                    $data = array('cname' => $user_info['CstName'], 'cardid' => $user_info['CardID'], 'mobile' => $user_info['MobileTel'], 'grender' => $user_info['Gender']);
                    $data['cid'] = $customer['CstGUID'];
                    pdo_update('chips', $data, array('id' => $id));
                    message('认筹单换名成功！', $this->createWebUrl('chips'));
                }
                if ($op == 'add') {
                    if (empty($customer)) {
                        $customer = $user_info;
                        biz_saveCustomer($customer, $_W['project']);
                    }

                    $result = biz_saveHolder($chips, $customer);
                    if ($result['result']) {
                        message('数据增加成功！', $this->createWebUrl('chips', array('id' => $id)));
                    } else {
                        message('数据保存出错：' . $result['msg'], $this->createWebUrl('customer', array('op'=>'add','id' => $id)), 'error');
                    }
                }

            }
        }
        if ($step == 2) {
            $cardTypes = biz_getDictionary('CardType');
            $khTypes = biz_getDictionary('kehuType');
        }
        include $this->template('chips_change');
    }


    // 删除权益人
    public function doWebDelHolder()
    {
        global $_GPC, $_W;
        $id = intval($_GPC['id']);
        $chips = biz_getChips($id);
        $holdname = explode(';', $chips['holdername']);
        $holdguid = explode(';', $chips['holderguid']);
        $url = $this->createWebUrl('delholder', array('id' => $id));
        if ($_W['isajax']) {
            include $this->template('holder_del');
            exit();
        }
        if ($_W['token'] == $_GPC['token']) {
            $holdids = $_GPC['holdids'];
            if (!empty($holdids)) {
                $hdiff = array_diff($holdguid, $holdids);
                $holderguid = implode(';', $hdiff);
                $state = pdo_update('chips', array('holderguid' => $holderguid), array('id' => $id));
                $chips['holderguid'] = $holderguid;
                if ($state !== false) {
                    $state = biz_saveHolderName($chips);
                }
                if ($state !== false) {
                    message('数据删除成功！', $this->createWebUrl('chips', array('id' => $id)), 'success');
                } else {
                    message('数据保存出错！', $this->createWebUrl('chips', array('id' => $id)), 'error');
                }
            } else {
                message('未选择删除权益人！', $this->createWebUrl('chips'), 'error');
            }

        }
    }

    // 意向房间设置
    function doWebRoomSet()
    {
        global $_W, $_GPC;
        $ProjGUID = $_W['project']['projguid'];
        $callback = $_GPC['callback'];
        $builds = db_getBuilds($ProjGUID, 'BldGUID', true);
        if (empty($builds)) {
            echo '系统未配置本次可售楼栋';
            exit;
        }
        $bid = trim($_GPC['buildid']);
        $uids = $_GPC['uids'];
        //$uids = implode(',', $uidArr);
        $sql = " FROM ims_p_building a INNER JOIN ims_p_room b ON  a.BldGUID = b.BldGUID ";
        $sql .= ' WHERE  a.ProjGUID=:ProjGUID and a.Status=1 ';
        //$sql .= " and b.Status='待售' ";
        $order = ' order by a.BldName,b.Floor,b.Unit,b.No';
        $params = array(':ProjGUID' => $ProjGUID);
        if (!empty($_GPC['keyword'])) {
            $sql .= ' AND b.`RoomCode` LIKE :roomname';
            $params[':roomname'] = "%{$_GPC['keyword']}%";
        }

        $pindex = max(1, intval($_GPC['page']));
        $psize = 10;
        $total = 0;


        $list = pdo_fetchall("SELECT b.*,a.BldName  " . $sql . " {$order} LIMIT " . (($pindex - 1) * $psize) . ",{$psize}", $params);
        $total = pdo_fetchcolumn("SELECT COUNT(b.RoomGUID) " . $sql, $params);
        $pager = pagination($total, $pindex, $psize, '', array('ajaxcallback' => 'null', 'mode' => $mode, 'uids' => $uids));
        include $this->template('roomSet');
        exit;

    }


    #region 模块注册功能
    public function RegisterOperate()
    {
        return
            array(
                'display' => '查看认筹单',
                'add' => '增加认筹单',
                'edit' => '修改认筹单',
                'delete' => '作废认筹单',
                'print' => '打印认筹单',
                'change' => '认筹单换名',
                'pay' => '认筹单付款',
                'confirm' => '认筹单确认',
            );
    }

    public function NavMenu()
    {
        return array(
            'menu' => array(
                'chips' => array('title' => '认筹单列表', 'url' => $this->createWebUrl('chips')),
                'prepay' => array('title' => '诚意金交款', 'url' => $this->createWebUrl('prepay')),
            ),
            'default' => 'welcome');
    }


    #endregion

}