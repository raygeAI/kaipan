<?php



function downExcel($map, $data)
{
    include_once IA_ROOT . '/framework/class/PHPExcel.php';
    include_once IA_ROOT . '/framework/class/PHPExcel/Writer/Excel5.php';
    $excel = new PHPExcel();
    //$map['title'] = iconv('utf-8", "gb2312", $map['title']);
    $excel->getProperties()->setCreator("时代地产");
    $excel->setActiveSheetIndex(0);
    $excel->getActiveSheet()->setTitle($map['title']);
    $excel->setActiveSheetIndex(0);
    $sheet = $excel->getActiveSheet();
    $c = range('A', 'Z');
    $i = 1;
    $cell = '';
    foreach ($map['fields'] as $k => $f) {
        $cell = $c[$k] . $i;
        $sheet->setCellValue($cell, $f['title']);
    }

    foreach ($data as $item) {
        $i++;
        foreach ($map['fields'] as $k => $f) {
            $cell = $c[$k] . $i;
            $value = $item[$f['field']];
            if ($f['type'] == 1) {
                $value = date('Y-m-d H:i:s', $item[$f['field']]);
            }
            //数值类型
            if ($f['type'] == 2) {
                $sheet->setCellValueExplicit($cell, $value, PHPExcel_Cell_DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($cell, $value);
            }

        }
    }

    $writer = new PHPExcel_Writer_Excel5($excel);

    header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
    header("Content-Type:application/force-download");
    header("Content-Type: application/vnd.ms-excel;charset=UTF-8");
    header("Content-Type:application/octet-stream");
    header("Content-Type:application/download");
    //header("Content-Disposition:attachment;filename=" . $map['title'] . ".xls");
    $ua =strtoupper( $_SERVER["HTTP_USER_AGENT"]);
    $filename=basename($map['title'] . ".xls");
    if (preg_match("/IE/", $ua)) {
        $encoded_filename = str_replace("+", "%20", urlencode($filename));
        //header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
    } else {
        header('Content-Disposition: attachment; filename=' . $filename);
    }
    header("Content-Transfer-Encoding:binary");
    $writer->save("php://output");
}



function down_BaseInfo($list){


    $map['title'] = '认筹单信息';
    $map['fields'] = array(
        array('title' => '姓名', 'field' => 'cname', 'type' => 0),
        array('title' => '性别', 'field' => 'grender', 'type' => 0),
        array('title' => '手机号码', 'field' => 'mobile', 'type' => 2),
        array('title' => '证件号码', 'field' => 'cardid', 'type' => 2),
        array('title' => '产品', 'field' => 'product', 'type' => 0),
        array('title' => '附属权益人', 'field' => 'holdername', 'type' => 0),
        array('title' => '通讯地址', 'field' => 'address', 'type' => 0),
        array('title' => '是否本地户口', 'field' => 'local', 'type' => 0),
        array('title' => '意向户型', 'field' => 'housetype', 'type' => 0),
        array('title' => '具体意向1', 'field' => 'intendroom1', 'type' => 0),
        array('title' => '具体意向2', 'field' => 'intendroom2', 'type' => 0),
        array('title' => '具体意向3', 'field' => 'intendroom3', 'type' => 0),
        array('title' => '业务员', 'field' => 'salesman', 'type' => 0),
        array('title' => '状态', 'field' => 'status', 'type' => 0),
        array('title' => '创建时间', 'field' => 'createtime', 'type' => 1),
    );
    global $status;

    foreach($list as &$item){
        $item['local'] = ($item['local']==1) ? '是':'否';
        $item['address']=biz_getAllCustomerField($item['cid'],'Address');
        // 具体意向处理
        $rooms = $item['intendroom'];
        $rooms = explode(',' ,$rooms);
        $item['intendroom1'] = isset($rooms[0]) ? $rooms[0]:'';
        $item['intendroom2'] = isset($rooms[1]) ? $rooms[1]:'';
        $item['intendroom3'] = isset($rooms[2]) ? $rooms[2]:'';
        $item['status'] = $status[$item['pretype']];
    }
    unset($item);
    downExcel($map, $list);

}


function down_PrepayInfo($list){
    $map['title'] = '诚意金交款';
    $map['fields'] = array(
        array('title' => '姓名', 'field' => 'cname', 'type' => 0),
        array('title' => '性别', 'field' => 'grender', 'type' => 0),
        array('title' => '手机号码', 'field' => 'mobile', 'type' => 2),
        array('title' => '证件号码', 'field' => 'cardid', 'type' => 2),
        array('title' => '意向户型', 'field' => 'housetype', 'type' => 0),
        array('title' => '开票日期1', 'field' => 'KpDate1', 'type' => 2),
        array('title' => '票据编号1', 'field' => 'InvoNo1', 'type' => 2),
        array('title' => '票据明细1', 'field' => 'Details1', 'type' => 2),
        array('title' => '开票日期2', 'field' => 'KpDate2', 'type' => 2),
        array('title' => '票据编号2', 'field' => 'InvoNo2', 'type' => 2),
        array('title' => '票据明细2', 'field' => 'Details2', 'type' => 2),
        array('title' => '创建日期', 'field' => 'createtime', 'type' => 1),
        array('title' => '销售人员', 'field' => 'salesman', 'type' => 0),
        array('title' => '状态', 'field' => 'status', 'type' => 0),
    );
    global $status;
    //addBrokerName($list, 'proid', 'proname');
    foreach ($list as &$item) {
        $bill = biz_getBills($item['qrcode'], 1, true);
        if (count($bill) == 1) {
            $item['KpDate1'] = !empty($bill[0]['KpDate']) ? $bill[0]['KpDate'] : '无';
            $item['InvoNo1'] = !isset($bill[0]['IvoNo']) ? $bill[0]['InvoNo'] : '无';
            $detail1 = $bill[0]['finance'];
            $dat1 = '';
            foreach ($detail1 as $d) {
                $dat1 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat1 = rtrim($dat1, "\n");
            $item['Details1'] = $dat1;
            $item['KpDate2'] = '无';
            $item['InvoNo2'] = '无';
            $item['Details2'] = '无';
        }
        if (count($bill) == 2) {
            $item['KpDate1'] = !empty($bill[0]['KpDate']) ? $bill[0]['KpDate'] : '无';
            $item['InvoNo1'] = !isset($bill[0]['IvoNo']) ? $bill[0]['InvoNo'] : '无';
            $detail1 = $bill[0]['finance'];
            $dat1 = '';
            foreach ($detail1 as $d) {
                $dat1 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat1 = rtrim($dat1, "\n");
            $item['Details1'] = $dat1;
            $item['KpDate2'] = !empty($bill[1]['KpDate']) ? $bill[1]['KpDate'] : '无';
            $item['InvoNo2'] = !isset($bill[1]['IvoNo']) ? $bill[1]['IvoNo'] : '无';
            $detail2 = $bill[1]['finance'];
            $dat2 = '';
            foreach ($detail2 as $d) {
                $dat2 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat2 = rtrim($dat2, "\n");
            $item['Details2'] = $dat2;
        }
        $item['status'] = $status[$item['pretype']];
    }
    downExcel($map, $list);   
}


function down_LuckyInfo($list){
    $map['title'] = '中签登记信息';
    $map['fields'] = array(
        array('title' => '姓名', 'field' => 'cname', 'type' => 0),
        array('title' => '性别', 'field' => 'grender', 'type' => 0),
        array('title' => '手机号码', 'field' => 'mobile', 'type' => 2),
        array('title' => '证件号码', 'field' => 'cardid', 'type' => 2),
        array('title' => '业务员', 'field' => 'salesman', 'type' => 0),
        array('title' => '签到状态', 'field' => 'signed', 'type' => 0),
        array('title' => '中签状态', 'field' => 'lucky', 'type' => 0),
    );

    foreach ($list as &$item) {
        $item['lucky'] = ($item['lucky'] == 1) ? '已中签' : '未中签';
        $item['signed'] = ($item['signed'] > 0) ? '已签到' : '未签到';
    }
    unset($item);
    downExcel($map, $list);
}


function down_OrderInfo($list){

    $map['title'] = '认购交款表';
    $map['fields'] = array(
        array('title' => '姓名', 'field' => 'cname', 'type' => 0),
        array('title' => '性别', 'field' => 'grender', 'type' => 0),
        array('title' => '手机号码', 'field' => 'mobile', 'type' => 2),
        array('title' => '证件号码', 'field' => 'cardid', 'type' => 2),
        array('title' => '房间信息', 'field' => 'roomcode', 'type' => 0),
        array('title' => '开票日期1', 'field' => 'KpDate1', 'type' => 2),
        array('title' => '票据编号1', 'field' => 'InvoNo1', 'type' => 2),
        array('title' => '票据明细1', 'field' => 'Details1', 'type' => 2),
        array('title' => '开票日期2', 'field' => 'KpDate2', 'type' => 2),
        array('title' => '票据编号2', 'field' => 'InvoNo2', 'type' => 2),
        array('title' => '票据明细2', 'field' => 'Details2', 'type' => 2),
        array('title' => '创建日期', 'field' => 'createtime', 'type' => 1),
        array('title' => '销售人员', 'field' => 'salesman', 'type' => 0),
        array('title' => '状态', 'field' => 'status', 'type' => 0),
    );
    //addBrokerName($list, 'proid', 'proname');
    foreach ($list as &$item) {
        $bill = biz_getBills($item['qrcode'], 2, true);
        if (count($bill) == 1) {
            $item['KpDate1'] = !empty($bill[0]['KpDate']) ? $bill[0]['KpDate'] : '无';
            $item['InvoNo1'] = !isset($bill[0]['IvoNo']) ? $bill[0]['InvoNo'] : '无';
            $detail1 = $bill[0]['finance'];
            $dat1 = '';
            foreach ($detail1 as $d) {
                $dat1 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat1 = rtrim($dat1, "\n");
            $item['Details1'] = $dat1;
            $item['KpDate2'] = '无';
            $item['InvoNo2'] = '无';
            $item['Details2'] = '无';
        }
        if (count($bill) == 2) {
            $item['KpDate1'] = !empty($bill[0]['KpDate']) ? $bill[0]['KpDate'] : '无';
            $item['InvoNo1'] = !isset($bill[0]['IvoNo']) ? $bill[0]['InvoNo'] : '无';
            $detail1 = $bill[0]['finance'];
            $dat1 = '';
            foreach ($detail1 as $d) {
                $dat1 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat1 = rtrim($dat1, "\n");
            $item['Details1'] = $dat1;
            $item['KpDate2'] = !empty($bill[1]['KpDate']) ? $bill[1]['KpDate'] : '无';
            $item['InvoNo2'] = !isset($bill[1]['IvoNo']) ? $bill[1]['IvoNo'] : '无';
            $detail2 = $bill[1]['finance'];
            $dat2 = '';
            foreach ($detail2 as $d) {
                $dat2 .= '金额:' . $d['money'] . ',银行:' . $d['bank'] . ',转账单编号:' . $d['FsettleNo'] . ',摘要:' . $d['note'] . "\n";
            }
            $dat2 = rtrim($dat2, "\n");
            $item['Details2'] = $dat2;
        }
        $item['status'] = $status[$item['pretype']];
    }
    downExcel($map, $list);
}