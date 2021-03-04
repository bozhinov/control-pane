<?php

/*
id INTEGER PRIMARY KEY AUTOINCREMENT,
username VARCHAR(150) UNIQUE NOT NULL, 
password VARCHAR(128) UNIQUE NOT NULL, 
first_name VARCHAR(32), 
last_name VARCHAR(32), 
last_login TIMESTAMP DATE, 
is_active BOOLEAN DEFAULT 'true' NULL, 
date_joined TIMESTAMP DATE DEFAULT (datetime('now','localtime'))  
);
*/

$res = Auth::json_usersGetInfo();
$html = '';
$nth = 0;
# TODO: refactor this with Tpl
$hres = $clonos->getTableChunk('users', 'tbody');

foreach($res as $r){

	$vars = [
		'id' => $r['id'],
		'login' => $r['username'],
		'first_name' => $r['first_name'],
		'last_name' => $r['last_name'],
		'date_joined' => $r['date_joined'],
		'last_login' => $r['last_login'],
		'is_active' => ($r['is_active']==1) ? 'icon-ok' : '',
		'edit_title' => $tpl->translate('edit_title'),
		'delete_title' => $tpl->translate('delete_title')
	];

	$html_tpl1 = $hres[1];
	foreach($vars as $var => $val){
		$html_tpl1 = str_replace('#'.$var.'#', $val, $html_tpl1);
	}
	$html. = $html_tpl1;
}

$html = str_replace(["\n","\r","\t"], '', $html);

$included_result_array = [
	'tbody' => $html,
	'error' => false,
	'func' => 'fillTable',
	'id' => 'userslist'
];