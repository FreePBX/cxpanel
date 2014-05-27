<?php

//Bootstrap FreePBX
$bootstrap_settings['freepbx_auth'] = false;
if(!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}

//Query the server information
if(function_exists('cxpanel_server_get')) {
	$serverInformation = cxpanel_server_get();
} else {
	echo "Module not installed";
	die;
}

//Reidrect to the client
$redirectUrl = 'http://' . $serverInformation['client_host'] . ':' . $serverInformation['client_port'] . '/client/client';
header('Location: ' . $redirectUrl);
?>
