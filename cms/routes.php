<?php


return [
	// ['news/('.URL_PATTERN.')', 'news:single/$1'],
	// ['news/([a-zA-Z0-9-,=;]+[^;])', 'news:list/$1'],
	// ['news', 'news:list'],
	
	
	['', 'page:index', 'method' => 'GET'],
	['('.URL_PATTERN.')', 'page:single/$1'],
	
	['login', 'user/login'],
	['login/auth', 'user/auth', 'POST'],
	
	['user', 'user'],
	
	
];

