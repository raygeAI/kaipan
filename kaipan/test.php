<?php
//增加接口调用
define('IN_SYS', true);
error_reporting(E_ALL & ~E_NOTICE);

require './framework/bootstrap.inc.php';
load()->classs('mssql');
load()->web('erp');
load()->web('business');
load()->web('dbapi');
 
$sql='select * from '.tablename('chips')." where ProtocolNO<>''  order by QSDate";
$list=pdo_fetchall($sql);
$i=0;
foreach($list as $item) {
    $i++;
    $t=date('H:i:s',$item['QSDate']);
    $no='FSRL150101'.sprintf('%04d', $i);
    pdo_update('chips',array('ProtocolNO'=>$no),array('id'=>$item['id']));
}

/*
$sql='select * from '.tablename('chips').' where pretype=0 and projguid=:projguid';
$list=pdo_fetchall($sql,array(':projguid'=>$projguid));
$inputBill=array(
    'Money'=>10000,
    'finance'=>array(
        0=>array('money'=>10000,'bank'=>'顺德工行容桂支行5668','note'=>'补手工记录','FsettleNo'=>'000000','Printed'=>1)
    )
);
foreach($list as $item){
    $bills=biz_getBills($item['qrcode']);
    if(empty($bills)){
        $inputBill['BillGUID'] = GUID();
        biz_addBill($item, $inputBill);
        pdo_update('bill',array('ErpSync'=>4,'BatchNo'=>'FSRL1','InvoNo'=>'000000'),array('BillGUID'=>$inputBill['BillGUID']));
        pdo_update('chips',array('premoney'=>10000,'pretype'=>1),array('id'=>$item['id']));
    }
}
*/