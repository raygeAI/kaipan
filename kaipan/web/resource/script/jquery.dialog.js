/*
 *jQuery简单消息框插件
 *2014-03-14
 *调用：
 *$.dialog.alert("提示消息");
 *对话框的调用：$.dialog.message("消息");仅有确定的对话框
 *$.dialog.message("消息", true, callback);带取消和回调函数的对话框,空函数请传$.noop
 */
jQuery.dialog = {
    /*
     *消息框
     */
    alert: function (text) {
        var $window = $('<div id="windowcenter"></div>');
        $window.css({ "position": "absolute", "width": "267px", "display": "none", "margin": "0 auto 0 -136px", "padding": "2px",
            "bottom": "60px", "left": "50%", "border-radius": "0.6em", "-webkit-border-radius": "0.6em", "-moz-border-radius": "0.6em",
            "background-color": "#f1f1f1", "-webkit-box-shadow": "0 0 10px rgba(0, 0, 0, 0.5)", "-moz-box-shadow": "0 0 10px rgba(0, 0, 0, 0.5)",
            "-o-box-shadow": "0 0 10px rgba(0, 0, 0, 0.5)", "box-shadow": "0 0 10px rgba(0, 0, 0, 0.5)",
            "font": "14px/1.5 Microsoft YaHei, Helvitica, Verdana, Arial, san-serif", "z-index": "10"
        });
        var $title = $('<div>操作提示</div>');
        $title.css({ "background-color": "#585858", "line-height": "26px", "padding": "5px 5px 5px 10px", "color": "#fff", "font-size": "16px",
            "border-radius": "0.5em 0.5em 0 0", "-webkit-border-radius": "0.5em 0.5em 0 0", "-moz-border-radius": "0.5em 0.5em 0 0"
        });
        var $close = $('<span></span>');
        $close.css({ "float": "right", "width": "26px", "height": "26px", "display": "block", "background-image": "url('data:image/png;base64," +
            "iVBORw0KGgoAAAANSUhEUgAAABoAAAAaCAYAAACpSkzOAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAACTSURBVEhL7dNtCoAgD" +
            "AZgb60nsGN1tPLVCVNHmg76kQ8E1mwv+GG27cestQ4PvTZ69SFocBGpWa8+zHt/Up+IN+MhgLlUmnIE1CpBQB2COZibfpnXhHFaIZkYph0SOeeK/QJ8o7KOek84fkCWSBtfL+N" +
            "y2MPpCkPFMH6PWEhWhKncIyEk69VfiUuVhqJefds+YcwNbEwxGqGIFWYAAAAASUVORK5CYII=')"
        });
        $close.click(function () {
            if ($("#windowcenter").length > 0) {
                $("#windowcenter").slideUp(500);
            }
        });
        $title.append($close);
        $window.append($title);
        var $content = $('<div></div>');
        $content.css({ "overflow": "auto", "padding": "10px", "color": "#222", "text-shadow": "0 1px 0 #FFFFFF" });
        var $text = $('<div></div>');
        $text.css({ "min-height": "30px", "font-size": "14px", "line-height": "20px" });
        $text.text(text);
        $content.append($text);
        $window.append($content);

        if ($("#windowcenter").length > 0) {
            $("#windowcenter").remove();
        }
        $("body").append($window);
        $("#windowcenter").slideToggle("slow");
        setTimeout('$("#windowcenter").slideUp(500)', 4000);
    },
    /*
     *对话框
     */
    message: function (text, hasCancel, callback) {
        var $popTips = $('<div id="popTips" style="display: block;"></div>');
        $popTips.css({ "position": "fixed", "z-index": "300", "left": "50%", "top": "20%", "width": "255px", "overflow": "hidden",
            "margin-left": "-122px", "background-color": "#FBFBFB", "font-size": "14px", "text-align": "center", "border-radius": "5px"
        });
        $popTips.append('<div></div>');

        var $popShow = $('<div></div>');
        var $title = $('<h4>温馨提示</h4>');
        $title.css({ "padding": "0", "margin": "0", "height": "34", "font-size": "16px", "border-bottom": "1px solid #EDEDED",
            "line-height": "34px", "color": "#252E32"
        });
        $popShow.append($title);
        var $text = $('<div>' + text + '</div>');
        $text.css({ "font-size": "16px", "padding": "20px 0px", "background-color": "#FFF" });
        $popShow.append($text);
        var $popBtns = $('<div></div>');
        $popBtns.css("text-align", "center");
        var a_css = { "display": "block", "font-weight": "700", "height": "40px", "line-height": "40px", "border-top": "1px solid #E2E1E0",
            "text-decoration": "none", "color": "#5B5B5B"
        };
        //取消事件或OK事件
        var cancelEvent = function () {
            if ($("#popTips").length > 0) {
                $("#popTips").remove();
            }
        }
        var $ok = $('<a href="javascript:void(0);">确定</a>');
        $ok.css(a_css);
        //有取消按钮并且有确定的回调函数，设置确定后调用回调函数并追加取消按钮同时设定取消事件
        if (hasCancel && $.isFunction(callback)) {
            $ok.click(callback);
            //设置取消按钮和取消事件
            $ok.css({ "width": "127px", "float": "right" });
            var $cancel = $('<a style="clear:both;width:127px; float:left; border-right:1px solid #E2E1E0" href="javascript:void(0);">取消</a>');
            $cancel.css(a_css);
            $cancel.click(cancelEvent);
            $popBtns.append($cancel);
        } else {
            $ok.click(cancelEvent);
        }
        $popBtns.append($ok);
        $popShow.append($popBtns);
        $popTips.append($popShow);
        if ($("#popTips").length > 0) {
            $("#popTips").remove();
        }
        $('body').append($popTips);
    }
};