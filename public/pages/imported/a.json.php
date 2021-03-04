<?php

$res = [
	0 => [
		'id' => 1,
		'name' => 'test',
		'path' => 'test/test/',
		'type' => 'клетка'
	]
];

$images = $clonos->getImportedImages();
$html = '';
$nth = 0;
$num = $nth & 1;
$html_tpl = '';
$hres = $clonos->getTableChunk('impslist','tbody');

if($hres !== false){
	$html_tmp = $hres[1];
	$html_tmp = replaceVars($html_tmp, [
		'deltitle' => ' title="'.$tpl->translate('Delete').'"',
		'dnldtitle' => ' title="'.$tpl->translate('Download').'"',
		'imptitle' => ' title="'.$tpl->translate('Create').'"')
	];
	$html_tpl_1 = $html_tmp;
}

foreach($images as $item){
	if(!isset($item['type'])) $item['type'] = 'unknown';

	if($hres !== false){
		$html_tpl = $html_tmp;
		$filename = $clonos->media_import.$item['name'];
		$sizefilename = $filename.'.size';
		if(file_exists($sizefilename)){
			$size = file_get_contents($sizefilename);
		} else {
			$size = filesize($filename);
		}
		$filesize = $clonos->fileSizeConvert($size, 1024, true);
		$query = "select count(*) as busy from taskd where status<2 and jname='${item['jname']}'";
		$busy = $clonos->_db_tasks->selectOne($query, []);
		$jstatus = '';
		$jbusy = '';
		if($busy['busy'] == 1){
			$jstatus = $tpl->translate('Exporting');
			$jbusy = 'busy';
		}

		$vars = [
			'nth-num' => 'nth'.$num,
			'id' => $item['name'],
			'jname' => $item['name'],
			'impsize' => $filesize,
			'jstatus' => $jstatus,
			'busy' => $jbusy,
			'imptype' => $tpl->translate($item['type']),
		];

		foreach($vars as $var => $val){
			$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
		}

		$html .= $html_tpl;
	}
}

function replaceVars($tpl, $vars)
{
	foreach($vars as $var => $val){
		$tpl = str_replace('#'.$var.'#', $val, $tpl);
	}
	return $tpl;
}

$included_result_array = [
	'tbody' => $html,
	'error' => false,
	'func' => 'fillTable',
	'id' => 'impslist',
	'template' => $html_tpl_1
];