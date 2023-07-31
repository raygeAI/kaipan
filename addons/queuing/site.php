<?php
defined('IN_IA') or exit('Access Denied');

class QueuingModuleSite extends IndustryModule
{
   
    public function __Process($call)
    {
        global $_W, $_GPC;
        $do = strtolower(substr($call, 5));
        $op = trim($_GPC['op']);
        if(empty($op)){
            $op=strtolower($op);
        }
        //inc目录存放业务处理
        include_once 'inc/' . $do . '.php';
    }


    public function doWebOrder()
    {
        $this->__Process(__FUNCTION__);
    }
    
    public function doWebGroup()
    {
        $this->__Process(__FUNCTION__);
    }

    public function doWebBook()
    {
        $this->__Process(__FUNCTION__);
    }
    
    public function doWebCall()
    {
        $this->__Process(__FUNCTION__);
    }
    
    public function doWebScreen()
    {
        global $_W;
        $last=memcached_get('callnum');
        $calls=explode(',', $last);
        $callnum=sprintf('%03d', $calls[0]);
        $list=biz_getCalledGroup($_W['project']['id']);
        $groupnum=array_keys($list);
        $groupnum= array_reverse($groupnum);
        $json=json_encode($groupnum);
        include $this->template('screen');
    }

    public function doWebWelcome()
    {
        global $_W;
        
        include $this->template('welcome');
    }
    
    public function doWebLucky()
    {
        $this->__Process(__FUNCTION__);
    }

    public function NavMenu()
    {
        return array(
            'menu' => array(
                'group' => array('title' => '签到分组', 'url' => $this->createWebUrl('group')),
                'list' => array('title' => '选房叫号', 'url' => $this->createWebUrl('call')),
                'lucky' => array('title' => '中签登记', 'url' => $this->createWebUrl('lucky')),
                'order' => array('title' => '认购交款', 'url' => $this->createWebUrl('order')),
                'book' => array('title' => '认购书列表','url' => $this->createWebUrl('book')),
                'screen' => array('title' => '叫号大屏幕', 'url' => $this->createWebUrl('screen'), 'direct' => true),
            ),
            'default' => 'group');
    }
}