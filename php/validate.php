<?php

class Validate {

	public function form(array $list, array $form)
	{
		foreach($list as $e => $type){
			if (!isset($form[$e])){
				throw new Exception('$e." is not set in form');
			}
		}

		foreach($list as $e => $type){

			switch($type){
				case 1: # INT
					$e = (int)$e;
					break;
				case 2: # INT 0 not accepted
					$e = (int)$e;
					if($e == 0){
						throw new Exception('$e." can't be 0");
					}
					break;
				case 3: # SHORT STRING
					if (filter_var($e, FILTER_SANITIZE_STRING) != $e){
						throw new Exception($e." string did not pass the validation");
					}
					$len = strlen($e);
					if ($len < 1 || $len > 20){
						throw new Exception($e." string did not pass the lenght validation");
					}
					break;
				case 4: # LONG STRING
					if (filter_var($e, FILTER_SANITIZE_STRING) != $e){
						throw new Exception($e." string did not pass the validation");
					}
					$len = strlen($e);
					if ($len < 1 || $len > 150){
						throw new Exception($e." string did not pass the lenght validation");
					}
					break;
				case 5: # STRING WITH SPECIAL
					if (filter_var($e, FILTER_SANITIZE_SPECIAL_CHARS) != $e){
						throw new Exception($e." string did not pass the validation");
					}
					$len = strlen($e);
					if ($len < 1 || $len > 20){
						throw new Exception($e." string did not pass the lenght validation");
					}
			}

			switch($e){
				case 'password':
					if ($len < 6){
						throw new Exception("Minimal password lenght is 6");
					}
					break;
			}
		}
	}

}