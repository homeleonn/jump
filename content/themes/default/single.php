<div class="container">
	<div style="float: left; padding: 10px;">
		<img src="<?=(isset($_jmp_post_img) ? $_jmp_post_img : THEME . 'img/news_thumb.jpg')?>" class="shower" style="max-width: 300px;">
	</div>
	<?php if(isset($terms)) echo $terms;?>
	<div style="padding: 10px;"><?=$content?></div>
	<div class="sep"></div>
	<?php include $this->get('comments');?>
	<?php 
		if(isAdmin()){
			echo '<a href="'.SITE_URL.'admin/'.$post_type.'/edit/'.$id.'/" title="Редактировать"><span class="icon-pencil"></span></a>';
		}
	?>
</div>