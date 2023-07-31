
//  二维码
function takecode() {
    JSBridge.takecode(function (codeInfo, isSuccess) {
        if (isSuccess == true) {
            var obj = eval('(' + codeInfo + ')');          
            window.location.href = "sign02.html?key=" + obj.code;
        }
        else {
            alert("二维码扫描不成功");
        }
    });
}


//获取Url参数
function getQueryString(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
    var r = window.location.search.substr(1).match(reg);
    if (r != null) return unescape(r[2]); return null;
}


// 选择图片：
function album() {
    JSBridge.album({ 'key': '厂房' }, function (picInfo, isSuccess) {
        if (isSuccess == true) {

            var obj = eval('(' + picInfo + ')');
            alert(obj.key + " " + obj.filename);

        }
        else {
            alert("失败");
        }
    });
}

//拍照：
function takephoto() {
    JSBridge.takephoto({ 'key': '厂房' }, function (picInfo, isSuccess) {
        if (isSuccess == true) {

            var obj = eval('(' + picInfo + ')');
            alert(obj.key + " " + obj.filename);

        }
        else {
            alert("失败");
        }
    });
}

//  拍视频：
function takevideo() {
    JSBridge.takevideo({ 'key': '厂房' }, function (videoInfo, isSuccess) {
        if (isSuccess == true) {

            var obj = eval('(' + videoInfo + ')');

            alert(obj.key + " " + obj.filename);

        }
        else {
            alert("失败");
        }
    });
}

// 录音
function takerecord() {
    JSBridge.takerecord({ 'key': '厂房' }, function (recordInfo, isSuccess) {
        if (isSuccess == true) {

            var obj = eval('(' + recordInfo + ')');
            alert(obj.key + " " + obj.filename);
        }
        else {
            alert("失败");
        }
    });
}