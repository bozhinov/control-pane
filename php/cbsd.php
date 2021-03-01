<?php

class CBSD {

	static function run($cmd, $args){

		$prepend = 'env NOCOLOR=1 /usr/local/bin/sudo /usr/local/bin/cbsd ';
		$defines = [
			'{cbsd_loc}' => "/usr/local/bin/cbsd"
		];

		$specs = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'r']
		];

		$cmd = vsprintf($cmd, $args);
		$cmd = strtr($cmd, $defines);
		$full_cmd = $prepend.trim($cmd);

		if ($cmd != escapeshellcmd($cmd)){
			die("Shell escape attempt");
		}

		$process = proc_open($full_cmd, $specs, $pipes, null, null);

		$error = false;
		$error_message = '';
		$message = '';

		if (is_resource($process)){

			$buf = stream_get_contents($pipes[1]);
			# TODO grab output from the std pipe
			$buf0 = stream_get_contents($pipes[0]);
			$buf1 = stream_get_contents($pipes[2]);
			fclose($pipes[0]);
			fclose($pipes[1]);
			fclose($pipes[2]);

			$return_value = proc_close($process);
			if($return_value == 0){
				$message = trim($buf); 
			} else {
				$error = true;
				$error_message = $buf;
			}

			return [
				'cmd' => $cmd,
				'full_cmd' => $full_cmd,
				'retval' => $return_value,
				'message' => $message,
				'error' => $error,
				'error_message' => $error_message
			];
		}
	}

	static function register_media($path, $file, $ext)
	{
		$cmd = 'cbsd media mode=register name=%s path=%s type=%s';
		$res = self::run($cmd, [$file, $path.$file, $ext]);
		echo json_encode($res);
	}
}

