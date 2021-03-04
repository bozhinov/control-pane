<?php

require_once('../php/auth.php');
require_once('../php/clonos.php');
require_once('../php/tpl.php');

function get_title($menu_config, $active)
{
	$title = 'Error';

	foreach($menu_config as $link => $val){
		if($active == $link){
			$title = $val['title'];
		}
	}

	if($title == 'Error'){
		$ot = Config::$other_titles;
		if(isset($ot[$active])){
			$title = $ot[$active];
		}
	}

	return $title;
}

$uri = trim($_SERVER['REQUEST_URI'],'/');
$chunks = Utils::gen_uri_chunks($uri);

$clonos = new ClonOS($chunks);
$auth = new Auth();
$tpl = new Tpl();
$lang = $tpl->get_lang();

if(isset($_GET['upload'])){
	include('upload.php');
	CBSD::register_media($path,$file,$ext);
	exit;
}
if(isset($_GET['download'])){
	include('download.php');
	CBSD::register_media($path,$file,$ext);
	exit;
}

$menu_config = Config::$menu;

if(empty($uri)){
	header('Location: /'.array_key_first($menu_config).'/',true);
	exit;
} else {
	$uri = $chunks[0];
	$active = trim($uri,'/');
}

$user_info = $auth->userAutologin(); # TODO: Move to SESSION
if($user_info['error']){
	$user_info['username'] = 'guest';
}

$tpl->assign([
	"version" => Config::$version,
	"user_info" => $user_info,
	"title" => get_title($menu_config, $active),
	"uri" => $uri,
	"isDev" => (getenv('APPLICATION_ENV') == 'development'), # TODO: Move to SESSION
	"lang" => $lang,
	"langs" => Config::$languages,
	"menu_active" => $active,
	"menu_conf" => $menu_config
]);
$tpl->draw("index.1");


switch ($url){
	case "authkey":
		$tpl->draw('dialogs/authkey');
		$tpl->draw('pages/authkey.'.$lang);
		break;
	case "bases":
		$tpl->assign('clonos', $clonos);
		$tpl->assign('baseCompileList', $clonos->getBasesCompileList());
		$tpl->draw('dialogs/bases');
		$tpl->draw('dialogs/bases-repo');
		$tpl->draw('pages/bases.'.$lang);
		break;
	case "bhyvevms":
		$tpl->assign('clonos', $clonos);
		$tpl->draw('dialogs/vnc-bhyve');
		$tpl->assign("media_iso_list", $clonos->media_iso_list());
		list($vm_res, $min_id) = $clonos->vm_packages_list();
		$tpl->assign([
			"vm_res" => $vm_res,
			"min_id" => $min_id,
			"ifs" => $clonos->get_interfaces(),
			"os_types_obtain" => $clonos->os_types_create('obtain'),
			"os_types" => $clonos->os_types_create()],
			"authkeys_list" => $clonos->authkeys_list()
		]);
		$tpl->draw('dialogs/bhyve-new');
		$tpl->draw('dialogs/bhyve-obtain');
		$tpl->draw('dialogs/bhyve-clone');
		$tpl->draw('dialogs/bhyve-rename');
		$tpl->draw('dialogs/jail-settings-config-menu');
		$tpl->draw('pages/bhyvevms.'.$lang);
		break;
	case "imported":
		$tpl->draw('dialogs/jail-import');
		$tpl->draw('dialogs/image-import');
		//$tpl->draw('dialogs/jail-settings-config-menu');
		$tpl->draw('pages/imported.'.$lang);
		break;
	case "instance_jail":
		$tpl->draw('pages/instance_jail.'.$lang);
		break;
	case "k8s":
		$tpl->draw('dialogs/k8s-new');
		$tpl->draw('pages/k8s.'.$lang);
		break;
	case "media":
		$tpl->draw('dialogs/media-upload');
		$tpl->draw('pages/media.'.$lang);
		break;
	case "nodes":
		break;
	case "overview":
		$tpl->draw('pages/overview.'.$lang);
		break;
	case "settings": # TODO
		$tpl->draw('pages/settings.'.$lang);
		break;
	default:
		$file_name = 'pages/'.$uri.'/'.$lang.'.index.php';
		if(file_exists($file_name)){
			include($file_name);
		} else {
			echo '<h1>Not implemented yet!</h1>';
		}
}

$tpl->draw("index.2");