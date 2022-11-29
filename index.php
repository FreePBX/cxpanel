<?php

//Bootstrap FreePBX
$bootstrap_settings['freepbx_auth'] = false;
include '/etc/freepbx.conf';

//Query the server information
if(\FreePBX::Modules()->checkStatus('cxpanel'))
{
	$redirectUrl = \FreePBX::Cxpanel()->getClientURL();
	header('Location: ' . $redirectUrl);
}
else
{
	echo _("Module not installed");
	die;
}