<?php

$db = new Db('base','authkey');
$res = $db->select('SELECT idx,name,authkey FROM authkey;', []);
$html = '';

if(count($res) > 0){
	$nth = 0;
	$num = $nth & 1;

	foreach($res as $item) {
		$hres = $clonos->getTableChunk('authkeyslist','tbody');
		if($hres !== false){
			$html_tmp = $hres[1];
			$vars = [
				'nth-num' => 'nth'.$num,
				'keyid' => $item['idx'],
				'keyname' => $item['name'],
				'keysrc' => $item['authkey'],
				'deltitle' => ' title="'.$tpl->translate('Delete').'"'
			];

			foreach($vars as $var => $val){
				$html_tmp = str_replace('#'.$var.'#', $val, $html_tmp);
			}
			$html .= $html_tmp;
		}
	}

	$included_result_array = [
		'tbody' => $html,
		'error' => false,
		'func' => 'fillTable',
		'id' => 'authkeyslist'
	];
}