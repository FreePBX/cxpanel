<?php

$brandName = $cxpanel->brandName;
$urlAppend = "";

if(isset($_REQUEST["cxpanel_debug"]))
{
	//Add the debug flag to the url append
	$urlAppend .= "&cxpanel_debug";
}

if(isset($_REQUEST["cxpanel_show_initial_passwords"]))
{
	$urlAppend .= "&cxpanel_show_initial_passwords";
}


//Set up the REST connection
$webProtocol = ($serverInformation['api_use_ssl'] == '1') ? 'https' : 'http';
$baseApiUrl  = $webProtocol . '://' . $serverInformation['api_host'] . ':' . $serverInformation['api_port'] . '/communication_manager/api/resource/';

$pest = new \FreePBX\modules\Cxpanel\CXPestJSON($baseApiUrl);
$pest->setupAuth($serverInformation['api_username'], $serverInformation['api_password']);

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
			$pest->post('server/coreServers/' . $coreServer->id . '/license/activate', $_REQUEST["cxpanel_activate_serial_key"], array(CURLOPT_HEADER => TRUE));
			
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
				$licensePropertyValue = ($moduleLicenseProperty->value == '-1') ? _('unlimited') : $moduleLicenseProperty->value;
				
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
			//TODO: Pendiente migrar estilos a archivos CSS
			$maintenanceExpirationDateStyle = 'padding: 0px 3px 0px 3px; background-color: rgb(235,15,12); border: 1px solid rgb(200,0,0); border-radius: 3px; color: white;';
			$maintenanceExpirationDateNote = _('Maintenance has expired.');
		}
		else if(time() > ($maintenanceExpirationDate - $warningPeriod))
		{
			//Calculate days remaining
			$daysRemaining = ($maintenanceExpirationDate - time()) / 86400;
			$daysRemaining = round($daysRemaining, 0, PHP_ROUND_HALF_DOWN);
			
			//TODO: Pendiente migrar estilos a archivos CSS
			$maintenanceExpirationDateStyle = 'padding: 0px 3px 0px 3px; background-color: rgb(251,255,138); border: 1px solid rgb(200,200,0); border-radius: 3px; color: black;';
			$maintenanceExpirationDateNote =  sprintf(_('Maintenance will expire in <span style="font-weight: bold">%s day(s)</span>.'), $daysRemaining);
		}
		
		//Format date
		$maintenanceExpirationDate = date('m/d/Y', $maintenanceExpirationDate);
		
		if(isset($maintenanceExpirationDateNote))
		{
			$maintenanceExpirationDate = $maintenanceExpirationDate . ' ' . $maintenanceExpirationDateNote;
		}
		
		if(isset($maintenanceExpirationDateStyle))
		{
			$maintenanceExpirationDate = '<span style="' . $maintenanceExpirationDateStyle . '">' . $maintenanceExpirationDate . '</span>'; 
		}
	}
	
	//Build the license additions
	if(isset($licenseExpirationDate))
	{
		$licenseAdditions = array(
			'title' 	  => _("Expiration Date"),
			'help'  	  => _("Displays the expiration date of the trial license."),
			'input_type'  => 'raw',
			'input_value' => $licenseExpirationDate,
		);
	}
	
	if(isset($maintenanceExpirationDate) && !$isXactView)
	{
		$licenseAdditions = array(
			'title' 	  => _("Maint. Expiration Date"),
			'help'  	  => _("Displays the expiration date of the license maintenance period."),
			'input_type'  => 'raw',
			'input_value' => $maintenanceExpirationDate,
		);
	}
	
	if($license->clientConnections != -1)
	{
		$licenseAdditions = array(
			'title' 	  => _("Clients"),
			'help'  	  => _("Displays the number of licensed client connections."),
			'input_type'  => 'raw',
			'input_value' => $license->clientConnections,
		);
	}
	
	if($license->configuredUsers != -1)
	{
		$licenseAdditions = array(
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
				'raw' => sprintf('<form name="cxpanel_bind_license_form" id="cxpanel_bind_license_form" method="post" action="config.php?type=setup&display=cxpanel&s" onsubmit="return checkBindForm();">', $urlAppend),
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('<span style="color: #FFCC00;"><b>%s</b></span>: %s', _("ATTENTION"), _("This license is being bound for the first time or is moving servers.</br>Please fill out the information below in order to complete the activation or you can cancel the activation.")),
			),
			array(
				'title' 	  => _("Licensed To:"),
				'help'  	  => _("Enter the name of the person or company this server is licensed to."),
				'input_type'  => 'text',
				'input_name'  => "cxpanel_bind_license_to",
				'input_size'  => "20",
				'input_value' => "",
			),
			array(
				'title' 	  => _("Email:"),
				'help'  	  => _("Enter the email address of the person or company this server is licensed to."),
				'input_type'  => 'text',
				'input_name'  => "cxpanel_bind_license_email",
				'input_size'  => "20",
				'input_value' => "",
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('
					<input type="hidden" name="cxpanel_bind_license_redirect_url" value="%s">
					<input type="Button" name="cxpanel_bind_license_cancel" value="%s" onClick="document.getElementById(\'cxpanel_bind_license_cancel_form\').submit();">
					<input type="Submit" name="cxpanel_bind_license" value="%s">
				',$licenseBindRedirectURI, _("Cancel"), _("Activate")),
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => "<br>",
			),
			array('raw' => '</form>'),
			array(
				'raw' => sprintf('<form name="cxpanel_bind_license_cancel_form" id="cxpanel_bind_license_cancel_form" method="post" action="config.php?type=setup&display=cxpanel%s">', $urlAppend),
			),
			array(
				'raw' => '<input type="hidden" name="cxpanel_bind_license_cancel_flag" value="true">',
			),
			array(
				'raw' => sprintf('<input type="hidden" name="cxpanel_bind_license_redirect_url" value="%s">', $licenseBindRedirectURI),
			),
			array('raw' => '</form>'),
		);
	}
	else
	{
		$licenseActivateAddition = array(
			'title' 	  => _("Activate:"),
			'help'  	  => _("Activates a license with a given serial key."),
			'input_type'  => 'raw',
			'input_value' => sprintf('
				<form name="cxpanel_activate_license_form" id="cxpanel_activate_license_form" method="post" action="config.php?type=setup&display=cxpanel%s" onsubmit="return checkActivationForm();">
					<input type="text" name="cxpanel_activate_serial_key" id="cxpanel_activate_serial_key">
					<input type="Submit" name="cxpanel_activate_license" value="%s">
				</form>
			',$urlAppend, _("Activate")),
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


//If sync_with_userman is enabled hide the view password and email password links
if($serverInformation['sync_with_userman'] == "1")
{
	$passwordAddition = '';
	$emailPasswordLink = '';
}
else
{
	//Create the email password link
	$emailPasswordLink  = '<tr><td colspan="2">';
	$emailPasswordLink .= sprintf('<form name="cxpanel_send_password_form" id="cxpanel_send_password_form" action="config.php?type=setup&display=cxpanel%s" method="post">', $urlAppend);
	$emailPasswordLink .= '<input type="hidden" name="cxpanel_send_passwords" value="true">';
	$emailPasswordLink .= sprintf('<a href="#" title="%s" onclick="emailPasswords();">%s</a>', _('Send initially generated passwords for extensions that have a voicemail email configured. Will not send password if it has been changed from the initially generated one.'), _('Email Initial Passwords'));
	$emailPasswordLink .= '</form>';
	$emailPasswordLink .= '</td></tr>';

	//Check if the initial password list needs to be shown
	$passwordAddition = array(
		array(
			'colspan' 	  => true,
			'colspan_raw' => sprintf('<a href="config.php?type=setup&display=cxpanel&cxpanel_show_initial_passwords%s">%s</a>',$urlAppend, _("View Initial User Passwords")),
		),
	);
	if(isset($_REQUEST["cxpanel_show_initial_passwords"]))
	{
		//Generate password list array
		$passwordList = array();
		$i = 0;
		foreach($cxpanel->user_list() as $user)
		{
			if($user['initial_password'] != "")
			{
				if(sha1($user['initial_password']) == $user['hashed_password'])
				{
					$passwordList[] = array(
						"user_id" 		   => $user['user_id'],
						"initial_password" => $user['initial_password']
					);
				}
			}
		}

		$passwordAddition = array(
			//Add Password list addition
			array(
				'colspan' => true,
				'title'   => _("Initial User Passwords"),
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('These are the initail passwords that have been created for the %s users during the installation of the module.</br> Extensions that were created after installation of the module or have had their password changed from the inital value will not show up in the list.</br></br>', $brandName),
			),
			//Format the list
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('<div style="height: 200px; overflow: auto;">%s</div>', cxpanel_array_to_table_2d($passwordList, 'style="width: 100%;"')),
			),
			array(
				'colspan' 	  => true,
				'colspan_raw' => sprintf('<a class="btn btn-default btn-export-password">%s</a>', _("Download as CSV")),
			),
		);
	}
}


//Check if debug needs to be shown
$debugAddition = array(
	array(
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<a href="config.php?type=setup&display=cxpanel&cxpanel_debug">%s</a>', _("View Debug")),
	),
);
if(isset($_REQUEST["cxpanel_debug"]))
{
	$debugAddition = array(
		array(
			'colspan' => true,
			'title'   => _('Debug'),
		),
		array(
			'colspan' 	  => true,
			'colspan_raw' => sprintf('<b>%s</b>', _("Main Log")),
		),
		array(
			'colspan' 	  => true,
			'colspan_raw' => sprintf('<textarea rows="10" cols="90">%s</textarea>', htmlspecialchars(cxpanel_read_file($cxpanel->getPath("log")))),
		),
		array(
			'colspan' 	  => true,
			'colspan_raw' => sprintf('<b>%s</b>', _("Modify Log")),
		),
		array(
			'colspan' 	  => true,
			'colspan_raw' => sprintf('<textarea rows="10" cols="90">%s</textarea>', htmlspecialchars(cxpanel_read_file($cxpanel->getPath("log_modify")))),
		),

		//Add the printout of the database tables
		array(
			'type'  => 'debug',
			'title' => _("Server"),
			'value' => cxpanel_array_to_table_1d($cxpanel->server_get()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Queues"),
			'value' => cxpanel_array_to_table_1d($cxpanel->queue_list()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Conference Rooms"),
			'value' => cxpanel_array_to_table_1d($cxpanel->conference_room_list()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Managed Items"),
			'value' => cxpanel_array_to_table_1d($cxpanel->managed_item_get_all()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Voicemail Agent"),
			'value' => cxpanel_array_to_table_1d($cxpanel->voicemail_agent_get()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Recording Agent"),
			'value' => cxpanel_array_to_table_1d($cxpanel->recording_agent_get()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Users"),
			'value' => cxpanel_array_to_table_2d($cxpanel->user_list()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Email"),
			//TODO: Get value in textarea
			'value' => cxpanel_array_to_table_1d($cxpanel->email_get()),
		),
		array(
			'type'  => 'debug',
			'title' => _("Phone Numbers"),
			'value' => cxpanel_array_to_table_2d($cxpanel->phone_number_list_all()),
		),
	);
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

//Check if a password batch send was requested
if(isset($_REQUEST["cxpanel_send_passwords"]))
{
	$passEmailResults = array(
		array (
			'colspan' 	  => true,
			'colspan_raw' => "<b>"._("The following is a list of users that were not sent password emails")."</b>",
		),
		array (
			'raw' => "<tr><td>"._("User")."</td><td>"._("Reason")."</td></tr>",
		),
		array (
			'colspan' 	  => true,
			'colspan_raw' => "<hr>",
		),
	);

	//Send emails
	foreach($cxpanel->user_list() as $user)
	{
		$voiceMailBox = $cxpanel->hook_voicemail_getMailBox($user['user_id']);
		$valid 		  = (sha1($user['initial_password']) == $user['hashed_password']);
		$new_row 	  = "<tr><td>%s</td><td>%s</td></tr>";
		
		if(	$voiceMailBox == null || empty($voiceMailBox['email']))
		{
			$passEmailResults[] = array('raw' => sprintf($new_row, $user['user_id'], _("No email set on extension page")));
			continue;
		}
		
		if(!$valid)
		{
			$passEmailResults[] = array('raw' => sprintf($new_row, $user['user_id'], _("Initial password no longer valid")));
			continue;
		}

		if($user['add_user'] != "1")
		{
			$passEmailResults[] = array('raw' => sprintf($new_row, $user['user_id'], _("Extension not set to add user")));
			continue;
		}

		//Send email
		$cxpanel->send_password_email($user['user_id']);
	}
}

//Grab the email settings information 
$emailSettings = $cxpanel->email_get();

//Grab the voicemail agent information
$voicemailAgentInformation = $cxpanel->voicemail_agent_get();

//Grab the recording agent information
$recordingAgentInformation = $cxpanel->recording_agent_get();

$table_lines = array(
	array (
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<h2 id="title">%s</h2>', $brandName),
	),
	array(
		'type' => 'list',
		'list' => empty($licenseBindAddition) ? array() : $licenseBindAddition,
	),
	array(
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<span style="color: #FF0000;">%s</span>', $serverErrorMessage),
	),
	array(
		'type' => 'list',
		'list' => $debugAddition,
	),
	array(
		'type' => 'list',
		'list' => empty($passwordAddition) ? array() : $passwordAddition,
	),
	array(
		'raw' => $emailPasswordLink,
	),
	array(
		'type' => 'list',
		'list' => empty($passEmailResults) ? array() : $passEmailResults,
	),

	array(
		'colspan' => true,
		'title' => _('Server'),
	),
	array(
		'title' => _("Connected:"),
		'help'  => sprintf(_("Displays if the module can connect to the %s server. If not the server may not be running or the connection information below may be incorrect."), $brandName),
		'value' => isset($serverErrorMessage) ? "<span style=\"color: #FF0000;\">"._("NO")."</span>" : "<span style=\"color: #00FF00;\">"._("YES")."</span>",
		//If an error occurred in the server query show disconnected else show connected
	),
	array(
		'title' => _("Version:"),
		'help'  => _("Displays the version of the server."),
		'value' => (empty($brand)) ? _("Unknown") : sprintf("%s build %s", $brand->version, $brand->build),
	),

	array(
		'colspan' => true,
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
		'type' => "list",
		'list' => (empty($licenseAdditions)) ? array() : $licenseAdditions,
	),
	array(
		'type' => "list",
		'list' => array(
			(empty($licenseActivateAddition)) ? array() : $licenseActivateAddition,
		)
	),
	array(
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<span style="color: #FF0000;">%s</span>', $licenseActivationErrorMessage),
	),
	array(
		'raw' => sprintf('<form name="cxpanel_settings_form" id="cxpanel_settings_form" action="config.php?type=setup&display=cxpanel%s" method="post" onsubmit="return checkForm();">', $urlAppend),
	),


	array (
		'colspan' => true,
		'title'   => _('General Settings'),
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


	array (
		'colspan' => true,
		'title'   => _('Server API Connection Settings'),
	),
	array(
		'title' 	  => _("Server Name:"),
		'help'  	  => _("Unique id of the core server instance to manage."),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_name",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['name']),
	),
	array(
		'title' 	  => _("Host:"),
		'help'        => sprintf(_('IP Address or host name of the %s server API. Set to "localhost" if the server is installed on the same machine.'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_api_host",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['api_host']),
	),
	array(
		'title' 	 => _("Port:"),
		'help'  	 => sprintf(_('Port of the %1$s server API.<br/><br/>Default Port: 58080<br/>Default SSL Port: 55050 (SSL is disalbed by default on the %1$s server. See the %1$s server security.xml file.)'), $brandName),
		'input_type' => 'number',
		'input_name' => "cxpanel_api_port",
		'input_size' => "20",
		'input_limit'=> array(
			"min" => "1",
			"max" => "65535",
		),
		'input_value' => htmlspecialchars($serverInformation['api_port']),
	),
	array(
		'title' 	  => _("Username:"),
		'help'  	  => sprintf(_('Username used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the %s server config directory under the communication_manager servlet security settings.'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_api_username",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['api_username']),
	),
	array(
		'title' 	  => _("Password:"),
		'help'  	  => sprintf(_('Password used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the %s server config directory under the communication_manager servlet security settings.'), $brandName),
		'input_type'  => 'password',
		'input_name'  => "cxpanel_api_password",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['api_password']),
	),
	array(
		'title' 		=> _("Use SSL:"),
		'help'  		=> sprintf(_('Check this option, if you have endabled SSL on the %1$s server API.<br/><br/>NOTE: If checked your %1$s server must have an SSL keystore configured and the communication_manager servlet security context must have SSL enabled in the security.xml file.</br>You will also need to specify the SSL port number in the API port field above.'), $brandName),
		'input_type' 	=> 'checkbox',
		'input_name' 	=> "cxpanel_api_use_ssl",
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['api_use_ssl'] == '1'),
	),
    

	array (
		'colspan' => true,
		'title'   => _('Asterisk Connection Settings'),
	),
	array(
		'title' 	  => _("Asterisk Server Host:"),
		'help'  	  => sprintf(_('The ip or hostname of the Asterisk server. This is used to tell the %1$s server how to connect to Asterisk. If the %1$s server and Asterisk are on the same machine this field should be set to "localhost".'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_asterisk_host",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['asterisk_host']),
	),

	
	array (
		'colspan' => true,
		'title'   => _('Module Client Link Settings'),
	),
	array(
		'title' 	  => _("Client Host:"),
		'help'  	  => sprintf(_('IP Address or host name of the %1$s client. This setting is used when accessing the %1$s client via the links in this GUI and for client links in password emails. If not set the ip or host name from the current URL will be utilized. Normally this should remain blank unless you have a remote %1$s Server install.'), $brandName),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_client_host",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($serverInformation['client_host']),
	),
	array(
		'title' 	 => _("Client Port:"),
		'help'  	 => sprintf(_('Web port of the %s client.'), $brandName),
		'input_type' => 'number',
		'input_name' => "cxpanel_client_port",
		'input_size' => "20",
		'input_limit'=> array(
			"min" => "1",
			"max" => "65535",
		),
		'input_value' => htmlspecialchars($serverInformation['client_port']),
	),
	array(
		'title' 		=> _("Use SSL:"),
		'help'  		=> sprintf(_('Check this option, if you have enabled SSL on the %1$s client interface.<br/><br/>NOTE: If checked your %1$s server must have an SSL keystore configured and the client servlet security context must have SSL enabled in the security.xml file.</br>You will also need to specify the SSL port number in the Client Port field above.'), $brandName),
		'input_type' 	=> 'checkbox',
		'input_name' 	=> "cxpanel_client_use_ssl",
		'input_value' 	=> "1",
		'input_checked' => ($serverInformation['client_use_ssl'] == '1'),
	),

	array (
		'colspan' => true,
		'title'   => _('Voicemail Agent Settings'),
	),
	array(
		'title' 	  => _("Identifier:"),
		'help'  	  => _('Identifier of the voicemail agent to bind and configure.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_identifier",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($voicemailAgentInformation['identifier']),
	),
	array(
		'title' 	  => _("Directory:"),
		'help'  	  => _('Path to the root voicemail directory.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_directory",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($voicemailAgentInformation['directory']),
	),
	array(
		'title' 	  => _("Resource Host:"),
		'help'  	  => _('Hostname or IP used to build voicemail playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_resource_host",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($voicemailAgentInformation['resource_host']),
	),
	array(
		'title' 	  => _("Resource Extension:"),
		'help'  	  => _('File extension used to build voicemail playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_voicemail_agent_resource_extension",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($voicemailAgentInformation['resource_extension']),
	),


	array (
		'colspan' => true,
		'title'   => _('Recording Agent Settings'),
	),
	array(
		'title' 	  => _("Identifier:"),
		'help'  	  => _('Identifier of the recording agent to bind and configure.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_identifier",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($recordingAgentInformation['identifier']),
	),
	array(
		'title' 	  => _("Directory:"),
		'help'  	  => _('Path to the root recording directory.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_directory",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($recordingAgentInformation['directory']),
	),
	array(
		'title' 	  => _("Resource Host:"),
		'help'  	  => _('Hostname or IP used to build recording playback URLs.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_resource_host",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($recordingAgentInformation['resource_host']),
	),
	array(
		'title' 	  => _("Resource Extension:"),
		'help'  	  => _('File extension used to build recording playback URLs. Also used as the file type when on demand recordings are made in the panel.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_resource_extension",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($recordingAgentInformation['resource_extension']),
	),
	array(
		'title' 	  => _("File Mask:"),
		'help'  	  => _('File name mask used to parse recording file names and create recording files when on demand recordings are made in the panel.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_recording_agent_filename_mask",
		'input_size'  => "20",
		'input_value' => htmlspecialchars($recordingAgentInformation['file_name_mask']),
	),


	array (
		'colspan' => true,
		'title'   => _('Password Email Settings'),
	),
	array(
		'title' 	  => _("Subject:"),
		'help'  	  => _('The subject text of the email. You can specify the following variables in the email:<br/><br/>%%userId%% = The the username that the password belongs to.<br/>%%password%% = The password value.<br/>%%clientURL%% = The URL used to log into the client. Built using the Client Host and Client Port fields above.'),
		'input_type'  => 'text',
		'input_name'  => "cxpanel_email_subject",
		'input_size'  => "50",
		'input_value' => htmlspecialchars($emailSettings['subject']),
	),
	array(
		'title' 	 => _("Body:"),
		'help'  	 => sprintf(_('The body text of the email. If HTML is selected as the type you can include HTML tags. You can specify the following variables in the email:<br/><br/>%%userId%% = The the username that the password belongs to.<br/>%%password%% = The password value.<br/>%%clientURL%% = The URL used to log into the client. Built using the Client Host and Client Port fields above.<br/>%%logo%% = The %s logo image.'), $brandName),
		'input_type' => 'textarea',
		'input_name' => "cxpanel_email_body",
		'input_size' => array(
			'cols' => "49",
			'rows' => "10",
		),
		'input_value' => htmlspecialchars($emailSettings['body']),
	),
	array(
		'colspan' 	  => true,
		'colspan_raw' => sprintf('<input type="Submit" name="cxpanel_settings" value="%s">', _('Submit Changes')),
	),
	array(
		'raw' => "</form>",
	),

);

function lineParse($line)
{
	$data_return = "";
	if (! empty($line))
	{
		if (isset($line['raw']))
		{
			$data_return = $line['raw'];
		}
		else if (! empty($line['type']) && $line['type'] == "debug")
		{
			$data_return  = sprintf('<tr><td colspan="2"><b>%s</b></td></tr>', $line['title']);
			$data_return .= sprintf('<tr><td colspan="2">%s</td></tr>', $line['value']);
		}
		else if (isset($line['colspan']) && $line['colspan'] == true)
		{
			if (isset($line['colspan_raw']))
			{
				$data_return = sprintf('<tr><td colspan="2">%s</td></tr>', $line['colspan_raw']);
			}
			else
			{
				$data_return = sprintf('<tr><td colspan="2"><h5>%s<hr></h5></td></tr>', $line['title']);
			}
		}
		else
		{
			$val = "";
			if (! empty($line['input_type']))
			{
				switch ($line['input_type'])
				{
					case 'checkbox':
						$val = sprintf('<input type="checkbox" name="%s" value="%s" %s />', $line['input_name'], $line['input_value'], ($line['input_checked'] == true ? 'checked' : ''));
						break;
					
					case 'text':
					case 'password':
						$val = sprintf('<input type="%s" id="%s" name="%s" value="%s" size="%s">', $line['input_type'], $line['input_name'], $line['input_name'], $line['input_value'], $line['input_size']);
						break;

					case 'number':
						$val = sprintf('<input type="number" name="%s" value="%s" size="%s" min="%s" max="%s">', $line['input_name'], $line['input_value'], $line['input_size'], $line['input_limit']['min'], $line['input_limit']['max']);
						break;

					case 'textarea':
						$val = sprintf('<textarea name="%s" cols="%s" rows="%s">%s</textarea>', $line['input_name'], $line['input_size']['cols'], $line['input_size']['rows'],  $line['input_value']);
						break;

					case 'raw':
						$val = $line['input_value'];
						break;

					default:
						dbug("??? > ". $line['input_type']);
				}
			}
			elseif (isset($line['value']))
			{
				$val = $line['value'];
			}
			$data_return = sprintf('<tr><td><a href="#" class="info">%s<span>%s</span></a></td><td>%s</td></tr>', $line['title'], $line['help'], $val);
		}
	}
	return $data_return;
}

?>

<script language="javascript">
	function export_passwords()
	{
		$url = window.FreePBX.ajaxurl + "?module=cxpanel&command=download_password_csv";
		window.location.assign($url);
	}
	$(document).ready(function()
	{
		$('.btn-export-password').on("click", function(e) { e.preventDefault(); export_passwords(); });
	});
	<!--
	function checkForm() {
		
		var settingsForm = document.getElementById('cxpanel_settings_form');				

		if(settingsForm.elements['cxpanel_name'].value.length == 0) {
			alert('Name cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_client_port'].value.length == 0) {
			alert('Client port cannot be blank.');
			return false;
		}
		
		if(settingsForm.elements['cxpanel_api_host'].value.length == 0) {
			alert('Server API host cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_api_port'].value.length == 0) {
			alert('Server API port cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_api_username'].value.length == 0) {
			alert('Server API username cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_api_password'].value.length == 0) {
			alert('Server API password cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_asterisk_host'].value.length == 0) {
			alert('Asterisk host cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_voicemail_agent_identifier'].value.length == 0) {
			alert('Voicemail agent identifier cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_voicemail_agent_directory'].value.length == 0) {
			alert('Voicemail agent directory cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_voicemail_agent_resource_host'].value.length == 0) {
			alert('Voicemail agent resource host cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_voicemail_agent_resource_extension'].value.length == 0) {
			alert('Voicemail agent resource extension cannot be blank.');
			return false;
		}
		
		if(settingsForm.elements['cxpanel_recording_agent_identifier'].value.length == 0) {
			alert('Recording agent identifier cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_recording_agent_directory'].value.length == 0) {
			alert('Recording agent directory cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_recording_agent_resource_host'].value.length == 0) {
			alert('Recording agent resource host cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_recording_agent_resource_extension'].value.length == 0) {
			alert('Recording agent resource extension cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_recording_agent_filename_mask'].value.length == 0) {
			alert('Recording agent filename mask cannot be blank.');
			return false;
		}

		if(settingsForm.elements['cxpanel_api_port'].value != parseInt(settingsForm.elements['cxpanel_api_port'].value)) {
			alert('Server port must be numeric.');
			return false;
		}

		return true;
	}

	function checkActivationForm() {
		var activateForm = document.getElementById('cxpanel_activate_license_form');				

		if(activateForm.elements['cxpanel_activate_serial_key'].value.length == 0) {
			alert('Please specify a serial key.');
			return false;
		}
	}

	function checkBindForm() {
		var activateForm = document.getElementById('cxpanel_bind_license_form');				

		if(activateForm.elements['cxpanel_bind_license_to'].value.length == 0) {
			alert('Please specify the licensed to value.');
			return false;
		}

		if(activateForm.elements['cxpanel_bind_license_email'].value.length == 0) {
			alert('Please specify an email for the license.');
			return false;
		}
	}

	function emailPasswords() {
		if(confirm('Email passwords to users?')) {
			document.getElementById('cxpanel_send_password_form').submit();
		}
	}
	
	//-->
</script>

<div class="content">
	<?php 
		echo $licenseActivationError; 
	?>
	<table style="width: 100%;">
		<?php
		foreach ($table_lines as $line)
		{

			if (! empty($line['type']) && $line['type'] == 'list' && isset($line['list']) && is_array($line['list']))
			{
				foreach ($line['list'] as $subLine)
				{
					$new_subLine = lineParse($subLine);
					if (! empty($new_subLine))
					{
						echo $new_subLine;
					}
				}
			}
			else
			{
				$new_line = lineParse($line);
				if (! empty($new_line))
				{
					echo $new_line;
				}
			}
		}
		?>
	</table>
</div>