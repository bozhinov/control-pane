<?php

$html = '';
$db = new Db('base', 'local');
if($db->isConnected()){
	$res = $db->select("select id,name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus,owner from vmpackages order by name asc", []);
}

$nth = 0;
$hres = $clonos->getTableChunk('packages', 'tbody');
$html_tpl = $hres[1];

foreach($res as $r){
	$html_tpl1 = $html_tpl;
	$vars = [
		'id' => $r['id'],
		'name' => $r['name'],
		'description' => $r['description'],
		'pkg_vm_ram' => $r['pkg_vm_ram'],
		'pkg_vm_disk' => $r['pkg_vm_disk'],
		'pkg_vm_cpus' => $r['pkg_vm_cpus'],
		'owner' => $r['owner'],
		'edit_title' => $tpl->translate('edit_title'),
		'delete_title' => $tpl->translate('delete_title')
	];

	foreach($vars as $var => $val){
		$html_tpl1 = str_replace('#'.$var.'#', $val, $html_tpl1);
	}
	$html .= $html_tpl1;
}


$html = str_replace(["\n","\r","\t"], '', $html);

$included_result_array = [
	'tbody' => $html,
	'error' => false,
	'func' => 'fillTable',
	'id' => 'packageslist'
];