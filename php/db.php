<?php

class Db {
	private $_pdo = null;
	private $_workdir = '';
	private $_filename = '';
	public $error = false;
	public $error_message = '';

	/*
		$place = base (This is a basic set of databases: local, nodes, etc)
		$place = file (specify a specific database for the full pathth)
	*/
	function __construct($place = 'base', $database = '', $connect = null)
	{
		if (is_null($connect)){
			$connect = $this->prep_connect($place, $database);
		}

		if (!$this->error){
			try {
				$this->_pdo = new PDO($connect);
				$this->_pdo->setAttribute(PDO::ATTR_TIMEOUT, 5000);
			} catch (PDOException $e){
				$this->error = true;
				$this->error_message = $e->getMessage();
			}
		}
	}

	private function prep_connect($place, $database)
	{
		$this->_workdir = getenv('WORKDIR');	// /usr/jails/
		$this->_filename = null;

		switch($place){
			case 'base': # TODO: is this correct?
				$this->_filename = $this->_workdir.'/var/db/'.$database.'.sqlite';
				break;
			case 'file':
				$this->_filename = $database;
				break;
			case 'helper':
				if(is_array($database)){
					///usr/jails/jails-system/cbsdpuppet1/helpers/redis.sqlite
					$this->_filename = $this->_workdir.'/jails-system/'.$database['jname'].'/helpers/'.$database['helper'].".sqlite";
				} else {
					$this->_filename = $this->_workdir.'/formfile/'.$database.".sqlite";
				}
				break;
			case 'cbsd-settings':
				$this->_filename = $this->_workdir.'/jails-system/CBSDSYS/helpers/cbsd.sqlite';
				break;
			case 'clonos':
				$this->_filename = '/var/db/clonos/clonos.sqlite';
				break;
			case 'racct':
				$this->_filename = $this->_workdir.'/jails-system/'.$database['jname'].'/racct.sqlite';
				break;
			case 'bhyve':
				$this->_filename = $this->_workdir.'/jails-system/'.$database['jname'].'/local.sqlite';
				
				break;
			default:
				$this->error = true;
				$this->error_message = 'DB file name not set or invalid';
				return null;
		}

		if(is_null($this->_filename) || !file_exists($this->_filename)){
			$this->error = true;
			$this->error_message = 'DB file name not set or not found!';
			return null;
		}

		return 'sqlite:'.$this->_filename;
	}

	# TODO once tested $values can have a default value of an empty array
	function select($sql, $values, $single = false){
		try {
			$query = $this->_pdo->prepare($sql);
			$i = 1;
			foreach($values as $v){
				if (count($v) == 1){ # TODO: Make default type string
					$query->bindParam($i, $v[0]);
				} elseif (count($v) == 2){ # if type defined
					$query->bindParam($i, $v[0], $v[1]);
				}
				$i++;
			}
			$query->execute();
			if ($single){
				$res = $query->fetch(PDO::FETCH_ASSOC);
			} else {
				$res = $query->fetchAll(PDO::FETCH_ASSOC);
			}
			return $res;
		} catch(PDOException $e) {
			# TODO: Handling ?
			return [];
		}
	}

	function selectOne($sql, $values)
	{
		return $this->select($sql, $values, true);
	}

	function insert($sql, $values)
	{
		try {
			$this->_pdo->beginTransaction();
			$query = $this->_pdo->prepare($sql);
			$i = 1;
			foreach($values as $v){
				if (count($v) == 1){ # TODO: Make default type string
					$query->bindParam($i, $v[0]);
				} elseif (count($v) == 2){ # if type defined
					$query->bindParam($i, $v[0], $v[1]);
				}
				$i++;
			}
			$query->execute();
			$lastId = $this->_pdo->lastInsertId();
			$this->_pdo->commit();
		} catch(PDOException $e) {
			$this->_pdo->rollBack();
			#throw new Exception($e->getMessage());
			return ['error' => true, 'info' => $e->getMessage()];
		}
		return ['error' => false, 'lastID' => $lastId];
	}

	function update($sql, $values)
	{
		try {
			$this->_pdo->beginTransaction();
			$query = $this->_pdo->prepare($sql);
			$i = 1;
			foreach($values as $v){
				if (count($v) == 1){ # TODO: Make default type string
					$query->bindParam($i, $v[0]);
				} elseif (count($v) == 2){ # if type defined
					$query->bindParam($i, $v[0], $v[1]);
				}
				$i++;
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->_pdo->commit();
		} catch(PDOException $e) {
			$this->_pdo->rollBack();
			#return false;
			throw new Exception($e->getMessage());
		}

		return ['rowCount' => $rowCount];
	}

	function isConnected()
	{ 
		return !is_null($this->_pdo);
	}

	function getWorkdir()
	{
		return $this->_workdir;
	}

	function getFileName()
	{
		return $this->_filename;
	}
}
