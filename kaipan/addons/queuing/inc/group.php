<?php
if(!in_array($op,array('list','delete','addgroup','delgroup','addrc'))){
    $op='list';
}

$gid = intval($_GPC['gid']);
$sid=intval($_GPC['sid']);
$pid = $_W['project']['id'];

if($op=='list'){
    // 添加组员
    $groups=biz_getAllSignGroup($pid);
    $keys=array_keys($groups);

    if(count($keys)>0 &&!in_array($sid,$keys)){
        $sid=$keys[0];
    }
    $sel_group=$groups[$sid];
    unset($keys);
    $signs=biz_getSignsByGroup($sid,$pid);
    include $this->template('group_list');
    exit;
}

if($op=='addrc'){
    if ($_W['isajax']&&$_W['ispost']){
        $qrcode = getInputGUID($_GPC['qrcode']);
        if(empty($qrcode) ){
            exit('请填写有效的认筹单号！');
        }
        $chips=biz_getChipsByQrcode($qrcode);
        if(empty($chips)||$chips['projguid']!=$_W['project']['projguid']) {
            exit('无效的认筹单！');
        }
        $num=intval($_GPC['sel_code']);
        $group=biz_getSignGroupByNum($num,$pid);
        if(empty($group)){
            exit('组数据不存在，请刷新页面！');
        }
        $presign = biz_getSignInfoByQrcode($qrcode,$pid);
        if (!empty($presign)){
            exit("认筹单已在{$presign['gid']}组，不能再次预设！");
        }

        if(biz_insertSignInfo($chips,$num,$pid,true)){
            Sign_updateGroup($group);
            exit('success');
        }else{
            exit('增加失败！');
        }

    }   
}


if($op=='addgroup'){

    if($_W['isajax']&&$_W['ispost'])
    {
        $index=intval($_GPC['num']);
        if($index<=0){
            exit('无效号码！');
        }
        if(!in_array($index,$keys)) {
            biz_addSignGroup($_GPC['num'],$pid);
            exit('success');
        }else{
            exit('号码已存在！');
        }
    }
}


if($op=='delete'){
    $id=intval($_GPC['id']);
    // 预设 && 未签到 可删除
    $sql = "DELETE FROM ". tablename('sign'). "where id=:id and signed=:signed and preset=:preset and pid=:pid";
    pdo_fetch($sql,array(':id'=>$id, ':signed'=>0, ':preset'=>1, ':pid' => $pid));
    // 总数 -1，签到的不予处理
    $update_sql = "UPDATE ". tablename('call_group'). "set maxnum=maxnum-1 where dispnum=:dispnum and pid=:pid";
    pdo_fetch($update_sql, array(':dispnum' => $gid, ':pid' => $pid));
    message('认筹单已经删除！', $this->createWebUrl($do,array('sid'=>$gid)));
    include $this->template('group_list');
    exit;
}

if($op=='delgroup'){
    if($_W['isajax']&&$_W['ispost'])
    {
        //$del_sql = "DELETE FROM ". tablename('call_group'). "where pid=:pid and dispnum=:sid";
        if(!(empty($sid) && empty($pid)))
        {
            pdo_delete('call_group', array('pid'=>$pid,'dispnum' => $sid));
            exit('success');
        }else{
            exit('not existing');
        }
    }
}