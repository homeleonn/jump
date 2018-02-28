<?php



return [
	// ['news/('.URL_PATTERN.')', 'news:single/$1'],
	// ['news/([a-zA-Z0-9-,=;]+[^;])', 'news:list/$1'],
	// ['news', 'news:list'],
	
	
	['', 'post|index'],
	['('.URL_PATTERN_SLASH.')', 'post|single|$1'],
	['user', 'user|index', 'GET'],
	['user/login', 'user|login', 'GET'],
	['user/login', 'user|entry', 'POST'],
	['user/comments/add/(\d+)', 'user|addComment|$1', 'POST'],
	['user/comments/add/product/(\d+)', 'user|addComment|$1|product', 'POST'],
	
	// ['('.URL_PATTERN.')-c(\d+)(/(' . FILTER_PATTERN . '))?', 'category|single|$1|$2|$4'],
	// ['('.URL_PATTERN.')-p(\d+)', 'product|single|$1|$2'],
	
	// ['login', 'user/login'],
	// ['login/auth', 'user/auth', 'POST'],
	
	// ['user', 'user'],
	
	
];

