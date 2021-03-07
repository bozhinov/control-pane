<?php

class Localization
{
	private $language = 'en';
	private $translate_arr = [];

	function __construct()
	{
		if (isset($_COOKIE['lang'])){
			$this->language = $_COOKIE['lang'];
			Validate::short_string($this->language);
		}
		(!array_key_exists($this->language, Config::$languages)) AND $this->language = 'en';
		include('../public/lang/'.$this->language.'.php');
		$this->translate_arr = $lang;
	}

	public function get_lang()
	{
		return $this->language;
	}

	public function translate($phrase)
	{
		return (isset($this->translate_arr[$phrase])) ? $this->translate_arr[$phrase] : $phrase;
	}
}