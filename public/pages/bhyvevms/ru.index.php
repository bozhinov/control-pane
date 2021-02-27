<?php
$tpl->draw('dialogs\vnc-bhyve');
$tpl->assign("media_iso_list", $this->media_iso_list());
$tpl->draw('dialogs\bhyve-new');
$tpl->assign("authkeys_list", $this->authkeys_list());
$tpl->draw('dialogs\bhyve-obtain');
$tpl->draw('dialogs\bhyve-clone');
$tpl->draw('dialogs\bhyve-rename');
$tpl->draw('dialogs\jail-settings-config-menu');
?>
<h1>Виртуальные машины</h1>

<p>
	<span class="top-button icon-plus id:bhyve-new">Создать из ISO</span>
	<span class="top-button icon-plus id:bhyve-obtain">Cloud образы</span>
</p>

<table class="tsimple" id="bhyveslist" width="100%">
	<thead>
		<th class="wdt-120">Имя сервера</th>
		<th class="txtleft">Виртуальная машина</th>
		<th class="wdt-120">Нагрузка</th>
		<th class="txtcenter wdt-70">RAM</th>
		<th class="wdt-30">CPU</th>
		<th class="txtcenter wdt-100">Тип ОС</th>
		<th class="txtcenter wdt-120">Статус</th>
		<th colspan="4" class="wdt-100">Действия</th>
		<th class="wdt-30">VNC</th>
		<th class="txtcenter wdt-50">VNC порт</th>
	</thead>
	<tbody></tbody>
</table>
