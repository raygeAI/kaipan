<?php
/**
 *  中心及开盘服务器间数据接口处理
 */

function syncFromClient_handler($guid)
{
    //从数据获取client相关信息

}

function importFromCenter_handler($guid)
{
    //从开盘服务器导入数据处理
    global $_W;
    $progress = 5;
    updateProgress($progress, '获取导入验证');
    $domain = $_W['config']['server'];
    $url = "http://{$domain}/db.php";//?XDEBUG_SESSION_START=PHPSTORM
    showLog("正在连接中心{$domain}.....");
    $res = curl($url, array('func' => 'get_token', 'guid' => $guid, 'client' => $_W['config']['client']));
    if (!empty($res) && $res['ok']) {
        $token = $res['token'];
        showLog("连接中心{$domain}成功，已获取验证<br/>");
    } else {
        showLog('获取验证失败:'.$res['msg']);
        updateProgress(100, '导入项目失败');
        return false;
    }
    $calls = array(
        'project' => '主体项目',
        'user'=>'用户信息',
        'build' => '楼盘信息',
        'room' => '房间信息',
        'chips' => '认筹信息',
    );
    $step = 20;
    $clear = false;
    foreach ($calls as $c => $m) {
        $progress += $step;
        updateProgress($progress, '正在获取' . $m);
        $data = curl($url, array('token' => $token, 'func' => 'get_data', 'type' => $c));
        if (empty($data) || empty($data['ok'])) {
            showLog('获取' . $m . '出错' . $data['msg'] . '<br>');
            continue;
        }
        if ($data['ok']) {
            showLog('获取' . $m . '成功<br>');
            foreach ($data['data'] as $sync) {
                try {
                    $res = sync_table($sync, true);
                    showLog(" >>{$res['table']}表{$res['total']}条记录，更新{$res['update']}条，插入{$res['insert']}条<br/>");

                } catch (Exception $e) {
                    showLog(" >>{$res['table']}表更新出错：".$e->getMessage().'<br/>');
                }
            }
        }
        sleep(1);
    }
    curl($url, array('token' => $token, 'func' => 'release_token'));
    showLog('导入完成');
    updateProgress(100, '导入完成');
}


function download_project($guid)
{
    $res = array();
    $rows = pdo_fetchall('select * from ' . tablename('project') . '  where projguid=:id', array(':id' => $guid));
    $options = db_getAllParams('s_DLGS', $guid);
    foreach ($rows as &$r) {
        unset($r['id']);
        $options = array_merge($options, db_getAllParams('s_RzBank', $r['BUGUID']));
        //代理公司过滤
        $id=$r['Level']==3?$r['ParentGUID']:$r['projguid'];
        $options = array_merge($options, db_getAllParams('s_DLGS', $id));
    }
    unset($r);
    $res[] = array('table' => 'project', 'key' => 'projguid', 'rows' => $rows);
    unset($rows);
    $res[] = array('table' => 'mybizparamoption', 'key' => 'ParamGUID', 'rows' => $options);
    unset($options);
    return $res;
}

function download_user($guid){
    $res[] = array('table' => 'mystation', 'key' => 'StationGUID', 'rows' => db_getMyStation($guid));
    $res[] = array('table' => 'mystationuser', 'key' => 'StationUserGUID', 'rows' => db_getMyStationUser($guid));
    $res[] = array('table' => 'myuser', 'key' => 'UserGUID', 'rows' => db_getMyUser($guid));
    return $res;
}

function download_build($guid)
{
    $res = array();
    $rows = db_getBuilds($guid);
    $res[] = array('table' => 'p_building', 'key' => 'BldGUID', 'rows' => $rows,
        'clear'=>array('ProjGUID'=>$guid),'clearchild'=>array('p_buildunit'));
    $units = array();
    foreach ($rows as $r) {
        $list = db_getBuildUnit($r['BldGUID']);
        $units = array_merge($units, $list);
    }
    unset($rows);
    $res[] = array('table' => 'p_buildunit', 'key' => 'UnitGUID', 'rows' => $units);
    return $res;
}

function sync_download($data, $clear = false)
{
    foreach ($data as $r) {
        sync_data($r['table'], $r['table'], $r['table']);
    }
}

function download_room($guid, $clear = false)
{
    $res[] = array('table' => 'p_room', 'key' => 'RoomGUID',
        'rows' => db_getRooms($guid),
        'clear'=>array('ProjGUID'=>$guid)
    );
    $res[] = array('table' => 's_payform', 'key' => 'PayFormGUID', 'rows' => db_getPayForm($guid),
        'clear'=>array('ProjGUID'=>$guid));
    $res[] = array('table' => 's_paydetail', 'key' => 'PayDetailGUID', 'rows' => db_getAllPayDetail($guid));
    $res[] = array('table' => 's_discountdefine', 'key' => 'DiscntGUID', 'rows' => db_getDiscount($guid));
    return $res;
}

function download_chips($guid, $clear = false)
{
    $rows=db_getChipsByProj($guid);
    foreach($rows as &$r){
        unset($r['id']);
    }
    unset($r);
    $res[] = array('table' => 'chips', 'key' => 'qrcode',
        'rows' => $rows,
        'clear'=>array('ProjGUID'=>$guid)
    );
    $res[] = array('table' => 'bill', 'key' => 'BillGUID', 'rows' => db_getBills($guid),
        'clear'=>array('ProjGUID'=>$guid));
    
    $res[] = array('table' => 's_fee', 'key' => 'FeeGUID', 'rows' => db_getFees($guid),
        'clear'=>array('ProjGUID'=>$guid));
    
    $res[] = array('table' => 'p_cstattach', 'key' => 'CstAttachGUID', 'rows' => db_getCstAttach($guid),
        'clear'=>array('ProjGUID'=>$guid));
    $res[] = array('table' => 'p_customer', 'key' => 'CstGUID', 'rows' => db_getCustomers($guid));

    $invoices= db_getInvoices($guid);
    $res[] = array('table' => 'p_invoice', 'key' => 'InvoGUID', 'rows' => $invoices,
        'clear'=>array('ProjGUID'=>$guid), 'clearchild'=>array('p_invoicedetail'));

    $InvoiceDetails=array();
    foreach($invoices as $i){
        $InvoiceDetails=array_merge($InvoiceDetails,  db_getInvoiceDetails($i['InvoGUID']));
    }
    $res[] = array('table' => 'p_invoicedetail', 'key' => 'InvoDetailGUID', 'rows' => $InvoiceDetails);
    unset($invoices);
    return $res;
}


function curl($path, $post = null, $decode = true)
{
    $SSL = substr($path, 0, 8) == "https://" ? true : false;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "$path");
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; zh-CN; rv:1.9.2.10)');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,3);
    curl_setopt($curl, CURLOPT_TIMEOUT,180);
    if (!empty($post)) {
        curl_setopt($curl, CURLOPT_POST, 80);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    }
    $contents = curl_exec($curl);

    $errno = curl_errno($curl);
    $error = curl_error($curl);
    curl_close($curl);
    if ($errno || empty($contents)) {
        return array('ok' => false, 'msg' => empty($error) ? '获取失败' : $error);
    } else if ($decode) {
        return json_decode($contents, true);
    } else {
        return $contents;
    }
}
