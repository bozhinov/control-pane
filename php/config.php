<?php

require_once("cbsd.php");

class Config
{
	public static $version = '20.12';
	/* Список языков, используемых в проекте */
	public static $languages = [
		'en' => 'English',
		'ru' => 'Russian'
	];

	public $os_types_names = [
		'netbsd' => 'NetBSD',
		'dflybsd' => 'DragonflyBSD',
		'linux' => 'Linux',
		'other' => 'Other',
		'freebsd' => 'FreeBSD',
		'openbsd' => 'OpenBSD',
		'windows' => 'Windows'
	];

	public static $other_titles = [
		'settings' => 'CBSD Settings',
		'users' => 'CBSD Users'
	];

	/* Меню проекта */
	/* Так же можно использовать подменю (в menu.php есть пример) */
	public static $menu = [
		'overview' => [
			'name' => 'Overview',
			'title' => 'Summary Overview',	// заголовки лучше делать более полными, чем просто повторение пункта меню
			'icon' => 'icon-chart-bar'
		],
		'jailscontainers' => [
			'name' => 'Jails containers',
			'title' => 'Jails containers control panel',
			'icon' => 'icon-server'
		],
		'instance_jail' => [
			'name' => 'Template for jail',
			'title' => 'Helpers and wizard for containers',
			'icon' => 'icon-cubes'
		],
		'bhyvevms' => [
			'name' => 'Bhyve VMs',
			'title' => 'Virtual machine control panel',
			'icon' => 'icon-th-list'
		],
		/*
		'nodes' =>[
			'name' => 'Nodes',
			'title' => 'Nodes control panel',
			'icon' => 'icon-buffer'
		],
		*/
		'vm_packages' => [
			'name' => 'VM Packages',
			'title' => 'Manage VM Packages group',
			'icon' => 'icon-cubes'
		],
		'k8s' => [
			'name' => 'K8S clusters',
			'title' => 'Manage K8S clusters',
			'icon' => 'icon-cubes'
		],
		'vpnet' => [
			'name' => 'Virtual Private Network',
			'title' => 'Manage for virtual private networks',
			'icon' => 'icon-plug'
		],
		'authkey' => [
			'name' => 'Authkeys',
			'title' => 'Manage for SSH auth key',
			'icon' => 'icon-key'
		],
		'media' => [
			'name' => 'Storage Media',
			'title' => 'Virtual Media Manager',
			'icon' => 'icon-inbox'
		],
		'imported' => [
			'name' => 'Imported images',
			'title' => 'Imported images',
			'icon' => 'icon-upload'
		],
		/*
		'repo' => [
			'name' => 'Repository',
			'title' => 'Remote repository',
			'icon' => 'icon-globe'
		],
		*/
		'bases' => [
			'name' => 'FreeBSD Bases',
			'title' => 'FreeBSD bases manager',
			'icon' => 'icon-database'
		],
		'sources' => [
			'name' => 'FreeBSD Sources',
			'title' => 'FreeBSD sources manager',
			'icon' => 'icon-edit'
		],
		/*
		'jail_marketplace' => [
			'name' => 'Jail Marketplace',
			'title' => 'Public remote containers marketplace',
			'icon' => 'icon-flag'
		],
		*//*
		'bhyve_marketplace' => [
			'name' => 'Bhyve Marketplace',
			'title' => 'Public remote virtual machine marketplace',
			'icon' => 'icon-flag-checkered'
		],
		*/
		'tasklog' => [
			'name' => 'TaskLog',
			'title' => 'System task log',
			'icon' => 'icon-list-alt'
		]
	];

	public $os_types = [
		[
			'os' => 'DragonflyBSD',
			'items' => [
				['name' => 'DragonflyBSD 4', 'type' => 'dflybsd', 'profile' => 'x86-4', 'obtain' => false]
			]
		],
		[
			'os' => 'FreeBSD',
			'items' => [
				['name'=> 'FreeBSD 11.0-RELEASE', 'type' => 'freebsd', 'profile' => 'FreeBSD-x64-11.0', 'obtain' => true],
				['name'=> 'FreeBSD pfSense 2.4.0-DEVELOP', 'type' => 'freebsd', 'profile' => 'pfSense-2-LATEST-amd64', 'obtain' => false],
				['name'=> 'FreeBSD OPNsense-16.7', 'type' => 'freebsd', 'profile' => 'OPNsense-16-RELEASE-amd64', 'obtain' => false]
			]
		],
		[
			'os' => 'Linux',
			'items' => [
				['name' => 'Linux Arch 2016','type' => 'linux', 'profile' => 'ArchLinux-x86-2016', 'obtain' => false],
				['name' => 'Linux CentOS 7', 'type' => 'linux', 'profile' => 'CentOS-7-x86_64', 'obtain' => false],
				['name' => 'Linux Debian 8', 'type' => 'linux', 'profile' => 'Debian-x86-8', 'obtain' => false],
				['name' => 'Linux Open Suse 42', 'type' => 'linux', 'profile' => 'opensuse-x86-42', 'obtain' => false],
				['name' => 'Linux Ubuntu 16.04', 'type' =>'linux', 'profile' => 'ubuntuserver-x86-16.04', 'obtain' => true],
				['name' => 'Linux Ubuntu 17.04', 'type' => 'linux', 'profile' => 'ubuntuserver-x86-17.04', 'obtain' => true]
			]
		],
		[
			'os' => 'Windows',
			'items' => [
				['name' => 'Windows 10', 'type' => 'windows', 'profile '=> '10_86x_64x', 'obtain' => false]
			]
		]
	];

	public $os_types_obtain = [];
	public $os_interface_names = [];

	function __construct()
	{
		$res = CBSD::run('get_bhyve_profiles src=vm clonos=1', []);
		if($res['retval'] == 0){
			$this->os_types = $this->create_bhyve_profiles($res);
		}

		$res1 = CBSD::run('get_bhyve_profiles src=cloud', []);
		if($res1['retval'] == 0){
			$this->os_types_obtain = $this->create_bhyve_profiles($res1);
		}

		$res2 = CBSD::run('cbsd get_interfaces', []);
		$list = [];
		if($res2['retval'] == 0){
			$res = json_decode($info['message'], true);
			if(!is_null($res) && $res != false){
				foreach($res as $item){
					$list[] = $item['name'];
				}
			}
		}
		$this->os_interface_names = $list;
	}

	function create_bhyve_profiles($info)
	{
		$os_names = [];
		$res = json_decode($info['message'], true);
		if(!is_null($res) && $res != false){
			foreach($res as $item){
				$os_name = $this->os_types_names[$item['type']];
				if(isset($os_names[$os_name])){
					$os_names[$os_name]['items'][] = $item;
				} else {
					$os_names[$os_name] = ['os' => $os_name,'items' => [$item]];
				}
			}
		}
		return $os_names;
	}
}