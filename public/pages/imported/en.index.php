<?php
$tpl->draw('dialogs/jail-import');
$tpl->draw('dialogs/image-import');
//$tpl->draw('dialogs/jail-settings-config-menu');
?>
<h1>Imported images:</h1>

<span class="top-button icon-upload id:jail-import">Import</span></p>

<table class="tsimple" id="impslist" width="100%">
	<thead>
		<td class="keyname">Image name</td>
		<td class="txtcenter wdt-120 impsize">Size</td>
		<td class="txtleft wdt-150">Type</td>
		<th class="txtcenter wdt-120">Status</th>
		<td class="wdt-80">Action</td>
	</thead>
	<tbody></tbody>
</table>