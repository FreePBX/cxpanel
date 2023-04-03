<?php
//Bootstrap FreePBX
$bootstrap_settings['freepbx_auth'] = false;
include '/etc/freepbx.conf';

//Query the server information
if(\FreePBX::Modules()->checkStatus('cxpanel'))
{
	$cxpanel 	 = \FreePBX::Cxpanel();
	$redirectUrl = $cxpanel->getClientURL();
	$infoStatus  = $cxpanel->checkOnline($redirectUrl, true);

	if($infoStatus['status'])
	{
		header('Location: ' . $redirectUrl);
		die;
	}
	echo "<p>"._("Offline system!")."</p>";
	echo "<p>";
	if (!empty($infoStatus['error'])) 
	{
		echo $infoStatus['error'];
	}
	else
	{
		echo sprintf(_("The system is not accessible at this time, an error occurred %s."), $infoStatus['info']['http_code']);
	}
	echo "</p>";
	echo sprintf('<p><a href="#"  onclick="location.reload();">%s</a></p>',_("Click here to try again"));
	die;
}
else
{
	echo _("Error: Module not installed!");
	die;
}