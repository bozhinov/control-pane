<?php

$html = '';
$db = new Db('base', 'nodes');
$nodes = $db->select('select nodename,ip from nodelist order by nodename desc', []);

$nodes[] = ['nodename' => 'local'];
$nodes = array_reverse($nodes);
$ids = [];
$nth = 0;
$hres = $clonos->getTableChunk('srcslist', 'tbody');

foreach($nodes as $node){

	$db1 = new Db('base', $node['nodename']);
	if($db1 !== false){

		$bases = $db1->select("SELECT idx,name,platform,ver,rev,date FROM bsdsrc ORDER BY CAST(ver AS int)", []);
		$num = $nth & 1;

		foreach($bases as $base){

			$idle = 1;
			if($node['nodename'] != 'local'){
				$idle = $clonos->check_locktime($node['ip']);
			}

			if($hres !== false){
				$html_tpl = $hres[1];
				$vers = (preg_match('#\.\d#', $base['ver'])) ? 'release' : 'stable';
				$vars = [
					'nth-num' => 'nth'.$num,
					'node' => $node['nodename'],
					'name' => $base['name'],
					'platform' => $base['platform'],
					'version' => $base['ver'],
					'version1' => $vers,
					'rev' => $base['rev'],
					'date' => $base['date'],
					'jstatus' => '',
					'icon' => '',
					'maintenance' => ($idle==0) ? ' maintenance' : '',
					'deltitle' => $tpl->translate('Delete'),
					'updtitle' => $tpl->translate('Update')
				];

				foreach($vars as $var=>$val)[
					$html_tpl=str_replace('#'.$var.'#', $val, $html_tpl);
				]
				$html .= $html_tpl;
			}
			$ids[] = '#src'.$base['ver'];

		}

		$nth++;
	}
}

$html = str_replace(["\n","\r","\t"], '', $html);

$tasks = '';
if(!empty($ids)){
	$tasks = $clonos->getRunningTasks($ids);
}

$html_tpl = str_replace(["\n","\r","\t"], '', $hres[1]);
if($hres !== false){
	$vars = [
		'nth-num' => 'nth0',
		'status' => '',
		'jstatus' => $tpl->translate('Updating'),
		//'icon' => 'spin6 animate-spin',
		'desktop' => ' s-off',
		'maintenance' => ' maintenance busy',
		'updtitle' => $tpl->translate('Update'),
		'deltitle' => $tpl->translate('Delete')
	];

	foreach($vars as $var => $val){
		$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
	}
}

$included_result_array = [
	'tbody' => $html,
	'error' => false,
	'func' => 'fillTable',
	'id' => 'srcslist',
	'tasks' => $tasks,
	'template' => $html_tpl
];