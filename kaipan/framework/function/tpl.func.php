<?php

defined('IN_IA') or exit('Access Denied');


function tpl_form_field_color($name, $value = '') {
	$s = '';
	if (!defined('TPL_INIT_COLOR')) {
		$s = '
		<script type="text/javascript">
			require(["jquery", "util"], function($, util){
				$(function(){
					$(".colorpicker").each(function(){
						var elm = this;
						util.colorpicker(elm, function(color){
							$(elm).parent().prev().find(":text").val(color.toHexString());
						});
					});
				});
			});
		</script>';
		define('TPL_INIT_COLOR', true);
	}
	$s .= '
		<div class="row row-fix">
			<div class="col-xs-6 col-sm-4" style="padding-right:0;">
				<input class="form-control" type="text" placeholder="请选择颜色" value="'.$value.'">
			</div>
			<div class="col-xs-2" style="padding:2px 0;">
				<input class="colorpicker" type="text" name="'.$name.'" value="'.$value.'" placeholder="">
			</div>
		</div>
		';
	return $s;
}


function tpl_form_field_icon($name, $value='') {
	$s = '';
	if (!defined('TPL_INIT_ICON')) {
		$s = '
		<script type="text/javascript">
			function showIconDialog(elm) {
				require(["util","jquery"], function(u, $){
					var btn = $(elm);
					var spview = btn.parent().prev();
					var ipt = spview.prev();
					if(!ipt.val()){
						spview.css("display","none");
					}
					u.iconBrowser(function(ico){
						ipt.val(ico);
						spview.show();
						spview.find("i").attr("class","");
						spview.find("i").addClass("fa").addClass(ico);
					});
				});
			}
		</script>';
		define('TPL_INIT_ICON', true);
	}
	$s .= '
	<div class="input-group" style="width: 300px;">
		<input type="text" value="'.$value.'" name="'.$name.'" class="form-control" autocomplete="off">
		<span class="input-group-addon" style="display:none"><i class="'.$value.' fa"></i></span>
		<span class="input-group-btn">
			<button class="btn btn-default" type="button" onclick="showIconDialog(this);">选择图标</button>
		</span>
	</div>
	';
	return $s;
}


function tpl_form_field_link($name, $value = '', $options = array()) {
	$s = '';
	if (!defined('TPL_INIT_LINK')) {
		$s = '
		<script type="text/javascript">
			function showLinkDialog(elm) {
				require(["util","jquery"], function(u, $){
					var ipt = $(elm).parent().prev();
					u.linkBrowser(function(href){
						ipt.val(href);
					});
				});
			}
		</script>';
		define('TPL_INIT_LINK', true);
	}
	$s .= '
	<div class="input-group">
		<input type="text" value="'.$value.'" name="'.$name.'" class="form-control ' . $options['css']['input'] . '" autocomplete="off">
		<span class="input-group-btn">
			<button class="btn btn-default ' . $options['css']['btn'] . '" type="button" onclick="showLinkDialog(this);">选择链接</button>
		</span>
	</div>
	';
	return $s;
}


function tpl_form_field_daterange($name, $value = array()) {
	$s = '';
	if (!defined('TPL_INIT_DATERANGE')) {
		$s = '
		<script type="text/javascript">
			require(["daterangepicker"], function($){
				$(function(){
					$(".daterange").each(function(){
						var elm = this;
						$(this).daterangepicker({
							format: "YYYY-MM-DD",
							startDate: $(elm).prev().prev().val(),
							endDate: $(elm).prev().val()
						}, function(start, end){
							$(elm).find(".date-title").html(start.format("YYYY-MM-DD") + " 至 " + end.format("YYYY-MM-DD"));
							$(elm).prev().prev().val(start.format("YYYY-MM-DD"));
							$(elm).prev().val(end.format("YYYY-MM-DD"));
						});
					});
				});
			});
		</script>';
		define('TPL_INIT_DATERANGE', true);
	}
	$startname = $name . '[start]';
	$endname = $name . '[end]';
	
	if($value['start']) {
		$value['starttime'] = $value['start'];
	}
	if($value['end']) {
		$value['endtime'] = $value['end'];
	}
	$value['starttime'] = empty($value['starttime']) ? date('Y-m-d') : $value['starttime'];
	$value['endtime'] = empty($value['endtime']) ? $value['starttime'] :  $value['endtime'];
	$s .= '
	<input name="'.$startname.'" type="hidden" value="'. $value['starttime'].'" />
	<input name="'.$endname.'" type="hidden" value="'. $value['endtime'].'" />
	<button class="btn btn-default daterange" type="button"><span class="date-title">'.$value['starttime'].' 至 '.$value['endtime'].'</span> <i class="fa fa-calendar"></i></button>
	';
	return $s;
}


function tpl_form_field_date($name, $value = '', $withtime = false) {
	$s = '';
	if (!defined('TPL_INIT_DATA')) {
		
		$format = '';
		if ($withtime) {
			$format = 'format : "yyyy-mm-dd hh:ii",
							minView : 0,';
		}
		
		$s = '
<script type="text/javascript">
	require(["datetimepicker"], function($){
		$(function(){
			$(".datetimepicker").each(function(){
				var withtime = $(this).attr("data-withtime");
				var opt = {
					language: "zh-CN",
					format: "yyyy-mm-dd",
					minView: 2,
					autoclose: true,
					'.$format.'
				};
				$(this).datetimepicker(opt);
			});
		});
	});
</script>';
		define('TPL_INIT_DATA', true);
	}
	$withtime = empty($withtime) ? 'false' : 'true';
	$value = !empty($value) ? $value : ($withtime ? date('Y-m-d H:i') : date('Y-m-d')); 
	$s .= '<input type="text" name="' . $name . '" value="'.$value.'" data-withtime="'.$withtime.'" placeholder="请选择日期时间"  readonly="readonly" class="datetimepicker form-control" />';
	return $s;
}


function tpl_form_field_calendar($name, $values = array()) {
	$html = '';
	if (!defined('TPL_INIT_CALENDAR')) {
		$html .= '
		<script type="text/javascript">
			function handlerCalendar(elm) {
				require(["jquery","moment"], function($, moment){
					var tpl = $(elm).parent().parent();
					var year = tpl.find("select.tpl-year").val();
					var month = tpl.find("select.tpl-month").val();
					var day = tpl.find("select.tpl-day");
					day[0].options.length = 1;
					if(year && month) {
						var date = moment(year + "-" + month, "YYYY-M");
						var days = date.daysInMonth();
						for(var i = 1; i <= days; i++) {
							var opt = new Option(i, i);
							day[0].options.add(opt);
						}
						day.val(day.attr("data-value"));
					}
				});
			}
			require(["jquery"], function($){
				$(".tpl-calendar").each(function(){
					handlerCalendar($(this).find("select.tpl-year")[0]);
				});
			});
		</script>';
		define('TPL_INIT_CALENDAR', true);
	}

	if (empty($values) || !is_array($values)) {
		$values = array(0,0,0);
	}
	$values['year'] = intval($values['year']);
	$values['month'] = intval($values['month']);
	$values['day'] = intval($values['day']);
	
	$year = array(date('Y'), '1914');
	$html .= '<div class="row row-fix tpl-calendar">
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[year]" onchange="handlerCalendar(this)" class="form-control tpl-year">
					<option value="">年</option>';
	for($i = $year[1]; $i <= $year[0]; $i++) {
		$html .= '<option value="' . $i . '"'.($i == $values['year'] ? ' selected="selected"' : '').'>' . $i . '</option>';
	}
	$html .= '	</select>
			</div>
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[month]" onchange="handlerCalendar(this)" class="form-control tpl-month">
					<option value="">月</option>';
	for($i = 1; $i <= 12; $i++) {
		$html .= '<option value="' . $i . '"'.($i == $values['month'] ? ' selected="selected"' : '').'>' . $i . '</option>';
	}
	$html .= '	</select>
			</div>
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[day]" data-value="' . $values['day'] . '" class="form-control tpl-day">
					<option value="">日</option>
				</select>
			</div>
		</div>';
	return $html;
}


function tpl_form_field_district($name, $values = array()) {
	$html = '';
	if (!defined('TPL_INIT_DISTRICT')) {
		$html .= '
		<script type="text/javascript">
			require(["jquery", "district"], function($, dis){
				$(".tpl-district-container").each(function(){
					var elms = {};
					elms.province = $(this).find(".tpl-province")[0];
					elms.city = $(this).find(".tpl-city")[0];
					elms.district = $(this).find(".tpl-district")[0];
					var vals = {};
					vals.province = $(elms.province).attr("data-value");
					vals.city = $(elms.city).attr("data-value");
					vals.district = $(elms.district).attr("data-value");
					dis.render(elms, vals, {withTitle: true});
				});
			});
		</script>';
		define('TPL_INIT_DISTRICT', true);
	}
	if (empty($values) || !is_array($values)) {
		$values = array('province'=>'','city'=>'','district'=>'');
	}
	if(empty($values['province'])) {
		$values['province'] = '';
	}
	if(empty($values['city'])) {
		$values['city'] = '';
	}
	if(empty($values['district'])) {
		$values['district'] = '';
	}
	$html .= '
		<div class="row row-fix tpl-district-container">
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[province]" data-value="' . $values['province'] . '" class="form-control tpl-province">
				</select>
			</div>
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[city]" data-value="' . $values['city'] . '" class="form-control tpl-city">
				</select>
			</div>
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
				<select name="' . $name . '[district]" data-value="' . $values['district'] . '" class="form-control tpl-district">
				</select>
			</div>
		</div>';
	return $html;
}


function tpl_form_field_category_2level($name, $parents, $children, $parentid, $childid){
	$html = '
<script type="text/javascript">
	window._'.$name.' = '.json_encode($children).';
</script>';
	if (!defined('TPL_INIT_CATEGORY')) {
		$html .= '	
<script type="text/javascript">
	function renderCategory(obj, name){
		var index = obj.options[obj.selectedIndex].value;
		require([\'jquery\', \'util\'], function($, u){
			$selectChild = $(\'#\'+name+\'_child\');
			var html = \'<option value="0">请选择二级分类</option>\';
			if (!window[\'_\'+name] || !window[\'_\'+name][index]) {
				$selectChild.html(html);
				return false;
			}
			for(var i=0; i< window[\'_\'+name][index].length; i++){
				html += \'<option value="\'+window[\'_\'+name][index][i][\'id\']+\'">\'+window[\'_\'+name][index][i][\'name\']+\'</option>\';
			}
			$selectChild.html(html);
		});
	}
</script>
			';
		define('TPL_INIT_CATEGORY', true);
	}
	
	$html .= 
'<div class="row row-fix tpl-category-container">
	<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
		<select class="form-control tpl-category-parent" id="'.$name.'_parent" name="'.$name.'[parentid]" onchange="renderCategory(this,\''.$name.'\')">
			<option value="0">请选择一级分类</option>';
	$ops = '';
	foreach ($parents as $row) {
		$html .= '
			<option value="'.$row['id'].'" '.(($row['id'] == $parentid) ? 'selected="selected"' : '').'>'.$row['name'].'</option>';
	}
	$html .='
		</select>
	</div>
	<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
		<select class="form-control tpl-category-child" id="'.$name.'_child" name="'.$name.'[childid]">
			<option value="0">请选择二级分类</option>';
			if (!empty($parentid) && !empty($children[$parentid])){
				foreach ($children[$parentid] as $row) {
					$html .= '
			<option value="'.$row['id'].'"'.(($row['id'] == $childid)? 'selected="selected"':'').'>'.$row['name'].'</option>';
				}
			}
	$html .='
		</select>
	</div>
</div>
';
	return $html;
}


function tpl_form_field_image($name, $value = '', $default = '', $options = array()) {
	global $_W;
	
	$s = '';
	if (!defined('TPL_INIT_IMAGE')) {
		$s = '
		<script type="text/javascript">
			function showImageDialog(elm, opts) {
				require(["util"], function(util){
					var btn = $(elm);
					var ipt = btn.parent().prev();
					var val = ipt.val();
					var img = ipt.parent().next().children();
					util.image(val, function(url){
						img.get(0).src = url.url;
						ipt.val(url.filename);
						ipt.attr("filename",url.filename);
						ipt.attr("url",url.url);
					}, opts);
				});
			}
		</script>';
		
		if (defined('IN_MOBILE')) {
			$s .= "
		<script type=\"text/javascript\">
		<!--
		window['__uniacid'] = {$_W['uniacid']};
		//-->
		</script>
			";
		}
		
		define('TPL_INIT_IMAGE', true);
	}
	if(empty($default)) {
		$default = './resource/images/nopic.jpg';
	}
	$val = $default;
	if(!empty($value)) {
		$val = tomedia($value);
	}
	if(empty($options['width'])) {
		$options['width'] = 800;
	}
	if(empty($options['height'])) {
		$options['height'] = 600;
	}
	if(!empty($options['global'])){
		$options['global'] = true;
	} else {
		$options['global'] = false;
	}
	
	$options = array_elements(array('width', 'height', 'extras', 'global'), $options);
	
	$s .= '
	<div class="input-group">
		<input type="text" name="'.$name.'" value="'.$value.'"'.($options['extras']['text'] ? $options['extras']['text'] : '').' class="form-control" autocomplete="off">
		<span class="input-group-btn">
			<button class="btn btn-default" type="button" onclick="showImageDialog(this, \'' . base64_encode(iserializer($options)) . '\');">选择图片</button>
		</span>
	</div>
	<div class="input-group" style="margin-top:.5em;">
		<img src="' . $val . '" onerror="this.src=\''.$default.'\'; this.title=\'图片未找到.\'" class="img-responsive img-thumbnail" width="150" '.($options['extras']['image'] ? $options['extras']['image'] : '').'/>
	</div>';
	return $s;
}


function tpl_form_field_audio($name, $value = '', $options = array()) {
	$s = '';
	if (!defined('TPL_INIT_AUDIO')) {
		$s = '
		<script type="text/javascript">
			function showAudioDialog(elm) {
				require(["util"], function(util){
					var btn = $(elm);
					var ipt = btn.parent().prev();
					var val = ipt.val();
					if(!ipt.val()){
						$(".audio-player").prev().find("button").eq(0).css("display","none");
					}
					util.audio(val, function(url){
						$(".audio-player").prev().find("button").eq(0).show();
						ipt.val(url.filename);
						ipt.attr("filename",url.filename);
						ipt.attr("url",url.url);
						$(elm).parent().parent().next().jPlayer("stop");
					});
				});
			}
			require(["jquery", "util", "jquery.jplayer"], function($, u){
				$(function(){
					$(".audio-player").prev().find("button").eq(0).click(function(){
						var src = $(this).parent().prev().val();
						if($(this).find("i").hasClass("fa-stop")) {
							$(this).parent().parent().next().jPlayer("stop");
						} else {
							if(src) {
								$(this).parent().parent().next().jPlayer("setMedia", {mp3: u.tomedia(src)}).jPlayer("play");
							}
						}
					});
					$(".audio-player").jPlayer({
						playing: function() {
							$(this).prev().find("i").removeClass("fa-play").addClass("fa-stop");
						},
						pause: function (event) {
							$(this).prev().find("i").removeClass("fa-stop").addClass("fa-play");
						},
						swfPath: "resource/components/jplayer",
						supplied: "mp3"
					});
				});
			});
		</script>';
		define('TPL_INIT_AUDIO', true);
	}
	$val = $default;
	if(!empty($value)) {
		$val = tomedia($value);
	}
	$options = array_elements(array('extras'), $options);
	$s .= '
	<div class="input-group">
		<input type="text" value="'.$value.'" name="'.$name.'" class="form-control" autocomplete="off" '.($options['extras']['text'] ? $options['extras']['text'] : '').'>
		<span class="input-group-btn">
			<button class="btn btn-default" type="button" style="display:none;"><i class="fa fa-play"></i></button>
			<button class="btn btn-default" type="button" onclick="showAudioDialog(this);">选择音乐</button>
		</span>
	</div>
	<div class="input-group audio-player">
	</div>';
	return $s;
}


function tpl_form_field_coordinate($field, $value = array()) {
	$s = '';
	if(!defined('TPL_INIT_COORDINATE')) {
		$s .= '<script type="text/javascript">
				function showCoordinate(elm) {
					require(["util"], function(util){
						var val = {};
						val.lng = parseFloat($(elm).parent().prev().prev().find(":text").val());
						val.lat = parseFloat($(elm).parent().prev().find(":text").val());
						util.map(val, function(r){
							$(elm).parent().prev().prev().find(":text").val(r.lng);
							$(elm).parent().prev().find(":text").val(r.lat);
						});
					});
				}
			</script>';
		define('TPL_INIT_COORDINATE', true);
	}
	$s .= '
		<div class="row">
			<div class="col-xs-4 col-sm-2">
				<input type="text" name="' . $field . '[lng]" value="'.$value['lng'].'" placeholder="地理经度"  class="form-control" />
			</div>
			<div class="col-xs-4 col-sm-2">
				<input type="text" name="' . $field . '[lat]" value="'.$value['lat'].'" placeholder="地理纬度"  class="form-control" />
			</div>
			<div class="col-xs-4 col-sm-2">
				<button onclick="showCoordinate(this);" class="btn btn-default" type="button">选择坐标</button>
			</div>
		</div>';
	return $s;
}


function tpl_fans_form($field, $value = '') {
	switch ($field) {
		case 'avatar':
			$avatar_url = '../attachment/images/global/avatars/';
			$html = '';
			if (!defined('TPL_INIT_AVATAR')) {
				$html .= '
				<script type="text/javascript">
					function showAvatarDialog(elm, opts) {
						require(["util"], function(util){
							var btn = $(elm);
							var ipt = btn.parent().prev();
							var img = ipt.parent().next().children();
							var content = \'<div class="clearfix file-browser">\';
							for(var i = 1; i <= 12; i++) {
								content += 
									\'<div title="头像\' + i + \'" class="thumbnail">\' +
										\'<em><img src="' . $avatar_url . 'avatar_\' + i + \'.jpg" class="img-responsive"></em>\' +
										\'<span class="text-center">头像\' + i + \'</span>\' +
									\'</div>\';
							}
							content += "</div>";
							var dialog = util.dialog("请选择头像", content);
							dialog.modal("show");
							dialog.find(".thumbnail").on("click", function(){
								var url = $(this).find("img").attr("src");
								img.get(0).src = url;
								ipt.val(url.replace(/^\.\.\//, ""));
								dialog.modal("hide");
							});
						});
					}
				</script>';
				define('TPL_INIT_AVATAR', true);
			}
			if (!defined('TPL_INIT_IMAGE')) {
				
				global $_W;
				if (defined('IN_MOBILE')) {
					$html .= "
				<script type=\"text/javascript\">
				<!--
				window['__uniacid'] = {$_W['uniacid']};
				//-->
				</script>
";
				}
				
				$html .= '
				<script type="text/javascript">
						
					function showImageDialog(elm, opts) {
						require(["util"], function(util){
							var btn = $(elm);
							var ipt = btn.parent().prev();
							var val = ipt.val();
							var img = ipt.parent().next().children();
							util.image(val, function(url){
								img.get(0).src = url.url;
								ipt.val(url.filename);
							}, opts);
						});
					}
				</script>';
				define('TPL_INIT_IMAGE', true);
			}
			$val = './resource/images/nopic.jpg';
			if(!empty($value)) {
				$val = tomedia($value);
			}
			$options = array();
			$options['width'] = '200';
			$options['height'] = '200';
			$html .= '
			<div class="input-group">
				<input type="text" value="'.$value.'" name="'.$field.'" class="form-control" autocomplete="off">
				<span class="input-group-btn">
					<button class="btn btn-default" type="button" onclick="showImageDialog(this, \'' . base64_encode(iserializer($options)) . '\');">选择图片</button>
					<button class="btn btn-default" type="button" onclick="showAvatarDialog(this);">系统头像</button>
				</span>
			</div>
			<div class="input-group" style="margin-top:.5em;">
				<img src="' . $val . '" class="img-responsive img-thumbnail" width="150" />
			</div>';
			break;
		case 'birth':
		case 'birthyear':
		case 'birthmonth':
		case 'birthday':
			$html = tpl_form_field_calendar('birth', $value);
			break;
		case 'reside':
		case 'resideprovince':
		case 'residecity':
		case 'residedist':
			$html = tpl_form_field_district('reside', $value);
			break;
		case 'bio':
		case 'interest':
			$html = '<textarea name="' . $field . '" class="form-control">'.$value.'</textarea>';
			break;
		case 'gender':
			$html = '
					<select name="gender" class="form-control">
						<option value="0" '.($value == 0 ? 'selected ' : '').'>保密</option>
						<option value="1" '.($value == 1 ? 'selected ' : '').'>男</option>
						<option value="2" '.($value == 2 ? 'selected ' : '').'>女</option>
					</select>';
			break;
		case 'education':
		case 'constellation':
		case 'zodiac':
		case 'bloodtype':
			if ($field == 'bloodtype') {
				$options = array('A', 'B', 'AB', 'O', '其它');
			} elseif($field == 'zodiac'){
				$options = array('鼠','牛','虎','兔','龙','蛇','马','羊','猴','鸡','狗','猪');
			} elseif($field == 'constellation'){
				$options = array('水瓶座','双鱼座','白羊座','金牛座','双子座','巨蟹座','狮子座','处女座','天秤座','天蝎座','射手座','摩羯座');
			} elseif($field == 'education'){
				$options = array('博士','硕士','本科','专科','中学','小学','其它');
			}
			$html = '<select name="'.$field.'" class="form-control">';
			foreach ($options as $item) {
				$html .= '<option value="'.$item.'" '.($value == $item ? 'selected ' : '').'>'.$item.'</option>';
			}
			$html .= '</select>';
			break;
		case 'nickname':
		case 'realname':
		case 'address':
		case 'mobile':
		case 'qq':
		case 'msn':
		case 'email':
		case 'telephone':
		case 'taobao':
		case 'alipay':
		case 'studentid':
		case 'grade':
		case 'graduateschool':
		case 'idcard':
		case 'zipcode':
		case 'site':
		case 'affectivestatus':
		case 'lookingfor':
		case 'nationality':
		case 'height':
		case 'weight':
		case 'company':
		case 'occupation':
		case 'position':
		case 'revenue':
		default:
			$html = '<input type="text" class="form-control" name="' . $field . '" value="'.$value.'" />';
			break;
	}
	return $html;
}