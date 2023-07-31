define(['bootstrap'], function($){
	var biz = {};
	
	biz.user = {};

	biz.user.browser = function(vals, callback, options) {
		require(['util'], function(u){
			var mode = 'visible';
			if(options && options.mode){
				mode = options.mode;
			} 
			var uids = '0';
			if($.isArray(vals) && vals.length>0){
				uids = vals.join();
			}
			var footer = 
				'<button type="button" class="btn btn-default" data-dismiss="modal">取消</button>' +
				'<button type="button" class="btn btn-primary">确认</button>';
			var url = './index.php?c=utility&a=user&do=browser';
			var dialog = u.dialog('请选择用户', [url+'&callback=aMember'+'&mode='+mode+'&uids=' + uids], footer,{containerName : 'user-browser-dialog'});
			dialog.modal('show');
			dialog.find('.btn.btn-primary').click(function(){
				var users = [];
				var chks = $('.user-browser :checkbox:checked');
				if(chks.length>0){
					chks.each(function(){
						users.push(this.value);
					});
					
					if($.isFunction(callback)) {
						callback(users);
						dialog.modal('hide');
					}
				}
			});
			
			window.aMember = {
				pIndex : 1,
				query : function() {
					var data = {
						keyword: $('#keyword').val(),
						page: aMember.pIndex,
						callback:'aMember',
						mode: mode,
						uids: uids
					};
					$.post(url, data, function(dat){
						dialog.find('.modal-body').html(dat);
					});
				}
			};
			
			window.p = function(url, p, state) {
				aMember.pIndex = p;
				window.aMember.query();
			}
			
		});
	}
	return biz;
});
