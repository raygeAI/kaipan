<?php
defined('IN_IA') or exit('Access Denied');

class ProjectModuleSite extends IndustryModule
{

    public function doWebList()
    {
        global $_W, $_GPC;
        $table = 'p_room';
        $Room = trim($_GPC['Room']);
        $status = trim($_GPC['status']);
        $sel_build = trim($_GPC['build']);
        $project = biz_unserializer($_W['project'], 'builds');
        $build = db_getBuilds($_W['project']['projguid'], '', !empty($project));
        //$default = 0;
        $condition = " WHERE ProjGUID=:ProjGUID ";
        $param = array(":ProjGUID" => $_W['project']['projguid']);
        if (!empty($Room)) {
            $condition .= " AND Room LIKE '%{$Room}%' ";
            $_GET['Room'] = $_GPC['Room'];
        }
        if (!empty($status)) {
            $condition .= " AND Status LIKE '%{$status}%' ";
            $_GET['status'] = $_GPC['status'];
        }
        if (!empty($sel_build)) {
            $condition .= " AND BldGUID=:BldGUID ";
            $param[':BldGUID'] = $sel_build;
            $_GET['build'] = $sel_build;
        }
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $sql = "SELECT * FROM " . tablename($table) . $condition;
        $sql .= " order by RoomCode limit " . ($pindex - 1) * $psize . "," . $psize;
        $list = pdo_fetchall($sql, $param);
        $total = pdo_fetchcolumn(" select count(*) from " . tablename($table) . $condition, $param);
        $pager = pagination($total, $pindex, $psize);
        include $this->template('build_list');
    }

    public function doWebWelcome()
    {
        global $_W, $_GPC;
        $set = biz_unserializer($_W['project'], 'builds');
        $builds = db_getBuilds($_W['project']['projguid'], '', !empty($set));

        foreach ($builds as $k => $b) {
            $builds[$k]['stat'] = biz_getRoomStatusStat($b['BldGUID']);
        }
        include $this->template('welcome');
    }

    public function doWebManage()
    {
        //开盘状态控制，是否允许修改处理
        global $_W, $_GPC;
        $ERP_ENABLE = ERP_ENABLE;
        $enable=$this->CheckRight('set',false);
        $status = biz_getDictionary('projstatus');
        $projGUID = $_W['project']['projguid'];
        $project = db_getProject($projGUID, true);
        $sync = biz_unserializer($project, 'sync');
        $sign = biz_unserializer($project, 'signset');
        $finance = biz_unserializer($project, 'finance');
        $prepay = $finance['prepay'];
        $order = $finance['order'];
        $book = $finance['book'];
        $pay = biz_unserializer($project, 'payform');
        
        
        $bank = biz_unserializer($project, 'bank');
        $sale = biz_unserializer($project, 'builds');
        $builds = db_getBuilds($projGUID, 'BldGUID');
        include $this->template('manage');
    }

    public function doWebSet()
    {

        global $_W, $_GPC;
        $op = $_GPC['op'];
        $this->CheckRight('set');

        //检查项目状态，是否允许设置
        $project = db_getProject($_W['pid'], false, false);

        if ((empty($project))) {//|| !empty($project['status'])
            if ($_W['isajax']) {
                echo('无效的项目数据或当前项目不允许设置！');
                exit;
            } else {
                message('无效的项目数据或当前项目不允许设置！', $this->createWebUrl('manage'));
            }
        }
        $url = $this->createWebUrl('set', array('op' => $op));
        if ($op == 'discount') {
            $discount = db_getDiscount($project['projguid'], 'DiscntGUID');
            $payform = db_getPayForm($project['projguid'], 'PayFormGUID');
            foreach ($payform as &$p) {
                if ($p['DisCount']) {
                    $p['title'] = $p['PayformName'] . "[{$p['DisCount']}%]";
                }
                if ($p['PreferentialPrice'] > 0) {
                    $p['title'] = $p['PayformName'] . "[-{$p['PreferentialPrice']}￥]";
                }
            }
            unset($p);
            $set = biz_unserializer($project, 'payform');
            if (empty($set)) {
                $set = array('pay_id' => '');
            } else if (count($set['pay_id']) > 0) {
                foreach ($discount as $k => $v) {
                    $discount[$k]['select'] = in_array($k, $set['dis_id']);
                }
            }
            if ($_W['isajax']) {
                include $this->template('discountSet');
                exit();
            }
            if ($_W['token'] == $_GPC['token']) {
                $pay_id = trim($_GPC['payform']);
                $calc = array('paydiscount' => 1.0, 'decmoney' => 0, 'discount' => 1.0);
                if (!empty($pay_id) && isset($payform[$pay_id])) {
                    $set['pay_id'] = $pay_id;
                    $set['Payform'] = $payform[$pay_id];
                    $set['title'] = $payform[$pay_id]['title'];
                    $set['details'] = db_getPaydetail($pay_id);
                }
                unset($set['dis_id']);
                foreach ($_GPC['discount'] as $d) {
                    if (isset($discount[$d])) {
                        $set['dis_id'][] = $d;
                    }
                }
                $calc = biz_getRoomPriceCalc($set, $discount, $payform);
                $set['calc'] = $calc;
                $tip=round($calc['discount']*100, 2);
                $set['discount'] = "减免{$calc['dec_money']},折扣{$tip}%";
                $data = array('payform' => iserializer($set));
                pdo_update('project', $data, array('id' => $_W['pid']));
                biz_mem_clearProject();
                $rooms = db_getRooms($_W['project']['projguid']);

                foreach ($rooms as $r) {
                    if (in_array($r['Status'],array('待售','销控'))) {

                        biz_calcRoomPrice($r, $calc, true);
                    }
                    //calc_payorder($r,true);
                }
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }

        if ($op == 'invoice') {

            load()->web('print');
            $set = biz_unserializer($project, 'finance');
            if (empty($set['prepay'])) {
                $prepay = $set;
            } else {
                $prepay = $set['prepay'];
            }
            $order = $set['order'];
            $prefix = $set['Prefix'];
            if ($_W['isajax']) {
                include $this->template('invoiceSet');
                exit();
            }
            if ($_W['token'] == $_GPC['token']) {
                
                $getInvoInfo=function($set)use($project){
      
                        $set['BatchNo'] = trim($set['BatchNo']);
                        $set['Prefix'] = trim($set['Prefix']);
                        $invo = biz_getInvoiceByBatchNo($set['BatchNo'], $set['Prefix'], $project);
                        $config = array(
                            'BatchNo' => $set['BatchNo'],
                            'Prefix' => $set['Prefix']
                           
                        );
                        if(!empty($invo)){
                            $config['InvoGUID'] = $invo['InvoGUID'];
                        }
                       return $config;
            
                };
                $set=array();
                $set['prepay']=$getInvoInfo($_GPC['prepay']);
                $set['order']=$getInvoInfo($_GPC['order']);
                $set['Prefix']=trim($_GPC['Prefix']);
                $set['ChipsPre']=trim($_GPC['ChipsPre']);
                $data = array('finance' => iserializer($set));
                pdo_update('project', $data, array('id' => $_W['pid']));
                biz_mem_clearProject($project);
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }

        if ($op == 'sign') {
            $set = biz_unserializer($project, 'signset');
            if (empty($set)) {
                $set = array('num' => 10, 'max' => 1);
            }
            if ($_W['isajax']) {
                include $this->template('signSet');
                exit();
            }
            if ($_W['token'] == $_GPC['token']) {
                $set = array('num' => intval($_GPC['num']), 'max' => intval($_GPC['max']));
                if ($set['num'] <= 0) {
                    $set['num'] = 10;
                }
                if ($set['max'] <= 0) {
                    $set['max'] = 1;
                }
                //只能一组
                $set['max'] = 1;
                $data = array('signset' => iserializer($set));
                pdo_update('project', $data, array('id' => $_W['pid']));
                if ($_GPC['clear']) {
                    pdo_update('sign', array('signed' => 0), array('pid' => $_W['pid']));
                    pdo_update('call_group', array('called' => 0), array('pid' => $_W['pid']));
                    //pdo_update('p_room',array('selectroom'=>0))
                    memcached_delete('global_sign_' . $project['id']);
                }
                biz_mem_clearProject($project);
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }

        if ($op == 'project') {

            $status = biz_getDictionary('projstatus');
            //删除创建默认状态
            unset($status[0]);
            $set = biz_unserializer($project, 'sync');
            if ($_W['isajax']) {
                include $this->template('projSet');
                exit();
            }
            if ($_W['token'] == $_GPC['token']) {
                $selStatus = intval($_GPC['status']);
                $set = array('client' => trim($_GPC['client']));
                $data = array(
                    'status' => $selStatus,
                    'sync' => iserializer($set)
                );
                pdo_update('project', $data, array('id' => $project['id']));
                biz_mem_clearProject($project);
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }
        if ($op == 'build') {
            $set = biz_unserializer($project, 'builds');
            if (empty($set['timeout'])) {
                $set['timeout'] = 30;
            }
            $builds = db_getBuilds($_W['project']['projguid'], 'BldGUID');
            if ($_W['isajax']) {
                include $this->template('buildSet');
                exit();
            }

            if ($_W['token'] == $_GPC['token']) {
                $bids = $_GPC['m_ids'];
                $timeout = intval($_GPC['timeout']);
                if ($timeout < 0 || $timeout > 300) {
                    $timeout = 30;
                }
                //统计房间数量


                // 更新
                pdo_update('p_building', array('status' => '0'), array('ProjGUID' => $project['projguid']));
                $keys = array_keys($builds);

                foreach ($bids as $bldGuid) {
                    if (in_array($bldGuid, $keys)) {
                        pdo_update('p_building', array('status' => '1'), array('BldGUID' => $bldGuid));
                    }
                }
                $sql='SELECT count(*) as cnt FROM ims_p_room ';
                $sql.=' a INNER JOIN ims_p_building b ON a.BldGUID = b.BldGUID ';
                $sql.=' WHERE  b.`Status`=1 and b.ProjGUID=:projguid';
                $cnt=pdo_fetchcolumn($sql,array(':projguid'=> $project['projguid']));
                $set = array('build' => $bids, 'timeout' => $timeout,'roomnum'=>$cnt);
                $data = array(
                    'builds' => iserializer($set)
                );
                pdo_update('project', $data, array('id' => $_W['pid']));
                biz_mem_clearProject();
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }
        if ($op == 'bank') {
            $banks = biz_getBanks($_W['project']['BUGUID']);

            $bk = empty($project['bank']) ? array() : iunserializer($project['bank']);
            if ($_W['isajax']) {
                include $this->template('bankSet');
                exit();
            }
            if ($_W['token'] == $_GPC['token']) {
                $bankid = $_GPC['bank'];
                $prebank = db_getPreBank($bankid);
                $data = array(
                    'bank' => iserializer($prebank),
                );
                pdo_update('project', $data, array('id' => $_W['pid']));
                biz_mem_clearProject($project);
                message('数据已设置', $this->createWebUrl('manage'));
            }
        }

    }

    public function NavMenu()
    {
        return array(
            'menu' => array(
                'manage' => array('title' => '开盘管理', 'url' => $this->createWebUrl('manage')),
                'list' => array('title' => '售房管理', 'url' => $this->createWebUrl('list')),
            ),
            'default' => 'welcome');
    }
}