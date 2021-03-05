<?php

class Auth {

	public $json_req = false;
	public $authorized = false;
	public $_user_info = [
		'id' => 0,
		'username' => 'guest'
	];
	private $db;
	private $form = [];
	private $_client_ip = '';

	function __construct()
	{
		$this->db = new Db('clonos');
		if(!$this->db->isConnected()){
			throw new Exception('Could not connect to users database');
		}
		$this->_client_ip = $_SERVER['REMOTE_ADDR'];
		(isset($_REQUEST['form_data'])) AND $this->form = $_REQUEST['form_data'];

		if(isset($_COOKIE['mhash'])){
			$query = "SELECT au.id,au.username FROM auth_user au, auth_list al WHERE al.secure_sess_id=? AND au.id=al.user_id AND au.is_active=1";
			$res = $this->db->selectOne($query, array([md5($_COOKIE['mhash'].$this->_client_ip)]));
			if(isset($res['id'])){
				$this->_user_info = $res;
				$this->authorized = true;
			} else {
				if($this->json_req) exit;
			}
		}

		$ccmd_res = ["authorized" => $this->authorized];

		if(isset($_REQUEST['mode'])){

			if ($_REQUEST['mode'] != 'login')){
				if(!$this->authorized){
					echo json_encode(['error' => true, 'unregistered_user' => true]);
					exit;
				}
			}

			switch ($_REQUEST['mode']){
				case 'login':
					$ccmd_res = $this->ccmd_login();
					break;
				case 'usersAdd':
					$ccmd_res = $this->ccmd_usersAdd();
					break;
				case 'usersEdit':
					$ccmd_res = $this->ccmd_usersEdit();
					break;
				case 'userRemove':
					$ccmd_res = $this->ccmd_userRemove();
					break;
				case 'userGetInfo':
					$ccmd_res = $this->ccmd_userGetInfo();
					break;
				case 'userEditInfo':
					$ccmd_res = $this->ccmd_userEditInfo();
					break;
				#default: # Can't uncomment it right now until I have a dispatcher of a sort
					# echo json_encode(['error' => true, 'unknown command' => true]);
			}
		}

		echo json_encode($ccmd_res);
	}

	private function getPasswordHash($password)
	{
		return hash('sha256', hash('sha256', $password) . $this->getSalt());
	}

	private function getSalt()
	{
		$salt_file = Config::$salt_file; # TODO: use config for this
		if(file_exists($salt_file)) return trim(file_get_contents($salt_file));
		throw new Exception('noSalt!');
	}

	private function ccmd_login()
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
		$user_info = $this->form;
		if(empty($user_info)) return ['errorCode' => 1,'message' => 'empty user info!'];

		if(isset($user_info['login']) && isset($user_info['password'])){
			$pass = $this->getPasswordHash($user_info['password']);
			$res = $this->db->selectOne("SELECT id,username,password FROM auth_user WHERE username=? AND is_active=1", array([$user_info['login']]));
			if(empty($res) || $res['password'] != $pass){
				//sleep(3); # TODO Why?
				return ['errorCode' => 1,'message' => 'user not found!'];
			}
			$res['errorCode'] = 0;

			$id = (int)$res['id'];
			$memory_hash = md5($id.$res['username'].time());
			$secure_memory_hash = md5($memory_hash.$this->_client_ip);

			$query = "UPDATE auth_list 
					SET sess_id=?,secure_sess_id=?,auth_time=datetime('now','localtime') 
					WHERE user_id=? AND user_ip=?";
			$qres = $this->db->update($query, [
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
					$qres = $this->db->insert($query, [
						[$id],
						[$memory_hash],
						[$secure_memory_hash],
						[$this->_client_ip]
					]);
				}
			}

			setcookie('mhash', $memory_hash, time() + 1209600); # 20min

			return $res;
		}
		return ['message' => 'unregistered user', 'errorCode' => 1];
	}

	private function ccmd_usersEdit()
	{
		$form = $this->form;

		if(!isset($form['user_id']) {
			return ['error' => true, 'error_message' => 'incorrect data!'];
		} else {
			$user_id = (int)$form['user_id'];
		}

		$username = $form['username'];
		$first_name = $form['first_name'];
		$last_name = $form['last_name'];
		$is_active = 0;
		if(isset($form['actuser']) && $form['actuser'] == 'on') $is_active = 1;

		$authorized_user_id = 0;
		if(isset($_COOKIE['mhash'])){
			$mhash = $_COOKIE['mhash'];
			if(!preg_match('#^[a-f0-9]{32}$#', $mhash)) return ['error' => true,'error_message' => 'Bad data'];
			$res1 = $this->db->selectOne("select user_id from auth_list WHERE sess_id=?", array([$mhash]));
			if(isset($res1['user_id'])){
				$authorized_user_id = (int)$res1['user_id'];
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
			$res = $this->db->update($query, [
				[$username],
				[$password],
				[$first_name],
				[$last_name],
				[$is_active],
				[$user_id]
			]);
		} else {
			$query = "UPDATE auth_user SET username=?,first_name=?,last_name=?,is_active=? WHERE id=?";
			$res = $this->db->update($query, [
				[$username],
				[$first_name],
				[$last_name],
				[$is_active],
				[$user_id]
			]);
		}
		return ['error' => false, 'res' => $res];
	}

	private function ccmd_usersAdd()
	{
		$user_info = $this->form;

		if(isset($user_info['username']) && isset($user_info['password'])){
			$user = $user_info['username'];
			$pass = $user_info['password'];
			if ((strlen($user) < 4) || strlen($pass) < 6){
				return ['error' => true, 'errorMessage' => 'Username or/and Password is not long enough!'];
			}
			$res = $this->db->selectOne("SELECT username FROM auth_user WHERE username=?", array([$user]));
			if(!empty($res)){
				return ['error' => true, 'errorType' => 'user-exists', 'errorMessage' => 'User always exists!'];
			}

			$pass = $this->getPasswordHash($pass);
			$is_active = 0;
			if(isset($user_info['actuser']) && $user_info['actuser'] == 'on') $is_active = 1;
			$query = "INSERT INTO auth_user
				(username,password,first_name,last_name,is_active,date_joined) VALUES
				(?,?,?,?,?,datetime('now','localtime'))";
			$res = $this->db->update($query, [
				[$user],
				[$pass],
				[$user_info['first_name']],
				[$user_info['last_name']],
				[$is_active]
			]);
			return ['form' => $user_info];
		} else {
			return ['error' => true];
		}
	}

	private function ccmd_userRemove()
	{
		$id = (int)$this->form['user_id'];
		if($id > 0){
			return $this->db->select("DELETE FROM auth_user WHERE id=?", array([(int)$id, PDO::PARAM_INT]));
		}
	}

	private function ccmd_userEditInfo()
	{
		if(!isset($this->form['user_id'])) return ['error' => true, 'error_message' => 'incorrect data!'];

		$user_id = (int)$this->form['user_id'];

		$res = $this->db->selectOne("SELECT username,first_name,last_name,is_active AS actuser FROM auth_user WHERE id=?", array([$user_id]));
		return [
			'dialog' => $this->form['dialog'],
			'vars' => $res,
			'error' => false,
			'tblid' => $this->form['tbl_id'],
			'user_id' => $user_id
		];
	}

	private function ccmd_userGetInfo()
	{
		return $this->db->selectOne("SELECT * FROM auth_user", []); // TODO: What?!
	}

	public static function json_usersGetInfo()
	{
		$db = new Db('clonos');
		if(!$db->isConnected()){
			throw new Exception('Could not connect to users database');
		}
		return $db->select("select id,username,first_name,last_name,date_joined,last_login,is_active from auth_user order by date_joined desc", []);
	}

	public function getUserName()
	{
		return $this->_user_info['username'];
	}

}