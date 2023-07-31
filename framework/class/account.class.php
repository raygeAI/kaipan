<?php

defined('IN_IA') or exit('Access Denied');


abstract class WeAccount {

	
	public static function create($acid) {
		$sql = 'SELECT * FROM ' . tablename('account') . ' WHERE `acid`=:acid';
		$account = pdo_fetch($sql, array(':acid' => $acid));
		if(!empty($account)) {
			if($account['type'] == '1') {
				if(!class_exists('WeiXinAccount')) {
					require IA_ROOT . '/framework/class/weixin.account.class.php';
				}
				return new WeiXinAccount($account);
			}
			if($account['type'] == '2') {
				if(!class_exists('YiXinAccount')) {
					require IA_ROOT . '/framework/class/yixin.account.class.php';
				}
				return new YiXinAccount($account);
			}
		}
		return null;
	}
	
	
	abstract public function __construct($account);

	
	public function checkSign() {
		trigger_error('not supported.', E_USER_WARNING);
	}

	
	public function fetchAccountInfo() {
		trigger_error('not supported.', E_USER_WARNING);
	}

	
	public function queryAvailableMessages() {
		return array();
	}
	
	
	public function queryAvailablePackets() {
		return array();
	}
	
	
	public function parse($message) {
		$packet = array();
		if (!empty($message)){
			$obj = simplexml_load_string($message, 'SimpleXMLElement', LIBXML_NOCDATA);
			if($obj instanceof SimpleXMLElement) {
				$packet['from'] = strval($obj->FromUserName);
				$packet['to'] = strval($obj->ToUserName);
				$packet['time'] = strval($obj->CreateTime);
				$packet['type'] = strval($obj->MsgType);
				$packet['event'] = strval($obj->Event);
				
				foreach ($obj as $variable => $property) {
					$packet[strtolower($variable)] = (string)$property;
				}
				
				if($packet['type'] == 'text') {
					$packet['content'] = strval($obj->Content);
					$packet['redirection'] = false;
					$packet['source'] = null;
				}
				if($packet['type'] == 'image') {
					$packet['url'] = strval($obj->PicUrl);
				}
				if($packet['type'] == 'voice') {
					$packet['media'] = strval($obj->MediaId);
					$packet['format'] = strval($obj->Format);
				}
				if($packet['type'] == 'video') {
					$packet['media'] = strval($obj->MediaId);
					$packet['thumb'] = strval($obj->ThumbMediaId);
				}
				if($packet['type'] == 'location') {
					$packet['location_x'] = strval($obj->Location_X);
					$packet['location_y'] = strval($obj->Location_Y);
					$packet['scale'] = strval($obj->Scale);
					$packet['label'] = strval($obj->Label);
				}
				if($packet['type'] == 'link') {
					$packet['title'] = strval($obj->Title);
					$packet['description'] = strval($obj->Description);
					$packet['url'] = strval($obj->Url);
				}
				
								if($packet['type'] == 'event') {
					$packet['type'] = $packet['event'];
				}
				if($packet['type'] == 'subscribe') {
										$scene = strval($obj->EventKey);
					if(!empty($scene)) {
						$packet['scene'] = str_replace('qrscene_', '', $scene);
						$packet['ticket'] = strval($obj->Ticket);
					}
				}
				if($packet['type'] == 'unsubscribe') {
									}
				if($packet['type'] == 'SCAN') {
										$packet['type'] = 'qr';
					$packet['scene'] = strval($obj->EventKey);
					$packet['ticket'] = strval($obj->Ticket);
				}
				if($packet['type'] == 'LOCATION') {
										$packet['type'] = 'trace';
					$packet['location_x'] = strval($obj->Latitude);
					$packet['location_y'] = strval($obj->Longitude);
					$packet['precision'] = strval($obj->Precision);
				}
				if($packet['type'] == 'CLICK') {
					$packet['type'] = 'click';
					$packet['content'] = strval($obj->EventKey);
				}
				if($packet['type'] == 'VIEW') {
					$packet['type'] = 'view';
					$packet['url'] = strval($obj->EventKey);
				}
				if($packet['type'] == 'ENTER') {
										$packet['type'] = 'enter';
				}
			}
		}
		return $packet;
	}
	
	
	public function response($packet) {
		if (!is_array($packet)) {
			return $packet;
		}
		if(empty($packet['CreateTime'])) {
			$packet['CreateTime'] = TIMESTAMP;
		}
		if(empty($packet['MsgType'])) {
			$packet['MsgType'] = 'text';
		}
		if(empty($packet['FuncFlag'])) {
			$packet['FuncFlag'] = 0;
		} else {
			$packet['FuncFlag'] = 1;
		}
		return array2xml($packet);
	}

	
	public function isPushSupported() {
		return false;
	}
	
	
	public function push($uniid, $packet) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function isBroadcastSupported() {
		return false;
	}
	
	
	public function broadcast($packet, $targets = array()) {
		trigger_error('not supported.', E_USER_WARNING);
	}

	
	public function isMenuSupported() {
		return false;
	}
	
	
	public function menuCreate($menu) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function menuDelete() {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function menuModify($menu) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function menuQuery() {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function queryFansActions() {
		return array();
	}
	
	
	public function fansGroupAll() {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansGroupCreate($group) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansGroupModify($group) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansMoveGroup($uniid, $group) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansQueryGroup($uniid) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansQueryInfo($uniid, $isPlatform) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function fansAll() {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function queryTraceActions() {
		return array();
	}
	
	
	public function traceCurrent($uniid) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function traceHistory($uniid, $time) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function queryBarCodeActions() {
		return array();
	}
	
	
	public function barCodeCreateDisposable($barcode) {
		trigger_error('not supported.', E_USER_WARNING);
	}
	
	
	public function barCodeCreateFixed($barcode) {
		trigger_error('not supported.', E_USER_WARNING);
	}
}


class WeUtility {
	
	public static function createModule($name) {
		global $_W;
		static $file;
		$classname = ucfirst($name) . 'Module';
		if(!class_exists($classname)) {
			$file = IA_ROOT . "/addons/{$name}/module.php";
			if(!is_file($file)) {
				$file = IA_ROOT . "/framework/builtin/{$name}/module.php";
			}
			if(!is_file($file)) {
				trigger_error('Module Definition File Not Found', E_USER_WARNING);
				return null;
			}
			require $file;
		}
		if(!class_exists($classname)) {
			trigger_error('Module Definition Class Not Found', E_USER_WARNING);
			return null;
		}
		$o = new $classname();
		$o->uniacid = $o->weid = $_W['uniacid'];
		$o->modulename = $name;
		load()->model('module');
		$o->module = module_fetch($name);
		$o->__define = $file;
		if($o instanceof WeModule) {
			return $o;
		} else {
			trigger_error('Module Class Definition Error', E_USER_WARNING);
			return null;
		}
	}

	

	
	public static function createModuleSite($name) {
		global $_W;
		static $file;
		$classname = "{$name}ModuleSite";
		if(!class_exists($classname)) {
			$file = IA_ROOT . "/addons/{$name}/site.php";
			if(!is_file($file)) {
				$file = IA_ROOT . "/framework/builtin/{$name}/site.php";
			}
			if(!is_file($file)) {
				trigger_error('ModuleSite Definition File Not Found '.$file, E_USER_WARNING);
				return null;
			}
			require $file;
		}
		if(!class_exists($classname)) {
			trigger_error('ModuleSite Definition Class Not Found', E_USER_WARNING);
			return null;
		}
		$o = new $classname();
		$o->uniacid = $o->weid = $_W['uniacid'];
		$o->modulename = $name;
		load()->model('module');
		$o->module = module_fetch($name);
		$o->__define = $file;
		$o->inMobile = defined('IN_MOBILE');
		if($o instanceof WeModuleSite) {
			return $o;
		} else {
			trigger_error('ModuleReceiver Class Definition Error', E_USER_WARNING);
			return null;
		}
	}

	
	public static function logging($level = 'info', $message = '') {
		if(!DEVELOPMENT) {
					}
		$filename = IA_ROOT . '/data/logs/' . date('Ymd') . '.log';
		load()->func('file');
		mkdirs(dirname($filename));
		$content = date('Y-m-d H:i:s') . " {$level} :\n------------\n";
		if(is_string($message)) {
			$content .= "String:\n{$message}\n";
		}
		if(is_array($message)) {
			$content .= "Array:\n";
			foreach($message as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		if($message == 'get') {
			$content .= "GET:\n";
			foreach($_GET as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		if($message == 'post') {
			$content .= "POST:\n";
			foreach($_POST as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		$content .= "\n";

		$fp = fopen($filename, 'a+');
		fwrite($fp, $content);
		fclose($fp);
	}
}

abstract class WeBase {
	
	public $modulename;
	
	public $module;
	
	public $weid;
	
	public $uniacid;
	
	public $__define;

	
	public function saveSettings($settings) {
		global $_W;
		$pars = array('module' => $this->modulename, 'uniacid' => $_W['uniacid']);
		$row = array();
		$row['settings'] = iserializer($settings);
		return pdo_update('uni_account_modules', $row, $pars) !== false;
	}

	
	protected function createMobileUrl($do, $query = array()) {
		$query['do'] = $do;
		$query['m'] = strtolower($this->modulename);
		return murl('entry', $query);
	}

	
	protected function createWebUrl($do, $query = array()) {
		$query['do'] = $do;
		$query['m'] = strtolower($this->modulename);
		return wurl('site/entry', $query);
	}

	
	protected function template($filename) {
		global $_W;
		$name = strtolower($this->modulename);
		$defineDir = dirname($this->__define);
		if(defined('IN_SYS')) {
            $source = IA_ROOT . "/web/themes/{$_W['template']}/{$name}/{$filename}.html";
            $compile = IA_ROOT . "/data/tpl/web/{$_W['template']}/{$name}/{$filename}.tpl.php";
            if(!empty($defineDir)){
                $source = $defineDir . "/template/{$filename}.html";
                $compile = IA_ROOT . "/data/tpl/web/addons/{$name}/{$filename}.tpl.php";
            }
			if(!is_file($source)) {
				$source = IA_ROOT . "/web/themes/default/{$name}/{$filename}.html";
			}
			if(!is_file($source)) {
				$source = $defineDir . "/template/{$filename}.html";
			}
			if(!is_file($source)) {
				$source = IA_ROOT . "/web/themes/{$_W['template']}/{$filename}.html";
			}
			if(!is_file($source)) {
				$source = IA_ROOT . "/web/themes/default/{$filename}.html";
			}
		} else {
			$source = IA_ROOT . "/app/themes/{$_W['template']}/{$name}/{$filename}.html";
			$compile = IA_ROOT . "/data/tpl/app/{$_W['template']}/{$name}/{$filename}.tpl.php";
			if(!is_file($source)) {
				$source = IA_ROOT . "/app/themes/default/{$name}/{$filename}.html";
			}
			if(!is_file($source)) {
				$source = $defineDir . "/template/mobile/{$filename}.html";
			}
			if(!is_file($source)) {
				$source = IA_ROOT . "/app/themes/{$_W['template']}/{$filename}.html";
			}
			if(!is_file($source)) {
				if (in_array($filename, array('header', 'footer', 'slide', 'toolbar', 'message'))) {
					$source = IA_ROOT . "/app/themes/default/common/{$filename}.html";
				} else {
					$source = IA_ROOT . "/app/themes/default/{$filename}.html";
				}
			}
		}
		if(!is_file($source)) {
			exit("Error: template source '{$filename}' is not exist!");
		}
		if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
			template_compile($source, $compile, true);
		}
		return $compile;
	}
}


abstract class WeModule extends WeBase {
	
	public function fieldsFormDisplay($rid = 0) {
		return '';
	}
	
	public function fieldsFormValidate($rid = 0) {
		return '';
	}
	
	public function fieldsFormSubmit($rid) {
			}
	
	public function ruleDeleted($rid) {
		return true;
	}
	
	public function settingsDisplay($settings) {
			}
}

abstract class WeModuleSite extends WeBase {

    public $inMobile;

    public function __call($name, $arguments) {
        $isWeb = stripos($name, 'doWeb') === 0;
        $isMobile = stripos($name, 'doMobile') === 0;
        if($isWeb || $isMobile) {
            $dir = IA_ROOT . '/addons/' . $this->modulename . '/inc/';
            if($isWeb) {
                $dir .= 'web/';
                $fun = strtolower(substr($name, 5));
            }
            if($isMobile) {
                $dir .= 'mobile/';
                $fun = strtolower(substr($name, 8));
            }
            $file = $dir . $fun . '.inc.php';
            if(file_exists($file)) {
                require $file;
                exit;
            }
        }
        trigger_error("访问的方法 {$name} 不存在.", E_USER_WARNING);
        return null;
    }



}


abstract class IndustryModule extends WeModuleSite {

	
	public function __call($name, $arguments) {
		$isWeb = stripos($name, 'doWeb') === 0;
		$isMobile = stripos($name, 'doMobile') === 0;
		if($isWeb || $isMobile) {
			$dir = IA_ROOT . '/addons/' . $this->modulename . '/inc/';
			if($isWeb) {
				//$dir .= 'web/';
				$fun = strtolower(substr($name, 5));
			}
			$file = $dir . $fun . '.inc.php';
			if(file_exists($file)) {
				require $file;
				exit;
			}
		}
		trigger_error("访问的方法 {$name} 不存在.", E_USER_WARNING);
		return null;
	}

    public function RegisterOperate()
    {
        return array();
    }

    public function CheckRight($operate,$showTip=true)
    {
        return checkModuleRight($this->modulename,$operate,$showTip);
    }
    
    public function CheckDataRight($chips,$showTip=true){
        global $_W;
        $enable=true;
        if(!empty($chips['StationCode'])){
            $enable=strpos($chips['StationCode'],$_W['rights']['StationCode'])!==false;
        }
        if($showTip&&!$enable){
            message('无权处理此数据！');
        }
        return $enable;
    }

    protected function createWebUrl($do, $query = array())
    {
        $query['do'] = $do;
        $query['m'] = strtolower($this->modulename);
        return wurl('project/module', $query);
    }
}
