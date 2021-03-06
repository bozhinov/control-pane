<?php

if(
	!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
	strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'XMLHttpRequest' ||
	!isset($_REQUEST['path'])
){
	echo '{}';
	exit;
}

require_once('../php/cbsd.php');
require_once('../php/config.php');
require_once('../php/validate.php');
require_once('../php/db.php');
require_once('../php/auth.php');
require_once('../php/clonos.php');
require_once('../php/tpl.php');

Validate::short_string($_REQUEST['path']);
$path = trim($_REQUEST['path'], DIRECTORY_SEPARATOR);

$clonos = new ClonOS();
$clonos->json_req = true;

$auth = new Auth();
$auth->json_req = true;

exit;