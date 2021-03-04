<?php

require_once("cbsd.php");
require_once('config.php');
require_once('localization.php');
require_once('db.php');
require_once('forms.php');
require_once('utils.php');

class ClonOS 
{
	public $server_name = '';
	public $workdir = '';
	public $mode = '';
	public $form = '';
	public $environment = '';
	public $realpath = '';
	public $realpath_public = '';
	public $realpath_page = '';
	public $uri_chunks = [];
	public $json_name = '';
	public $table_templates = [];
	public $url_hash = '';
	public $media_import = '';
	public $json_req = false;
	public $sys_vars = [];
	private $_vars = [];
	private $_locale;
	private $_db = null;
	private $_cmd_array = [
		'jcreate','jstart','jstop',
		'jrestart','jedit','jremove',
		'jexport','jimport','jclone',
		'jrename','madd','sstart',
		'sstop','projremove','bcreate',
		'bstart','bstop','brestart',
		'bremove','bclone','brename',
		'vm_obtain','removesrc','srcup',
		'removebase','world','repo','forms'
	];

	function __construct($uri_chunks = null){

		$this->_vars = $_POST;

		$this->workdir = getenv('WORKDIR'); # // /usr/jails
		$this->environment = getenv('APPLICATION_ENV');
		$this->realpath = '../'; # /usr/local/www/clonos/
		$this->realpath_public = '../public/'; # /usr/local/www/clonos/public/
		$this->media_import = '../media_import/';

		if($this->environment == 'development'){
			$sentry_file = '../php/sentry.php';
			if(file_exists($sentry_file)) include($sentry_file);
		}

		if(isset($_SERVER['SERVER_NAME']) && !empty(trim($_SERVER['SERVER_NAME']))){
			$this->server_name = $_SERVER['SERVER_NAME'];
		} else {
			$this->server_name = $_SERVER['SERVER_ADDR'];
		}

		if (is_null($uri_chunks)) { # TODO Do we need this ?
			$this->uri_chunks = Utils::gen_uri_chunks(trim($_SERVER['REQUEST_URI'],'/'));
		} else {
			$this->uri_chunks = $uri_chunks;
		}

		$this->config = new Config();
		$this->_locale = new Localization($this->realpath_public);

		if(isset($this->_vars['path'])){
			$this->realpath_page = $this->realpath_public.'pages/'.$this->uri_chunks[0].'/';
			$this->json_name = $this->realpath_page.'a.json.php';
		} else if($_SERVER['REQUEST_URI']){
			if(isset($this->uri_chunks[0])){
				$this->realpath_page = $this->realpath_public.'pages/'.$this->uri_chunks[0].'/';
			}
		}

		if(isset($this->_vars['hash'])) $this->url_hash = preg_replace('/^#/','',$this->_vars['hash']);
		if(isset($this->_vars['mode'])) $this->mode = $this->_vars['mode'];
		if(isset($this->_vars['form_data'])) $this->form = $this->_vars['form_data'];

		if(isset($_POST['mode'])){
			// functions, running without parameters
			$new_array = []; # TODO: Fix this mess
			$cfunc = 'ccmd_'.$this->mode;
			if(method_exists($this, $cfunc)){
				$ccmd_res = $this->$cfunc();
				if(is_array($ccmd_res)){
					$new_array = array_merge($this->sys_vars, $ccmd_res);
				} else {
					echo json_encode($ccmd_res);
					return;
				}
				echo json_encode($new_array);
				return;
			}

			#$included_result_array = ''; # used in a.jason files
			switch($this->mode){
				//case 'login':	 		echo json_encode($this->login()); break;
				case 'getTasksStatus':
					echo json_encode($this->_getTasksStatus($this->form['jsonObj']));
					break;

				/*
				case '_getJsonPage':
					if(file_exists($this->json_name)){
						include($this->json_name);
						if(is_array($included_result_array)){
							$new_array=array_merge($this->sys_vars,$included_result_array);
							echo json_encode($new_array);
							return;
						} else {
							echo '{}';
						}
					} else {
						echo '{}';
					}
					break;
				*/
				//case 'freejname':		echo json_encode($this->getFreeJname()); break;
				case 'helpersAdd':		echo json_encode($this->helpersAdd($this->mode)); break;
				case 'addHelperGroup':		echo json_encode($this->addHelperGroup($this->mode)); break;
				//case 'addJailHelperGroup':	echo json_encode($this->addJailHelperGroup()); break;
				//case 'deleteJailHelperGroup':	echo json_encode($this->deleteJailHelperGroup()); break;
				case 'deleteHelperGroup':	echo json_encode($this->deleteHelperGroup($this->mode)); break;
				//case 'jailRestart':		echo json_encode($this->jailRestart()); break;
				//case 'jailStart':		echo json_encode($this->jailStart()); break;
				//case 'jailStop':		echo json_encode($this->jailStop()); break;
				//case 'jailRemove':		echo json_encode($this->jailRemove()); break;
				//case 'saveJailHelperValues':	echo json_encode($this->saveJailHelperValues()); break;

				case 'saveHelperValues':
					$redirect = '/jailscontainers/';
				case 'jailAdd':
					if(!isset($redirect)) {
						$redirect = '';
					} else {
						echo json_encode($this->jailAdd($redirect));
					} 
					break;

				//case 'jailClone':		echo json_encode($this->jailClone()); break;
				//case 'jailRename':		echo json_encode($this->jailRename()); break;
				//case 'bhyveRename':		echo json_encode($this->bhyveRename()); break;
				//case 'jailEdit':		echo json_encode($this->jailEdit()); break;
				//case 'jailEditVars':		echo json_encode($this->jailEditVars()); break;
				//case 'jailCloneVars':		echo json_encode($this->jailCloneVars()); break;
				//case 'jailRenameVars':		echo json_encode($this->jailRenameVars()); break;
				//case 'bhyveRenameVars':		echo json_encode($this->bhyveRenameVars()); break;
				//case 'bhyveRestart':		echo json_encode($this->bhyveRestart()); break;
				//case 'bhyveStart':		echo json_encode($this->bhyveStart()); break;
				//case 'bhyveStop':		echo json_encode($this->bhyveStop()); break;
				//case 'bhyveAdd':		echo json_encode($this->bhyveAdd()); break;
				//case 'bhyveRemove':		echo json_encode($this->bhyveRemove()); break;
				//case 'bhyveEdit':		echo json_encode($this->bhyveEdit()); break;
				//case 'bhyveEditVars':		echo json_encode($this->bhyveEditVars()); break;
				//case 'bhyveObtain':		echo json_encode($this->bhyveObtain()); break;
				//case 'bhyveClone':		echo json_encode($this->bhyveClone()); break;		
				//case 'authkeyAdd':		echo json_encode($this->authkeyAdd()); break;
				//case 'authkeyRemove':		echo json_encode($this->()); break;
				//case 'vpnetAdd':		echo json_encode($this->vpnetAdd()); break;
				//case 'vpnetRemove':		echo json_encode($this->vpnetRemove()); break;
				//case 'updateBhyveISO':		echo json_encode($this->updateBhyveISO()); break;
				/*
				case 'mediaAdd':
					//echo json_encode($this->mediaAdd());
					break;
				*/
				//case 'mediaRemove':		echo json_encode($this->mediaRemove()); break;
				//case 'logLoad':			echo json_encode($this->logLoad()); break;
				//case 'logFlush':		echo json_encode($this->logFlush()); break;
				//case 'basesCompile':		echo json_encode($this->basesCompile()); break;
				//case 'repoCompile':		echo json_encode($this->repoCompile()); break;
				//case 'srcUpdate':		echo json_encode($this->srcUpdate()); break;
				//case 'srcRemove':		echo json_encode($this->srcRemove()); break;
				//case 'baseRemove':		echo json_encode($this->baseRemove()); break;
				//case 'vmTemplateAdd':		echo json_encode($this->vmTemplateAdd()); break;
				//case 'vmTemplateEditInfo':	echo json_encode($this->vmTemplateEditInfo()); break;
				//case 'vmTemplateEdit':		echo json_encode($this->vmTemplateEdit()); break;
				//case 'vmTemplateRemove':	echo json_encode($this->vmTemplateRemove()); break;
				//case 'getImportedImageInfo':	echo json_encode($this->getImportedImageInfo()); break;
				//case 'imageImport':		echo json_encode($this->imageImport()); break;
				//case 'imageExport':		echo json_encode($this->imageExport()); break;
				//case 'imageRemove': echo json_encode($this->imageRemove()); break;
				//case 'getSummaryInfo': echo json_encode($this->getSummaryInfo()); break;
					
/*				case 'saveHelperValues':
					echo json_encode($this->saveHelperValues());
					break;
*/
			}
		}
	}

	function ccmd_getJsonPage()
	{
		$included_result_array = false;
		if(file_exists($this->json_name)){
			include($this->json_name);
			if(is_array($included_result_array)){
				$new_array = array_merge($this->sys_vars, $included_result_array);
				echo json_encode($new_array);
				exit;
			}
		}
		echo json_encode($this->sys_vars);
		exit;
	}

	function getTableChunk($table_name, $tag)
	{
		if(isset($this->table_templates[$table_name][$tag])){
			return $this->table_templates[$table_name][$tag];
		}

		$file_name = $this->realpath_page.$table_name.'.table';
		if(!file_exists($file_name)) return false;
		$file = file_get_contents($file_name);
		$pat = '#[\s]*?<'.$tag.'[^>]*>(.*)<\/'.$tag.'>#iUs';
 		if(preg_match($pat, $file, $res)){
			$this->table_templates[$table_name][$tag] = $res;
			return $res;
		}
		return ''; # TODO ???
	}

	function check_locktime($nodeip)
	{
		$lockfile = $this->workdir."/ftmp/shmux_${nodeip}.lock";
		if (file_exists($lockfile)){
			$cur_time = time();
			$difftime =(($cur_time - filemtime($lockfile)) / 60);
			if ($difftime > 1){ 
				return round($difftime);
			}
		}

		return 0; //too fresh or does not exist
	}

	function check_vmonline($vm)
	{
		$vmmdir = "/dev/vmm";

		if(file_exists($vmmdir)){
			if($handle = opendir($vmmdir)){
				while(false !== ($entry = readdir($handle))){
					if($entry[0] == ".") continue;
					if($vm == $entry) {
						closedir($handle);
						return 1;
					}
				}
				closedir($handle);
			}
		}

		return 0;
	}

	function get_node_info($nodename, $value)
	{
		$db = new Db('', '', $this->realpath."/var/db/nodes.sqlite"); 
		if (!$db->isConnected()) return ['error' => true, 'res' => $db->error_message];

		$result = $db->select("SELECT ? FROM nodelist WHERE nodename=?", array([$value], [$nodename]));

		foreach($result as $res){
			if(isset($res[$value])){
				return $res[$value];
			}
		}
		// TODO: what if not found ?
	}

	function getRunningTasks($ids = [])
	{
		$check_arr = [
			'jcreate'=>'Creating',
			'jstart'=>'Starting',
			'jstop'=>'Stopping',
			'jrestart'=>'Restarting',
			'jremove'=>'Removing',
			'jexport'=>'Exporting',
			'jclone'=>'Cloning',
			'bcreate'=>'Creating',
			'bstart'=>'Starting',
			'bstop'=>'Stopping',
			'brestart'=>'Restarting',
			'bremove'=>'Removing',
			'bclone'=>'Cloning',
			'vm_obtain'=>'Creating',
			'removesrc'=>'Removing',
			'srcup'=>'Updating',
			'removebase'=>'Removing',
			'world'=>'Compiling',
			'repo'=>'Fetching',
			'imgremove'=>'Removing'
		];

		$res = [];
		if(!empty($ids)){
			$tid = join("','", $ids);
			$query = "SELECT id,cmd,status,jname FROM taskd WHERE status<2 AND jname IN (?)";
			$cmd = '';
			$txt_status = '';
			$tasks = (new Db('base','cbsdtaskd'))->select($query, array([$tid]));
			foreach($tasks as $task){
				$rid = preg_replace('/^#/','',$task['jname']);
				foreach($check_arr as $key => $val){
					if(strpos($task['cmd'], $key) !== false){
						$cmd = $key;
						$txt_status = $val;
						break;
					}
				}
				$res[$rid]['status'] = $task['status'];
				$res[$rid]['task_cmd'] = $cmd;
				$res[$rid]['txt_status'] = $txt_status;
				$res[$rid]['task_id'] = $task['id'];
			}
			return $res;
		}
		return null;
	}

/*
	function getProjectsListOnStart()
	{
		$query = 'SELECT * FROM projects';
		$res = $this->_db->select($query, []);
		echo '	var projects = ', json_encode($res), PHP_EOL;
	}
*/

/*
	function getTaskStatus($task_id)
	{
		$status = (new Db('base','cbsdtaskd'))->selectOne("SELECT status,logfile,errcode FROM taskd WHERE id=?", array([$task_id]);

		if($status['errcode'] > 0) $status['errmsg'] = file_get_contents($status['logfile']);

		return $status;
	}
*/

	private function doTask($key, $task)
	{
		if($task['status'] != -1) return false;

		switch($task['operation']){
			case 'jstart':		$res = $this->jailStart($key); break;
			case 'jstop':		$res = $this->jailStop($key); break;
			case 'jrestart':	$res = $this->jailRestart($key); break;
			//case 'jedit':		$res = $this->jailEdit('jail'.$key); break;
			case 'jremove':		$res = $this->jailRemove($key); break;
						
			case 'bstart':		$res = $this->bhyveStart($key); break;
			case 'bstop':		$res = $this->bhyveStop($key); break;
			case 'brestart':	$res = $this->bhyveRestart($key); break;
			case 'bremove':		$res = $this->bhyveRemove($key); break;
			case 'removesrc':	$res = $this->srcRemove($key); break;
			case 'srcup':		$res = $this->srcUpdate($key); break;
			case 'removebase':	$res = $this->baseRemove($key); break;
						
			//case 'jexport':	$res = $this->jailExport('jail'.$key,$task['jname'], $key); break;
			//case 'jimport':	$res = $this->jailImport('jail'.$key,$task['jname'], $key); break;
			//case 'jclone':	$res = $this->jailClone('jail'.$key,$key,$obj[$key]); break;
			//case 'madd':		$res = $this->moduleAdd('jail'.$key,$task['jname'], $key); break;
			//case 'mremove':	$res = $this->moduleRemove('jail'.$key, $task['jname'], $key); break;
			//case 'sstart':	$res = $this->serviceStart($task); break;
			//case 'sstop':		$res = $this->serviceStop($task); break;
			//case 'projremove':	$res = $this->projectRemove($key, $task); break;
		}
	}

	function _getTasksStatus($jsonObj)
	{
		$tasks = [];
		$obj = json_decode($jsonObj, true);

		if(isset($obj['proj_ops'])) return $this->GetProjectTasksStatus($obj);
		if(isset($obj['mod_ops'])) return $this->GetModulesTasksStatus($obj);

		$ops_array = $this->_cmd_array;
		$stat_array = [
			'jcreate' => [$this->_locale->translate('Creating'), $this->_locale->translate('Created')],
			'jstart' =>  [$this->_locale->translate('Starting'), $this->_locale->translate('Launched')],
			'jstop' =>   [$this->_locale->translate('Stopping'), $this->_locale->translate('Stopped')],
			'jrestart' =>[$this->_locale->translate('Restarting'), $this->_locale->translate('Restarted')],
			'jedit' =>   [$this->_locale->translate('Saving'), $this->_locale->translate('Saved')],
			'jremove' => [$this->_locale->translate('Removing'), $this->_locale->translate('Removed')],
			'jexport' => [$this->_locale->translate('Exporting'), $this->_locale->translate('Exported')],
			'jimport' => [$this->_locale->translate('Importing'), $this->_locale->translate('Imported')],
			'jclone' =>  [$this->_locale->translate('Cloning'), $this->_locale->translate('Cloned')],
			'madd' => 	 [$this->_locale->translate('Installing'), $this->_locale->translate('Installed')],
			//'mremove' => ['Removing','Removed'],
			'sstart' =>  [$this->_locale->translate('Starting'), $this->_locale->translate('Started')],
			'sstop' =>   [$this->_locale->translate('Stopping'), $this->_locale->translate('Stopped')],
			'vm_obtain' => [$this->_locale->translate('Creating'), $this->_locale->translate('Created')],
			'srcup' =>   [$this->_locale->translate('Updating'), $this->_locale->translate('Updated')],
			'world' =>   [$this->_locale->translate('Compiling'), $this->_locale->translate('Compiled')],
			'repo' =>    [$this->_locale->translate('Fetching'), $this->_locale->translate('Fetched')],
			//'projremove' => ['Removing', 'Removed'],
		];
		$stat_array['bcreate'] = &$stat_array['jcreate'];
		$stat_array['bstart'] = &$stat_array['jstart'];
		$stat_array['bstop'] = &$stat_array['jstop'];
		$stat_array['brestart'] = &$stat_array['jrestart'];
		$stat_array['bremove'] = &$stat_array['jremove'];
		$stat_array['bclone'] = &$stat_array['jclone'];
		$stat_array['removesrc'] = &$stat_array['jremove'];
		$stat_array['removebase'] = &$stat_array['jremove'];
		$stat_array['imgremove'] = &$stat_array['jremove'];

		foreach($obj as $key => $task){
			if(in_array($task['operation'], $ops_array)){
				if(false !== ($res = $this->runTask($key, $task))){
					if($res['error']) $obj[$key]['retval'] = $res['retval'];
					if(!empty($res['error_message'])) $obj[$key]['error_message'] = $res['error_message'];

					if(isset($res['message'])){
						$task_id = intval($res['message']);
						if($task_id > 0){
							$tasks[] = $task_id;
							$obj[$key]['task_id'] = $task_id;
							//$obj[$key]['txt_log'] = file_get_contents('/tmp/taskd.'.$task_id.'.log');
						}
					}
				} else {
					$tasks[] = $task['task_id'];
				}
			}

			($task['status'] == -1) AND $obj[$key]['status'] = 0;
		}

		$ids = join(',', $tasks);
		if(empty($ids)) return $obj;

		$statuses = (new Db('base','cbsdtaskd'))->select("SELECT id,status,logfile,errcode FROM taskd WHERE id IN (?)", array([$ids]));

		//print_r($statuses);
		foreach($obj as $key => $task){
			foreach($statuses as $stat){
				if($task['task_id'] != $stat['id']) continue;

				$obj[$key]['status'] = $stat['status'];
				$num = ($stat['status'] < 2 ? 0 : 1);
				$obj[$key]['txt_status'] = $stat_array[$obj[$key]['operation']][$num];
				if($stat['errcode'] > 0){
					$obj[$key]['errmsg'] = file_get_contents($stat['logfile']);
					$obj[$key]['txt_status'] = $this->_locale->translate('Error');
				}

				//Return the IP of the cloned jail if it was assigned by DHCP
				if($stat['status'] == 2){
					switch($task['operation']){
						case 'jcreate':
						case 'jclone':
							$res = $this->getJailInfo($obj[$key]['jail_id'], $task['operation']);
							if(isset($res['html'])) $obj[$key]['new_html'] = $res['html'];
							break;
						case 'bclone':
							$res = $this->getBhyveInfo($obj[$key]['jail_id']);
							if(isset($res['html'])) $obj[$key]['new_html'] = $res['html'];
							break;
						case 'repo':
							$res = $this->fillRepoTr($obj[$key]['jail_id'], true, false);
							if(isset($res['html'])) $obj[$key]['new_html'] = $res['html'];
							break;
						case 'srcup':
							$res = $this->getSrcInfo($obj[$key]['jail_id']);
							if(isset($res['html'])) $obj[$key]['new_html'] = $res['html'];
							break;
					}
				}
			}
		}

		return $obj;
	}

	function ccmd_jailRename()
	{
		$form = $this->_vars['form_data'];
		$cmd = "task owner=%s mode=new {cbsd_loc} jrename old=%s new=%s host_hostname=%s ip4_addr=%s restart=1";
		$args = [
			$this->_user_info['username'], 
			$form['oldJail'], 
			$form['jname'], 
			$form['host_hostname'], 
			$form['ip4_addr']
		];
		$res = CBSD::run($cmd, $args);

		$err = 'Jail is not renamed!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Jail was renamed!';
			$taskId = $res['message'];
		} else {
			$err = $res['error'];
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $form['jname'],
			'taskId' => $taskId,
			'mode' => $this->mode
		];
	}

	function ccmd_jailClone()
	{
		$form = $this->_vars['form_data'];
		$cmd = 'task owner=%s mode=new {cbsd_loc} jclone checkstate=0 old=%s new=%s host_hostname=%s ip4_addr=%s';
		$args = [
			$this->_user_info['username'], 
			$form['oldJail'], 
			$form['jname'],
			$form['host_hostname'], 
			$form['ip4_addr']
		];
		$res = CBSD::run($cmd, $args);

		$err = 'Jail is not cloned!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Jail was cloned!';
			$taskId = $res['message'];
		} else {
			$err = $res['error'];
		}

		$html = '';
		$hres = $this->getTableChunk('jailslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'nth-num' => 'nth0',	// TODO: actual data
				'node' => 'local',		// TODO: actual data
				'ip4_addr' => str_replace(',',',<wbr />',$form['ip4_addr']),
				'jname' => $form['jname'],
				'jstatus' => $this->_locale->translate('Cloning'),
				'icon' => 'spin6 animate-spin',
				'desktop' => ' s-on',
				'maintenance' => ' maintenance',
				'protected' => 'icon-cancel',
				'protitle' => $this->_locale->translate('Delete'),
				'vnc_title' => $this->_locale->translate('Open VNC'),
				'reboot_title' => $this->_locale->translate('Restart jail')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $form['jname'],
			'taskId' => $taskId,
			'mode' => $this->mode,
			'html' => $html
		];
	}

	function getJailInfo($jname, $op = '')
	{
		$stats = ['' => '', 'jclone' => 'Cloned', 'jcreate' => 'Created'];
		$html = '';
		$db = new Db('base','local');
		if($db->isConnected()){
			$jail = $db->selectOne("SELECT jname,ip4_addr,status,protected FROM jails WHERE jname=?", array([$jname]));
			$hres = $this->getTableChunk('jailslist','tbody');
			if($hres !== false){
				$html_tpl = $hres[1];
				$vars = [
					'nth-num' => 'nth0',
					'node' => 'local',
					'ip4_addr' => str_replace(',', ',<wbr />', $jail['ip4_addr']),
					'jname' => $jail['jname'],
					'jstatus' => $this->_locale->translate($stats[$op]),
					'icon' => 'spin6 animate-spin',
					'desktop' => ' s-on',
					'maintenance' => ' maintenance',
					'protected' => ($jail['protected']==1) ? 'icon-lock' : 'icon-cancel',
					'protitle' => ($jail['protected']==1) ? ' title="'.$this->_locale->translate('Protected jail').'"' : ' title="'.$this->_locale->translate('Delete').'"',
					'vnc_title' => $this->_locale->translate('Open VNC'),
					'reboot_title' => $this->_locale->translate('Restart jail'),
				];

				foreach($vars as $var => $val){
					$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
				}
				$html .= $html_tpl;
			}
		}

		$html = preg_replace('#<tr[^>]*>#','', $html);
		$html = str_replace(['</tr>',"\n","\r","\t"], '', $html);

		return ['html' => $html];
	}

	function saveSettingsCBSD()
	{
		return ['error' => true, 'errorMessage' => 'Method is not complete yet! line: '.__LINE__];
	}

	function ccmd_saveJailHelperValues()
	{
		if(!isset($this->uri_chunks[1]) || !isset($this->url_hash)) return ['error' => true, 'errorMessage' => 'Bad url!'];
		$jail_name = $this->uri_chunks[1];

		$db = new Db('helper', ['jname' => $jail_name, 'helper' => $this->url_hash]);
		if(!$db->isConnected()) return ['error' => true, 'errorMessage' => 'No helper database!'];
	
		foreach($this->form as $key => $val) {
			if($key != 'jname' && $key != 'ip4_addr') {
				$db->update("update forms set new=? where param=?", array([$val], [$key]));
			}
		}

		$res = CBSD::run('task owner=%s mode=new {cbsd_loc} forms module=%s jname=%s inter=0', [$this->_user_info['username'], $this->url_hash, $jail_name]);

		$err = 'Helper values is saved!';
		$taskId = -1;
		if($res['retval'] == 0) {
			$err = 'Helper values was not saved!';
			$taskId = $res['message'];
		}

		return [
			'jail_id' => $jail_name,
			'taskId' => $taskId,
			'mode' => $this->mode
		];
	}

	function jailAdd($redirect = '')
	{
		$form = $this->form;
		$helper = preg_replace('/^#/', '', $this->_vars['hash']);
		$db_path = '';
		$with_img_helpers = '';
		if($this->mode == 'saveHelperValues'){
			if($helper == '' && $this->_vars['path'] == '/settings/') return $this->saveSettingsCBSD();

			if(!isset($this->_vars['db_path'])){
				$res = CBSD::run('make_tmp_helper module=%s', [$helper]);
				if($res['retval'] == 0){
					$db_path = $res['message'];
				} else {
					echo json_encode(['error' => true,'errorMessage' => 'Error opening temporary form database!']);
					return;
				}
			} else { 
				$db_path = $this->_vars['db_path'];
			}

			$db = new Db('file', $db_path);
			if($db->isConnected()){
				foreach($form as $key => $val){
					if($key != 'jname' && $key != 'ip4_addr'){
						$db->update("update forms set new=? where param=?", array([$val], [$key]));
						unset($form[$key]);
					}
				}

				$with_img_helpers = $db_path;
			}

			$form['interface'] = 'auto';
			$form['user_pw_root'] = '';
			$form['astart'] = 1;
			$form['host_hostname'] = $form['jname'].'.my.domain';
		}

		$err = [];
		$arr = [
			'workdir' => $this->workdir,
			'mount_devfs' => 1,
			'arch' => 'native',
			'mkhostfile' => 1,
			'devfs_ruleset' => 4,
			'ver' => 'native',
			'mount_src' => 0,
			'mount_obj' => 0,
			'mount_kernel' => 0,
			'applytpl' => 1,
			'floatresolv' => 1,
			'allow_mount' => 1,
			'allow_devfs' => 1,
			'allow_nullfs' => 1,
			'mkhostsfile' => 1,
			'pkg_bootstrap' => 0,
			'mdsize' => 0,
			'runasap' => 0,
			'with_img_helpers' => $with_img_helpers
		];

		$arr_copy = ['jname','host_hostname','ip4_addr','user_pw_root','interface'];
		foreach($arr_copy as $a){
			(isset($form[$a])) AND $arr[$a] = $form[$a];
		}

		$arr_copy = ['baserw','mount_ports','astart','vnet'];
		foreach($arr_copy as $a){
			if(isset($form[$a]) && $form[$a] == 'on'){
				$arr[$a] = 1;
			} else {
				$arr[$a] = 0;
			}
		}

		$sysrc = [];
		(isset($form['serv-ftpd'])) AND $sysrc[] = $form['serv-ftpd'];
		(isset($form['serv-sshd'])) AND $sysrc[] = $form['serv-sshd'];
		$arr['sysrc_enable'] = implode(' ', $sysrc);

		/* create jail */
		$file_name = '/tmp/'.$arr['jname'].'.conf';

		$file = file_get_contents($this->realpath_public.'templates/jail.tpl');
		if(!empty($file)) {
			foreach($arr as $var => $val){
				$file = str_replace('#'.$var.'#', $val, $file);
			}
		}
		file_put_contents($file_name ,$file);

		$res = CBSD::run('task owner=%s mode=new {cbsd_loc} jcreate inter=0 jconf=%s', [$this->_user_info['username'], $file_name]);

		$err = 'Jail is not created!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Jail was created!';
			$taskId = $res['message'];
		}

		// local - change to the real server on which the jail was created!
		$jid = $arr['jname'];

		$table = 'jailslist';
		$hres = $this->getTableChunk($table,'tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'nth-num' => 'nth0',	// TODO: fix actual data!
				'node' => 'local',	// TODO: fix actual data!
				'ip4_addr' => str_replace(',', ',<wbr />', $form['ip4_addr']),
				'jname' => $arr['jname'],
				'jstatus' => $this->_locale->translate('Creating'),
				'icon' => 'spin6 animate-spin',
				'desktop' => ' s-off',
				'maintenance'=> ' busy maintenance',
				'protected' => 'icon-cancel',
				'protitle' => $this->_locale->translate('Delete'),
				'vnc_title' => $this->_locale->translate('Open VNC'),
				'reboot_title' => $this->_locale->translate('Restart jail')
			];
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $jid,
			'taskId' => $taskId,
			'mode' => $this->mode,
			'redirect' => $redirect,
			'db_path' => $db_path
		];
	}

	function ccmd_jailRenameVars()
	{
		$res = [];
		$form = $this->_vars['form_data'];
		if(!isset($form['jail_id'])) return ['error' => true, 'error_message' => 'Bad jail id!'];

		$err = false;
		$db = new Db('base','local');
		if($db->isConnected()){
			$query = "SELECT jname,host_hostname FROM jails WHERE jname=?;"; //ip4_addr
			$res['vars'] = $db->selectOne($query, array([$form['jail_id']]));
		} else {
			$err = true;
		}

		if(empty($res['vars'])) $err = true;

		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.');
			$res['jail_id'] = $form['jail_id'];
			$res['reload'] = true;
			return $res;
		}

		$orig_jname = $res['vars']['jname'];
		//$res['vars']['jname'].='clone';
		$res['vars']['ip4_addr'] = 'DHCP';
		$res['vars']['host_hostname'] = preg_replace('/^'.$orig_jname.'/', $res['vars']['jname'], $res['vars']['host_hostname']);
		$res['error'] = false;
		$res['dialog'] = $form['dialog'];
		$res['jail_id'] = $form['jail_id'];
		return $res;
	}

	function ccmd_jailCloneVars()
	{
		$res = [];
		$form = $this->_vars['form_data'];
		if(!isset($form['jail_id'])) return ['error' => true, 'error_message' => 'Bad jail id!'];

		$err = false;
		$db= new Db('base','local');
		if($db->isConnected()){
			$query = "SELECT jname,host_hostname FROM jails WHERE jname=?;";	//ip4_addr
			$res['vars'] = $db->selectOne($query, array([$form['jail_id']]));
		} else {
			$err = true;
		}

		(empty($res['vars'])) AND $err = true;
		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.');
			$res['jail_id'] = $form['jail_id'];
			$res['reload'] = true;
			return $res;
		}

		$orig_jname = $res['vars']['jname'];
		$res['vars']['jname'] .= 'clone';
		$res['vars']['ip4_addr'] = 'DHCP';
		$res['vars']['host_hostname'] = preg_replace('/^'.$orig_jname.'/', $res['vars']['jname'], $res['vars']['host_hostname']);
		$res['error'] = false;
		$res['dialog'] = $form['dialog'];
		$res['jail_id'] = $form['jail_id'];
		return $res;
	}

	function ccmd_jailEditVars()
	{
		$res = [];
		$form = $this->_vars['form_data'];
		if(!isset($form['jail_id'])) return ['error' => true, 'error_message' => 'Bad jail id!'];

		$err = false;
		$db = new Db('base','local');
		if($db->isConnected()){
			$query = "SELECT jname,host_hostname,ip4_addr,allow_mount,interface,mount_ports,astart,vnet FROM jails WHERE jname=?;";
			$res['vars'] = $db->selectOne($query, array([$form['jail_id']]));
		} else {
			$err = true;
		}
		(empty($res['vars'])) AND $err = true;

		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.');
			$res['jail_id'] = $form['jail_id'];
			$res['reload'] = true;
			return $res;
		}

		$res['error'] = false;
		$res['dialog'] = $form['dialog'];
		$res['jail_id'] = $form['jail_id'];
		return $res;
	}

	function ccmd_jailEdit()
	{
		$form = $this->_vars['form_data'];
		$str = [];
		$jname = $form['jname'];
		$arr = ['host_hostname','ip4_addr','allow_mount','interface','mount_ports','astart','vnet'];
		foreach($arr as $a){
			if(isset($form[$a])){
				$val = $form[$a];
				($val == 'on') AND $val = 1;
				$str[] = $a.'='.$val;
			} else {
				$str[] = $a.'=0';
			}
		}

		$res = CBSD::run('jset jname=%s %s', [$jname, join(' ', $str)]);
		$res['mode'] = 'jailEdit';
		$res['form'] = $form;
		return $res;
	}

	function ccmd_jailStart()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jstart inter=0 jname=%s',
			[$this->_user_info['username'], $this->_vars['form_data']['jname']]
		); // autoflush=2
	}

	function ccmd_jailStop()
	{
		return CBSD::run(
			'task owner='.$username.' mode=new {cbsd_loc} jstop inter=0 jname=%s',
			[$this->_user_info['username'], $this->_vars['form_data']['jname']]
		); // autoflush=2
	}

	function ccmd_jailRestart()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jrestart inter=0 jname=%s',
			[$this->_user_info['username'], $this->_vars['form_data']['jname']]
		);	// autoflush=2
	}

	function ccmd_jailRemove()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jremove inter=0 jname=%s',
			[$this->_user_info['username'], $this->_vars['form_data']['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveClone()
	{
		$form = $this->_vars['form_data'];
		$res = CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bclone checkstate=0 old=%s new=%s',
			[$this->_user_info['username'], $form['oldBhyve'], $form['vm_name']]
		);

		$err = 'Virtual Machine is not renamed!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Virtual Machine was renamed!';
			$taskId = $res['message'];
		} else {
			$err = $res['error'];
		}

		$html = '';
		$hres = $this->getTableChunk('bhyveslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'nth-num' => 'nth0', // TODO: actual data
				'node' => 'local',	 // TODO: actual data
				'jname' => $form['vm_name'],
				'vm_ram' => $form['vm_ram'],
				'vm_cpus' => $form['vm_cpus'],
				'vm_os_type' => $form['vm_os_type'],
				'jstatus' => $this->_locale->translate('Cloning'),
				'icon' => 'spin6 animate-spin',
				'desktop' => ' s-on',
				'maintenance' => ' maintenance',
				'protected' => 'icon-cancel',
				'protitle' => $this->_locale->translate('Delete'),
				'vnc_title' => $this->_locale->translate('Open VNC'),
				'reboot_title' => $this->_locale->translate('Restart VM')
			];

			foreach($vars as $var=>$val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return [
			'errorMessage' => $err,
			'vm_name' => $form['vm_name'],
			'jail_id' => $form['vm_name'],
			'taskId' => $taskId,
			'mode' => $this->mode,
			'html' => $html
		];
	}

	function getBhyveInfo($jname)
	{
		$statuses = ['Not Launched','Launched','unknown-1','Maintenance','unknown-3','unknown-4','unknown-5','unknown-6'];
		$html = '';
		$db = new Db('base','local');
		if($db->isConnected()){
			$bhyve = $db->selectOne("SELECT jname,vm_ram,vm_cpus,vm_os_type,hidden FROM bhyve WHERE jname=?", array([$jname]));
			$hres = $this->getTableChunk('bhyveslist','tbody');
			if($hres !== false){
				$html_tpl = $hres[1];
				$status = $this->check_vmonline($bhyve['jname']);
				$vars = [
					'jname' => $bhyve['jname'],
					'nth-num' => 'nth0',
					'desktop' => '',
					'maintenance' => '',
					'node' => 'local',
					'vm_name' => '',
					'vm_ram' => $this->fileSizeConvert($bhyve['vm_ram']),
					'vm_cpus' => $bhyve['vm_cpus'],
					'vm_os_type' => $bhyve['vm_os_type'],
					'vm_status' => $this->_locale->translate($statuses[$status]),
					'desktop' => ($status == 0) ? ' s-off' : ' s-on',
					'icon' => ($status == 0) ? 'play' : 'stop',
					'protected' => 'icon-cancel',
					'protitle' => ' title="'.$this->_locale->translate('Delete').'"',
					'vnc_title' => $this->_locale->translate('Open VNC'),
					'reboot_title' => $this->_locale->translate('Restart bhyve')
				];

				foreach($vars as $var => $val){
					$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
				}
				$html .= $html_tpl;
			}
		}

		$html = preg_replace('#<tr[^>]*>#','', $html);
		$html = str_replace(['</tr>',"\n","\r","\t"], '', $html);

		return ['html' => $html];
	}

	function ccmd_bhyveEditVars()
	{
		$res = [];
		$form = $this->_vars['form_data'];
		if(!isset($form['jail_id'])) return ['error' => true, 'error_message' => 'Bad jail id!'];

		$err = false;
		$db = new Db('base','local');
		if($db->isConnected())	{
			$query = "SELECT b.jname as vm_name,vm_cpus,vm_ram,vm_vnc_port,bhyve_vnc_tcp_bind,interface FROM bhyve AS b INNER JOIN jails AS j ON b.jname=j.jname AND b.jname=?;";
			$res['vars'] = $db->selectOne($query, array([$form['jail_id']]));
			$res['vars']['vm_ram'] = $this->fileSizeConvert($res['vars']['vm_ram'], 1024, false, true);
		} else {
			$err = true;
		}
		(empty($res['vars'])) AND $err = true;

		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.');
			$res['jail_id'] = $form['jail_id'];
			$res['reload'] = true;
			return $res;
		}

		$res['vars']['vm_vnc_password'] = '-nochange-';
		$res['error'] = false;
		$res['dialog'] = $form['dialog'];
		$res['jail_id'] = $form['jail_id'];
		$res['iso_list'] = $this->ccmd_updateBhyveISO($form['jail_id']);
		return $res;
	}

	function ccmd_bhyveRename()
	{
		$form = $this->_vars['form_data'];
		$res = CBSD::run(
			"task owner=%s mode=new /usr/local/bin/cbsd brename old=%s new=%s restart=1",
			[$this->_user_info['username'], $form['oldJail'], $form['jname']]
		);

		$err = 'Virtual Machine is not renamed!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Virtual Machine was renamed!';
			$taskId = $res['message'];
		} else {
			$err = $res['error'];
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $form['jname'],
			'taskId' => $taskId,
			'mode' => $this->mode
		];
	}

	function ccmd_bhyveRenameVars()
	{
		$res = [];
		$form = $this->_vars['form_data'];
		if(!isset($form['jail_id'])) return ['error' => true, 'error_message' => 'Bad jail id!'];

		$jname = $form['jail_id'];
		$err = false;
		$db = new Db('base','local');
		if($db->isConnected()){
			$query = "SELECT jname,vm_ram,vm_cpus,vm_os_type,hidden FROM bhyve WHERE jname=?"; //ip4_addr
			$res['vars'] = $db->selectOne($query, array([$jname]));
		} else {
			$err = true;
		}

		(empty($res['vars'])) AND $err = true;

		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.'); // XSS 
			$res['jail_id'] = $form['jail_id']; // TODO Possible XSS
//			$res['reload']=true;
			return $res;
		}

		$res['error'] = false;
		$res['dialog'] = $form['dialog']; // Possible XSS
		$res['jail_id'] = $form['jail_id'];
		return $res;
	}

	function ccmd_bhyveEdit()
	{
		$form = $this->form;
		$str = [];
		$jname = $form['jname'];

		$ram = $form['vm_ram'];
		$ram_tmp = $ram;
		$ram = str_replace(' ', '', $ram);
		$ram = str_ireplace('mb', 'm', $ram);
		$ram = str_ireplace('gb', 'g', $ram);
		$form['vm_ram'] = $ram;

		$arr = ['vm_cpus','vm_ram','bhyve_vnc_tcp_bind','vm_vnc_port','interface'];
		if($form['vm_vnc_password'] != '-nochange-') $arr[] = 'vm_vnc_password';
		foreach($arr as $a){
			if(isset($form[$a])){
				$val = $form[$a];
				if($val == 'on') $val = 1;
				$str[] = $a.'='.$val;
			} else {
				$str[] = $a.'=0';
			}
		}

		$form['vm_ram'] = $ram_tmp;

		/* check mounted ISO */
		$db = new Db('base','storage_media');
		if(!$db->isConnected()) return false; // TODO: Fix return

		$res = $db->selectOne('SELECT * FROM media WHERE jname=? AND type="iso"', array([$jname]));
		if($res !== false && !empty($res)){
			CBSD::run(
				'cbsd media mode=unregister name="%s" path="%s" jname=%s type=%s',
				[$res['name'], $res['path'], $jname, $res['type']]
			);
			$res = $db->selectOne(
				'SELECT * FROM media WHERE idx=?',
				array([(int)$form['vm_iso_image']])
			);
			if($res !== false && !empty($res) && $form['vm_iso_image'] != -2){
				CBSD::run(
					'cbsd media mode=register name="%s" path="%s" jname=%s type=%s',
					[$res['name'], $res['path'], $jname, $res['type']]
				);
			}
		}

		/* end check */
		$res = CBSD::run('bset jname=%s %s', [$jname, join(' ', $str)]);
		$res['mode'] = 'bhyveEdit';
		$res['form'] = $form;
		return $res;
	}

	function ccmd_bhyveAdd()
	{
		$form = $this->form;
		$os_types = $this->config->os_types;
		$sel_os = $form['vm_os_profile'];
		list($os_num, $item_num) = explode('.',$sel_os);
		if(!isset($os_types[$os_num])) return ['error' => true, 'errorMessage' => 'Error in list of OS types!'];
		$os_name = $os_types[$os_num]['os'];
		$os_items = $os_types[$os_num]['items'][$item_num];

		$err = [];
		$arr = [
			'workdir' => $this->workdir,
			'jname' => $form['vm_name'],
			'host_hostname' => '',
			'ip4_addr' => '',
			'arch' => 'native',
			'ver' => 'native',
			'astart' => 0,
			'interface' => $form['interface'],
			'vm_size' => $form['vm_size'],
			'vm_cpus' => $form['vm_cpus'],
			'vm_ram' => $form['vm_ram'],
			'vm_os_type' => $os_items['type'],
			'vm_efi' => 'uefi',
			'vm_os_profile' => $os_items['profile'],
			'vm_guestfs' => '',
			'bhyve_vnc_tcp_bind' => $form['bhyve_vnc_tcp_bind'],
			'vm_vnc_port' => $form['vm_vnc_port'],
			'vm_vnc_password' => $form['vm_vnc_password']
		];

		$iso = true;
		$res = ['name' => '', 'path' => '', 'iso_var_block' => ''];
		$crlf = "\r\n";
		$iso_var_block = 'iso_extract=""'.$crlf.'iso_img_dist=""'.$crlf.'iso_img=""'.$crlf.'iso_site=""';
		$iso_id = $form['vm_iso_image'];
		if(!empty($iso_id)){
			$iso_id = (int)$iso_id;
			if($iso_id > 0){
				$db = new Db('base','storage_media');
				if(!$db->isConnected()) return false; // TODO: return error
				$res = $db->selectOne('SELECT name,path FROM media WHERE idx= ?', array([$iso_id])); // OK, $iso_id is casted as int above.
				if($res === false || empty($res)) $iso = false;
			}
			
			if($iso_id == -1) $iso = false;
			
			if($iso){
				$arr['register_iso_as']='register_iso_as="'.$res['name'].'"';
				$arr['register_iso_name']='register_iso_name="'.$res['path'].'"';
				if($iso_id != -2) $arr['iso_var_block'] = $iso_var_block;
			}
		}

		/* create vm */
		$file_name = '/tmp/'.$arr['jname'].'.conf';

		$file = file_get_contents($this->realpath_public.'templates/vm.tpl');
		if(!empty($file)){
			foreach($arr as $var => $val){
				$file = str_replace('#'.$var.'#', $val,$file);
			}
		}
		//echo $file;exit;
		file_put_contents($file_name, $file);

		$res = CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bcreate inter=0 jconf=%s',
			[$this->_user_info['username'], $file_name]
		);

		$err = 'Virtual Machine is not created!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Virtual Machine was created!';
			$taskId = $res['message'];
		}
		// local - change to the real server on which the jail is created!
		$jid = $arr['jname'];

		$vm_ram = str_replace('g', ' GB', $form['vm_ram']);

		$html = '';
		$hres = $this->getTableChunk('bhyveslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'nth-num' => 'nth0',// TODO: actual data
				'node' => 'local',	// TODO: actual data
				'jname' => $arr['jname'],
				'vm_status' => $this->_locale->translate('Creating'),
				'vm_cpus' => $form['vm_cpus'],
				'vm_ram' => $vm_ram,
				'vm_os_type' => $os_items['type'],	//$os_name,
				'vnc_port' => '',
				'vnc_port_status' => '',
				'icon' => 'spin6 animate-spin',
				'desktop'=>' s-off',
				'maintenance'=>' maintenance',
				'protected'=>'icon-cancel',
				'protitle'=>$this->_locale->translate('Delete'),
				'vnc_title'=>$this->_locale->translate('Open VNC'),
				'reboot_title'=>$this->_locale->translate('Restart VM')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $jid,
			'taskId' => $taskId,
			'html' => $html,
			'mode' => $this->mode
		];
	}

	function ccmd_bhyveObtain()
	{
		$form = $this->_vars['form_data'];
		$os_types = $this->config->os_types;
		$os_types_obtain = $this->config->os_types_obtain;
		$sel_os = $form['vm_os_profile'];
		list($os_num, $item_num) = explode('.', $sel_os);
		if(!isset($os_types[$os_num])) return ['error' => true, 'errorMessage' => 'Error in list of OS types!'];
		//$os_name = $os_types[$os_num]['os'];
		$os_items = $os_types[$os_num]['items'][$item_num];
		$os_type = $os_items['type'];

		// os select
		list($one, $two) = explode('.', $sel_os, 2);

		if(isset($os_types_obtain[$one])){
			if(isset($os_types_obtain[$one]['items'][$two])){
				$os_profile = $os_types_obtain[$one]['items'][$two]['profile'];
				$os_type = $os_types_obtain[$one]['items'][$two]['type'];
			}
		}

		$key_name = '/usr/home/olevole/.ssh/authorized_keys';
		if(!isset($form['vm_authkey'])) $form['vm_authkey'] = 0;
		$key_id = (int)$form['vm_authkey'];

		$db = new Db('base','authkey');
		if(!$db->isConnected()) return ['error' => true, 'errorMessage' => 'Database error!'];

		$nres = $db->selectOne('SELECT authkey FROM authkey WHERE idx=?', array([$key_id, PDO::PARAM_INT]));
		if($nres['authkey'] !== false){
			$authkey = $nres['authkey'];
		} else { 
			$authkey='';
		}

		$user_pw = (!empty($form['user_password'])) ? ' ci_user_pw_user='.$form['user_password'].' ' : '';

		$res = CBSD::run( // TODO: THIS SEEMS WRONG pw_user={$form['vm_password']} {$user_pw}vnc_password={$form['vnc_password']}";
			'task owner=%s mode=new {cbsd_loc} bcreate jname=%s 
			vm_os_profile="%s" imgsize=%s vm_cpus=%s vm_ram=%s vm_os_type=%s mask=%s 
			ip4_addr=%s ci_ip4_addr=%s ci_gw4=%s ci_user_pubkey="%s" ci_user_pw_user=%s %svnc_password=%s',
			[
				$this->_user_info['username'],
				$form['vm_name'],
				$os_profile,
				$form['vm_size'],
				$form['vm_cpus'],
				$form['vm_ram'],
				$os_type,
				$form['mask'],
				$form['ip4_addr'],
				$form['ip4_addr'],
				$form['gateway'],
				$authkey,
				$form['vm_password'],
				$user_pw,
				$form['vnc_password']
			]
		);

		$err = 'Virtual Machine is not created!';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Virtual Machine was created!';
			$taskId = $res['message'];
		}

		$vm_ram = str_replace('g', ' GB', $form['vm_ram']);

		$html = '';
		$hres = $this->getTableChunk('bhyveslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'nth-num' => 'nth0',				// TODO: actual data
				'node' => 'local',				// TODO: actual data
				'jname' => $form['vm_name'],
				'vm_status' => $this->_locale->translate('Creating'),
				'vm_cpus' => $form['vm_cpus'],
				'vm_ram' => $vm_ram,
				'vm_os_type' => $os_type,
				'icon' => 'spin6 animate-spin',
				'desktop' => ' s-off',
				'maintenance' => ' maintenance',
				'protected' => 'icon-cancel',
				'protitle' => $this->_locale->translate('Delete'),
				'vnc_title' => $this->_locale->translate('Open VNC'),
				'reboot_title' => $this->_locale->translate('Restart VM')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return [
			'errorMessage' => $err,
			'jail_id' => $form['vm_name'],
			'taskId' => $taskId,
			'html' => $html,
			'mode' => $this->mode
		];
	}

	function ccmd_bhyveStart()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bstart inter=0 jname=%s',
			[$this->_user_info['username'], $this->form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveStop()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bstop inter=0 jname=%s',
			[$this->_user_info['username'], $this->form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveRestart()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} brestart inter=0 jname=%s',
			[$this->_user_info['username'], $this->form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveRemove()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bremove inter=0 jname=%s',
			[$this->_user_info['username'], $this->form['jname']]
		);	// autoflush=2
	}

	function ccmd_authkeyAdd()
	{
		$db = new Db('base','authkey');
		if(!$db->isConnected()) return ['error' => 'Database error'];

		$res = $db->insert("INSERT INTO authkey (name,authkey) VALUES (?, ?)", array([$this->form['keyname']], [$this->form['keysrc']]));
		if($res['error']) return ['error' => $res];
		
		$html = '';
		$hres = $this->getTableChunk('authkeyslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'keyid' => $res['lastID'],
				'keyname' => $this->form['keyname'],
				'keysrc' => $this->form['keysrc'],
				'deltitle' => $this->_locale->translate('Delete')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return ['keyname' => $this->form['keyname'], 'html' => $html];
	}

	function ccmd_authkeyRemove()
	{
		$form = $this->_vars['form_data'];
		$db = new Db('base','authkey');
		if(!$db->isConnected()) return ['error' => true, 'res' => 'Database error'];

		$res = $db->update('DELETE FROM authkey WHERE idx=?', array([$form['auth_id']]));
		if($res === false) return ['error' => true,'res' => print_r($res,true)];

		return ['error' => false, 'auth_id' => $form['auth_id']];
	}

	function ccmd_vpnetAdd()
	{
		$form = $this->_vars['form_data'];
		$db = new Db('base','vpnet');
		if(!$db->isConnected()) return ['error' => 'Database error'];

		$res = $db->insert("INSERT INTO vpnet (name,vpnet) VALUES (?, ?)", [[$form['netname']], [$form['network']]]);
		if($res['error']) return ['error' => $res];

		$html = '';
		$hres = $this->getTableChunk('vpnetslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'netid' => $res['lastID'],
				'netname' => $form['netname'],
				'network' => $form['network'],
				'deltitle' => $this->_locale->translate('Delete')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return ['netname' => $form['netname'], 'html' => $html];
	}

	function ccmd_vpnetRemove()
	{
		$form = $this->_vars['form_data'];
		$db = new Db('base', 'vpnet');
		if(!$db->isConnected()) return ['error' => true, 'res' => 'Database error'];

		$res = $db->update('DELETE FROM vpnet WHERE idx=?', array([(int)$form['vpnet_id']]));
		if($res === false) return ['error' => true, 'res' => print_r($res, true)];

		return ['error' => false, 'vpnet_id' => $form['vpnet_id']];
	}

	function ccmd_mediaRemove()
	{
		$db = new Db('base', 'storage_media');
		if(!$db->isConnected()) return ['error' => true, 'res' => 'Database error'];

		$res = $db->selectOne('SELECT * FROM media WHERE idx=?', array([(int)$this->form['media_id'], PDO::PARAM_INT]));
		if($res === false || empty($res)) return ['error' => true, 'res'=> print_r($res, true)];

		$res = CBSD::run(
			'media mode=remove name="%s" path="%s" jname="%s" type="%s"', //.$res['name']
			[$res['name'], $res['path'], $res['jname'], $res['type']]
		);

		if($res['error']){
			$arr['error'] = true;
			$arr['error_message'] = 'File image was not deleted! '.$res['error_message'];
		} else {
			$arr['error'] = false;
		}

		$arr['media_id'] = $this->form['media_id'];
		$arr['cmd'] = $res;

		return $arr;
	}

	function ccmd_srcRemove()
	{
		$ver = str_replace('src', '', $this->formform['jname']);
		if(empty($ver)) return ['error' => true, 'errorMessage' => 'Version of sources is emtpy!'];
		return CBSD::run(
			'task owner='.$username.' mode=new {cbsd_loc} removesrc inter=0 ver=%s jname=#src%s',
			[$this->_user_info['username'], $ver, $ver]
		);
	}

	function ccmd_srcUpdate()
	{
		$ver = str_replace('src', '', $this->form['jname']);
		$stable = (preg_match('#\.\d#', $ver)) ? 0 : 1;
		if(empty($ver)) return ['error' => true, 'errorMessage' => 'Version of sources is emtpy!'];
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} srcup stable=%s inter=0 ver=%s jname=#src%s',
			[$this->_user_info['username'], $stable, $ver, $ver]
		);
	}

	function getSrcInfo($id)
	{
		$id = str_replace('src', '', $id);
		$db = new Db('base', 'local');
		if(!$db->isConnected()) return ['error' => true, 'errorMessage' => 'Database error'];
		$res = $db->selectOne("SELECT idx,name,platform,ver,rev,date FROM bsdsrc WHERE ver=?", array([(int)$id, PDO::PARAM_INT]));

		$hres = $this->getTableChunk('srcslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$ver = $res['ver'];
			$vars = [
				'nth-num' => 'nth0',
				'maintenance' => ' busy',
				'node' => 'local',
				'ver' => $res['ver'],
				'ver1' => strlen(intval($res['ver'])) < strlen($res['ver']) ? 'release' : 'stable',
				'rev' => $res['rev'],
				'date' => $res['date'],
				'protitle' => $this->_locale->translate('Update'),
				'protitle' => $this->_locale->translate('Delete')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		$html = preg_replace('#<tr[^>]*>#', '', $html);
		$html = str_replace(['</tr>',"\n","\r","\t"], '', $html);

		return ['html' => $html,'arr' => $res];
	}

	function ccmd_baseRemove()
	{
		$id = $this->form['jname'];
		preg_match('#base([0-9\.]+)-([^-]+)-(\d+)#', $id, $res);
		$ver = $res[1];
		$arch = $res[2];
		$stable = $res[3];

		return $this->CBSD::run(
			'task owner=%s mode=new {cbsd_loc} removebase inter=0 stable=%s ver=%s arch=%s jname=#%s',
			[$this->_user_info['username'], $stable, $ver, $arch, $this->form['jname']]
		);
	}

	function ccmd_basesCompile()
	{
		$form = $this->form;
		if(!isset($form['sources']) || !is_numeric($form['sources'])) return ['error' => true, 'errorMessage' => 'Wrong OS type selected!'];
		$id = (int)$form['sources'];

		$db = new Db('base','local');
		if(!$db->isConnected()) return ['error'=>true,'errorMessage'=>'Database connect error!'];

		$base = $db->selectOne("SELECT idx,platform,ver FROM bsdsrc WHERE idx=?", array([$id, PDO::PARAM_INT])); // Casted above as 
		$ver = $base['ver'];
		$stable_arr = ['release','stable'];
		$stable_num = strlen(intval($ver)) < strlen($ver) ? 0 : 1;
		$stable = $stable_arr[$stable_num];
		$bid = $ver.'-amd64-'.$stable_num;	// !!! КОСТЫЛЬ

		$res = $this->fillRepoTr($id);
		$html = $res['html'];
		$res = $res['arr'];

		$res = CBSD::run(
			'task owner=%s mode=new {cbsd_loc} world inter=0 stable=%s ver=%s jname=#base%s',
			[$this->_user_info['username'], $res['stable'], $ver, $bid]
		);

		$err = '';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'World compile start!';
			$taskId = $res['message'];
		}

		return [
			'errorMessage' => '',
			'jail_id' => 'base'.$bid,
			'taskId' => $taskId,
			'html' => $html,
			'mode' => $this->mode,
			'txt_status' => $this->_locale->translate('Compiling')
		];
	}

	function fillRepoTr($id, $only_td = false, $bsdsrc = true)
	{
		$html = '';

		$db = new Db('base', 'local');
		if($db->isConnected()){
			if($bsdsrc){
				$res = $db->selectOne("SELECT idx,platform,ver FROM bsdsrc WHERE idx=?", array([(int)$id, PDO::PARAM_INT]));
				$res['name'] = '—';
				$res['arch'] = '—';
				$res['targetarch'] = '—';
				$res['stable'] = strlen(intval($res['ver'])) < strlen($res['ver']) ? 0 : 1;
				$res['elf'] = '—';
				$res['date'] = '—';
			} else {
				$res = $db->selectOne("SELECT idx,platform,name,arch,targetarch,ver,stable,elf,date FROM bsdbase WHERE ver=?", array([(int)$id, PDO::PARAM_INT]));
			}
			$hres = $this->getTableChunk('baseslist', 'tbody');
			if($hres !== false){
				$html_tpl = $hres[1];
				$ver = $res['ver'];
				$vars = [
					'bid' => $res['idx'],
					'nth-num' => 'nth0',
					'node' => 'local',
					'ver' => $res['ver'],
					'name' => 'base',
					'platform' => $res['platform'],
					'arch' => $res['arch'],
					'targetarch' => $res['targetarch'],
					'stable' => ($res['stable'] == 0) ? 'release' : 'stable',
					'elf' => $res['elf'],
					'date' => $res['date'],
					'maintenance' => ' busy',
					'protitle' => $this->_locale->translate('Delete')
				];

				foreach($vars as $var => $val){
					$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
				}
				$html = $html_tpl;
			}
		}

		if($only_td){
			$html = preg_replace('#<tr[^>]*>#','',$html);
			$html = str_replace(['</tr>',"\n","\r","\t"],'',$html);
		}

		return ['html' => $html, 'arr' => $res];
	}

	function ccmd_repoCompile()
	{
		if(!isset($this->form['version']) || !is_numeric($this->form['version'])) {
			return ['error' => true, 'errorMessage' => 'Wrong OS type input!'];
		}

		$ver = $this->form['version'];
		$stable_arr = ['release','stable'];
		$html = '';
		$hres = $this->getTableChunk('baseslist', 'tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			# TODO: This next line is weird
			$stable_num = strlen(intval($ver)) < strlen($ver) ? 0 : 1;	//'release':'stable';
			$stable = $stable_arr[$stable_num];

			$bid = $ver.'-amd64-'.$stable_num;	// !!! КОСТЫЛЬ

			$vars = [
				'nth-num' => 'nth0',
				'bid' => $bid,
				'node' => 'local',
				'ver' => $ver,
				'name' => 'base',
				'platform' => '—',
				'arch' => '—',
				'targetarch' => '—',
				'stable' => $stable,
				'elf' => '—',
				'date' => '—',
				'maintenance' => ' busy',
				'protitle' => $this->_locale->translate('Delete')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		$res = CBSD::run(
			'task owner=%s mode=new {cbsd_loc} repo action=get sources=base inter=0 stable=%s ver=%s jname=#base%s',
			[$this->_user_info['username'], $stable_num, $ver, $bid]
		);

		$err = '';
		$taskId = -1;
		if($res['retval'] == 0){
			$err = 'Repo download start!';
			$taskId = $res['message'];
		}

		return [
			'errorMessage'=>'',
			'jail_id' => 'base'.$bid,
			'taskId' => $taskId,
			'html' => $html,
			'mode' => $this->mode,
			'txt_status' => $this->_locale->translate('Fetching')
		];
	}

	function ccmd_logLoad()
	{
		$log_id = $this->_vars['form_data']['log_id'];
		if(!is_numeric($log_id)) return ['error' => 'Log ID must be a number'];

		$html = '';
		$buf = '';
		$log_file = '/tmp/taskd.'.$log_id.'.log';
		if(file_exists($log_file)){
			$filesize = filesize($log_file);
			if($filesize <= 204800){
				$buf = file_get_contents($log_file);
			} else {
				$fp = fopen($log_file,'r');
				if($fp) {
					fseek($fp, -1000, SEEK_END);
					$buf = fread($fp, 1000);
					$html = '<strong>Last 1000 Bytes of big file data:</strong><hr />';
				}
				fclose($fp);
			}
			$buf = htmlentities(trim($buf));
			$arr = preg_split('#\n#iSu', trim($buf));
			if ($arr != false){
				foreach($arr as $txt){
					$html.='<p class="log-p">'.$txt.'</p>';
				}
			}
			return ['html'=>'<div style="font-weight:bold;">Log ID: '.$log_id.'</div><br />'.$html];
		}

		return ['error'=>'Log file is not exists!'];
	}

	function ccmd_logFlush()
	{
		return CBSD::run('task mode=flushall', []);
	}

	function getBasesCompileList()
	{
		$db1 = new Db('base', 'local');
		$list = [];
		if($db1 !== false){
			$bases = $db1->select("SELECT idx,platform,ver FROM bsdsrc order by cast(ver AS int)", []);

			foreach($bases as $base){
				$val = $base['idx'];
				$stable = strlen(intval($base['ver'])) < strlen($base['ver']) ? 'release' : 'stable';
				$name = $base['platform'].' '.$base['ver'].' '.$stable;
				$list[$val] = $name;
			}
		}
		return $list;
	}

	function helpersAdd($mode)
	{
		if($this->uri_chunks[0] != 'jailscontainers' || empty($this->uri_chunks[1])) return ['error' => true, 'errorMessage' => 'Bad url!'];
		$jail_id = $this->uri_chunks[1];
		$helpers = array_keys($this->form);
		foreach($helpers as $helper){
			$res = CBSD::run(
				'task owner=%s mode=new {cbsd_loc} forms inter=0 module=%s jname=%s',
				[$this->_user_info['username'], $helper, $jail_id]
			);
		}
		return ['error' => false];
	}

	function ccmd_addJailHelperGroup()
	{
		if($this->uri_chunks[0] != 'jailscontainers' || empty($this->uri_chunks[1]) || empty($this->url_hash)){
			return ['error' => true, 'errorMessage' => 'Bad url!'];
		}
		$jail_id = $this->uri_chunks[1];
		$helper = $this->url_hash;

		$db = new Db('helper', ['jname' => $jail_id, 'helper' => $helper]);
		if(!$db->isConnected()) return ['error' => true, 'errorMessage'=> 'No database file!'];

		$db_path = $db->getFileName();

		$res = CBSD::run(
			'forms inter=0 module=%s formfile=%s group=add',
			[$helper, $db_path]
		);

		$html = (new Forms('', $helper, $db_path))->generate();

		return ['html' => $html];
	}

	function addHelperGroup($mode)
	{
		$module = $this->url_hash;
		if(isset($this->form)){
			$form = $this->form;
		} else { 
			$form = [];
		}

		if(isset($form['db_path']) && !empty($form['db_path']))	{
			$db_path = $form['db_path'];
			if(!file_exists($db_path)){
				$res = CBSD::run('make_tmp_helper module=%s', [$module]);
				if($res['retval'] == 0){
					$db_path = $res['message'];
				} else {
					return ['error' => true, 'errorMessage' => 'Error on open temporary form file!'];
				}
			}
		} else{
			$res = CBSD::run('make_tmp_helper module=%s', [$module]);
			if($res['retval'] == 0) $db_path = $res['message'];
		}
		CBSD::run('forms inter=0 module=%s formfile=%s group=add', [$module, $db_path]);
		$html = (new Forms('', $module, $db_path))->generate();

		return ['db_path' => $db_path, 'html' => $html];
	}

	function deleteHelperGroup($mode)
	{
		$module = $this->url_hash;
		if(isset($this->form)){
			$form = $this->form;
		} else {
			$form = [];
		}
		if(!isset($form['db_path']) || empty($form['db_path'])) return;

		if(!file_exists($form['db_path'])) return ['error'=>true, 'errorMessage' => 'Error on open temporary form file!'];

		$index = $form['index'];
		$index = str_replace('ind-', '', $index);

		$db_path = $form['db_path'];
		$res = CBSD::run(
			'forms inter=0 module=%s formfile=%s group=del index=%s',
			[$module, $db_path, $index]
		);
		$html = (new Forms('', $module, $db_path))->generate();

		return ['db_path' => $db_path,'html' => $html];
	}

	function ccmd_deleteJailHelperGroup()
	{
		if(!isset($this->uri_chunks[1]) || !isset($this->url_hash)){
			return ['error' => true,'errorMessage' => 'Bad url!'];
		}

		$jail_id = $this->uri_chunks[1];
		$helper = $this->url_hash;
		$index = str_replace('ind-', '', $this->form['index']);

		$db = new Db('helper', ['jname' => $jail_id, 'helper' => $helper]);
		if($db->error) return ['error'=> true, 'errorMessage' => 'No helper database!'];

		$db_path = $db->getFileName();
		$res = CBSD::run(
			'forms inter=0 module=%s formfile=%s group=del index=%s',
			[$helper, $db_path, $index]
		);
		$html = (new Forms('', $helper, $db_path))->generate();
		return ['html'=>$html];
	}

	function ccmd_getFreeJname($in_helper = false, $type = 'jail')
	{
		$arr = [];

		/* TODO: CHECK THE ORIGINAL CODE
			$add_cmd=($in_helper)?' default_jailname='.$this->url_hash:'';
			$add_cmd1=' default_jailname='.$type;
			$res=$this->cbsd_cmd("freejname".$add_cmd.$add_cmd1);
		*/
		if ($in_helper) {
			$res = CBSD::run('freejname default_jailname=%s default_jailname=%s', [$this->url_hash, $type]);
		} else {
			$res = CBSD::run('freejname default_jailname=%s', [$type]);
		}

		if($res['error']){
			$arr['error'] = true;
			$arr['error_message'] = $err['error_message'];
		} else {
			$arr['error'] = false;
			$arr['freejname'] = $res['message'];
		}
		return $arr;
	}

	function ccmd_getFreeCname()
	{
		$arr = [];
		$res = $this->CBSD::run("freejname default_jailname=kube", []);
		if($res['error']){
			$arr['error'] = true;
			$arr['error_message'] = $err['error_message'];
		} else {
			$arr['error'] = false;
			$arr['freejname'] = $res['message'];
		}
		return $arr;
	}

	function ccmd_k8sCreate()
	{
		$form = $this->form;
		$res = [];
		$ass_arr = [
			'master_nodes' => 'init_masters',
			'worker_nodes' => 'init_workers',
			'master_ram' => 'master_vm_ram',
			'master_cpus' => 'master_vm_cpus',
			'master_img' => 'master_vm_imgsize',
			'worker_ram' => 'worker_vm_ram',
			'worker_cpus' => 'worker_vm_cpus',
			'worker_img' => 'worker_vm_imgsize'
		];

		$add_param = [
			'master_ram' => 'g',
			'master_img' => 'g',
			'worker_ram' => 'g',
			'worker_img '=> 'g'
		];

		foreach($form as $key => $value){
			if(isset($ass_arr[$key])){
				if(isset($add_param[$key])){
					$value .= $add_param[$key];
				}
				$res[$ass_arr[$key]] = $value;
			}
		}

		$res['pv_enable'] = "0";
		if(isset($form['pv_enable'])){
			if($form['pv_enable'] == 'on') $res['pv_enable'] = "1";
		}

		$res['kubelet_master'] = "0";
		if(isset($form['kubelet_master'])){
			if($form['kubelet_master'] == 'on') $res['kubelet_master'] = "1";
		}

		$cname = $form['cname'];

		$url = 'http://144.76.225.238/api/v1/create/'.$cname;
		$result = $this->postCurl($url, $res);

		return $result;
	}

	function ccmd_k8sRemove()
	{
		$form = $this->form;
		if(isset($form['k8sname']) && !empty($form['k8sname'])){
			$url = 'http://144.76.225.238/api/v1/destroy/'.$form['k8sname'];
			return ($this->getCurl($url));
		} else {
			return ['error' => 'true', 'errorMessage' => 'something wrong...'];
		}
	}

	function postCurl($url, $vars = false)
	{
		if($vars === false) return ['error' => true, 'errorMessage' => 'something wrong...'];

		$txt_vars = json_encode($vars);

		$ch = curl_init($url);
//		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $txt_vars);
		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	function getCurl($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, false);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	function GhzConvert($Hz = 0)
	{
		$h = 1;
		$l = 'Mhz';
		if($Hz > 1000){
			$h = 1000;
			$l = 'Ghz';
		}

		return round($Hz/$h,2).' '.$l;
	}

	function fileSizeConvert(int $bytes, $bytes_in_mb = 1024, $round = false, $small = false)
	{
		$arBytes = [
			0 => ["tb", pow($bytes_in_mb, 4)],
			1 => ["gb", pow($bytes_in_mb, 3)],
			2 => ["mb", pow($bytes_in_mb, 2)],
			3 => ["kb", $bytes_in_mb],
			4 => ["b", 1]
		];

		$result = '0 MB';
		foreach($arBytes as $arItem){
			if($bytes >= $arItem[1]){
				$result = $bytes / $arItem[1];
				if($round) $result = round($result);
				$result = str_replace(".", "," , strval(round($result, 2))).($small ? strtolower(substr($arItem[0], 0, 1)) : " ".strtoupper($arItem[0]));
				break;
			}
		}
		return $result;
	}

	function colorizeCmd($cmd_string)
	{
		$arr = $this->_cmd_array;
		foreach($arr as $item){
			$cmd_string = str_replace($item,'<span class="cbsd-cmd">'.$item.'</span>', $cmd_string);
		}

		$cmd_string = preg_replace('#(\/.+/cbsd)#','<span class="cbsd-lnch">$1</span>', $cmd_string);

		return '<span class="cbsd-str">'.$cmd_string.'</span>';
	}

	function media_iso_list()
	{
		$db = new Db('base','storage_media');
		$res = $db->select('select * from media where type="iso"', []);
		if($res === false || empty($res)) return;

		$list = [];
		foreach($res as $r){
			$list[] = [$r['idx'], $r['name'], $r['type']];
		}
		return $list;
	}

	function ccmd_updateBhyveISO($iso = '')
	{
		$db = new Db('base', 'storage_media');
		$res = $db->select('SELECT * FROM media WHERE type="iso"', []);
		if($res === false || empty($res)) return [];

		$sel = '';
		$html = '<option value="-2"></option><option value="-1"#sel#>Profile default ISO</option>';
		foreach($res as $r){
			$sel1 = '';
			if(empty($sel) && $iso == $r['jname']) $sel1 = '#sel1#';
			$html .= '<option value="'.$r['idx'].'"'.$sel1.'>'.$r['name'].'.'.$r['type'].'</option>';
		}

		if(strpos($html,'#sel1#') !== false){
			$html = str_replace('#sel1#', ' selected="selected"', $html);
			$html = str_replace('#sel#', '', $html);
		}else{
			$html = str_replace('#sel1#', '', $html);
			$html = str_replace('#sel#', ' selected="selected"', $html);
		}

		return $html;
	}

	function get_interfaces()
	{
		return $this->config->os_interface_names;
	}

	function ccmd_vmTemplateAdd()
	{
		$db = new Db('base', 'local');
		if(!$db->isConnected()) return $this->messageError('data incorrect!');
		$owner = $this->_user_info['username'];
		$query = "INSERT INTO vmpackages (name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus,owner,timestamp)
			VALUES (?,?,?,?,?,?,datetime('now','localtime'))";

		$res = $db->insert($query, [
			[$this->form['name']],
			[$this->form['description']],
			[$this->form['pkg_vm_ram']],
			[$this->form['pkg_vm_disk']],
			[$this->form['pkg_vm_cpus']],
			[$owner]
		]);

		if($res['error'] == false){
			return $this->messageSuccess($res); 
		} else {
			return $this->messageError('sql error!', $res);
		}
	}

	function ccmd_vmTemplateEditInfo()
	{
		if(!isset($this->form['template_id'])) return $this->messageError('incorrect data!');

		$tpl_id = (int)$this->form['template_id'];
		$db = new Db('base', 'local');
		if(!$db->isConnected()) return $this->messageError('DB connection error!');

		$res = $db->selectOne(
			"select name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus from vmpackages where id=?",
			array([$tpl_id, PDO::PARAM_INT])
		);
		return $this->messageSuccess(['vars' => $res, 'template_id' => $tpl_id]);
	}

	function ccmd_vmTemplateEdit()
	{
		$id = $this->form['template_id'];
		if(!isset($id) || $id < 1) $this->messageError('wrong data!');
		$db = new Db('base','local');
		if(!$db->isConnected()) return $this->messageError('db connection error!');

		$query = "update vmpackages set
			name=?,description=?, pkg_vm_ram=?,pkg_vm_disk=?, pkg_vm_cpus=?, owner=?, timestamp=datetime('now','localtime')
			where id=?";

		$res = $db->update($query, [
			[$this->form['name'], PDO::PARAM_STR],
			[$this->form['description'], PDO::PARAM_STR],
			[$this->form['pkg_vm_ram'],  PDO::PARAM_STR],
			[$this->form['pkg_vm_disk'], PDO::PARAM_STR],
			[$this->form['pkg_vm_cpus'], PDO::PARAM_STR],
			[$this->_user_info['username'], PDO::PARAM_STR],
			[(int)$id, PDO::PARAM_INT]
		]);
		if($res !== false) return $this->messageSuccess($res);

		return $this->messageError('sql error!');
	}

	function ccmd_vmTemplateRemove()
	{
		$id = $this->form['template_id'];
		if(!is_numeric($id) || (int)$id <= 0) return $this->messageError('wrong data!');

		$db = new Db('base','local');
		if(!$db->isConnected()) return $this->messageError('DB connection error!');

		$res = $db->select("DELETE FROM vmpackages WHERE id=?", array([$id, PDO::PARAM_INT]));
		return $this->messageSuccess($res);
	}

	function messageError($message,$vars=[])
	{
		return array_merge(['error' => true, 'error_message' => $message], $vars);
	}

	function messageSuccess($vars=[])
	{
		return array_merge(['error' => false], $vars);
	}

	function getImportedImages()
	{
		$images = [];
		$path = $this->media_import;
		$files = $this->getImagesList($path);
		foreach($files as $key => $file){
			if(file_exists($file['fullname'])){
				$fp = fopen($file['fullname'], 'r');
				$buf = fread($fp, 300);
				fclose($fp);

				$res = $this->getImageVar('emulator', $buf);
				$res1 = $this->getImageVar('jname', $buf);
				if(isset($res)) $files[$key]['type'] = $res;
				if(isset($res1)) $files[$key]['jname'] = $res1;
			}
		}
		return $files;
	}

	function ccmd_getImportedImageInfo()
	{
		return $this->getImageInfo($this->form['id']);
	}

	function getImagesList($path)
	{
		$files = [];
		foreach (glob($path."*.img") as $filename){
			$files[] = [
				'name' => pathinfo($filename)['basename'],
				'fullname' => $filename
			];
		}
		return $files;
	}

	function getImageInfo($imgname)
	{
		if(empty($imgname)) return false;

		$file = $this->media_import.$imgname;
		if(!file_exists($file)) return false;

		$fp = fopen($file,'r');
		$buf = fread($fp,300);
		fclose($fp);

		$type = $this->getImageVar('emulator', $buf);
		$jname = $this->getImageVar('jname', $buf);
		$orig_jname = $jname;
		$ip = $this->getImageVar('ip4_addr', $buf);
		$hostname = $this->getImageVar('host_hostname', $buf);

		$name_comment = '';
		$db = new Db('base','local');
		if($db->isConnected()){
			$jail = $db->selectOne("SELECT jname FROM jails WHERE jname=?", array([$jname]));

			if($jname == $jail['jname']){
				$jres = $this->ccmd_getFreeJname(false, $type);
				if($jres['error']) return $this->messageError('Something wrong...');
				$jname = $jres['freejname'];
				$name_comment = '* '.$this->_locale->translate('Since imported name already exist, we are change it');
			}
		}

		return [
			'orig_jname' => $orig_jname,
			'jname' => $jname,
			'host_hostname' => $hostname,
			'ip4_addr' => $ip,
			'file_id' => $imgname,
			'type' => $type,
			'name_comment' => $name_comment
		];
	}

	function getImageVar($name, $buf)
	{
		$val = false;
		$pat = '#'.$name.'="([^\"]*)"#';
		preg_match($pat, $buf, $res);
		if(!empty($res)) $val = $res[1];
		return $val;
	}

	function ccmd_imageExport()
	{
		// cbsd jexport jname=XXX dstdir=<path_to_imported_dir>
		$jname = $this->form['id'];
		if(empty($jname)) $this->messageError('Jname is incorrect in export command! Is «'.$jname.'».');

		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jexport gensize=1 jname=%s dstdir=%s',
			[$this->_user_info['username'], $jname, $this->media_import]
		);
	}

	function ccmd_imageImport()
	{
		$file_id = $this->form['file_id'];
		$jname = $this->form['jname'];
		$res = $this->getImageInfo($file_id);
		if($res === false) return $this->messageError('File not found!');

		$cmd = 'task owner=%s mode=new {cbsd_loc} jimport ';
		$attrs = [$this->_user_info['username']];

		if($jname != $res['orig_jname']) {
			$cmd .= 'new_jname=%s ';
			$attrs[] = $jname;
		}

		if($this->form['ip4_addr'] != $res['ip4_addr']){
			$cmd .= 'new_ip4_addr=%s ';
			$attrs[] = $this->form['ip4_addr'];
		}

		if($this->form['host_hostname'] != $res['host_hostname']) {
			$cmd .= 'new_host_hostname=%s ';
			$attrs[] = $this->form['host_hostname'];
		}

		$cmd .= 'jname=%s';
		$attrs[] = $file;

		return CBSD::run($cmd, $attrs);
	}

	function ccmd_imageRemove()
	{
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} imgremove path=%s img=$s',
			[$this->_user_info['username'], $this->media_import, $this->form['jname']]
		);
	}

	function ccmd_getSummaryInfo()
	{
		if(!isset($this->form['mode'])) $this->form['mode'] = '';
		$jail_name = $this->form['jname'];
		$res = [];

		if(empty($jail_name)) return $res;

		$res['jname'] = $jail_name;

		$db = new Db('racct', ['jname' => $jail_name]);
		if($db->isConnected()){
			$query = $db->select(
				"SELECT ? as name,idx as time,memoryuse,pcpu,pmem,maxproc,openfiles,readbps, writebps,readiops,writeiops FROM racct ORDER BY idx DESC LIMIT 25;",
				array([$jail_name])
			);	// where idx%5=0
			$res['__all'] = $query;
		}

		if($this->form['mode'] == 'bhyveslist'){
			$res['properties'] = $this->getSummaryInfoBhyves();
			return $res;
		}

		//$workdir/jails-system/$jname/descr
		$filename = $this->workdir.'/jails-system/'.$jail_name.'/descr';
		if(file_exists($filename)) $res['description'] = nl2br(file_get_contents($filename));

		$sql = "SELECT host_hostname,ip4_addr,allow_mount,allow_nullfs,allow_fdescfs,interface,baserw,mount_ports,
			  astart,vnet,mount_fdescfs,allow_tmpfs,allow_zfs,protected,allow_reserved_ports,allow_raw_sockets,
			  allow_fusefs,allow_read_msgbuf,allow_vmm,allow_unprivileged_proc_debug
			  FROM jails WHERE jname=?";
		$db = new Db('base','local');
		if($db->isConnected()){
			$query = $db->selectOne($sql, array([$jail_name]));
			$html = '<table class="summary_table">';

			foreach($query as $q => $k){
				if(is_numeric($k) && ($k == 0 || $k == 1)){
					$k = ($k == 0) ? 'no' : 'yes';
				}
				$html.='<tr><td>'.$this->_locale->translate($q).'</td><td>'.$this->_locale->translate($k).'</td></tr>';
			}

			$html .= '</table>';
			$res['properties'] = $html;
		}

		return $res;
	}

	function getSummaryInfoBhyves()
	{
		$html='';

		/*
		$bool = [
			'created','astart','vm_cpus','vm_os_type','vm_boot','vm_os_profile','bhyve_flags',
			'vm_vnc_port','bhyve_vnc_tcp_bind','bhyve_vnc_resolution','ip4_addr','state_time',
			'cd_vnc_wait','protected','hidden','maintenance','media_auto_eject','jailed'
		];
		*/
		$bool = ['astart','hidden','jailed','cd_vnc_wait','protected','media_auto_eject'];
		$chck = [
			'bhyve_generate_acpi','bhyve_wire_memory','bhyve_rts_keeps_utc','bhyve_force_msi_irq',
			'bhyve_x2apic_mode','bhyve_mptable_gen','bhyve_ignore_msr_acc','xhci'
		];

		$db = new Db('bhyve', ['jname' => $this->form['jname']]);
		if($db->isConnected()) {
			$sql = "SELECT created, astart, vm_cpus, vm_ram, vm_os_type, vm_boot, vm_os_profile, bhyve_flags,
				vm_vnc_port, virtio_type, bhyve_vnc_tcp_bind, bhyve_vnc_resolution, cd_vnc_wait,
				protected, hidden, maintenance, ip4_addr, vnc_password, state_time,
				vm_hostbridge, vm_iso_path, vm_console, vm_efi, vm_rd_port, bhyve_generate_acpi,
				bhyve_wire_memory, bhyve_rts_keeps_utc, bhyve_force_msi_irq, bhyve_x2apic_mode,
				bhyve_mptable_gen, bhyve_ignore_msr_acc, bhyve_vnc_vgaconf text, media_auto_eject,
				vm_cpu_topology, debug_engine, xhci, cd_boot_firmware, jailed FROM settings";
			$query = $db->selectOne($sql, []);
			$html = '<table class="summary_table">';

			foreach($query as $q => $k){
				if(in_array($q, $bool)){
					$k = ($k == 0) ? 'no' : 'yes';
				}
				if(in_array($q, $chck)){
					$k = ($k==0) ? 'no' : 'yes';
				}

				if($q == 'vm_ram') $k = $this->fileSizeConvert($k);
				if($q == 'state_time') $k = date('d.m.Y H:i:s', $k);

				$html .= '<tr><td>'.$this->_locale->translate($q).'</td><td>'.$this->_locale->translate($k).'</td></tr>';
			}

			$html .= '</table>';
		}

		return $html;
	}

	function authkeys_list()
	{
		$db = new Db('base','authkey');
		$res = $db->select('SELECT idx,name FROM authkey;', array());

		$list = [];
		if(!empty($res))foreach($res as $item){
			$list[item['idx']] = $item['name'];
		}
		return $list;
	}

	function vm_packages_list()
	{
		$db = new Db('base','local');
		$res = $db->select('select id,name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus,owner from vmpackages order by name asc;', array());

		$html = [];
		$min = 0;
		$min_id = 0;
		foreach($res as $item){
			$cpu = $item['pkg_vm_cpus'];
			$ram = trim($item['pkg_vm_ram']);
			$ed = substr($ram, -1);
			if($ed == 'b'){
				$ed = substr($ram, -2, 1).'b';
				$ram = substr($ram, 0, -2);
			}
			if($ed == 'm' || $ed == 'g') $ed .= 'b';
			if($ed == 'mb'){
				$ram1 = substr($ram, 0, -1);
				$ram1 = $ram1/1000000;
			}
			if($ed == 'gb'){
				$ram1 = substr($ram, 0, -1);
				$ram1 = $ram1/1000;
			}
			$res1 = $cpu + $ram1;
			if($min>$res1 || $min==0) {
				$min = $res1;
				$min_id = $item['id'];
			}

			$html[] = [
				'name' => $item['name'],
				'text' => '(cpu: '.$cpu.'; ram: '.$ram.'; hdd: '.$item['pkg_vm_disk'].')',
				'id' => $item['id'],
				'description' => $item['description']
			];
		}
		return [$html, $min_id];
	}

	function os_types_create($obtain = 'new')
	{
		return ($obtain == 'obtain') ? $this->config->os_types_obtain : $this->config->os_types;
	}
}