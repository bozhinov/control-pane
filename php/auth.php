<?php

class Auth {

	public $json_req = false;
	public $authorized = false;
	public $_user_info = [
		'id' => 0,
		'error' => false,
		'username' => 'guest'
	];
	private $db;
	private $_client_ip = '';

	function __construct()
	{
		$this->db = new Db('clonos');
		if(!$this->db->isConnected()){
			throw new Exception('Could not connect to users database');
		}
		$this->_client_ip = $_SERVER['REMOTE_ADDR'];
		$f = (isset($_REQUEST['form_data'])) ? $_REQUEST['form_data'] : [];
		$this->validate = new Validate($f);

		if(isset($_COOKIE['mhash'])){ # TODO table 'auth_list' needs to be stored in memory not sqlite
			$query = "SELECT au.id,au.username FROM auth_user au, auth_list al WHERE al.secure_sess_id=? AND au.id=al.user_id AND au.is_active=1";
			$res = $this->db->selectOne($query, array([md5($_COOKIE['mhash'].$this->_client_ip)]));
			if(isset($res['id'])){
				$this->_user_info = $res;
				$this->authorized = true;
			} else {
				if($this->json_req) exit;
			}
		}

		if ($this->json_req){
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
		try {
			$form = $this->validate([
				['login', 3],
				['password', 5]
			]);
		} catch (Exception){
			return ['message' => 'unregistered user', 'errorCode' => 1];
		}

		$pass = $this->getPasswordHash($form['password']);
		$res = $this->db->selectOne(
			"SELECT id, username, password FROM auth_user WHERE username=? AND is_active=1", [
			[$form['login']]
		]);
		if(empty($res) || $res['password'] != $pass){
			//sleep(3); # TODO Why?
			return ['errorCode' => 1,'message' => 'user not found!'];
		}
		$res['errorCode'] = 0;
		unset($res['password']);  // no need to get this outside of this function

		$id = (int)$res['id'];
		$memory_hash = md5($id.$res['username'].time());
		$secure_memory_hash = md5($memory_hash.$this->_client_ip);

		$qres = $this->db->update(
			"UPDATE auth_list SET sess_id=?,secure_sess_id=?,auth_time=datetime('now','localtime') WHERE user_id=? AND user_ip=?", [
			[$memory_hash],
			[$secure_memory_hash],
			[$id],
			[$this->_client_ip]
		]);

		if(isset($qres['rowCount'])){
			if($qres['rowCount'] == 0){
				$qres = $this->db->insert(
					"INSERT INTO auth_list (user_id,sess_id,secure_sess_id,user_ip,auth_time) VALUES (?,?,?,?,datetime('now','localtime'))", [
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

	private function ccmd_usersEdit()
	{
		# TODO: This function has 6 exits
		try {
			$this->validate->add_default('actuser', 'off');
			$form = $this->validate([
				['user_id', 2], # non-zero int
				['username', 3],
				['password', 5],
				['first_name', 4],
				['last_name', 4],
				['actuser', 33] # Non mandatory
			]);
		} catch (Exception){
			return ['error' => true, 'error_message' => 'incorrect data!'];
		}

		$is_active = ($form['actuser'] == 'on') ? 1 : 0;
		$authorized_user_id = 0;

		if(isset($_COOKIE['mhash'])){
			$mhash = $_COOKIE['mhash'];
			if(!preg_match('#^[a-f0-9]{32}$#', $mhash)){
				return ['error' => true,'error_message' => 'Bad data'];
			}
			$res1 = $this->db->selectOne("select user_id from auth_list WHERE sess_id=?", array([$mhash]));
			if(isset($res1['user_id'])){
				$authorized_user_id = (int)$res1['user_id'];
			} else {
				return ['error' => true, 'error_message' => 'you are still not authorized'];
			}
		} else {
			return ['error' => true, 'error_message' => 'you must be authorized for this operation!'];
		}

		if($form['user_id'] != $authorized_user_id){
			return ['error' => true, 'error_message' => 'I think you\'re some kind of hacker'];
		}

		if(isset($form['password'])){
			$res = $this->db->update("UPDATE auth_user SET username=?,password=?,first_name=?,last_name=?,is_active=? WHERE id=?", [
				[$form['username']],
				[$this->getPasswordHash($form['password'])],
				[$form['first_name']],
				[$form['last_name']],
				[$is_active],
				[$form['user_id']]
			]);
		} else {
			$res = $this->db->update("UPDATE auth_user SET username=?,first_name=?,last_name=?,is_active=? WHERE id=?" [
				[$form['username']],
				[$form['first_name']],
				[$form['last_name']],
				[$is_active],
				[$form['user_id']]
			]);
		}
		return ['error' => false, 'res' => $res];
	}

	private function ccmd_usersAdd()
	{
		try {
			$this->validate->add_default('actuser', 'off');
			$form = $this->validate([
				['username', 3],
				['password', 5],
				['first_name', 4],
				['last_name', 4],
				['actuser', 3]
			]);
		} catch (Exception){
			return ['error' => true, 'error_message' => 'incorrect data!'];
		}

		$res = $this->db->selectOne("SELECT username FROM auth_user WHERE username=?", array([$form['username']]));
		if(!empty($res)){
			return ['error' => true, 'errorType' => 'user-exists', 'errorMessage' => 'User always exists!'];
		}

		$is_active = ($form['actuser'] == 'on') ? 1 : 0;
		$res = $this->db->update("INSERT INTO auth_user (username,password,first_name,last_name,is_active,date_joined) VALUES (?,?,?,?,?,datetime('now','localtime'))", [
			[$form['username']],
			[$this->getPasswordHash($form['password'])],
			[$form['first_name']],
			[$form['last_name']],
			[$is_active]
		]);

		return ['form' => $form];
	}

	private function ccmd_userRemove()
	{
		try {
			$form = $this->validate([
				['user_id', 1] # non-zero int
			]);
		} catch (Exception){
			return ['error' => true, 'error_message' => 'incorrect data!'];
		}

		return $this->db->select("DELETE FROM auth_user WHERE id=?", array([$form['user_id'], PDO::PARAM_INT]));
	}

	private function ccmd_userEditInfo()
	{
		try {
			$form = $this->validate([
				['user_id', 1], # non-zero int
				['tbl_id'], 3],
				['dialog'], 4] # TODO - THIS WILL FAIL
			]);
		} catch (Exception){
			return ['error' => true, 'error_message' => 'incorrect data!'];
		}

		$res = $this->db->selectOne("SELECT username,first_name,last_name,is_active AS actuser FROM auth_user WHERE id=?", array([[$form['user_id']]));
		return [
			'dialog' => $form['dialog'], # TODO . this is not OK
			'vars' => $res,
			'error' => false,
			'tblid' => $form['tbl_id'],
			'user_id' => [$form['user_id']
		];
	}

	private function ccmd_userGetInfo()
	{
		return $this->db->selectOne("SELECT * FROM auth_user", []); // TODO: What?!
	}

	public function json_usersGetInfo()
	{
		return $this->db->select("select id,username,first_name,last_name,date_joined,last_login,is_active from auth_user order by date_joined desc", []);
	}

	public function getUserName()
	{
		return $this->_user_info['username'];
	}

}