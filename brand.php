<?php

$cxpanelBrandName = FALSE;
$brandFile = '/etc/schmooze/operator-panel-brand';

if (file_exists($brandFile)) {
	$cxpanelBrandName = file_get_contents($brandFile);
}

if($cxpanelBrandName === FALSE || trim($cxpanelBrandName) == "") {
	$cxpanelBrandName = 'iSymphony';
}

$cxpanelBrandName = trim($cxpanelBrandName);
