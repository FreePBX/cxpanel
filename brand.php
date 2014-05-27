<?php 

$cxpanelBrandName = file_get_contents('/etc/schmooze/operator-panel-brand');
if($cxpanelBrandName === FALSE) {
	$cxpanelBrandName = 'iSymphony';
}

?>