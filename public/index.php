<?php

require_once('../php/auth.php');
require_once('../php/clonos.php');
require_once('../php/tpl.php');

$uri = trim($_SERVER['REQUEST_URI'], '/');
$chunks = Utils::gen_uri_chunks($uri);

$clonos = new ClonOS($chunks);
$auth = new Auth();
$tpl = new Tpl();
$lang = $tpl->get_lang();

if(isset($_GET['upload'])){
	include('upload.php');
	CBSD::register_media($path, $file, $ext);
	exit;
}
if(isset($_GET['download'])){
	include('download.php');
	CBSD::register_media($path, $file, $ext);
	exit;
}

$menu_config = Config::$menu;

if(empty($uri)){
	header('Location: /'.array_key_first($menu_config).'/', true);
	exit;
} else {
	$uri = $chunks[0];
	$active = trim($uri, '/');
}

$user_info = $auth->userAutologin(); # TODO: Move to SESSION
if($user_info['error']){
	$user_info['username'] = 'guest';
}

$tpl->assign([
	"version" => Config::$version,
	"user_info" => $user_info,
	"title" => Config::get_title($active),
	"uri" => $uri,
	"isDev" => (getenv('APPLICATION_ENV') == 'development'), # TODO: Move to SESSION
	"lang" => $lang,
	"langs" => Config::$languages,
	"menu_active" => $active,
	"menu_conf" => $menu_config
]);
$tpl->draw("index.1");


switch ($active){
	case "authkey":
		$tpl->draw('dialogs/authkey');
		$tpl->draw('pages/'.$lang.'/authkey');
		break;
	case "bases":
		$tpl->assign('clonos', $clonos);
		$tpl->assign('baseCompileList', $clonos->getBasesCompileList());
		$tpl->draw('dialogs/bases');
		$tpl->draw('dialogs/bases-repo');
		$tpl->draw('pages/'.$lang.'/bases');
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
			"os_types" => $clonos->os_types_create(),
			"authkeys_list" => $clonos->authkeys_list()
		]);
		$tpl->draw('dialogs/bhyve-new');
		$tpl->draw('dialogs/bhyve-obtain');
		$tpl->draw('dialogs/bhyve-clone');
		$tpl->draw('dialogs/bhyve-rename');
		$tpl->draw('dialogs/jail-settings-config-menu');
		$tpl->draw('pages/'.$lang.'/bhyvevms');
		break;
	case "imported":
		$tpl->draw('dialogs/jail-import');
		$tpl->draw('dialogs/image-import');
		//$tpl->draw('dialogs/jail-settings-config-menu');
		$tpl->draw('pages/'.$lang.'/imported');
		break;
	case "instance_jail":
		$tpl->draw('pages/'.$lang.'/instance_jail');
		break;
	case "jailscontainers":
		if(isset($chunks[1])){
			$tpl->assign('chunk', $chunks[1]);
			$tpl->draw('dialogs/helpers-add');
			$tpl->draw('pages/'.$lang.'/jailscontainers_helper');
		}
		$tpl->draw('dialogs/vnc');
		$tpl->draw('dialogs/jail-settings');
		$tpl->draw('dialogs/jail-settings-config-menu');
		$tpl->draw('dialogs/jail-import');
		$tpl->draw('dialogs/jail-clone');
		$tpl->draw('dialogs/jail-rename');
		$tpl->draw('pages/'.$lang.'/jailscontainers');
		break;
	case "k8s":
		$tpl->draw('dialogs/k8s-new');
		$tpl->draw('pages/'.$lang.'/k8s');
		break;
	case "media":
		$tpl->draw('dialogs/media-upload');
		$tpl->draw('pages/'.$lang.'/media');
		break;
	case "nodes":
		break;
	case "overview":
		$tpl->draw('pages/'.$lang.'/overview');
		break;
	case "settings": # TODO
		require_once('../php/forms2.php');
		$settings_tpl = (new Forms2())->generate();
		$tpl->assign('settings_tpl', $settings_tpl);
		$tpl->draw('pages/'.$lang.'/settings');
		break;
	case "sources":
		$tpl->assign('clonos', $clonos);
		$tpl->draw('dialogs/src-get');
		$tpl->draw('pages/'.$lang.'/sources');
		break;
	case "sqlite":
		$tpl->draw('pages/'.$lang.'/sqlite');
		break;
	case "tasklog":
		$tpl->draw('dialogs/tasklog');
		$tpl->draw('pages/'.$lang.'/tasklog');
		break;
	case "users":
		$tpl->draw('dialogs/users-new');
		$tpl->draw('pages/'.$lang.'/users');
		break;
	case "vm_packages":
		$tpl->draw('dialogs/vm_packages-new');
		$tpl->draw('pages/'.$lang.'/vm_packages');
		break;
	case "vpnet":
		$tpl->draw('dialogs/vpnet');
		$tpl->draw('pages/'.$lang.'/vpnet');
		break;
			echo '<h1>Not implemented yet!</h1>';
}

$tpl->draw("index.2");