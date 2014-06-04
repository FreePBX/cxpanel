<?php 

$cxpanelBrandName = file_get_contents('/etc/schmooze/operator-panel-brand');
if($cxpanelBrandName === FALSE || trim($cxpanelBrandName) == "") {
	$cxpanelBrandName = 'iSymphony';
}

$cxpanelBrandName = trim($cxpanelBrandName);

