<?php

if(
	!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
	strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'XMLHttpRequest' ||
	!isset($_REQUEST['path'])
){
	echo '{}';
	exit;
}

$path = trim($_REQUEST['path'], DIRECTORY_SEPARATOR);

include('../php/clonos.php');
include('../php/auth.php');

$clonos = new ClonOS();
$clonos->json_req = true;

$auth = new Auth();
$auth->json_req = true;

exit;