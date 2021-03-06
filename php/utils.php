<?php

class Utils
{
	public static function gen_uri_chunks($uri)
	{
		$uri_chunks = [];
		if(!empty($uri)){
			$str = str_replace('/index.php', '', $uri);
			$uri_chunks = explode('/', $str);
		}else if(isset($_POST['path'])){
			$str = trim($_POST['path'], '/');
			Validate::url($str);
			$uri_chunks = explode('/', $str);
		}

		foreach ($uri_chunks as $u){
			Validate::short_string($u);
		}

		return $uri_chunks;
	}
}