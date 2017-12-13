<?php

namespace Jump\helpers;

class Common{
	
	static $currentPageOptions;
	
	public static function exceptionMessage($e){
		if(isset($e->xdebug_message)){
			echo '<table border="1" cellspacing="0" cellpadding="2">'. $e->xdebug_message. '</table>';
		}else{
			trigger_error($e->getMessage() . ' in ' . $e->getFile() . ' on line: ' . $e->getLine());
		}
	}
	
	public static function postSlug(){
		return self::$currentPageOptions['slug'];
	}
	
	public static function loadCurrentPostOptions(){
		if(!self::$currentPageOptions)
			self::$currentPageOptions = helperDI::get('config')->getCurrentPageOptions();
	}
	
	public static function getConfig($filename){
		return include ROOT . 'Jump/config/' . $filename . '.php';
	}
}