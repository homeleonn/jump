<?//=var_dump(get_defined_vars());?>
<div class="list-wrapper container-fluid">
	<div class="col-sm-<?php if(empty($taxonomy)): ?>12<?php else: ?>9<?php endif; ?>" style="float: right;">
		<?php 
		if($this->haveChild()):
			while($post = $this->theChild()):
		?>
		<div class="col-sm-3 list-item">
			<div>
				<a href="<?=$post['url']?>">
					<div class="thumb"><img src="<?=THEME . 'img/news_thumb.jpg'?>" alt="" width="100%"></div>
					<div class="name"><?=$post['title']?></div>
				</a>
			</div>
		</div>
		<?php 
			endwhile;
		else:
			echo 'Архивов нет!';
		endif;
		?>
		
	</div>
	<?php if(!empty($taxonomy)): ?>
	<div class="col-sm-3">
		<?=$filters;?>
	</div>
	<?php endif; ?>
</div>
<?php if(!isset($rewrite['paged']) && $rewrite['paged']): ?>
<?=$pagenation;?>
<?php endif; ?>