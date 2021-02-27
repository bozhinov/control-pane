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
		if(isset(Config::$other_titles[$active])){
			$title = $other_titles[$active];
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

$file_name = 'pages/'.$uri.'/'.$lang.'.index.php';
if(file_exists($file_name)){
	include($file_name);
} else {
	echo '<h1>Not implemented yet!</h1>';
}

$tpl->draw("index.2");