<?php

$brandName = $cxpanel->brandName;

//Set up the REST connection
$pest = $cxpanel->getApiREST();
$licenseAdditions = array();

//Grab the version and license information
try
{
	//Grab the server information
	$brand = $pest->get('server/brand');
	$coreServer = $pest->get('server/coreServers/getBySlug/' . $serverInformation['name']);

	//Handle licensing requests
	try
	{
		/*
		 * Check if a license activation request was made.
		 * This needs to be done before we get query the license information
		 */
		if(isset($_REQUEST["cxpanel_activate_license"]) && !isset($serverErrorMessage))
		{
			$url = sprintf('server/coreServers/%s/license/activate',$coreServer->id);
			$pest->post($url, $_REQUEST["cxpanel_activate_serial_key"], array(CURLOPT_HEADER => TRUE));
			
			//Flag FreePBX for reload
			needreload();
		}
		
		/*
		 * Check if a license bind cancel request was made.
		 */
		if(isset($_REQUEST["cxpanel_bind_license_cancel_flag"]))
		{
			$pest->post($_REQUEST["cxpanel_bind_license_redirect_url"], new \FreePBX\modules\Cxpanel\cxpanel_bind_request(true, "", ""));
		}
		
		/*
		 * Check if a license bind request was made.
		 */
		if(isset($_REQUEST["cxpanel_bind_license"]))
		{
			$pest->post($_REQUEST["cxpanel_bind_license_redirect_url"], new \FreePBX\modules\Cxpanel\cxpanel_bind_request(false, $_REQUEST["cxpanel_bind_license_to"], $_REQUEST["cxpanel_bind_license_email"]));
			
			//Flag FreePBX for reload
			needreload();
		}
		
	}
	catch (\FreePBX\modules\Cxpanel\CXPest_TemporaryRedirect $e)
	{
		$licenseBindRedirectURI = $e->redirectUri;
	}
	catch (Exception $e)
	{
		$licenseActivationErrorMessage = $e->getMessage();
	}


	//Grab the license information
	$license = $pest->get('server/coreServers/' . $coreServer->id . '/license');
	$moduleLicenses = $pest->get('server/coreServers/' . $coreServer->id . '/license/moduleLicenses');
	
	$licenseModuleAdditions = array();
	//Grab the module license properties
	foreach($moduleLicenses as $moduleLicense)
	{
		$moduleLicenseProperties = $pest->get('server/coreServers/' . $coreServer->id . '/license/moduleLicenses/' . $moduleLicense->id . "/properties");
		foreach($moduleLicenseProperties as $moduleLicenseProperty)
		{
			//Only show properties that should be displayed
			if($moduleLicenseProperty->display)
			{
				//If the value of the license property is '-1' show unlimited
				$licensePropertyValue = ($moduleLicenseProperty->value == '-1') ? _('Unlimited') : $moduleLicenseProperty->value;
				
				//Add the property to the list
				$licenseModuleAdditions[] = array(
					'title' 	  => $moduleLicenseProperty->displayName,
					'help'  	  => $moduleLicenseProperty->description,
					'input_type'  => 'raw',
					'input_value' => $licensePropertyValue,
				);
			}
		}
	}
	
	//Store the general license properties
	
	$licenseExpirationDate 		= isset($license->expirationDate) ? date('m/d/Y', $license->expirationDate / 1000) : null;
	$maintenanceExpirationDate 	= isset($license->maintenanceExpirationDate) ? ($license->maintenanceExpirationDate / 1000) : null;

	// If we are running on a PBXact system (XactView operator panel brand), 
	// do not display the maintenance expiration date. This modification was 
	// added at the request of Sangoma, in order to facilitate changes to the
	// licensing process for XactView.
	//
	// See FPBX-36
	$isXactView = $cxpanel->brandName == "XactView";

	//Highlight and format the maintenance expiration date.
	if(isset($maintenanceExpirationDate) && !$isXactView)
	{
		//Check if maintenance has expired or is about to
		$warningPeriod = 30 * 86400;
		if(time() > $maintenanceExpirationDate)
		{
			$maintenanceExpirationDateStyle = 'cxpanel_license_maintenance_expired';
			$maintenanceExpirationDateNote = _('Maintenance has expired.');
		}
		else if(time() > ($maintenanceExpirationDate - $warningPeriod))
		{
			//Calculate days remaining
			$daysRemaining = ($maintenanceExpirationDate - time()) / 86400;
			$daysRemaining = round($daysRemaining, 0, PHP_ROUND_HALF_DOWN);
			
			$maintenanceExpirationDateStyle = 'cxpanel_license_warning_period';
			$maintenanceExpirationDateNote =  sprintf(_('Maintenance will expire in <span class="cxpanel_license_days">%s day(s)</span>.'), $daysRemaining);
		}
		
		//Format date
		$maintenanceExpirationDate = date('m/d/Y', $maintenanceExpirationDate);
		
		if(isset($maintenanceExpirationDateNote))
		{
			$maintenanceExpirationDate = sprintf("%s - %s", $maintenanceExpirationDate, $maintenanceExpirationDateNote);
		}
		if(isset($maintenanceExpirationDateStyle))
		{
			$maintenanceExpirationDate = sprintf('<span class="%s">%s</span>', $maintenanceExpirationDateStyle, $maintenanceExpirationDate);
		}
	}
	
	//Build the license additions
	if(isset($licenseExpirationDate))
	{
		$licenseAdditions[] = array(
			'title' 	  => _("Expiration Date"),
			'help'  	  => _("Displays the expiration date of the trial license."),
			'input_type'  => 'raw',
			'input_value' => $licenseExpirationDate,
		);
	}
	if(isset($maintenanceExpirationDate) && !$isXactView)
	{
		$licenseAdditions[] = array(
			'title' 	  => _("Maint. Expiration Date"),
			'help'  	  => _("Displays the expiration date of the license maintenance period."),
			'input_type'  => 'raw',
			'input_value' => $maintenanceExpirationDate,
		);
	}
	if($license->clientConnections != -1)
	{
		$licenseAdditions[] = array(
			'title' 	  => _("Clients"),
			'help'  	  => _("Displays the number of licensed client connections."),
			'input_type'  => 'raw',
			'input_value' => $license->clientConnections,
		);
	}
	if($license->configuredUsers != -1)
	{
		$licenseAdditions[] = array(
			'title' 	  => _("Users"),
			'help'  	  => _("Displays the total number of users that can be enabled."),
			'input_type'  => 'raw',
			'input_value' => $license->configuredUsers,
		);
	}
	if (! empty($licenseModuleAdditions))
	{
		$licenseAdditions = array_merge($licenseAdditions, $licenseModuleAdditions);
	}

	
	/*
	 * Create the license activation button if we are not handling the license bind.
	 * If we are handleing the license bind create the license bind form.
	 */
	if(isset($licenseBindRedirectURI))
	{
		$licenseBindAddition = array(
			array(
				'input_type' => 'form',
				'input_name' => 'cxpanel_bind_license_form',
				'method' 	 => 'post',
				'action' 	 => 'config.php?type=setup&display=cxpanel',
			),
			array(
				'input_type'  => 'hidden',
				'input_name'  => 'cxpanel_bind_license',
				'input_value' => 'cxpanel_bind_license',
			),
			array(
				'input_type'  => 'hidden',
				'input_name'  => 'cxpanel_bind_license_redirect_url',
				'input_value' => $licenseBindRedirectURI,
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('<div class="alert alert-warning" role="alert"><b>%s</b>: %s</div>', _("ATTENTION"), _("This license is being bound for the first time or is moving servers.</br>Please fill out the information below in order to complete the activation or you can cancel the activation.")),
			),
			array(
				'title' 	  => _("Licensed To:"),
				'help'  	  => _("Enter the name of the person or company this server is licensed to."),
				'input_type'  => 'text',
				'input_name'  => "cxpanel_bind_license_to",
				'input_value' => "",
			),
			array(
				'title' 	  => _("Email:"),
				'help'  	  => _("Enter the email address of the person or company this server is licensed to."),
				'input_type'  => 'text',
				'input_name'  => "cxpanel_bind_license_email",
				'input_value' => "",
				'placeholder' => 'user@domain.tld',
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('
					<div class="row">
						<div class="form-group">
							<div class="col-md-3"></div>
							<div class="col-md-9">
								<button type="Button" class="btn btn-danger btn-bind-license-cancel">%s</button>
								<button type="Button" class="btn btn-success btn-bind-license">%s</button>
							</div>
						</div>
					</div>
				', _("Cancel"), _("Activate")),
			),
			array('raw' => '</form>'),
			
			array(
				'input_type' => 'form',
				'input_name' => 'cxpanel_bind_license_cancel_form',
				'method' 	 => 'post',
				'action' 	 => 'config.php?type=setup&display=cxpanel',
			),
			array(
				'input_type'  => 'hidden',
				'input_name'  => 'cxpanel_bind_license_cancel_flag',
				'input_value' => 'true',
			),
			array(
				'input_type'  => 'hidden',
				'input_name'  => 'cxpanel_bind_license_redirect_url',
				'input_value' => $licenseBindRedirectURI,
			),
			array('raw' => '</form>'),
		);
	}
	else
	{
		$licenseActivateAddition = array(
			array(
				'input_type' => 'form',
				'input_name' => 'cxpanel_activate_license_form',
				'method' 	 => 'post',
				'action' 	 => 'config.php?type=setup&display=cxpanel',
			),
			array(
				'input_type'  => 'hidden',
				'input_name'  => 'cxpanel_activate_license',
				'input_value' => 'cxpanel_activate_license',
			),
		 	array(
				'title' 	  => _("Activate:"),
				'help'  	  => _("Activates a license with a given serial key."),
				'input_type'  => 'raw',
				'input_value' => sprintf('
    				<div class="input-group">
      					<input type="text" name="cxpanel_activate_serial_key" id="cxpanel_activate_serial_key" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="form-control">
      					<span class="input-group-btn">
        					<button class="btn btn-default btn-activate-license" type="button">%s <i class="fa fa-refresh" aria-hidden="true"></i></button>
      					</span>
    				</div>
					', _("Activate")
				),
			),
			array('raw' => '</form>'),
		);
	}
}
catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
{
	$serverErrorMessage = _('The specified core server has not been created yet.</br>If this is the first installation run "Apply Config" in order to create the core server.</br>If you believe this is an error verify your "Server Name" below.');
}
catch (\FreePBX\modules\Cxpanel\CXPest_Forbidden $e)
{
	$serverErrorMessage = sprintf(_('This server is not allowed to access the %1$s server.</br>Modify the %1$s server\'s security.xml file to include this server\'s IP address in the whitelist of the communication_mananger servlet security settings.</br>You will have to restart your %1$s server once the change has been made.'), $brandName);
}
catch (\FreePBX\modules\Cxpanel\CXPest_Unauthorized $e)
{
	$serverErrorMessage = sprintf(_('Failed to authenticate with the %1$s server.</br>Verify that the "Server API Username" and "Server API Password" below are correct.</br>Also verify that you have a proper realm auth user defined in the %1$s server\'s security.xml file for the communication_manager servlet security settings.'), $brandName);
}
catch (\FreePBX\modules\Cxpanel\CXPest_UnknownResponse $e)
{
	$serverErrorMessage = sprintf(_('Failed to contact the %1$s server.</br>Verify that your %1$s server is installed and running and that the server API host and port are correct in the fields below.<br/>If you have SSL enabled below and are using the SSL port for the API connection you need to enable SSL in the %1$s server\'s security.xml file for the communication_manager servlet.'), $brandName);
}
catch (\FreePBX\modules\Cxpanel\CXPest_Found $e)
{
	$serverErrorMessage = sprintf(_('Failed to connect to the %1$s server.<br/>The %1$s server is secured via SSL.<br/> Set the API port below to the SSL port of the %1$s server and check "Use SSL".<br/>The server\'s SSL port can be found in the main.xml file. Default is 55050.'), $brandName);
}
catch (Exception $e)
{
	$serverErrorMessage = sprintf(_("An unexpected error occurred while trying to connect to the %s server.</br>%s"), $brandName, $e->getMessage());
}


//If the userman module is installed show the general settings
if(function_exists('setup_userman'))
{
	$syncWithUsermanAddition = array(
		'title' 		=> _("Sync With User Managment:"),
		'help'  		=> sprintf(_('If checked  %1$s users will be created based on the users that are configured in User Managment.<br />If unchecked  %1$s users will be created based on the  %1$s settings in the Extensions page.'), $brandName),
		'input_type' 	=> 'checkbox',
		'input_name' 	=> 'cxpanel_sync_with_userman',
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['sync_with_userman'] == '1'),
	);
}

//Grab the email settings information 
$emailSettings = $cxpanel->email_get();

//Grab the voicemail agent information
$voicemailAgentInformation = $cxpanel->voicemail_agent_get();

//Grab the recording agent information
$recordingAgentInformation = $cxpanel->recording_agent_get();

$table_lines = array(
	array(
		'raw' => empty($licenseActivationErrorMessage) ? '' : sprintf('<div class="alert alert-danger" role="alert">%s</div>', $licenseActivationErrorMessage),
	),

	array(
		'name'  => 'activation',
		'title' => _('Activation'),
	),
	array(
		// Activate License
		'type' => "list",
		'list' => empty($licenseActivateAddition) ? array() : $licenseActivateAddition,
	),
	array(
		// Bind License
		'type' => 'list',
		'list' => empty($licenseBindAddition) ? array() : $licenseBindAddition,
	),
	array('type' => 'endsec'),


	array(
		'name'  => 'server',
		'title' => _('Server'),
	),
	array(
		'colspan' 	  => true,
		'colspan_raw' => empty($serverErrorMessage) ? '' : sprintf('<div class="alert alert-danger" role="alert">%s</div>', $serverErrorMessage),
	),
	array(
		'title' => _("Connected:"),
		'help'  => sprintf(_("Displays if the module can connect to the %s server. If not the server may not be running or the connection information below may be incorrect."), $brandName),
		'value' => sprintf('<span class="cxpanel_connection_%s">%s</span>',  (isset($serverErrorMessage) ? "no" : "yes"), (isset($serverErrorMessage) ? _("NO") : _("YES"))),
		//If an error occurred in the server query show disconnected else show connected
	),
	array(
		'title' => _("Version:"),
		'help'  => _("Displays the version of the server."),
		'value' => (empty($brand)) ? _("Unknown") : sprintf("%s build %s", $brand->version, $brand->build),
	),
	array('type' => 'endsec'),


	array(
		'name'  => 'license',
		'title' => _('License'),
	),
	array(
		'title' => _("Licensed To:"),
		'help'  => _("Displays the name of the person or company this server is licensed to."),
		'value' => (empty($license)) ? _("Unknown") : $license->licensedTo,
	),
	array(
		'title' => _("Serial Key:"),
		'help'  => _("Displays the serial key of the installed license."),
		'value' => (empty($license)) ? _("Unknown") : $license->serial,
	),
	array(
		'title' => _("Type:"),
		'help'  => _("The license type."),
		'value' => (empty($license)) ? _("Unknown") : $license->type,
	),
	array(
		// Info License
		'type' => "list",
		'list' => empty($licenseAdditions) ? array() : $licenseAdditions,
	),
	array('type' => 'endsec'),


	array (
		'name'  => 'settings',
		'title' => _('General Settings'),
	),
	array(
		'input_type' => 'form',
		'input_name' => 'cxpanel_settings_form',
		'method' 	 => 'post',
		'action' 	 => 'config.php?type=setup&display=cxpanel',
	),
	array(
		'input_type'  => 'hidden',
		'input_name'  => 'cxpanel_settings',
		'input_value' => 'cxpanel_settings',
	),
	$syncWithUsermanAddition,
	array(
		'title' 		=> _("Clean Unknown Items:"),
		'help'  		=> sprintf(_('If selected, all items on the %1$s server that are not configured in FreePBX will be removed.<br/><br/>If not selected, only items created by this instance of the module will be removed if they are no longer configured in FreePBX.<br/><br/>For example, you should disable this option if utilizing a single %1$s core server managing multiple PBXs, as this instance of the module should not remove items that were created by other instances of the module on the other PBXs.'), $brandName),
		'input_type'	=> 'checkbox',
		'input_name' 	=> 'cxpanel_clean_unknown_items',
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['clean_unknown_items'] == '1'),
	),
	array('type' => 'endsec'),


	array (
		'name'  => 'settings_api',
		'title' => _('Server API Connection Settings'),
	),
	array(
		'title' 	  => _("Server Name:"),
		'help'  	  => _("Unique id of the core server instance to manage."),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_name",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['name']),
		'placeholder' => $cxpanel->defaultVal['api']['name'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Host:"),
		'help'        => sprintf(_('IP Address or host name of the %s server API. Set to "localhost" if the server is installed on the same machine.'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_api_host",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['api_host']),
		'placeholder' => $cxpanel->defaultVal['api']['host'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	 => _("Port:"),
		'help'  	 => sprintf(_('Port of the %1$s server API.<br/><br/>Default Port: 58080<br/>Default SSL Port: 55050 (SSL is disalbed by default on the %1$s server. See the %1$s server security.xml file.)'), $brandName),
		'input_type' => 'number',
		'input_name' => "cxpanel_api_port",
		'input_size' => "5",
		'input_limit'=> array(
			"min" => "1",
			"max" => "65535",
		),
		'input_value' => htmlspecialchars($serverInformation['api_port']),
		'placeholder' => $cxpanel->defaultVal['api']['port'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Username:"),
		'help'  	  => sprintf(_('Username used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the %s server config directory under the communication_manager servlet security settings.'), $brandName),
		'help_show'   => true,
		'input_type'  => 'text',
		'input_name'  => "cxpanel_api_username",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['api_username']),
		'placeholder' => $cxpanel->defaultVal['api']['username'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Password:"),
		'help'  	  => sprintf(_('Password used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the %s server config directory under the communication_manager servlet security settings.'), $brandName),
		'help_show'   => true,
		'input_type'  => 'password',
		'input_name'  => "cxpanel_api_password",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['api_password']),
		// 'placeholder' => $cxpanel->defaultVal['api']['password'],
	),
	array(
		'title' 		=> _("Use SSL:"),
		'help'  		=> sprintf(_('Check this option, if you have endabled SSL on the %1$s server API.<br/><br/>NOTE: If checked your %1$s server must have an SSL keystore configured and the communication_manager servlet security context must have SSL enabled in the security.xml file.</br>You will also need to specify the SSL port number in the API port field above.'), $brandName),
		'help_show'   	=> true,
		'input_type' 	=> 'checkbox',
		'input_name' 	=> "cxpanel_api_use_ssl",
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['api_use_ssl'] == '1'),
	),
    array('type' => 'endsec'),


	array (
		'name'  => 'settings_asterisk',
		'title' => _('Asterisk Connection Settings'),
	),
	array(
		'title' 	  => _("Asterisk Server Host:"),
		'help'  	  => sprintf(_('The ip or hostname of the Asterisk server. This is used to tell the %1$s server how to connect to Asterisk. If the %1$s server and Asterisk are on the same machine this field should be set to "localhost".'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_asterisk_host",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['asterisk_host']),
		'placeholder' => $cxpanel->defaultVal['asterisk']['host'],
		'btn_reset'	  => true,
	),
	array('type' => 'endsec'),
	

	array (
		'name'  => 'module_cli',
		'title' => _('Module Client Link Settings'),
	),
	array(
		'title' 	  => _("Client Host:"),
		'help'  	  => sprintf(_('IP Address or host name of the %1$s client. This setting is used when accessing the %1$s client via the links in this GUI and for client links in password emails. If not set the ip or host name from the current URL will be utilized. Normally this should remain blank unless you have a remote %1$s Server install.'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_client_host",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($serverInformation['client_host']),
		'placeholder' => $cxpanel->defaultVal['client']['host'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	 => _("Client Port:"),
		'help'  	 => sprintf(_('Web port of the %s client.'), $brandName),
		'input_type' => 'number',
		'input_name' => "cxpanel_client_port",
		'input_size' => "5",
		'input_limit'=> array(
			"min" => "1",
			"max" => "65535",
		),
		'input_value' => htmlspecialchars($serverInformation['client_port']),
		'placeholder' => $cxpanel->defaultVal['client']['port'],
		'btn_reset'	  => true,
	),
	array(
		'title' 		=> _("Use SSL:"),
		'help'  		=> sprintf(_('Check this option, if you have enabled SSL on the %1$s client interface.<br/><br/>NOTE: If checked your %1$s server must have an SSL keystore configured and the client servlet security context must have SSL enabled in the security.xml file.</br>You will also need to specify the SSL port number in the Client Port field above.'), $brandName),
		'input_type' 	=> 'checkbox',
		'input_name' 	=> "cxpanel_client_use_ssl",
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['client_use_ssl'] == '1'),
	),
	array('type' => 'endsec'),


	array (
		'name'  => 'settings_voicemail',
		'title' => _('Voicemail Agent Settings'),
	),
	array(
		'title' 	  => _("Identifier:"),
		'help'  	  => _('Identifier of the voicemail agent to bind and configure.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_identifier",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($voicemailAgentInformation['identifier']),
		'placeholder' => $cxpanel->defaultVal['voicemail']['identifier'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Directory:"),
		'help'  	  => _('Path to the root voicemail directory.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_directory",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($voicemailAgentInformation['directory']),
		'placeholder' => $cxpanel->defaultVal['voicemail']['directory'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Resource Host:"),
		'help'  	  => _('Hostname or IP used to build voicemail playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_resource_host",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($voicemailAgentInformation['resource_host']),
		'placeholder' => $cxpanel->defaultVal['voicemail']['resource_host'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Resource Extension:"),
		'help'  	  => _('File extension used to build voicemail playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_resource_extension",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($voicemailAgentInformation['resource_extension']),
		'placeholder' => $cxpanel->defaultVal['voicemail']['resource_extension'],
		'btn_reset'	  => true,
	),
	array('type' => 'endsec'),


	array (
		'name'  => 'settings_recording',
		'title' => _('Recording Agent Settings'),
	),
	array(
		'title' 	  => _("Identifier:"),
		'help'  	  => _('Identifier of the recording agent to bind and configure.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_identifier",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($recordingAgentInformation['identifier']),
		'placeholder' => $cxpanel->defaultVal['recording']['identifier'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Directory:"),
		'help'  	  => _('Path to the root recording directory.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_directory",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($recordingAgentInformation['directory']),
		'placeholder' => $cxpanel->defaultVal['recording']['directory'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Resource Host:"),
		'help'  	  => _('Hostname or IP used to build recording playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_resource_host",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($recordingAgentInformation['resource_host']),
		'placeholder' => $cxpanel->defaultVal['recording']['resource_host'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Resource Extension:"),
		'help'  	  => _('File extension used to build recording playback URLs. Also used as the file type when on demand recordings are made in the panel.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_resource_extension",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($recordingAgentInformation['resource_extension']),
		'placeholder' => $cxpanel->defaultVal['recording']['resource_extension'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("File Mask:"),
		'help'  	  => _('File name mask used to parse recording file names and create recording files when on demand recordings are made in the panel.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_filename_mask",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($recordingAgentInformation['file_name_mask']),
		'placeholder' => $cxpanel->defaultVal['recording']['file_name_mask'],
		'btn_reset'	  => true,
	),
	array('type' => 'endsec'),


	array (
		'name'  => 'settings_mail',
		'title' => _('Password Email Settings'),
	),
	array(
		'title' 	  => _("From:"),
		'help'  	  => _('email of the sender of the emails.'),
		'input_type'  => 'email',
		'input_name'  => "cxpanel_email_from",
		'input_size'  => "100",
		'input_value' => $emailSettings['from'],
		'placeholder' => $cxpanel->defaultVal['email']['from'],
		'default' 	  => "",
		'btn_reset'	  => true,
	),
	array(
		'title' 	  => _("Subject:"),
		'help'  	  => _('The subject text of the email. You can specify the following variables in the email:<br/>
							<br/>
							%%userId%%    = The the username that the password belongs to.<br/>
							%%password%%  = The password value.<br/>
							%%clientURL%% = The URL used to log into the client. Built using the Client Host and Client Port fields above.
						'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_email_subject",
		'input_size'  => "1000",
		'input_value' => htmlspecialchars($emailSettings['subject']),
		'placeholder' => $cxpanel->defaultVal['email']['subject'],
		'btn_reset'	  => true,
	),
	array(
		'title' 	 => _("Body:"),
		'help'  	 => _('The body text of the email. If HTML is selected as the type you can include HTML tags.'),
		'input_type' => 'textarea',
		'input_name' => "cxpanel_email_body_editor",
		'input_class'=> 'cxpanel_email_body_editor',
		'input_size' => array(
			'cols' 		=> "49",
			'rows' 		=> "10",
			'maxlength' => "4096"
		),
		'input_value' => htmlspecialchars($emailSettings['body']),
		'label_down'  => '<div class="box_legend_keys">
							<ul class="list-group">
								<li class="list-group-item active">'. _('You can specify the following variables in the email:').'</li>
								<li class="list-group-item">'. sprintf(_("The username that the password belongs to. %s"), '<code class="legend_tag">%%userId%%</code>').'</li>
								<li class="list-group-item">'. sprintf(_("The display name that the password belongs to. %s"), '<code class="legend_tag">%%display%%</code>').'</li>
								<li class="list-group-item">'. sprintf(_("The password value. %s"), '<code class="legend_tag">%%password%%</code>').'</li>
								<li class="list-group-item">'. sprintf(_("The URL used to log into the client. %s <br> Built using the Client Host and Client Port fields above."), '<code class="legend_tag">%%clientURL%%</code>').'</li>
								<li class="list-group-item">'. sprintf(_("The %s logo image. %s"), $brandName, '<code class="legend_tag">%%logo%%</code>').'</li>
							</ul>
						</div>',
	),
	array(
		'input_type'  => 'hidden',
		'input_name'  => 'cxpanel_email_body',
		'input_value' => htmlspecialchars($emailSettings['body']),
	),
	array('type' => 'endsec'),
	

	array(
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<h5><hr></h5><button type="button" class="btn btn-lg btn-block btn-settings-save"><i class="fa fa-floppy-o" aria-hidden="true"></i> %s</button>', _('Save Changes')),
	),
	array(
		'raw' => "</form>",
	),
);
?>

<div class="container-fluid">
	<div class = "display full-border">
        <div class="row">
            <div class="col-sm-12">
			<?php
				echo load_view(__DIR__."/view.cxpanel.toolbar.php", array('brandName' => $brandName, 'sync_with_userman' => $serverInformation['sync_with_userman']));
			?>
            </div>
        </div>
		<?php
		if (! empty($licenseActivationError))
		{
			echo load_view(__DIR__."/view.cxpanel.license.error.php", array('licenseActivationError' => $licenseActivationError));
		}
		?>
		<div class="row">
			<div class='col-md-12'>
				<div class='fpbx-container'>
					<div class="display no-border">
						<div class="container-fluid">
							<div id="cxpanel_forms">
								<?php 
									echo load_view(__DIR__."/view.cxpanel.list.php", array('table_lines' => $table_lines));
								?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>