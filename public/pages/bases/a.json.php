<?php

$html = '';
$db = new Db('base','nodes');
$nodes = $db->select('select nodename,ip from nodelist order by nodename desc', []);
$nodes[] = ['nodename' => 'local'];
$nodes = array_reverse($nodes);

$ids = [];
$nth = 0;
$hres = $clonos->getTableChunk('baseslist','tbody');
foreach($nodes as $node){
	$db1 = new Db('base', $node['nodename']);
	if($db1->isConnected()){
		$bases = $db1->select("SELECT idx,platform,name,arch,targetarch,ver,stable,elf,date FROM bsdbase order by cast(ver AS int)", []);
		$num = $nth & 1;
		foreach($bases as $base){
			$idle = 1;
			if($node['nodename'] != 'local'){
				$idle = $clonos->check_locktime($node['ip']);
			}

			$ids[] = $base['idx'];
			$id = 'base'.$base['ver'].'-'.$base['arch'].'-'.$base['stable'];

			if($hres !== false){
				$html_tpl = $hres[1];
				$vars = [
					'id' => $id,
					'nth-num' => 'nth'.$num,
					'node' => $node['nodename'],
					'name' => $base['name'],
					'platform' => $base['platform'],
					'arch' => $base['arch'],
					'targetarch' => $base['targetarch'],
					'version' => $base['ver'],
					'version1' => ($base['stable']==1) ? 'stable' : 'release',
					'elf' => $base['elf'],
					'date' => $base['date'],
					'jstatus' => '',
					'maintenance' => ($idle==0) ? ' maintenance' : '',
					'deltitle' => $tpl->translate('Delete')
				];

				foreach($vars as $var => $val){
					$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
				}
				$html .= $html_tpl;
			}

			$ids[] = '#'.$id;
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
		//'jstatus' => $tpl->translate('Updating'),
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
	'id' => 'baseslist',
	'tasks' => $tasks,
	'template' => $html_tpl
];