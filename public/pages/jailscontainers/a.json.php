<?php
$jail_name = '';
(isset($this->uri_chunks[1])) AND $jail_name = $this->uri_chunks[1];
if(!empty($jail_name)){
	include('helpers.json.php');
	return;
}

$html='';

$db = new Db('base','nodes');
$res = $db->select('select nodename from nodelist', []);
$nodes = ['local'];
foreach($res as $val){
	$nodes[] = $val['nodename'];
}
$statuses = ['Not Launched','Launched','unknown-1','Maintenance','unknown-3','unknown-4','unknown-5','unknown-6'];
$allnodes = [];
$jail_ids = [];
$nth = 0;
$hres = $this->getTableChunk('jailslist','tbody');

foreach($nodes as $node){
	$db1 = new Db('base',$node);
	if($db1 !== false){
		$jails = $db1->select("SELECT jname,ip4_addr,status,protected FROM jails WHERE emulator!='bhyve' and hidden!=1 order by jname asc;", []);
		$allnodes[$node] = $jails;

		$num = $nth & 1;
		foreach($jails as $jail){
			if($hres !== false){
				$jname = $jail['jname'];
				$vnc_port_status = 'grey';
				$vnc_port = '';
				$vnc_port_file = $this->workdir.'/jails-system/'.$jname.'/vnc_port';
				if(file_exists($vnc_port_file)){
					$vnc_port = trim(file_get_contents($vnc_port_file));
				}

				$html_tpl = $hres[1];
				$status = $jail['status'];
				$vars = [
					'nth-num' => 'nth'.$num,
					'node' => $node,
					'ip4_addr' => str_replace(',',',<wbr />',$jail['ip4_addr']),
					'jname' => $jname,
					'vnc_port' => $vnc_port,
					'vnc_port_status' => $vnc_port_status,
					'status' => $status,
					'jstatus' => $tpl->translate($statuses[$status]),
					'icon' => ($status == 0) ? 'play' : 'stop',
					'desktop' => ($status == 0) ? ' s-off' : ' s-on',
					'maintenance' => ($status == 3)?' maintenance':'',
					'protected' => ($jail['protected'] == 1) ? 'icon-lock' : 'icon-cancel',
					'protitle' => ($jail['protected'] == 1) ? ' title="'.$tpl->translate('Protected jail').'"' : ' title="'.$tpl->translate('Delete').'"',
					'vnc_title' => $tpl->translate('Open VNC'),
					'reboot_title' => $tpl->translate('Restart jail'),
				];

				foreach($vars as $var => $val){
					$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
				}
				if($node != 'local') $html_tpl = str_replace('<span class="icon-cog"></span>', '', $html_tpl);

				$html .= $html_tpl;
			}

			$jail_ids[] = $jail['jname'];

		}

		$nth++;
	}
}

$html = str_replace(["\n","\r","\t"], '', $html);

$tasks = '';
if(!empty($jail_ids)){
	$tasks = $this->getRunningTasks($jail_ids);
}

$html_tpl_1 = str_replace(["\n","\r","\t"], '', $hres[1]);
if($hres !== false){
	$vars = [
		'nth-num' => 'nth0',
		'status' => '',
		'jstatus' => $tpl->translate('Creating'),
		'icon' => 'spin6 animate-spin',
		'desktop' => ' s-off',
		'maintenance' => ' maintenance busy',
		'protected' => 'icon-cancel',
		'protitle' => '',
		'vnc_title' => $tpl->translate('Open VNC'),
		'reboot_title' => $tpl->translate('Restart jail')
	];

	foreach($vars as $var => $val){
		$html_tpl_1 = str_replace('#'.$var.'#', $val, $html_tpl_1);
	}
}

$protected = [
	0 => [
		'icon' => 'icon-cancel',
		'title' => $tpl->translate('Delete')
	],
	1 => [
		'icon' => 'icon-lock',
		'title' => $tpl->translate('Protected jail')
	]
];

$included_result_array = [
	'tbody' => $html,
	'error' => false,
	'func' => 'fillTable',
	'id' => 'jailslist',
	'tasks' => $tasks,
	'template' => $html_tpl_1,
	'protected' => $protected,
];