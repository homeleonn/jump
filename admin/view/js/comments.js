$(function(){
	;(function($){
		var $show 	= $('#admin-comment-block-show');
		var $cancel = $('#admin-cancel-comment');
		var $add 	= $('#admin-add-comment');
		var $commentText = $('#comment-text');
		
		$show.click(function(){
			if($(this).hasClass('none')) return;
			$(this).addClass('none');
			$("#admin-comments-block").removeClass('none');
		});
		
		$cancel.click(close);
		
		$add.click(function(){
			$.post(root + 'user/comments/add/' + $('#post_id').val() + '/', {comment: $commentText.val()}, function(){
				close();
			});
		});
		
		function close(){
			$show.removeClass('none');
			$("#admin-comments-block").addClass('none');
			$commentText.val('');
		}
		
		
	})(window.jQuery);
});

