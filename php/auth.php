<?php

//case 'usersAdd':		echo json_encode($this->usersAdd()); return;
//case 'usersEdit':		echo json_encode($this->usersEdit()); return;
//case 'userRemove':		echo json_encode($this->userRemove()); return;
//case 'userGetInfo':		echo json_encode($this->userGetInfo()); return;
//case 'userEditInfo':		echo json_encode($this->userEditInfo()); return;

class Auth {

	public $authorized = false; # TODO Move to SESSION
	private $_user_info = [
		'id' => 0,
		'username' => 'guest',
		'unregistered' => true
	];

	private $_client_ip = '';

	function __construct()
	{
		$this->_client_ip = $_SERVER['REMOTE_ADDR'];
		$this->form = (isset($_POST['form_data'])) : $_POST['form_data'] : [];
		$ures = $this->userAutologin();
		if($ures !== false){
			if(isset($ures['id']) && is_numeric($ures['id']) && $ures['id'] > 0){
				$this->_user_info = $ures;
				$this->_user_info['unregistered'] = false;
				$this->authorized = true;
			} else {
				$this->_user_info['unregistered'] = true;
				if($this->json_req) exit;
			}
		}

		if(isset($_POST['mode'])){
			if(isset($this->_user_info['error']) && $this->_user_info['error']){
				if($_POST['mode'] != 'login'){
					echo json_encode(['error' => true, 'unregistered_user' => true]);
					exit;
				}
			}

			if($this->_user_info['unregistered'] && $_POST['mode'] != 'login'){
				echo json_encode(['error' => true, 'unregistered_user' => true]);
				exit;
			}

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
		}
	}

	function ccmd_login()
	{
		return $this->userRegisterCheck($this->form);
	}

	function userRegisterCheck($user_info=[])
	{
		/*
		[0] => Array
		(
			[id] => 1
			[username] => admin
			[password] => 01...87a
			[first_name] => Admin
			[last_name] => Admin
			[last_login] => 
			[is_active] => 1
			[date_joined] => 2017-12-02 00:09:00
			[sess_id] => 
			[secure_sess_id] => 
		)
		*/
		if(empty($user_info)) return false;
		if(isset($user_info['login']) && isset($user_info['password'])){
			$db = new Db('clonos');
			if($db->isConnected()){
				$pass = $this->getPasswordHash($user_info['password']);
				$res = $db->selectOne("SELECT id,username,password FROM auth_user WHERE username=? AND is_active=1", array([$user_info['login']]));
				if(empty($res) || $res['password'] != $pass){
					//sleep(3); # TODO Why?
					return ['errorCode' => 1,'message' => 'user not found!'];
				}
				$res['errorCode']=0;

				$id = (int)$res['id'];
				$memory_hash = md5($id.$res['username'].time());
				$secure_memory_hash = md5($memory_hash.$this->_client_ip);

				$query = "UPDATE auth_list 
						SET sess_id=?,secure_sess_id=?,auth_time=datetime('now','localtime') 
						WHERE user_id=? AND user_ip=?";
				$qres = $db->update($query, [
					[$memory_hash],
					[$secure_memory_hash],
					[$id],
					[$this->_client_ip]
				]);

				if(isset($qres['rowCount'])){
					if($qres['rowCount'] == 0){
						$query = "INSERT INTO auth_list
							(user_id,sess_id,secure_sess_id,user_ip,auth_time) VALUES
							(?,?,?,?,datetime('now','localtime'))";
						$qres = $db->insert($query, [
							[$id],
							[$memory_hash],
							[$secure_memory_hash],
							[$this->_client_ip]
						]);
					}
				}

				setcookie('mhash', $memory_hash,time() + 1209600);

				return $res;
			}
		}
		return ['message' => 'unregistered user','errorCode' => 1];
	}

	function userRegister($user_info=[])
	{
		if(empty($user_info)) return false;
		if(isset($user_info['username']) && isset($user_info['password'])){
			$db = new Db('clonos');
			if($db->isConnected()) {
				$res = $db->selectOne("SELECT username FROM auth_user WHERE username=?", array([$user_info['username']]));
				if(!empty($res)){
					$res['user_exsts'] = true;
					return $res;
				}

				$password = $this->getPasswordHash($user_info['password']);
				$is_active = 0;
				if(isset($user_info['actuser']) && $user_info['actuser'] == 'on') $is_active = 1;
				$query = $db->query_protect("INSERT INTO auth_user
					(username,password,first_name,last_name,is_active,date_joined) VALUES
					(?,?,?,?,?,datetime('now','localtime'))");
				$res = $db->insert($query, [
					[$user_info['username']],
					[$password],
					[$user_info['first_name']],
					[$user_info['last_name']],
					[$is_active]
				]);
				return ['error' => false, 'res' => $res];
			}
		}
	}

	function userAutologin()
	{
		if(isset($_COOKIE['mhash'])){
			$memory_hash = $_COOKIE['mhash'];
			$secure_memory_hash = md5($memory_hash.$this->_client_ip);
			$db = new Db('clonos');
			if($db->isConnected()){
				$query = "SELECT au.id,au.username FROM auth_user au, auth_list al WHERE al.secure_sess_id=? AND au.id=al.user_id AND au.is_active=1";
				$res = $db->selectOne($query, array([$secure_memory_hash]));
				if(!empty($res)){
					$res['error'] = false;
					return $res;
				}
			}
		}
		return ['error' => true];
	}

	function getPasswordHash($password)
	{
		return hash('sha256', hash('sha256', $password).$this->getSalt());
	}

	private function getSalt()
	{
		$salt_file = '/var/db/clonos/salt';
		if(file_exists($salt_file)) return trim(file_get_contents($salt_file));
		return 'noSalt!';
	}

	function ccmd_usersEdit()
	{
		$form = $this->form;

		if(!isset($form['user_id']) || !is_numeric($form['user_id']) || $form['user_id']<1){
			return ['error' => true,'error_message' => 'incorrect data!'];
		}
		$db = new Db('clonos');
		if(!$db->isConnected())	return ['error' => true, 'error_message' => 'db connection lost!'];

		$user_id = (int)$form['user_id'];
		$username = $form['username'];
		$first_name = $form['first_name'];
		$last_name = $form['last_name'];
		$is_active = 0;
		if(isset($form['actuser']) && $form['actuser'] == 'on') $is_active = 1;

		$authorized_user_id = 0;
		if(isset($_COOKIE['mhash'])){
			$mhash = $_COOKIE['mhash'];
			if(!preg_match('#^[a-f0-9]{32}$#', $mhash)) return ['error' => true,'error_message' => 'Bad data'];
			$query1 = "select user_id from auth_list WHERE sess_id=? limit 1";
			$res1 = $db->selectOne($query1, array([$mhash]));
			if($res1['user_id']>0){
				$authorized_user_id = $res1['user_id'];
			} else {
				return ['error' => true, 'error_message' => 'you are still not authorized'];
			}
		} else {
			return ['error' => true, 'error_message' => 'you must be authorized for this operation!'];
		}

		if($user_id == 0 || $user_id != $authorized_user_id){
			return ['error' => true, 'error_message' => 'I think you\'re some kind of hacker'];
		}

		if(isset($form['password'])){
			$password = $this->getPasswordHash($form['password']);
			$query = "UPDATE auth_user SET username=?,password=?,first_name=?,last_name=?,is_active=? WHERE id=?";
			$res = $db->update($query, [
				[$username],
				[$password],
				[$first_name],
				[$last_name],
				[$is_active],
				[(int)$user_id]
			]);
		} else {
			$query = "UPDATE auth_user SET username=?,first_name=?,last_name=?,is_active=? WHERE id=?";
			$res = $db->update($query, [
				[$username],
				[$first_name],
				[$last_name],
				[$is_active],
				[(int)$user_id]
			]);
		}
		return ['error' => false, 'res' => $res];
	}

	function ccmd_usersAdd()
	{
		$form = $this->form;

		$res = $this->userRegister($form);
		if($res !== false){
			if(isset($res['user_exists']) && $res['user_exists']){
				return ['error' => true, 'errorType' => 'user-exists', 'errorMessage' => 'User always exists!'];
			}
			return $res;
		}
		return ['form' => $form];
	}

	function ccmd_userRemove()
	{
		$id = $this->form['user_id'];
		if(is_numeric($id) && $id > 0){
			$db = new Db('clonos');
			if(!$db->isConnected()) return ['error' => true, 'error_message' => 'DB connection error!'];

			$res = $db->select("DELETE FROM auth_user WHERE id=?", array([(int)$id, PDO::PARAM_INT]));
			return $res;
		}
	}

	function ccmd_userEditInfo()
	{
		if(!isset($this->form['user_id'])) return ['error' => true, 'error_message' => 'incorrect data!'];

		$db = new Db('clonos');
		if(!$db->isConnected()) return ['error' => true, 'error_message' => 'DB connection error!'];
		$user_id = (int)$this->form['user_id'];

		$res = $db->selectOne("SELECT username,first_name,last_name,is_active AS actuser FROM auth_user WHERE id=?", array([$user_id]));
		return [
			'dialog' => $this->form['dialog'],
			'vars' => $res,
			'error' => false,
			'tblid' => $this->form['tbl_id'],
			'user_id' => $user_id
		];
	}

	function ccmd_userGetInfo()
	{
		$db = new Db('clonos');
		if(!$db->isConnected()) return ['DB connection error!'];

		$res = $db->selectOne("SELECT * FROM auth_user", []); // TODO: What?!
		return $res;
	}

	public static function json_usersGetInfo()
	{
		$db = new Db('clonos');
		if($db->isConnected()){
			$res = $db->select("select id,username,first_name,last_name,date_joined,last_login,is_active from auth_user order by date_joined desc", []);
		} else {
			return ['error' => true, 'error_message' => 'DB connection error!'];
		}
		return $res;
	}

	function getUserName()
	{
		return $this->_user_info['username'];
	}

}