<?php

require_once('localization.php');
require_once('forms.php');
require_once('utils.php');

class ClonOS 
{
	public $workdir = '';
	public $mode = null;
	public $environment = '';
	public $realpath = '';
	public $realpath_public = '';
	public $realpath_page = '';
	public $uri_chunks = [];
	public $table_templates = [];
	public $url_hash = '';
	public $media_import = '';
	public $username; # Comes from Auth
	private $form_validate;
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

	function __construct($uri_chunks = null)
	{
		$this->workdir = getenv('WORKDIR'); # // /usr/jails
		$this->environment = getenv('APPLICATION_ENV');

		$paths = Config::$paths;
		$this->realpath = $paths['realpath'];
		$this->realpath_public = $paths['public'];
		$this->media_import = $paths['media_import'];;

		if($this->environment == 'development'){
			$sentry_file = '../php/sentry.php';
			if(file_exists($sentry_file)) include($sentry_file);
		}

		if (is_null($uri_chunks)) {
			$this->uri_chunks = Utils::gen_uri_chunks(trim($_SERVER['REQUEST_URI'],'/'));
		} else {
			$this->uri_chunks = $uri_chunks;
		}

		$this->config = new Config();
		# TODO by the end of all this we shouldn't need locale here
		$this->_locale = new Localization($this->realpath_public);

		if(isset($_POST['path'])){ # TODO Do we need this or just json?
			$this->realpath_page = $this->realpath_public.'pages/'.$this->uri_chunks[0].'/';
		} else if($_SERVER['REQUEST_URI']){
			if(isset($this->uri_chunks[0])){
				$this->realpath_page = $this->realpath_public.'pages/'.$this->uri_chunks[0].'/';
			}
		}

		if (isset($_POST['hash'])){
			$this->url_hash == $_POST['hash'];
			Validate::short_string($this->url_hash);
			$this->url_hash = preg_replace('/^#/', '', $this->url_hash);
		}

		$form_data = (isset($_POST['form_data'])) ? $_POST['form_data'] : [];
		$this->form_validate = new Validate($form_data);
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
		$db = new Db('', '', $this->realpath."/var/db/nodes.sqlite"); # TODO: Why is this here?
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

	function _getTasksStatus()
	{
		$tasks = [];
		$validated = $this->form_validate->these(['jsonObj' => 4]);
		$obj = json_decode($validated['jsonObj'], true);

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
		$form = $this->form_validate->these([
			'oldJail' => 3,
			'jname' => 3,
			'host_hostname' => 3, # Todo check max hostname len
			'ip4_addr' => 3, # TODO Add IP validation
			'oldJail' => 3
		]);

		$res = CBSD::run("task owner=%s mode=new {cbsd_loc} jrename old=%s new=%s host_hostname=%s ip4_addr=%s restart=1", [
			$this->username,
			$form['oldJail'],
			$form['jname'], 
			$form['host_hostname'],
			$form['ip4_addr']
		]);

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
		$form = $this->form_validate->these([
			'oldJail' => 3,
			'jname' => 3,
			'host_hostname' => 3, # Todo check max hostname len
			'ip4_addr' => 3, # TODO Add IP validation
			'oldJail' => 3
		]);

		$cmd = 'task owner=%s mode=new {cbsd_loc} jclone checkstate=0 old=%s new=%s host_hostname=%s ip4_addr=%s';
		$args = [
			$this->username,
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
		if(!isset($this->uri_chunks[1]) || trim($this->url_hash) == '') return ['error' => true, 'errorMessage' => 'Bad url!'];
		$jail_name = $this->uri_chunks[1];

		$db = new Db('helper', ['jname' => $jail_name, 'helper' => $this->url_hash]);
		if(!$db->isConnected()) return ['error' => true, 'errorMessage' => 'No helper database!'];

		$form = $this->form_validate->all();
		foreach($form as $key => $val) {
			if($key != 'jname' && $key != 'ip4_addr') {
				$db->update("update forms set new=? where param=?", array([$val], [$key]));
			}
		}

		$res = CBSD::run('task owner=%s mode=new {cbsd_loc} forms module=%s jname=%s inter=0', [$this->username, $this->url_hash, $jail_name]);

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
		$form = $this->form_validate->all();

		$db_path = '';
		$with_img_helpers = '';
		if($this->mode == 'saveHelperValues'){
			if(trim($this->url_hash) == '' && $this->uri_chunks[0] == 'settings') return $this->saveSettingsCBSD();

			if(!isset($this->_vars['db_path'])){
				$res = CBSD::run('make_tmp_helper module=%s', [$this->url_hash]);
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

		$res = CBSD::run('task owner=%s mode=new {cbsd_loc} jcreate inter=0 jconf=%s', [$this->username, $file_name]);

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
		$err = false;

		try {
			$form = $this->form_validate->these([
				'jail_id' => 3,
				'dialog' => 4
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad jail id!'];
		}

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
		$err = false;

		try {
			$form = $this->form_validate->these([
				'jail_id' => 3,
				'dialog' => 4
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad jail id!'];
		}

		$db = new Db('base','local');
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
		$err = false;

		try {
			$form = $this->form_validate->these([
				'jail_id' => 3,
				'dialog' => 4
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad jail id!'];
		}

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
		$form = $this->form_validate->all();
		$str = [];
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

		$res = CBSD::run('jset jname=%s %s', [$form['jname'], join(' ', $str)]);
		$res['mode'] = $this->mode;
		$res['form'] = $form;
		return $res;
	}

	function ccmd_jailStart()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jstart inter=0 jname=%s',
			[$this->username, $form['jname']]
		); // autoflush=2
	}

	function ccmd_jailStop()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jstop inter=0 jname=%s',
			[$$this->username, $form['jname']]
		); // autoflush=2
	}

	function ccmd_jailRestart()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jrestart inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_jailRemove()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jremove inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveClone()
	{
		$form = $this->form_validate->these([
			'jname' => 3,
			'oldBhyve' => 3,
			'vm_name' => 3,
			'vm_ram' => 3,
			'vm_cpus' => 3,
			'vm_os_type' => 3
		]);
		$res = CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bclone checkstate=0 old=%s new=%s',
			[$this->username, $form['oldBhyve'], $form['vm_name']]
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
		$err = false;

		try {
			$form = $this->form_validate->these([
				'jail_id' => 3,
				'dialog' => 4
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad data!'];
		}

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
		try {
			$form = $this->form_validate->these([
				'oldJail' => 3,
				'jname' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad data!'];
		}

		$res = CBSD::run(
			"task owner=%s mode=new /usr/local/bin/cbsd brename old=%s new=%s restart=1",
			[$this->username, $form['oldJail'], $form['jname']]
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
		$err = false;

		try {
			$form = $this->form_validate->these([
				'jail_id' => 3,
				'dialog' => 4
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad data!'];
		}

		$db = new Db('base','local');
		if($db->isConnected()){
			$res['vars'] = $db->selectOne(
				"SELECT jname,vm_ram,vm_cpus,vm_os_type,hidden FROM bhyve WHERE jname=?", [
				[$form['jail_id']]
			]);
		} else {
			$err = true;
		}

		(empty($res['vars'])) AND $err = true;

		if($err){
			$res['error'] = true;
			$res['error_message'] = $this->_locale->translate('Jail '.$form['jail_id'].' is not present.');
			$res['jail_id'] = $form['jail_id'];
//			$res['reload']=true;
			return $res;
		}

		$res['error'] = false;
		$res['dialog'] = $form['dialog'];
		$res['jail_id'] = $jname;
		return $res;
	}

	function ccmd_bhyveEdit()
	{
		$form = $this->form_validate->all();
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
		if(!empty($res)){
			CBSD::run(
				'cbsd media mode=unregister name="%s" path="%s" jname=%s type=%s',
				[$res['name'], $res['path'], $jname, $res['type']]
			);
			$res = $db->selectOne(
				'SELECT * FROM media WHERE idx=?',
				array([(int)$form['vm_iso_image']])
			);
			if(!empty($res) && $form['vm_iso_image'] != -2){
				CBSD::run(
					'cbsd media mode=register name="%s" path="%s" jname=%s type=%s',
					[$res['name'], $res['path'], $jname, $res['type']]
				);
			}
		}

		/* end check */
		$res = CBSD::run('bset jname=%s %s', [$jname, join(' ', $str)]);
		$res['mode'] = $this->mode;
		$res['form'] = $form;
		return $res;
	}

	function ccmd_bhyveAdd()
	{
		$form = $this->form_validate->all();
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
				if(empty($res)) $iso = false;
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
			[$this->username, $file_name]
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
		$form = $this->form_validate->all();
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
			$authkey = '';
		}

		$params = [
			$this->username,
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
			$form['vm_password']
		];

		if (empty($form['user_password'])){

			$params[] = $form['vnc_password'];

			$res = CBSD::run(
				'task owner=%s mode=new {cbsd_loc} bcreate jname=%s 
				vm_os_profile="%s" imgsize=%s vm_cpus=%s vm_ram=%s vm_os_type=%s mask=%s 
				ip4_addr=%s ci_ip4_addr=%s ci_gw4=%s ci_user_pubkey="%s" vnc_password=%s',
				$params
			);
		} else {

			$params[] = $user_pw;
			$params[] = $form['vnc_password'];

			$res = CBSD::run(
				'task owner=%s mode=new {cbsd_loc} bcreate jname=%s 
				vm_os_profile="%s" imgsize=%s vm_cpus=%s vm_ram=%s vm_os_type=%s mask=%s 
				ip4_addr=%s ci_ip4_addr=%s ci_gw4=%s ci_user_pubkey="%s" ci_user_pw_user=%s vnc_password=%s',
				$params
			);
		}

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
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bstart inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveStop()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bstop inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveRestart()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} brestart inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_bhyveRemove()
	{
		$form = $this->form_validate->these(['jname' => 3]);
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} bremove inter=0 jname=%s',
			[$this->username, $form['jname']]
		);	// autoflush=2
	}

	function ccmd_authkeyAdd()
	{
		try {
			$form = $this->form_validate->these([
				'keyname' => 3,
				'keysrc' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad key data!'];
		}

		$db = new Db('base','authkey');
		if(!$db->isConnected()) return ['error' => 'Database error'];

		$res = $db->insert("INSERT INTO authkey (name,authkey) VALUES (?, ?)", array([$form['keyname']], [$form['keysrc']]));
		if($res['error']) return ['error' => $res];
		
		$html = '';
		$hres = $this->getTableChunk('authkeyslist','tbody');
		if($hres !== false){
			$html_tpl = $hres[1];
			$vars = [
				'keyid' => $res['lastID'],
				'keyname' => $form['keyname'],
				'keysrc' => $form['keysrc'],
				'deltitle' => $this->_locale->translate('Delete')
			];

			foreach($vars as $var => $val){
				$html_tpl = str_replace('#'.$var.'#', $val, $html_tpl);
			}
			$html = $html_tpl;
		}

		return ['keyname' => $form['keyname'], 'html' => $html];
	}

	function ccmd_authkeyRemove()
	{

		try {
			$form = $this->form_validate->these(['auth_id' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad key data!'];
		}
		$db = new Db('base','authkey');
		if(!$db->isConnected()) return ['error' => true, 'res' => 'Database error'];

		$res = $db->update('DELETE FROM authkey WHERE idx=?', array([$form['auth_id']]));
		if($res === false) return ['error' => true,'res' => print_r($res,true)];

		return ['error' => false, 'auth_id' => $form['auth_id']];
	}

	function ccmd_vpnetAdd()
	{
		try {
			$form = $this->form_validate->these([
				'network' => 3,
				'netname' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad jail id!'];
		}
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
		try {
			$form = $this->form_validate->these(['vpnet_id' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad vpnet id!'];
		}

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

		try {
			$form = $this->form_validate->these(['media_id' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad media id!'];
		}

		$db_res = $db->selectOne('SELECT name, path, jname, type FROM media WHERE idx=?', array([(int)$form['media_id'], PDO::PARAM_INT]));
		if(empty($db_res)) return ['error' => true, 'res'=> print_r($res, true)];

		$res = CBSD::run('media mode=remove name="%s" path="%s" jname="%s" type="%s"', $db_res);

		if($res['error']){
			$arr['error_message'] = 'File image was not deleted! '.$res['error_message'];
		}

		$arr['media_id'] = $form['media_id'];
		$arr['cmd'] = $res;

		return $arr;
	}

	function ccmd_srcRemove()
	{
		try {
			$form = $this->form_validate->these(['jname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad name!'];
		}
		$ver = str_replace('src', '', $form['jname']);
		if(empty($ver)) return ['error' => true, 'errorMessage' => 'Version of sources is emtpy!'];
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} removesrc inter=0 ver=%s jname=#src%s',
			[$this->username, $ver, $ver]
		);
	}

	function ccmd_srcUpdate()
	{
		try {
			$form = $this->form_validate->these(['jname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad name!'];
		}
		$ver = str_replace('src', '', $form['jname']);
		$stable = (preg_match('#\.\d#', $ver)) ? 0 : 1;
		if(empty($ver)) return ['error' => true, 'errorMessage' => 'Version of sources is emtpy!'];
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} srcup stable=%s inter=0 ver=%s jname=#src%s',
			[$this->username, $stable, $ver, $ver]
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
		try {
			$form = $this->form_validate->these(['jname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad name!'];
		}
		$id = $form['jname'];
		preg_match('#base([0-9\.]+)-([^-]+)-(\d+)#', $id, $res);
		$ver = $res[1];
		$arch = $res[2];
		$stable = $res[3];

		return $this->CBSD::run(
			'task owner=%s mode=new {cbsd_loc} removebase inter=0 stable=%s ver=%s arch=%s jname=#%s',
			[$this->username, $stable, $ver, $arch, $id]
		);
	}

	function ccmd_basesCompile()
	{
		try {
			$form = $this->form_validate->these(['sources' => 1]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Wrong OS type selected!'];
		}
		$id = $form['sources'];

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
			[$this->username, $res['stable'], $ver, $bid]
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
		try {
			$form = $this->form_validate->these(['version' => 1]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Wrong OS type input!'];
		}

		$ver = $form['version'];
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
			[$this->username, $stable_num, $ver, $bid]
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
		try {
			$form = $this->form_validate->these(['log_id' => 1]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Log ID must be a number'];
		}

		$log_id = $form['log_id'];
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

		return ['error' => true, 'errorMessage' => 'Log file is not exists!'];
	}

	function ccmd_logFlush()
	{
		return CBSD::run('task mode=flushall', []);
	}

	function getBasesCompileList()
	{
		$db1 = new Db('base', 'local');
		$list = [];
		if($db1->isConnected()){
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

	function helpersAdd()
	{
		if($this->uri_chunks[0] != 'jailscontainers' || empty($this->uri_chunks[1])) return ['error' => true, 'errorMessage' => 'Bad url!'];
		$jail_id = $this->uri_chunks[1];

		$form = $this->form_validate->all();
		$helpers = array_keys($form);
		foreach($helpers as $helper){
			$res = CBSD::run(
				'task owner=%s mode=new {cbsd_loc} forms inter=0 module=%s jname=%s',
				[$this->username, $helper, $jail_id]
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

	function addHelperGroup()
	{
		$module = $this->url_hash;
		if ($this->form_validate->exists('db_path')){
			var_dump($_POST); # TODO
			throw new Exception("SECURITY PROBLEM 1");
			try{
				$this->form_validate->these(['db_path' => 4]);
			} catch(Exception $e){
				return ['error' => true, 'error_message' => 'Bad data!'];
			}
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

	function deleteHelperGroup()
	{
		$module = $this->url_hash;

		try {
			$form = $this->form_validate->these(['index' => 3, 'db_path' => 4]); # TODO: Figure out if this is a real file path and validate it.
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Error on open temporary form file!'];
		}

		if(!file_exists($form['db_path'])) return ['error' => true, 'errorMessage' => 'Error on open temporary form file!'];

		var_dump($_POST); # TODO
		throw new Exception("SECURITY PROBLEM 2");
			
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
		if(!isset($this->uri_chunks[1]) || trim($this->url_hash) != ''){
			return ['error' => true,'errorMessage' => 'Bad url!'];
		}
		try {
			$form = $this->form_validate->these(['index' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad data!'];
		}
		$jail_id = $this->uri_chunks[1];
		$helper = $this->url_hash;
		$index = str_replace('ind-', '', $form['index']);

		$db = new Db('helper', ['jname' => $jail_id, 'helper' => $helper]);
		if($db->error) return ['error'=> true, 'errorMessage' => 'No helper database!'];

		$db_path = $db->getFileName();
		$res = CBSD::run(
			'forms inter=0 module=%s formfile=%s group=del index=%s',
			[$helper, $db_path, $index]
		);
		$html = (new Forms('', $helper, $db_path))->generate();
		return ['html' => $html];
	}

	function ccmd_getFreeJname($in_helper = false, $type = 'jail')
	{
		$arr = [];

		if ($in_helper) {
			$res = CBSD::run('freejname default_jailname=%s', [$this->url_hash]);
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
		$form = $this->form_validate->all();
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
		try {
			$form = $this->form_validate->these(['k8sname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}

		if(isset($form['k8sname']) && !empty($form['k8sname'])){
			$url = 'http://144.76.225.238/api/v1/destroy/'.$form['k8sname'];
			return ($this->getCurl($url));
		} else {
			return ['error' => 'true', 'errorMessage' => 'Something went wrong!'];
		}
	}

	function postCurl($url, $vars = false)
	{
		if($vars === false) return ['error' => true, 'errorMessage' => 'Something went wrong!'];

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
			["tb", pow($bytes_in_mb, 4)],
			["gb", pow($bytes_in_mb, 3)],
			["mb", pow($bytes_in_mb, 2)],
			["kb", $bytes_in_mb],
			["b", 1]
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
		return $db->select('select idx, name, type from media where type="iso"', []);
	}

	function ccmd_updateBhyveISO($iso = '')
	{
		$db = new Db('base', 'storage_media');
		$res = $db->select('SELECT * FROM media WHERE type="iso"', []);
		if(empty($res)) return [];

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
		try {
			$form = $this->form_validate->these([
				'name' => 3,
				'description' => 4,
				'pkg_vm_ram' => 3,
				'pkg_vm_disk' => 3,
				'pkg_vm_cpus' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}
		$db = new Db('base', 'local');
		if(!$db->isConnected()) return $this->messageError('data incorrect!');
		$query = "INSERT INTO vmpackages (name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus,owner,timestamp)
			VALUES (?,?,?,?,?,?,datetime('now','localtime'))";

		$res = $db->insert($query, [
			[$form['name']],
			[$form['description']],
			[$form['pkg_vm_ram']],
			[$form['pkg_vm_disk']],
			[$form['pkg_vm_cpus']],
			[$this->username]
		]);

		if($res['error'] == false){
			return $this->messageSuccess($res); 
		} else {
			return $this->messageError('sql error!', $res);
		}
	}

	function ccmd_vmTemplateEditInfo()
	{
		try {
			$form = $this->form_validate->these(['template_id' => 1]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}

		$db = new Db('base', 'local');
		if(!$db->isConnected()) return $this->messageError('DB connection error!');

		$res = $db->selectOne(
			"select name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus from vmpackages where id=?",
			array([$form['template_id'], PDO::PARAM_INT])
		);
		return $this->messageSuccess(['vars' => $res, 'template_id' => $form['template_id']]);
	}

	function ccmd_vmTemplateEdit()
	{
		try {
			$form = $this->form_validate->these([
				'template_id' => 1,
				'name' => 3,
				'description' => 4,
				'pkg_vm_ram' => 3,
				'pkg_vm_disk' => 3,
				'pkg_vm_cpus' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}

		if(!isset($id) || $id < 1) $this->messageError('wrong data!');
		$db = new Db('base','local');
		if(!$db->isConnected()) return $this->messageError('db connection error!');

		$query = "update vmpackages set
			name=?,description=?, pkg_vm_ram=?,pkg_vm_disk=?, pkg_vm_cpus=?, owner=?, timestamp=datetime('now','localtime')
			where id=?";

		$res = $db->update($query, [
			[$form['name'], PDO::PARAM_STR],
			[$form['description'], PDO::PARAM_STR],
			[$form['pkg_vm_ram'],  PDO::PARAM_STR],
			[$form['pkg_vm_disk'], PDO::PARAM_STR],
			[$form['pkg_vm_cpus'], PDO::PARAM_STR],
			[$this->username, PDO::PARAM_STR],
			[(int)$id, PDO::PARAM_INT]
		]);
		if($res !== false) return $this->messageSuccess($res);

		return $this->messageError('sql error!');
	}

	function ccmd_vmTemplateRemove()
	{
		try {
			$form = $this->form_validate->these(['template_id' => 2]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}

		$db = new Db('base','local');
		if(!$db->isConnected()) return $this->messageError('DB connection error!');

		$res = $db->select("DELETE FROM vmpackages WHERE id=?", array([$form['template_id'], PDO::PARAM_INT]));
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
		$form = $this->form_validate->these(['id' => 1]);
		return $this->getImageInfo($form['id']);
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
		try {
			$form = $this->form_validate->these(['id' => 1]);
		} catch(Exception $e){
			$this->messageError('Jname is incorrect in export command! Is «'.$form['id'].'».');
		}

		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} jexport gensize=1 jname=%s dstdir=%s',
			[$this->username, $form['id'], $this->media_import]
		);
	}

	function ccmd_imageImport()
	{
		try {
			$form = $this->form_validate->these([
				'file_id' => 3,
				'jname' => 3,
				'ip4_addr' => 3,
				'host_hostname' => 3
			]);
		} catch(Exception $e){
			return ['error' => true, 'error_message' => 'Bad data!'];
		}
		$res = $this->getImageInfo($form['file_id']);
		if($res === false) return $this->messageError('File not found!');

		$cmd = 'task owner=%s mode=new {cbsd_loc} jimport ';
		$attrs = [$this->username];

		if($form['jname'] != $res['orig_jname']) {
			$cmd .= 'new_jname=%s ';
			$attrs[] = $form['jname'];
		}

		if($form['ip4_addr'] != $res['ip4_addr']){
			$cmd .= 'new_ip4_addr=%s ';
			$attrs[] = $form['ip4_addr'];
		}

		if($form['host_hostname'] != $res['host_hostname']) {
			$cmd .= 'new_host_hostname=%s ';
			$attrs[] = $form['host_hostname'];
		}

		$cmd .= 'jname=%s';
		$attrs[] = $file;

		return CBSD::run($cmd, $attrs);
	}

	function ccmd_imageRemove()
	{
		try {
			$form = $this->form_validate->these(['jname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}
		return CBSD::run(
			'task owner=%s mode=new {cbsd_loc} imgremove path=%s img=$s',
			[$this->username, $this->media_import, $form['jname']]
		);
	}

	function ccmd_getSummaryInfo()
	{
		try {
			$form = $this->form_validate->these(['jname' => 3, 'mode' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}
		$jail_name = $form['jname'];
		$res = ['jname' => $jail_name];

		$db = new Db('racct', ['jname' => $jail_name]);
		if($db->isConnected()){
			$query = $db->select(
				"SELECT ? as name,idx as time,memoryuse,pcpu,pmem,maxproc,openfiles,readbps, writebps,readiops,writeiops FROM racct ORDER BY idx DESC LIMIT 25;",
				array([$jail_name])
			);	// where idx%5=0
			$res['__all'] = $query;
		}

		if($form['mode'] == 'bhyveslist'){
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

		try {
			$form = $this->form_validate->these(['jname' => 3]);
		} catch(Exception $e){
			return ['error' => true, 'errorMessage' => 'Bad data!'];
		}

		$db = new Db('bhyve', ['jname' => $form['jname']]);
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
		$res = $db->select('SELECT idx,name FROM authkey;', []);

		$list = [];
		foreach($res as $item){
			$list[item['idx']] = $item['name'];
		}
		return $list;
	}

	function vm_packages_list()
	{
		$db = new Db('base','local');
		$res = $db->select('select id,name,description,pkg_vm_ram,pkg_vm_disk,pkg_vm_cpus,owner from vmpackages order by name asc;', []);

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
				$ram1 = $ram1 / 1000000;
			}
			if($ed == 'gb'){
				$ram1 = substr($ram, 0, -1);
				$ram1 = $ram1 / 1000;
			}
			$res1 = $cpu + $ram1;
			if($min > $res1 || $min == 0) {
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