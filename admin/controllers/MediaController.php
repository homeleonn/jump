<?php

namespace admin\controllers;

use Jump\Controller;
use Jump\helpers\Uploader;
use Jump\helpers\Msg;
use Jump\helpers\Common;

class MediaController extends Controller{
	public function actionShow($async = false){
		$data['media'] = $this->model->getAll();
		$data['title'] = 'Медиа';
		
		if($async === 'async'){
			$this->view->render('media/show', $data, false);
		}else{
			return $data;
		}
	}
	
	public function actionAdd(){//echo(json_encode($_FILES));exit;
		if(!isset($_FILES['files'])) return 0;
		$files = $_FILES['files'];
		$upDir = date('Y/m', time()) . '/';
		$dir = UPLOADS_DIR . $upDir;
		$urlDir = UPLOADS . $upDir;
		$uploader = new Uploader($dir);
		$thumbW = 150;
		$thumbH = 150;
		$thumbSrcList = [];
		$insert = [];
		$i = 0;
		
		while(isset($files['name'][$i])){
			if(($result = $uploader->img($files['tmp_name'][$i], $files['name'][$i])) !== false){
				$src = $upDir . $result['new_name'];
				
				$thumbSrcList[] = [
					'thumb' => $urlDir . $uploader->thumbCut($result['new_src'], $result['mime'], $result['w'], $result['h'], $thumbW, $thumbH),
					'orig' => $urlDir . $result['new_name']
				];
				
				$filesData = array_map([$this->db, 'escapeString'], [$files['name'][$i], $files['type'][$i]]);
				$insert[] = "('{$src}', {$filesData[0]}, {$filesData[1]})";
			}
			
			$i++;
			
			if($i > 50) break;
		}
		
		if(!empty($insert)){
			$i = 0;
			$this->db->query('LOCK TABLE media WRITE');
			
			foreach($insert as $ins){
				$this->model->insert($ins);
				$thumbSrcList[$i++]['id'] = $this->db->insertId();
			}
			
			$this->db->query('UNLOCK TABLES');
			Msg::json(['thumbSrcList' => $thumbSrcList]);
		}
		
	}
	
	public function actionDel($id){
		$media = $this->db->getRow('Select * from media where id = ?i', (int)$id);
		
		if($media){
			$src = UPLOADS_DIR . $media['src'];
			array_map('unlink', [$src, Common::prefix($src, '-150x150')]);
			$this->db->query('Delete from media where id = ' . $media['id']);
			$this->db->query('Delete from postmeta where meta_key = ?s and meta_value = ?i', '_jmp_post_img', $media['id']);
		}
		
		exit;
	}
}