<?php

$username = $clonos->_user_info['username'];
$db = new Db('base', 'cbsdtaskd');
$res = $db->select("SELECT id,st_time,end_time,cmd,status,errcode,logfile FROM taskd WHERE owner='?' ORDER BY id DESC", array([$username]));
$html = '';

if($res !== false){
	$nth = 0;
	$num = $nth & 1;

	foreach($res as $item){
		$hres = $clonos->getTableChunk('tasklog','tbody');
		if($hres !== false){
			$html_tmp = $hres[1];
			$vars = [
				'nth-num' => 'nth'.$num,
				'logid' => $item['id'],
				'logcmd' => $clonos->colorizeCmd($item['cmd']),
				'logstarttime' => date("d.m.Y H:i", strtotime($item['st_time'])),
				'logendtime' => date("d.m.Y H:i", strtotime($item['end_time'])),
				'logstatus' => $item['status'],
				'logerrcode' => $item['errcode'],
				'logsize' => '0 B'
			];

			$logsize = 0;
			$logfile = $item['logfile'];
			if(file_exists($logfile)){
				$logsize = filesize($logfile);
				$vars['logsize'] = $clonos->fileSizeConvert($logsize, 1024, true);
			}

			$vars['buttvalue'] = $tpl->translate('Open');
			$vars['disabled'] = ($logsize > 0) ? '' : 'disabled';

			$status = '';
			if($item['status'] == 1) $status = ' progress';
			if($item['status'] == 2 && $item['errcode'] == 0) $status = ' ok';
			if($item['status'] == 2 && $item['errcode'] != 0) $status = ' error';
			$vars['status'] = $status;
			
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
		'id' => 'taskloglist'
	];
}