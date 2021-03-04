<?php
$tpl->assign('clonos', $clonos);
$tpl->draw('dialogs/vnc-bhyve');
$tpl->assign("media_iso_list", $clonos->media_iso_list());
list($vm_res, $min_id) = $clonos->vm_packages_list();
$tpl->assign("vm_res", $vm_res);
$tpl->assign("min_id", $min_id);
$tpl->assign("ifs", $clonos->get_interfaces());
$tpl->assign("os_types_obtain", $clonos->os_types_create('obtain'));
$tpl->assign("os_types", $clonos->os_types_create());
$tpl->assign("authkeys_list", $clonos->authkeys_list());
$tpl->draw('dialogs/bhyve-new');
$tpl->draw('dialogs/bhyve-obtain');
$tpl->draw('dialogs/bhyve-clone');
$tpl->draw('dialogs/bhyve-rename');
$tpl->draw('dialogs/jail-settings-config-menu');
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
