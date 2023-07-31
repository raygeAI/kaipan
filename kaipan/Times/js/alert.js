/*
 js弹窗代码:用户体验极佳的Alert提示效果
 pubdate:2011-05-26 10:15
 e-mail:xpsem2010@gmail.com
 source:小拼SEM博客
*/
//获取指定ID的元素
function $xp(id) {
    return document.getElementById(id);
}
//通用事件获取接口
function getEvent() {
    if (CheckBrowser() == 'IE') return window.event;
    func = getEvent.caller;
    while (func != null) {
        var arg0 = func.arguments[0];
        if (arg0) {
            if ((arg0.constructor == Event || arg0.constructor == MouseEvent)
            || (typeof (arg0) == "object" && arg0.preventDefault && arg0.stopPropagation)) {
                return arg0;
            }
        }
        func = func.caller;
    }
    return null;
}
//alert
function NewAlertBox(msg, time) {//time为消失时间
    var msgbg, msgcolor, bordercolor, content, posLeft, posTop;
    //弹出窗口设置
    msgbg = "#FFF";   //内容背景
    msgcolor = "#000";  //内容颜色
    bordercolor = "#ccc";  //边框颜色
    //遮罩背景设置
    content = "这里是表情图片地址（HTML格式）" + msg;
    var sWidth, sHeight;
    sWidth = screen.availWidth - 20;//防止溢出
    if (screen.availHeight > document.body.scrollHeight) {
        sHeight = screen.availHeight; //少于一屏
    } else {
        sHeight = document.body.scrollHeight; //多于一屏
    }
    //创建遮罩背景
    var maskObj = document.createElement("div");
    maskObj.setAttribute('id', 'maskdiv');
    maskObj.style.position = "absolute";
    maskObj.style.top = "0";
    maskObj.style.left = "0";
    maskObj.style.background = "#fff";
    maskObj.style.filter = "Alpha(opacity=40);";
    maskObj.style.opacity = "0.4";
    maskObj.style.width = sWidth + "px";
    maskObj.style.height = sHeight + "px";
    maskObj.style.zIndex = "10000";
    document.body.appendChild(maskObj);
    //创建弹出窗口
    var msgObj = document.createElement("div")
    msgObj.setAttribute("id", "msgdiv");
    msgObj.style.position = "absolute";
    var event = getEvent();//申明event
    if (CheckBrowser() == 'IE') {
        posLeft = event.clientX + 10;
        posTop = event.clientY + document.documentElement.scrollTop;
    }
    else {
        posLeft = event.pageX + 10 + "px";//ff下要申明px
        posTop = event.pageY + 10 + "px";
    }
    msgObj.style.top = posTop;
    msgObj.style.left = posLeft;
    msgObj.style.fontSize = "12px";
    msgObj.style.background = msgbg;
    msgObj.style.border = "1px solid " + bordercolor;
    msgObj.style.zIndex = "10001";
    //创建内容
    var bodyObj = document.createElement("div");
    bodyObj.setAttribute("id", "msgbody");
    bodyObj.style.padding = "10px";
    bodyObj.style.lineHeight = "1.5em";
    //var txt = document.createTextNode(content);
    //bodyObj.appendChild(txt);
    bodyObj.innerHTML = content;
    //生成窗口
    document.body.appendChild(msgObj);
    $xp("msgdiv").appendChild(bodyObj);
    if (time != '') setTimeout("CloseMsg()", time);
    else setTimeout("CloseMsg()", 2000);//默认两秒后自动消失
    return false;
}
//移除对象
function CloseMsg() {
    document.body.removeChild($xp("maskdiv"));
    $xp("msgdiv").removeChild($xp("msgbody"));
    document.body.removeChild($xp("msgdiv"));
}
//判断浏览器类型
function CheckBrowser() {
    var cb = "Unknown";
    if (window.ActiveXObject) {
        cb = "IE";
    } else if (navigator.userAgent.toLowerCase().indexOf("firefox") != -1) {
        cb = "Firefox";
    } else if ((typeof document.implementation != "undefined") && (typeof document.implementation.createDocument != "undefined") && (typeof HTMLDocument != "undefined")) {
        cb = "Mozilla";
    } else if (navigator.userAgent.toLowerCase().indexOf("opera") != -1) {
        cb = "Opera";
    }
    return cb;
}