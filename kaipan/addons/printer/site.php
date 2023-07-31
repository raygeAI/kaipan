<?php
defined('IN_IA') or exit('Access Denied');

class PrinterModuleSite extends IndustryModule
{
    public function doWebTask()
    {
        global $_W, $_GPC;
        $table = 'printtask';
        $op = !empty($_GPC['op']) ? $_GPC['op'] : 'list';
        if ($op == 'list') {
            $status=array('完成','等待','出错');

            $enable=$this->CheckRight('deltask',false);
            $condition = " `projguid`=:projguid ";
            $params = array(':projguid' => $_W['project']['projguid']);
            $selstatus='';
            if(isset($_GPC['status'])){
                $selstatus=$_GPC['status'];
            }
            if(!empty($selstatus)){
                $condition.=' and status=:status';
                $params[':status']=$selstatus;
            }
            if(isset($_GPC['keyword'])) {
                $keyword = trim($_GPC['keyword']);
                $condition .= " AND `printname` LIKE :keyword";
                $params[':keyword'] = "%{$keyword}%";
            }
            
            // 删除所有已完成的打印任务
            if($_GPC['submit'] == '删除'){
                pdo_delete('printtask', array('complate' => 1));
                message('删除数据成功!', $this->createWebUrl('task'));
            }
            $printtype = biz_getDictionary('printtype');
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;
            $sql = "SELECT * FROM " . tablename($table) ." WHERE {$condition}". ' order by createtime desc,updatetime  ';
            $list = pdo_fetchall($sql . " limit " . ($pindex - 1) * $psize . "," . $psize,$params);
            $total = pdo_fetchcolumn(" select count(*) from " . tablename($table)." WHERE {$condition}",$params);
            $pager = pagination($total, $pindex, $psize);
            
            foreach($list as &$item){
                $item['delete']=$enable?true:($item['createid']==$_W['uid']);
            }
            unset($item);
            include $this->template('task_list');
            exit;
        }

        if ($op == 'delete') {
            $id = $_GPC['id'];
            $task = db_getPrintTask($id);
            if (!empty($task)) {
                $enable=$this->CheckRight('deltask',false);
                if ($enable||$task['createid'] == $_W['uid']) {
                    pdo_delete('printtask', array('id' => $task['id']));
                    //message('删除成功！',$this->createWebUrl('task'));
                    exit('success');
                } else {
                    exit('无权删除该数据！');
                }
            } else {
                exit('无效数据');
            }
        }
        // 查看打印数据
        if($op == 'disp'){
            $id = $_GPC['id'];
            $task = db_getPrintTask($id);
            $printdata = iunserializer($task['printdata']);
            if($_W['isajax']){
                include $this->template('printdata_disp');
                exit;
            }
        }
    }

    public function doWebPrinter()
    {
        global $_GPC;
        $table = 'printer';
        $modules=biz_getPrintModule();
        $sql = " SELECT * FROM " . tablename($table);
        //$list = pdo_fetchall($sql);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $list = pdo_fetchall("SELECT * FROM " . tablename($table) . " limit " . ($pindex - 1) * $psize . "," . $psize);
        $total = pdo_fetchcolumn(" select count(*) from " . tablename($table));
        $pager = pagination($total, $pindex, $psize);
        
        foreach($list as &$item){
            $item['modulename']='';
            if(isset($modules[$item['moduleid']])){
                $m=$modules[$item['moduleid']];
                $item['modulename']=$m['computer'];
                $item['local']=$m['local']==1?'本地':'远程';
            }
        }
        unset($item);
        include $this->template('printer_list');
    }

    // 书签对应字段
    public function doWebTemplate()
    {
        global $_W, $_GPC;
        $op = !empty($_GPC['op']) ? $_GPC['op'] : 'list';
        $printtype = biz_getDictionary('printtype');
        if ($op == 'list') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;
            $table = 'printtemplate';
            $sql = "SELECT * FROM " . tablename($table) . " limit " . ($pindex - 1) * $psize . "," . $psize;
            $list = pdo_fetchall($sql);
            $total = pdo_fetchcolumn(" select count(*) from " . tablename($table));
            $pager = pagination($total, $pindex, $psize);
            include $this->template('template_list');
            exit;
        }
        if ($op == 'delete') {
            $this->CheckRight('deltemp');
            $id=$_GPC['id'];
            $template=db_getPrintTemplate($id);
            if(!empty($template)){
                pdo_delete('printtemplate', array('id' => $template['id']));
                exit('success');
            } else {
                exit('无效数据!');
            }
        }
        if($op=='down'){
            $id=$_GPC['id'];
            $template=db_getPrintTemplate($id);
            header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
            header("Content-Type:application/force-download");
            header("Content-Type: application/vnd.ms-word;charset=UTF-8");
            header("Content-Type:application/octet-stream");
            header("Content-Type:application/download");
            $ua = $_SERVER["HTTP_USER_AGENT"];
            $filename=$template['title'] . ".docx";
            if (preg_match("/MSIE/", $ua)) {
                $filename = urlencode($filename);
                header('Content-Disposition: attachment; filename=' . $filename);
            } else {
                header('Content-Disposition: attachment; filename=' . $filename);
            }
            header("Content-Transfer-Encoding:binary");
            echo base64_decode($template['content']);
            exit;
        }
        load()->web('print');
        if ($op == 'post') {
            $this->CheckRight('addtemp');
            if (checksubmit()) {
                if (!empty($_FILES['wordfile']['name'])) {
                    if ($_FILES['wordfile']['error'] != 0) {
                        message('文件上传失败，请重试！',$this->createWebUrl('template', array('op' => 'post')), 'error');
                    }
                } else {
                    message('请选择要上传的模板！' ,$this->createWebUrl('template', array('op' => 'post')), 'warning');
                }
                $word = GetUploadFile('wordfile');
                if(empty($word)){
                    message('上传文件失败，目录无写入权限！',$this->createWebUrl('template', array('op' => 'post')), 'error');
                }
                $tags=GetTagsOfXml($word);
                if(empty($tags)){
                    message('无效的模板文件，无法读取标签！',$this->createWebUrl('template', array('op' => 'post')), 'error');
                }
                $data = array(
                    'id'=>GUID(),
                    'title' => trim($_GPC['title']),
                    'printtype' => $_GPC['printtype'],
                    'tags' => iserializer($tags),
                    'content'=>base64_encode(file_get_contents($word)),
                    'tagsnum' => count($tags),
                    'status' => '0',
                    'createtime' => TIMESTAMP
                );
                @unlink($word);
                if (empty($data['title'])) {
                    $data['title'] = $_FILES['wordfile']['name'];
                }
                if(!empty($_GPC['onlyCurr'])){
                    $data['project'] = $_W['project']['projguid'];
                }
                pdo_insert('printtemplate', $data);
                //$test=biz_Print_getTemplateById($data['id']);
                message('保存数据成功！', $this->CreateWebUrl('template'));
            }
            include $this->template('template_post');
            exit;
        }

        if ($op == 'tags') {
            $id=$_GPC['id'];
            $template=db_getPrintTemplate($id,true);
            if(empty($template)){
                if ($_W['isajax']) {
                    echo '无效参数,无法获取模板信息';
                    exit;
                }
            }
            $disable=!empty($template['status']);
            $url=$this->createWebUrl('template',array('op'=>$op,'id'=>$id));
      
            $map=$template['datamap'];
            $fields=biz_Print_getDataField($template['printtype']);
            if(empty($map))
            {
                $map=array();
                foreach($template['tags'] as $t){
                    $map[$t]='';
                }
            }
            $json=json_encode($map);
           
            if ($_W['isajax']) {
                include $this->template('tagEdit');
                exit;
            }
            if ($_W['token'] == $_GPC['token']) {
                $map=json_decode(htmlspecialchars_decode($_GPC['json']));
                $map=object_to_array($map);
                if(!$disable) {
                    $update = array(
                        'datamap' => iserializer($map)
                    );
                    pdo_update('printtemplate', $update, array('id' => $id));
                    message('模板标签数据已配置', $this->createWebUrl('template'));
                }else{
                    message('模板已启用，不允许修改', $this->createWebUrl('template'));
                }
            }

        }
        if ($op == 'set') {
            $this->CheckRight('tag');
            $id=$_GPC['id'];
            $template=db_getPrintTemplate($id,true);
            if(empty($template)){
                message('无效打印模板', $this->createWebUrl('template'), 'error');
            }
            $disable=!empty($template['status']);
            $url=$this->createWebUrl('template',array('op'=>'set','id'=>$id));
            $map=$template['datamap'];
            $fields=biz_Print_getDataField($template['printtype']);
            if(empty($map))
            {
                $map=array();
                foreach($template['tags'] as $t){
                    $map[$t]='';
                }
            }

            if ($_W['token'] == $_GPC['token']) {
                if(!$disable) {
                    $update = array(
                        'datamap' => iserializer($_GPC['map'])
                    );
                    pdo_update('printtemplate', $update, array('id' => $id));
                    message('模板标签数据已配置', $this->createWebUrl('template'));
                }else{
                    message('模板已启用，不允许修改', $this->createWebUrl('template'));
                }
            }
            include $this->template('tagSet2');
        }
    }

 

    public function doWebWelcome()
    {
        global $_W;
        include $this->template('welcome');
    }

    public function NavMenu()
    {
        return array(
            'menu' => array(
                'task' => array('title' => '打印任务列表', 'url' => $this->createWebUrl('task')),
                'printer' => array('title' => '打印机列表', 'url' => $this->createWebUrl('printer')),
                'template' => array('title' => '打印模板管理', 'url' => $this->createWebUrl('template')),
            ),
            'default' => 'task');
    }


}