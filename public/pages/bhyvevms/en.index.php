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
<h1>Bhyve VMs</h1>

<p>
	<span class="top-button icon-plus id:bhyve-new">Create from ISO</span>
	<span class="top-button icon-plus id:bhyve-obtain">Cloud images</span>
</p>

<table class="tsimple" id="bhyveslist" width="100%">
	<thead>
		<th class="wdt-120">Node name</th>
		<th class="txtleft">VM</th>
		<th class="wdt-120">Usage</th>
		<th class="txtcenter wdt-70">RAM</th>
		<th class="wdt-30">CPU</th>
		<th class="txtcenter wdt-100">OS type</th>
		<th class="txtcenter wdt-120">Status</th>
		<th colspan="4" class="wdt-100">Action</th>
		<th class="wdt-30">VNC</th>
		<th class="txtcenter wdt-50">VNC port</th>
	</thead>
	<tbody></tbody>
</table>
