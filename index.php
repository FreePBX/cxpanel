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

/*
 * If set utilize the client_host stored in the database else utilize the host
 * from the current URL.
 */
$clientHost = $serverInformation['client_host'];
if($clientHost == "") {
	$httpHost = explode(':', $_SERVER['HTTP_HOST']);
	$clientHost = $httpHost[0];
}

//Reidrect to the client
$redirectUrl = 'http://' . $clientHost . ':' . $serverInformation['client_port'] . '/client/client';
header('Location: ' . $redirectUrl);

