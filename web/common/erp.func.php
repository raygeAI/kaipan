<?php
/**
 *定义erp相关数据处理接口
 *
 */

defined('IN_IA') or exit('Access Denied');

define('ERP', true);

/**
 * @param $guid
 * @return bool|mixed
 */
function erp_getCstAttach($guid)
{
    $sql = 'SELECT b.* FROM p_CstAttach a RIGHT  JOIN p_Customer b ON  a.CstGUID = b.CstGUID ';
    $sql .= " where a.ProjGUID='{$guid}' and b.CardID<>'' ";
    return MsSql()->fetchall($sql);
}

/**
 * @param $guid
 * @return bool|mixed
 */
function erp_getAllCustomers($guid)
{
    $sql = 'SELECT b.* FROM p_CstAttach a RIGHT  JOIN p_Customer b ON  a.CstGUID = b.CstGUID ';
    $sql .= " where a.ProjGUID='{$guid}' and b.CardID<>'' ";
    return MsSql()->fetchall($sql);
}

/**
 * 获得项目客户信息
 * @param $cardID
 * @param $projGUID
 * @return bool|mixed
 */
function erp_getCustomerInfo($cardID, $projGUID)
{
    $sql = 'select b.* from p_CstAttach a,p_Customer b where a.CstGUID=b.CstGUID ';
    $sql .= " and a.ProjGUID='{$projGUID}'";
    $sql .= " and b.CardID='{$cardID}'";
    return MsSql()->fetch($sql);
}


/**
 * 查询用户楼盘对应的经纪人ID
 * @param $mobile
 * @param $buildGUID
 * @return  经纪人GUID
 */
function erp_getBrokerInfo($mobile, $buildGUID)
{
    return '';
}

/**
 * 获取项目信息
 *  要求在售？
 * @param $query
 * @return array
 */
function erp_getProjects($query)
{
    $where = ' SaleNum>0 ';
    if (is_array($query)) {
        if (isset($query['guid'])) {
            $where .= " and ProjGUID='" . $query['guid'] . "'";
        }
        if (isset($query['name'])) {
            $where .= " and ProjShortName like'" . $query['guid'] . "'";
        }
    } else {
        $where = "ProjGUID='$query'";
    }
    $sql = 'SELECT ProjGUID,ProjShortName FROM p_Project WHERE ' . $where;
    return MsSql()->fetchall($sql);
}


/**
 * @param $guid
 * @return bool|mixed
 */
function erp_getProject($guid)
{
    //
    $sql = 'SELECT * FROM p_Project WHERE ' . " ProjGUID='$guid'";
    return MsSql()->fetch($sql);
}

function erp_getProjectByCode($code)
{
    $sql = 'SELECT * FROM p_Project WHERE ' . " ProjCode='$code'";
    return MsSql()->fetch($sql);
}

/**
 * 获得产品类型
 * @param $projGUID
 * @return array
 */
function erp_getBuildProduct($projGUID)
{
    $sql = "select BuildProductTypeGUID as 'guid', BProductTypeShortCode as 'code', BProductTypeShortName as 'name' FROM p_BuildProductType";
    $sql .= " WHERE  (ProjGUID = '$projGUID')";
    return MsSql()->fetchall($sql);
}

/**
 * @param $projGUID
 * @return array
 */
function erp_getBuilds($projGUID)
{
    $sql = 'select * from p_Building';
    $sql .= " WHERE (ProjGUID = '$projGUID')";
    return MsSql()->fetchall($sql);
}


/**
 * 获得楼盘对应的楼层信息
 * @param $bldGUID
 * @return array
 */
function erp_getBuildFloor($bldGUID)
{
    $sql = 'select * from p_FloorPlan';
    $sql .= " WHERE  (BldGUID = '$bldGUID')";
    return MsSql()->fetchall($sql);
}

/**
 * 获得楼盘对应的房间信息
 * @param $bldGUID
 * @return array
 */
function erp_getBuildRoom($bldGUID)
{
    $sql = 'select * from p_Room';
    $sql .= " WHERE  (BldGUID = '$bldGUID')"; //AND Status='待售'
    return MsSql()->fetchall($sql);
}


/**
 * 获得楼幢单元信息
 * @param $bldGUID
 * @return array
 */
function erp_getBuildUnit($bldGUID)
{
    $sql = 'select * from p_buildunit';
    $sql .= " WHERE  (BldGUID = '$bldGUID')"; //AND Status='待售'
    return MsSql()->fetchall($sql);
}

/**
 * 项目户型字典
 * @param $projGUID
 * @return array
 */
function erp_getBuildHuXing($projGUID)
{
    $sql = 'select HuXing as name, HuXingGUID as guid FROM p_HxSet';
    $sql .= " WHERE  (ProjGUID = '$projGUID')";
    return MsSql()->fetchall($sql);
}


function erp_getAllParams($ParamName, $ScopeGUID)
{
    $sql = "SELECT * FROM mybizparamoption ";
    $sql .= " WHERE ParamName='{$ParamName}' AND ScopeGUID='{$ScopeGUID}'";
    return MsSql()->fetchall($sql);
}


/**
 * 获得岗位员工数据
 * @param $projGUID
 * @return array
 */
function erp_getStationUser($projGUID)
{
    $sql = "select a.StationGUID,a.StationName,c.UserGUID,c.UserName from mystation a,mystationUser b,myUser c ";
    $sql .= "where b.UserGUID=c.UserGUID and a.StationGUID=b.StationGUID  and ProjGUID='{$projGUID}'";
    return MsSql()->fetchall($sql);
}


/**
 * @param $usercode
 * @param $password
 * @return bool
 */
function erp_login($usercode, $password)
{
    $sql = " SELECT us.UserGUID,us.UserName,us.Password,bu.BUGUID,bu.BUName,bu.IsEndCompany  FROM myUser us,myBusinessUnit bu  WHERE us.BUGUID = bu.BUGUID  AND (us.IsDisabeld=0 OR us.IsDisabeld IS null) ";
    $sql .= " AND us.UserCode='{$usercode}'";
    $user = MsSql()->fetch($sql);
    $result = false;
    if (!empty($user)) {
        $result = strtolower($user['Password']) == md5($password);
    }
    return $result;
}

/**
 * 获得项目下的职位信息
 * @param $projGUID
 */
function erp_getStation($projGUID)
{
    $sql = " SELECT * FROM mystation";
    $sql .= " where ProjGUID='{$projGUID}'";
    return MsSql()->fetchall($sql);
}

/**
 * 获得项目下的职位-用户关系
 * @param $stationGUID
 */
function erp_getStation_User($stationGUID)
{
    $sql = " SELECT * FROM mystationUser ";
    $sql .= " where StationGUID='{$stationGUID}'";
    return MsSql()->fetchall($sql);
}

/**
 * 获得职位下的用户
 * @param $stationGUID
 */
function erp_getUsersByStation($stationGUID)
{
    $sql = " SELECT u.* FROM myUser u ,mystationUser s where s.UserGUID=u.UserGUID ";
    $sql .= " and s.StationGUID='{$stationGUID}'";
    return MsSql()->fetchall($sql);
}


/**
 * 获取项目票据关联信息
 * @param $projGUID
 * @return array
 */
function erp_getInvoice2Proj($projGUID)
{
    $sql = " SELECT * FROM p_Invoice2Proj";
    $sql .= " where ProjGUID='{$projGUID}'";
    return MsSql()->fetchall($sql);
}


/**
 * 获取拆扣相关信息
 * @param $projGUID
 */
function erp_getDiscountDefine($projGUID)
{
    $sql = " SELECT * FROM s_DiscountDefine";
    $sql .= " where ProjGUID='{$projGUID}'";
    return MsSql()->fetchall($sql);
}

/**
 * 获取票据详细信息
 * @param $invoGUID
 * @return array
 */
function erp_getInvoice($invoGUID)
{
    $sql = " SELECT * FROM p_Invoice";
    $sql .= " where InvoGUID='{$invoGUID}'";
    return MsSql()->fetch($sql);
}

/**
 * 获取票据明细信息
 * @param $invoGUID
 * @return array
 */
function erp_getInvoiceDetail($invoGUID)
{
    $sql = " SELECT * FROM p_InvoiceDetail";
    $sql .= " where InvoGUID='{$invoGUID}'";
    return MsSql()->fetchall($sql);
}

/**
 * @param $projGUID
 * @return array
 */
function erp_get_PayForm($projGUID)
{
    $sql = " SELECT * FROM s_payform";
    $sql .= " where ProjGUID='{$projGUID}'";
    return MsSql()->fetchall($sql);
}

function erp_get_PayDetail($PayFormGUID)
{
    $sql = " SELECT * FROM s_paydetail";
    $sql .= " where PayFormGUID='{$PayFormGUID}'";
    return MsSql()->fetchall($sql);
}


function erp_write_Voucher($bill, $project)
{
    $inserted = false;
    if (empty($bill['InvoDetailGUID'])) {
        return $inserted;
    }
    //检查票据明细是否使用
    $vouchguid = GUID();
    $voucher = array(
        'VouchGUID' => $vouchguid,
        'Jkr' => $bill['Jkr'],
        'Kpr' => $bill['Kpr'],
        'BatchNo' => $bill['BatchNo'],
        'ProjGUID' => $bill['ProjGUID'],
        'BuGUID' => $project['BUGUID'],
        'KpDate' => $bill['KpDate'],
        'VouchType' => '收款单',
        'CreateTime' => $bill['KpDate'],
        'CreateDate' => $bill['KpDate'],
        'BuGUID' => $project['BUGUID'],
        'signguid' => $project['projguid'],
    );
    //
    if ($bill['BillType'] == 1) {
        $voucher['InvoDetailGUID'] = $bill['InvoDetailGUID'];
        $voucher['InvoNO'] = $bill['InvoNo'];
        $voucher['Invotype'] = '收据';
    } else {
        $voucher['Invotype'] = '无票据';
    }
    $inserted = MsSql()->insertObject('s_Voucher', $voucher);
    if(!$inserted){
        logging('写入s_Voucher出错', $bill['BillGUID']);
    }
    if ($inserted) {
        $details = iunserializer($bill['Details']);
        foreach ($details as $f) {
            $getin = array(
                'GetinGUID' => GUID(),
                'VouchGUID' => $vouchguid,
                'GetDate' => $bill['KpDate'],
                'Amount' => $f['money'],
                'Bz' => '人民币',
                'ExRate' => 1,
                'RmbAmount' => $f['money'],
                'FsettleNo' => $f['FsettleNo'],
                'Remark' => $f['note'],
                'YhPayform' => 'POS机',
                'RzBank' => $f['bank'],
                'SpState' => 0,
                'InSequence' => 1,
                'GetForm' => '银行',
                'BeforeYe' => $f['money'],
                'BeforeRmbYe' => $f['money'],
                'signguid' => $project['projguid'],
            );
            if ($bill['BillType'] == 1) {
                $getin['ItemType'] = '其它';
                $getin['ItemName'] = '预约金';

            } else {
                $getin['ItemType'] = '定金';
                $getin['ItemName'] = '非贷款类房款';
                //todo：取销售员对应guid
                //$getin['SaleGUID'] = $bill['Qrcode'],
            }
            if (MsSql()->insertObject('s_Getin', $getin) === false) {
                $inserted = false;
                logging('s_Getin', $bill['BillGUID']);
            }
        }
    }
    return $inserted;
}

function erp_Write_Trade($chips)
{
    $write = false;
    $projGUID = $chips['projguid'];
    //生成交易trade,再写入order
    $data = MsSql()->fetch("select TradeGUID from s_Trade where TradeGUID='{$chips['qrcode']}'");
    if (!empty($data)) {
        return false;
    }
    $trade = array(
        'BDCustomer' => $chips['local'],
        'HadHouseTS' => $chips['housenum'],
        'RoomGUID' => $chips['roomguid'],
        'TradeGUID' => $chips['qrcode'],
        'signguid' => $projGUID,
    );
    $write = MsSql()->insertObject('s_Trade', $trade);

    if ($write) {
        //生成trade2cst
        $info = biz_getChipsCustomerInfo($chips);
        $list = explode(',', $info['guid']);
        $i=1;
        foreach ($list as $cid) {
            $trade2cst = array('Trade2CstGUID' => GUID(),
                'TradeGUID' => $chips['qrcode'],
                'CstGUID' => $cid,
                'signguid' => $projGUID,
                'CstNum' => $i,
                'PropertyRate' => 100,
            );
            $i++;
            $write &= MsSql()->insertObject('s_Trade2Cst', $trade2cst);
        }
    } else {
        logging('写入s_Trade出错', $chips['qrcode']);
    }
    return $write;
}


function erp_Write_Order($chips, $payform)
{
    $write = false;
    $projGUID = $chips['projguid'];
    $room = db_getRoomByGUID($chips['roomguid']);
    $AjTotal = AjTotal($chips['projguid'], $chips['qrcode']);
    $OrderGUID = GUID();
    $order = array(
        'OrderGUID' => $OrderGUID,
        'TradeGUID' => $chips['qrcode'],
        'ProjGuid' => $chips['projguid'],
        'Ywy' => $chips['salesman'],
        'CreatedBy' => $chips['creator'],
        'BUGUID' => $room['BUGUID'],
        'RoomGUID' => $chips['roomguid'],
        'DiscntValue' => $room['DiscntValue'],
        'BldCjPrice' => $room['BldCjPrice'],
        'BldArea' => $room['BldArea'],
        'Price' => $room['Price'],
        'TnArea' => $room['TnArea'],
        'TnPrice' => $room['TnPrice'],
        'Total' => $room['Total'],
        'CjTotal' => $room['RoomTotal'],
        'RmbCjTotal' => $room['RoomTotal'],
        'DiscntRemark' => $payform['calc']['remark'],
        'PayFormName' => $payform['Payform']['PayformName'],
        'RoomTotal' => $room['RoomTotal'],
        'TnCjPrice' => $room['TnCjPrice'],
        'Bz' => '人民币',
        'OrderType' => '认购',
        'Status' => '激活',
        'AreaStatus' => '预售',
        'ExRate' => 1.00,
        'PotocolNO' => $chips['ProtocolNO'],
        //'salesmanGUID'=>$chips['salesmanGUID'],
        'QSDate' => date("Y-m-d", $chips['QSDate']),
        'EndDate' => date("Y-m-d", strtotime("+10 days ", $chips['QSDate'])),
        'CreatedOn' => date("Y-m-d", $chips['QSDate']),
        'ModiDate' => date("Y-m-d", $chips['QSDate']),
        'YwblDate' => date("Y-m-d", $chips['QSDate']),
        'DLGS' => $chips['agency'],
        'CreatedByGUID' => $chips['createid'],
        'IsCreatorUse' => 1,
        'SpState' => 0,
        'NewZsAmount' => 0,
        'Xzdlgs' => $chips['agencychild'],
        'IsJjx' => 0,
        'IsZx' => 0,
        'TjrRoomBldArea' => 0,
        'TjrDiscnt' => 100,
        'XyTotal' => $room['RoomTotal'],
        'CjSum' => 0,
        'CalMode' => $room['CalcRentMode'],
        'Earnest' => $chips['shouldpay'],
        'UserGUIDList' => $chips['salesmanGUID'],
        'fjcsdj' => $room['CsDjTotal'],
        'AjTotal' => $AjTotal['Amount'],
        'signguid' => $projGUID
    );
    $write = MsSql()->insertObject('s_Order', $order);
    if (!$write) {
        logging('写入s_Order出错', $chips['qrcode']);
    }
    if ($write){
        $oc2sale = array(
            'OC2SaleGUID'=>GUID(),
            'SaleGUID'=>$OrderGUID,
            'SaleType'=>'定单',
            'UserGUID'=>$chips['salesmanGUID'],
            'FTRate'=>100,
            'signguid'=>$projGUID,
            //'Remark'=>'',
        );
        $write = MsSql()->insertObject('s_OC2Sale',$oc2sale);
        if (!$write){
            logging('写入s_OC2Sale出错',$chips['qrcode']);
        }
    }
    if ($write) {
        $discount[] = array(
            'DiscntGUID' => $payform['Payform']['PayFormGUID'],
            'SaleType' => '定单',
            'DiscntName' => $payform['Payform']['PayformName'],
            'DisCntType' => '付款方式',
            'CalMethod' => '打折',
            'DiscntValue' => $payform['Payform']['DisCount'],
            'PreferentialPrice' => '0.00',
            'Remark' => '付款方式定义的折扣',
        );
        $keys=array_keys($discount[0]);
        foreach ($payform['disc_Details'] as $p) {
            $discount[] = array_elements($keys,$p);
        }
        $i=1;
        foreach ($discount as $d) {
            $d['OCDiscountGUID']= GUID();
            $d['SaleGUID'] = $OrderGUID;
            $d['OCDiscountGUID'] = GUID();
            $d['signguid'] = $projGUID;
            $d['Sequence']=$i++;
            if (MsSql()->insertObject('s_OCDiscount', $d) === false) {
                logging('写入s_OCDiscount出错', $d['Sequence']);
            }
        }
    }


    if ($write) {
        // 更新房间状态信息，
        $customers = biz_getChipsCustomerInfo($chips);
        $update = array('Status' => $room['Status'],
            'CstGUIDList' => $customers['guid'],
            'CstName' => $customers['name'],
            'ChooseRoomDate' => $room['ChooseRoomDate'],
            'Status'=>$room['Status'],
            'ChooseRoomCstName'=>$customers['name'],
        );
       // $sql = "update p_Room set Status='{$room['Status']}',CstGUIDList='{$update['CstGUIDList']}',ChooseRoomCstName='{$update['CstName']}',ChooseRoomDate='{$update['ChooseRoomDate']}' where RoomGUID='{$chips['roomguid']}' ";
        if (MsSql()->update('p_Room',$update,array('RoomGUID'=>$chips['roomguid'])) === false) {
            logging('写入p_Room出错', $chips['roomguid']);
            $write = false;
        }
    }
    return $write;
}


function erp_Write_Customer($chips)
{

    $write = false;
    $project = biz_getProject($chips['projguid']);
    $fields = array('CstGUID', 'CstName','CstType','KhFl', 'CardID', 'OfficeTel', 'HomeTel', 'MobileTel', 'PostCode', 'Address', 'CstType', 'Gender', 'CardType', 'WorkAddr', 'Country', 'CompanyPhone', 'signguid');
    //要检查客户是否已录入
    $info = biz_getChipsCustomerInfo($chips);
    $list = explode(',', $info['guid']);
    foreach ($list as $cid) {
        $customer = biz_getCustomerInfo(array('CstGUID' => $cid));
        if (empty($customer['erp'])) {
            //$customer['CreatedBy']=$chips['creator'];
            $customer['CstType']='个人';
            //$customer['KhFl']='普通客户';
            $customer['signguid'] = $chips['projguid'];
            $write = MsSql()->insertObject('p_Customer', array_elements($fields, $customer));
            // 生成CstAttach(客户项目对应表)
        }
        $exist = MsSql()->fetch('select * from p_CstAttach where ' . " CstGUID='{$cid}' and ProjGUID='{$chips['projguid']}'");
        if ($write && (empty($exist))) {
            $attach = array(
                'CstAttachGUID' => GUID(),
                'CstGUID' => $cid,
                'ProjGUID' => $chips['projguid'],
                'BUGUID' => $project['BUGUID'],
                'signguid' => $chips['projguid']
            );
            $write = MsSql()->insertObject('p_CstAttach', $attach);
            if ($write === false) {
                logging('写入p_CstAttach出错', $cid);
            }
        }
    }
    return $write;
}

function  erp_Write_fee($chips)
{
    $write = false;
    $unfields = array('ProjGUID', 'SyncTime', 'Rate','Flag');
    $sql = " select * from " . tablename('s_fee') . " where TradeGUID=:guid order by Sequence";
    $list = pdo_fetchall($sql, array(':guid' => $chips['qrcode']));
    foreach ($list as $fee) {
        if ($fee['SyncTime'] == 0) {
            foreach ($unfields as $field) {
                unset($fee[$field]);
            }
            if (empty($fee['lastDate'])) {
                unset($fee['lastDate']);
            }
            $fee['signguid'] = $chips['projguid'];
            $write = MsSql()->insertObject('s_Fee', $fee);
            if ($write) {
                pdo_update('s_fee', array('SyncTime' => TIMESTAMP), array('FeeGUID' => $fee['FeeGUID']));
            } else {
                logging('写入s_fee出错', $fee['FeeGUID']);
            }
        }
    }
    return $write;
}
 
 
 


 