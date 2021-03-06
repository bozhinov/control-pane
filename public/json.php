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

$auth = new Auth();
$auth->json_req = true;

$clonos = new ClonOS();
$clonos->username = $auth->getUserName();

Validate::short_string($_REQUEST['path']);
$path = trim($_REQUEST['path'], DIRECTORY_SEPARATOR);

if (isset($_REQUEST['mode'])
	Validate::short_string($_REQUEST['mode']);
	$mode = $_REQUEST['mode'];
	$clonos->mode = $mode;
 else {
	throw new Exception("JSON: Mode not set");
}

$ret = [];

switch($mode){
	case 'getTasksStatus':
		$ret = $clonos->_getTasksStatus();
		break;
	case 'jailRename':
		$ret = $clonos->ccmd_jailRename();
		break;
	case 'jailClone':
		$ret = $clonos->ccmd_jailClone();
		break;
	case 'jailAdd':
		$ret = $clonos->ccmd_jailAdd();
		break;
	case 'jailRenameVars':
		$ret = $clonos->ccmd_jailRenameVars();
		break;
	case 'jailCloneVars':
		$ret = $clonos->ccmd_jailCloneVars();
		break;
	case 'jailEditVars':
		$ret = $clonos->ccmd_jailEditVars();
		break;
	case 'jailEdit':
		$ret = $clonos->ccmd_jailEdit();
		break;
	case 'jailStart':
		$ret = $clonos->ccmd_jailStart();
		break;
	case 'jailStop':
		$ret = $clonos->ccmd_jailStop();
		break;
	case 'jailRestart':
		$ret = $clonos->ccmd_jailRestart();
		break;
	case 'jailRemove':
		$ret = $clonos->ccmd_jailRemove();
		break;
	case 'saveJailHelperValues':
		$ret = $clonos->ccmd_saveJailHelperValues();
	case 'getJsonPage': # TODO Rework this
		$included_result_array = false;
		$json_name = $clonos->realpath_page.'a.json.php';
		if(file_exists($json_name)){
			include($json_name);
		}
		$ret = $included_result_array;
		break;
	case 'helpersAdd':
		$ret = $clonos->helpersAdd();
		break;
	case 'addHelperGroup':
		$ret = $clonos->addHelperGroup();
		break;
	case 'deleteHelperGroup':
		$ret = $clonos->deleteHelperGroup();
		break;
	case 'saveHelperValues':
		$redirect = '/jailscontainers/';
	case 'jailAdd':
		if(!isset($redirect)) {
			$redirect = '';
		} else {
			$ret = $clonos->jailAdd($redirect);
		} 
		break;
	case 'bhyveClone':
		$ret = $clonos->ccmd_bhyveClone();
		break;
	case 'bhyveEditVars':
		$ret = $clonos->ccmd_bhyveEditVars();
		break;
	case 'bhyveRename':
		$ret = $clonos->ccmd_bhyveRename();
		break;
	case 'bhyveRenameVars':
		$ret = $clonos->ccmd_bhyveRenameVars();
		break;
	case 'bhyveEdit':
		$ret = $clonos->ccmd_bhyveEdit();
		break;
	case 'bhyveAdd':
		$ret = $clonos->ccmd_bhyveAdd();
		break;
	case 'bhyveObtain':
		$ret = $clonos->ccmd_bhyveObtain();
		break;
	case 'bhyveStart':
		$ret = $clonos->ccmd_bhyveStart();
		break;
	case 'bhyveStop':
		$ret = $clonos->ccmd_bhyveStop();
		break;
	case 'bhyveRestart':
		$ret = $clonos->ccmd_bhyveRestart();
		break;
	case 'bhyveRemove':
		$ret = $clonos->ccmd_bhyveRemove();
		break;
	case 'authkeyAdd':
		$ret = $clonos->ccmd_authkeyAdd();
		break;
	case 'authkeyRemove':
		$ret = $clonos->ccmd_authkeyRemove();
		break;
	case 'vpnetAdd':
		$ret = $clonos->ccmd_vpnetAdd();
		break;
	case 'vpnetRemove':
		$ret = $clonos->ccmd_vpnetRemove();
		break;
	case 'mediaRemove':
		$ret = $clonos->ccmd_mediaRemove();
		break;
	case 'srcRemove':
		$ret = $clonos->ccmd_srcRemove();
		break;
	case 'srcUpdate':
		$ret = $clonos->ccmd_srcUpdate();
		break;
	case 'baseRemove':
		$ret = $clonos->ccmd_baseRemove();
		break;
	case 'basesCompile':
		$ret = $clonos->ccmd_basesCompile();
		break;
	case 'repoCompile':
		$ret = $clonos->ccmd_repoCompile();
		break;
	case 'logLoad':
		$ret = $clonos->ccmd_logLoad();
		break;
	case 'logFlush':
		$ret = $clonos->ccmd_logFlush();
		break;
	case 'addJailHelperGroup':
		$ret = $clonos->ccmd_addJailHelperGroup();
		break;
	case 'deleteJailHelperGroup':
		$ret = $clonos->ccmd_deleteJailHelperGroup();
		break;
	case 'getFreeJname':
		$ret = $clonos->ccmd_getFreeJname();
		break;
	case 'getFreeCname':
		$ret = $clonos->ccmd_getFreeCname();
		break;
	case 'k8sCreate':
		$ret = $clonos->ccmd_k8sCreate();
		break;
	case 'k8sRemove':
		$ret = $clonos->ccmd_k8sRemove();
		break;
	case 'updateBhyveISO':
		$ret = $clonos->ccmd_updateBhyveISO();
		break;
	case 'vmTemplateAdd':
		$ret = $clonos->ccmd_vmTemplateAdd();
		break;
	case 'vmTemplateEditInfo':
		$ret = $clonos->ccmd_vmTemplateEditInfo();
		break;
	case 'vmTemplateEdit':
		$ret = $clonos->ccmd_vmTemplateEdit();
		break;
	case 'vmTemplateRemove':
		$ret = $clonos->ccmd_vmTemplateRemove();
		break;
	case 'getImportedImageInfo':
		$ret = $clonos->ccmd_getImportedImageInfo();
		break;
	case 'imageExport':
		$ret = $clonos->ccmd_imageExport();
		break;
	case 'imageImport':
		$ret = $clonos->ccmd_imageImport();
		break;
	case 'imageRemove':
		$ret = $clonos->ccmd_imageRemove();
		break;
	case 'getSummaryInfo':
		$ret = $clonos->ccmd_getSummaryInfo();
		break;
	case default:
		echo json_encode(["error" => "not_implemented"]);
		exit();
}

echo json_encode($ret);