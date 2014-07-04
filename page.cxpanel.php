<?php
/*
 *Name         : page.cxpanel.php
 *Author       : Michael Yara
 *Created      : July 2, 2008
 *Last Updated : April 25, 2014
 *History      : 3.0
 *Purpose      : Information page for module
 */

//Includes
require_once(dirname(__FILE__)."/lib/CXPestJSON.php");
require_once(dirname(__FILE__)."/lib/cxpanel.class.php");

//Check for a server settings update action
if(isset($_REQUEST["cxpanel_settings"])) {
		
	cxpanel_server_update(	trim($_REQUEST["cxpanel_name"]), 
							trim($_REQUEST["cxpanel_asterisk_host"]), 
							trim($_REQUEST["cxpanel_client_host"]),
							trim($_REQUEST["cxpanel_client_port"]),
							trim($_REQUEST["cxpanel_api_host"]), 
							trim($_REQUEST["cxpanel_api_port"]), 
							trim($_REQUEST["cxpanel_api_username"]), 
							trim($_REQUEST["cxpanel_api_password"]),
							($_REQUEST["cxpanel_api_use_ssl"] == "1"),
							($_REQUEST["cxpanel_sync_with_userman"] == "1"));
		
	cxpanel_voicemail_agent_update(	trim($_REQUEST["cxpanel_voicemail_agent_identifier"]),
									trim($_REQUEST["cxpanel_voicemail_agent_directory"]),
									trim($_REQUEST["cxpanel_voicemail_agent_resource_host"]),
									trim($_REQUEST["cxpanel_voicemail_agent_resource_extension"]));
	
	cxpanel_recording_agent_update(	trim($_REQUEST["cxpanel_recording_agent_identifier"]),
									trim($_REQUEST["cxpanel_recording_agent_directory"]),
									trim($_REQUEST["cxpanel_recording_agent_resource_host"]),
									trim($_REQUEST["cxpanel_recording_agent_resource_extension"]),
									trim($_REQUEST["cxpanel_recording_agent_filename_mask"]));
	
	cxpanel_email_update(	$_REQUEST["cxpanel_email_subject"], 
							$_REQUEST["cxpanel_email_body"]);
	
	//Flag FreePBX for reload
	needreload();
}

//Check if debug needs to be shown
$debugAddition = "<tr><td colspan=\"2\"><a href=\"config.php?type=setup&display=cxpanel&cxpanel_debug\">View Debug</a></td></tr>";
$urlAppend = "";
if(isset($_REQUEST["cxpanel_debug"])) {
		
	//Add log displays
	$debugAddition = "	<tr><td colspan=\"2\"><h5>Debug<hr></h5></td></tr>
						<tr><td colspan=\"2\"><b>Main Log</b></td></tr>
						<tr><td colspan=\"2\"><textarea rows=\"10\" cols=\"90\">" . 
						htmlspecialchars(cxpanel_read_file($amp_conf['AMPWEBROOT'] . 
						"/admin/modules/cxpanel/main.log")) . 
						"</textarea></td></tr>
						<tr><td colspan=\"2\"><b>Modify Log</b></td></tr>
						<tr><td colspan=\"2\"><textarea rows=\"10\" cols=\"90\">" . 
						htmlspecialchars(cxpanel_read_file($amp_conf['AMPWEBROOT'] . 
						"/admin/modules/cxpanel/modify.log")) . 
						"</textarea></td></tr>";
	
	//Add the printout of the database tables
	$debugAddition .= "<tr><td colspan=\"2\"><b>Server</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_1d(cxpanel_server_get()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Voicemail Agent</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_1d(cxpanel_voicemail_agent_get()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Recording Agent</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_1d(cxpanel_recording_agent_get()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Server</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_1d(cxpanel_server_get()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Email</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_1d(cxpanel_email_get()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Users</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_2d(cxpanel_user_list()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Phone Numbers</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_2d(cxpanel_phone_number_list_all()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Queues</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_2d(cxpanel_queue_list()) . "</td></tr>";
	$debugAddition .= "<tr><td colspan=\"2\"><b>Conference Rooms</b></td></tr><tr><td colspan=\"2\">" . cxpanel_array_to_table_2d(cxpanel_conference_room_list()) . "</td></tr>";
	
	//Add the debug flag to the url append
	$urlAppend .= "&cxpanel_debug";
}

//Check if the initial password list needs to be shown
$passwordAddition = "<tr><td colspan=\"2\"><a href=\"config.php?type=setup&display=cxpanel&cxpanel_show_initial_passwords$urlAppend\">View Initial User Passwords</a></td></tr>";
if(isset($_REQUEST["cxpanel_show_initial_passwords"])) {

	//Add Password list addition
	$passwordAddition = "	<tr><td colspan=\"2\"><h5>Initial User Passwords<hr></h5></td></tr>
							<tr><td colspan=\"2\">These are the initail passwords that have been created for the $cxpanelBrandName users during the installation of the module.</br> Extensions that were created after installation of the module or have had their password changed from the inital value will not show up in the list.</br></br></td></tr>";
	
	//Generate password list array
	$passwordList = array();
	$userList = cxpanel_user_list();
	$i = 0;
	foreach($userList as $user) {
		if($user['initial_password'] != "") {		
			if(sha1($user['initial_password']) == $user['hashed_password']) {
				$passwordList[$i] = array("user_id" => $user['user_id'], "initial_password" => $user['initial_password']);
				$i++;
			}
		}
	}
	
	//Format the list
	$passwordAddition .= "<tr><td colspan=\"2\"><div style=\"height: 200px; overflow: auto;\">" . cxpanel_array_to_table_2d($passwordList, "style=\"width: 100%;\"") . "</div></td></tr>";
	$passwordAddition .= "<tr><td colspan=\"2\">
								<form name=\"cxpanel_download_password_csv_form\" id=\"cxpanel_download_password_csv_from\" method=\"post\" action=\"config.php?type=setup&display=cxpanel$urlAppend\">
									<input type=\"hidden\" name=\"cxpanel_download_password_csv\" value=\"true\">
									<a href=\"#\" onclick=\"document.getElementById('cxpanel_download_password_csv_from').submit();\">Download as CSV</a>
								</form>
							</td></tr>";
	
	//Add the password list flag to the url append
	$urlAppend .= "&cxpanel_show_initial_passwords";
}

//Create the email password link
$emailPasswordLink = '	<tr>
							<td colspan="2">
								<form name="cxpanel_send_password_form" id="cxpanel_send_password_form" action="config.php?type=setup&display=cxpanel' . $urlAppend . '" method="post">
									<input type="hidden" name="cxpanel_send_passwords" value="true">
									<a href="#" title="Send initially generated passwords for extensions that have a voicemail email configured. Will not send password if it has been changed from the initially generated one." onclick="emailPasswords();">Email Initial Passwords</a>
								</form>
							</td>
						</tr>';

//Check if the csv download was requested
if(isset($_REQUEST["cxpanel_download_password_csv"])) {
	
	//Open the temp file
	$filepath =  '/tmp/' . uniqid() . ".csv";
	$output = fopen($filepath, 'w');
	
	//Generate password csv content
	$passwordList = array();
	$userList = cxpanel_user_list();
	fputcsv($output, array('user_id', 'initial_password'));
	foreach($userList as $user) {
		if($user['initial_password'] != "") {
			if(sha1($user['initial_password']) == $user['hashed_password']){
				fputcsv($output, array($user['user_id'], $user['initial_password']));
			}
		}
	}
	fclose($output);
	
	//Issue the downlaod to the user and cleanup
	download_file($filepath, "password.csv", "text/csv", true);
	unlink($filepath);
}

//Check if a password batch send was requested
if(isset($_REQUEST["cxpanel_send_passwords"])) {
	$passEmailResults = "<tr><td colspan=\"2\"></br><b>The following is a list of users that were not sent password emails</b></td></tr>" .
	                    	"<tr>" .
	                    		"<td>User</td>" .
	                    		"<td>Reason</td>" .
	                    	"</tr>" .
	                    	"<tr>" .
	                    		"<td colspan=\"2\"><hr></td>" .
	                    	"</tr>";
						
	//Send emails
	$userList = cxpanel_user_list();
	foreach($userList as $user) {
		$voiceMailBox = voicemail_mailbox_get($user['user_id']);
		$valid = (sha1($user['initial_password']) == $user['hashed_password']);
		
		if(	$voiceMailBox == null || 
			!isset($voiceMailBox['email']) ||
			$voiceMailBox['email'] == "") {
			$passEmailResults .= "<tr><td>" . $user['user_id'] . "</td><td>No email set on extension page</td></tr>";
			continue;
		}
		
		if(!$valid) {
			$passEmailResults .= "<tr><td>" . $user['user_id'] . "</td><td>Initial password no longer valid</td></tr>";
			continue;
		}

		if($user['add_user'] != "1") {
			$passEmailResults .= "<tr><td>" . $user['user_id'] . "</td><td>Extension not set to add user</td></tr>";
			continue;
		}
		
		//Send email
		cxpanel_send_password_email($user['user_id']);
	}
}

//Grab the email settings information 
$emailSettings = cxpanel_email_get();

//Grab the server information
$serverInformation = cxpanel_server_get();

//Grab the voicemail agent information
$voicemailAgentInformation = cxpanel_voicemail_agent_get();

//Grab the recording agent information
$recordingAgentInformation = cxpanel_recording_agent_get();

//Set up the REST connection
$webProtocol = ($serverInformation['api_use_ssl'] == '1') ? 'https' : 'http';
$baseApiUrl = $webProtocol . '://' . $serverInformation['api_host'] . ':' . $serverInformation['api_port'] . '/communication_manager/api/resource/';
$pest = new CXPestJSON($baseApiUrl);
$pest->setupAuth($serverInformation['api_username'], $serverInformation['api_password']);

//Grab the version and license information
try { 
	
	//Grab the server information
	$brand = $pest->get('server/brand');
	$coreServer = $pest->get('server/coreServers/getBySlug/' . $serverInformation['name']);

	//Handle licensing requests
	try {
		
		/*
		 * Check if a license activation request was made.
		 * This needs to be done before we get query the license information
		 */
		if(isset($_REQUEST["cxpanel_activate_license"]) && !isset($serverErrorMessage)) {
			$pest->post('server/coreServers/' . $coreServer->id . '/license/activate', $_REQUEST["cxpanel_activate_serial_key"], array(CURLOPT_HEADER => TRUE));
			
			//Flag FreePBX for reload
			needreload();
		}
		
		/*
		 * Check if a license bind cancel request was made.
		 */
		if(isset($_REQUEST["cxpanel_bind_license_cancel_flag"])) {
			$pest->post($_REQUEST["cxpanel_bind_license_redirect_url"], new cxpanel_bind_request(true, "", ""));
		}
		
		/*
		 * Check if a license bind request was made.
		 */
		if(isset($_REQUEST["cxpanel_bind_license"])) {
			$pest->post($_REQUEST["cxpanel_bind_license_redirect_url"],
			new cxpanel_bind_request(false, $_REQUEST["cxpanel_bind_license_to"], $_REQUEST["cxpanel_bind_license_email"]));
			
			//Flag FreePBX for reload
			needreload();
		}
		
	} catch (CXPest_TemporaryRedirect $e) {
		$licenseBindRedirectURI = $e->redirectUri;
	} catch (Exception $e) {
		$licenseActivationErrorMessage = $e->getMessage();
	}

	//Grab the license information
	$license = $pest->get('server/coreServers/' . $coreServer->id . '/license');
	$moduleLicenses = $pest->get('server/coreServers/' . $coreServer->id . '/license/moduleLicenses');
	
	//Grab the module license properties
	foreach($moduleLicenses as $moduleLicense) {
		$moduleLicenseProperties = $pest->get('server/coreServers/' . $coreServer->id . '/license/moduleLicenses/' . $moduleLicense->id . "/properties");
		foreach($moduleLicenseProperties as $moduleLicenseProperty) {
			
			//Only show properties that should be displayed
			if($moduleLicenseProperty->display) {
				
				//If the value of the license property is '-1' show unlimited
				$licensePropertyValue = ($moduleLicenseProperty->value == '-1') ? 'unlimited' : $moduleLicenseProperty->value;
				
				//Add the property to the list
				$licenseModuleAdditions .= "<tr>
												<td><a href=\"#\" class=\"info\">" . $moduleLicenseProperty->displayName . ":<span>" . $moduleLicenseProperty->description . "</span></a></td>
												<td>" . $licensePropertyValue . "</td>						
											</tr>";
			}
		}
	}
	
	//Store the general license properties
	$versionDisplay = $brand->version . " build " . $brand->build;
	$licensedToDisplay = $license->licensedTo;
	$licensedTypeDisplay = $license->type;
	$licenseSerialKeyDisplay = $license->serial;
	$licenseExpirationDate = isset($license->expirationDate) ? date('m/d/Y', $license->expirationDate / 1000) : null;
	$maintenanceExpirationDate = isset($license->maintenanceExpirationDate) ? ($license->maintenanceExpirationDate / 1000) : null;
	
	//Highlight and format maintenance expiration date
	if(isset($maintenanceExpirationDate)) {
		
		//Check if maintenance has expired or is about to
		$warningPeriod = 30 * 86400;
		if(time() > $maintenanceExpirationDate) {
			$maintenanceExpirationDateStyle = 'padding: 0px 3px 0px 3px; background-color: rgb(235,15,12); border: 1px solid rgb(200,0,0); border-radius: 3px; color: white;';
			$maintenanceExpirationDateNote = 'Maintenance has expired.';
		} else if(time() > ($maintenanceExpirationDate - $warningPeriod)) {
			
			//Calculate days remaining
			$daysRemaining = ($maintenanceExpirationDate - time()) / 86400;
			$daysRemaining = round($daysRemaining, 0, PHP_ROUND_HALF_DOWN);
			
			$maintenanceExpirationDateStyle = 'padding: 0px 3px 0px 3px; background-color: rgb(251,255,138); border: 1px solid rgb(200,200,0); border-radius: 3px; color: black;';
			$maintenanceExpirationDateNote = 'Maintenance will expire in <span style="font-weight: bold">' . $daysRemaining . ' day(s)</span>.';
		}
		
		//Format date
		$maintenanceExpirationDate = date('m/d/Y', $maintenanceExpirationDate);
		
		if(isset($maintenanceExpirationDateNote)) {
			$maintenanceExpirationDate = $maintenanceExpirationDate . ' ' . $maintenanceExpirationDateNote;
		}
		
		if(isset($maintenanceExpirationDateStyle)) {
			$maintenanceExpirationDate = '<span style="' . $maintenanceExpirationDateStyle . '">' . $maintenanceExpirationDate . '</span>'; 
		}
	}
	
	//Build the license additions
	if(isset($licenseExpirationDate)) {
		$licenseAdditions .= "	<tr>
									<td><a href=\"#\" class=\"info\">Expiration Date:<span>Displays the expiration date of the trial license.</span></a></td>
									<td>$licenseExpirationDate</td>						
								</tr>";
	}
	
	if(isset($maintenanceExpirationDate)) {
		$licenseAdditions .= "	<tr>
		<td><a href=\"#\" class=\"info\">Maint. Expiration Date:<span>Displays the expiration date of the license maintenance period.</span></a></td>
		<td>$maintenanceExpirationDate</td>
		</tr>";
	}
	
	if($license->clientConnections != -1) {
		$licenseAdditions .= "	<tr>
									<td><a href=\"#\" class=\"info\">Clients:<span>Displays the number of licensed client connections.</span></a></td>
									<td>" . $license->clientConnections . "</td>						
								</tr>";
	}
	
	if($license->configuredUsers != -1) {
		$licenseAdditions .= "	<tr>
									<td><a href=\"#\" class=\"info\">Users:<span>Displays the total number of users that can be enabled.</span></a></td>
									<td>" . $license->configuredUsers . "</td>						
								</tr>";
	}
	$licenseAdditions .= $licenseModuleAdditions;
	
	/*
	 * Create the license activation button if we are not handling the license bind.
	 * If we are handleing the license bind create the license bind form.
	 */
	if(isset($licenseBindRedirectURI)) {
		$licenseBindAddition = "	<form name=\"cxpanel_bind_license_form\" id=\"cxpanel_bind_license_form\" method=\"post\" action=\"config.php?type=setup&display=cxpanel$urlAppend\" onsubmit=\"return checkBindForm();\">
									<tr><td colspan=\"2\"><span style=\"color: #FFCC00;\"><b>ATTENTION</b></span>: This license is being bound for the first time or is moving servers.</br>Please fill out the information below in order to complete the activation or you can cancel the activation.</td></tr>
									<tr>
											<td><a href=\"#\" class=\"info\">Licensed To:<span>Enter the name of the person or company this server is licensed to.</span></a></td>
											<td><input type=\"text\" name=\"cxpanel_bind_license_to\" id=\"cxpanel_bind_license_to\"></td>		
									</tr>
									<tr>
											<td><a href=\"#\" class=\"info\">Email:<span>Enter the email address of the person or company this server is licensed to.</span></a></td>
											<td><input type=\"text\" name=\"cxpanel_bind_license_email\" id=\"cxpanel_bind_license_email\"></td>		
									</tr>
									<tr>
										<td colspan=\"2\">
											<input type=\"hidden\" name=\"cxpanel_bind_license_redirect_url\" value=\"$licenseBindRedirectURI\">
											<input type=\"Button\" name=\"cxpanel_bind_license_cancel\" value=\"Cancel\" onClick=\"document.getElementById('cxpanel_bind_license_cancel_form').submit();\">
											<input type=\"Submit\" name=\"cxpanel_bind_license\" value=\"Activate\">
										</td>
									</tr>
									<tr><td colspan=\"2\"></br></td></tr>
									</form>
									<form name=\"cxpanel_bind_license_cancel_form\" id=\"cxpanel_bind_license_cancel_form\" method=\"post\" action=\"config.php?type=setup&display=cxpanel$urlAppend\">
										<input type=\"hidden\" name=\"cxpanel_bind_license_cancel_flag\" value=\"true\">
										<input type=\"hidden\" name=\"cxpanel_bind_license_redirect_url\" value=\"$licenseBindRedirectURI\">
									</form>";
	} else {
		$licenseActivateAddition = "	<tr>
											<td><a href=\"#\" class=\"info\">Activate:<span>Activates a license with a given serial key.</span></a></td>
											<td>
												<form name=\"cxpanel_activate_license_form\" id=\"cxpanel_activate_license_form\" method=\"post\" action=\"config.php?type=setup&display=cxpanel$urlAppend\" onsubmit=\"return checkActivationForm();\">
												<input type=\"text\" name=\"cxpanel_activate_serial_key\" id=\"cxpanel_activate_serial_key\">
												<input type=\"Submit\" name=\"cxpanel_activate_license\" value=\"Activate\">
												</form>
											</td>						
										</tr>";
	}
} catch (CXPest_NotFound $e) {	
	$serverErrorMessage = "The specified core server has not been created yet.</br>If this is the first installation run \"Apply Config\" in order to create the core server.</br>If you believe this is an error verify your \"Server Name\" below.";
} catch (CXPest_Forbidden $e) {	
	$serverErrorMessage = "This server is not allowed to access the $cxpanelBrandName server.</br>Modify the $cxpanelBrandName's security.xml file to include this server's IP address in the whitelist of the communication_mananger servlet security settings.</br>You will have to restart your $cxpanelBrandName server once the change has been made.";
} catch (CXPest_Unauthorized $e) {
	$serverErrorMessage = "Failed to authenticate with the $cxpanelBrandName server.</br>Verify that the \"Server API Username\" and \"Server API Password\" below are correct.</br>Also verify that you have a proper realm auth user defined in the $cxpanelBrandName server's security.xml file for the communication_manager servlet security settings.";
} catch (CXPest_UnknownResponse $e) {	
	$serverErrorMessage = "Failed to contact the $cxpanelBrandName server.</br>Verify that your $cxpanelBrandName server is installed and running and that the server API host and port are correct in the fields below.<br/>If you have SSL enabled below and are using the SSL port for the API connection you need to enable SSL in the $cxpanelBrandName server's security.xml file for the communication_manager servlet";
} catch (CXPest_Found $e) {
	$serverErrorMessage = "Failed to connect to the $cxpanelBrandName server.<br/>The $cxpanelBrandName server is secured via SSL.<br/> Set the API port below to the SSL port of the $cxpanelBrandName server and check \"Use SSL\".<br/>The server's SSL port can be found in the main.xml file. Default is 55050.";
} catch (Exception $e) {
	$serverErrorMessage = "An unexpected error occurred while trying to connect to the $cxpanelBrandName server.</br>" . $e->getMessage();
}

//If an error occurred in the server query show disconnected else show connected
$serverRunningDisplay = isset($serverErrorMessage) ? "<span style=\"color: #FF0000;\">NO</span>" : "<span style=\"color: #00FF00;\">YES</span>";

//If the userman module is installed show the general settings
if(function_exists('setup_userman')) {
	$generalSettingsAddition = 	'<tr><td colspan="2"><h5>General Settings<hr></h5></td></tr>' .
								'<tr>' .
						        	'<td><a href="#" class="info">Sync With User Managment:<span>If checked ' . $cxpanelBrandName . ' users will be created based on the users that are configured in User Managment.<br />If unchecked ' . $cxpanelBrandName . ' users will be created based on the ' . $cxpanelBrandName . ' settings in the Extensions page.</span></a></td>' .
						            '<td><input type="checkbox" name="cxpanel_sync_with_userman" value="1" ' . ($serverInformation['sync_with_userman'] == '1' ? 'checked' : '')  .'/></td>' .
						       	'</tr>';
}

//If sync_with_userman is enabled hide the view password and email password links
if($serverInformation['sync_with_userman'] == "1") {
	$passwordAddition = '';
	$emailPasswordLink = '';
}

?>

<script language="javascript">
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
	<?php echo $licenseActivationError; ?>
	<table>
		<tr><td colspan="2"><h2 id="title"><?php echo $cxpanelBrandName; ?></h2></td></tr>
		<?php echo $licenseBindAddition; ?>
		<tr><td colspan="2"><span style="color: #FF0000;"><?php echo $serverErrorMessage; ?></span></td></tr>
		<?php echo $debugAddition; ?>	
		<?php echo $passwordAddition; ?>
		<?php echo $emailPasswordLink; ?>
		<?php echo $passEmailResults; ?>
		<tr><td colspan="2"><h5>Server<hr></h5></td></tr>
		<tr>
			<td><a href="#" class="info">Connected:<span>Displays if the module can connect to the <?php echo $cxpanelBrandName; ?> server. If not the server may not be running or the connection information below may be incorrect.</span></a></td>
			<td><?php echo $serverRunningDisplay; ?></td>						
		</tr>
		<tr>
			<td><a href="#" class="info">Version:<span>Displays the version of the server.</span></a></td>
			<td><?php echo $versionDisplay; ?></td>						
		</tr>
		<tr><td colspan="2"><h5>License<hr></h5></td></tr>
		<tr>
			<td><a href="#" class="info">Licensed To:<span>Displays the name of the person or company this server is licensed to.</span></a></td>
			<td><?php echo $licensedToDisplay; ?></td>						
		</tr>
		<tr>
			<td><a href="#" class="info">Serial Key:<span>Displays the serial key of the installed license.</span></a></td>
			<td><?php echo $licenseSerialKeyDisplay; ?></td>						
		</tr>
		<tr>
			<td><a href="#" class="info">Type:<span>The license type.</span></a></td>
			<td><?php echo $licensedTypeDisplay; ?></td>
		</tr>
		<?php echo $licenseAdditions; ?>
		<?php echo $licenseActivateAddition; ?>
		<tr><td colspan="2"><span style="color: #FF0000;"><?php echo $licenseActivationErrorMessage; ?></span></td></tr>
		<form name="cxpanel_settings_form" id="cxpanel_settings_form" action="config.php?type=setup&display=cxpanel<?php echo $urlAppend; ?>" method="post" onsubmit="return checkForm();">
		<?php echo $generalSettingsAddition; ?>
		<tr><td colspan="2"><h5>Server API Connection Settings<hr></h5></td></tr>
		<tr>
        	<td><a href="#" class="info">Server Name:<span>Unique id of the core server instance to manage.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_name" value="<?php echo htmlspecialchars($serverInformation['name']); ?>"></td>
       	</tr>      	
		<tr>
            <td><a href="#" class="info">Host:<span>IP Address or host name of the <?php echo $cxpanelBrandName; ?> server API. Set to "localhost" if the server is installed on the same machine.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_api_host" value="<?php echo htmlspecialchars($serverInformation['api_host']); ?>" /></td>
       	</tr>
        <tr>
            <td><a href="#" class="info">Port:<span>Port of the <?php echo $cxpanelBrandName; ?> server API.<br/><br/>Default Port: 58080<br/>Default SSL Port: 55050 (SSL is disalbed by default on the <?php echo $cxpanelBrandName; ?> server. See the <?php echo $cxpanelBrandName; ?> server security.xml file.)</span></a></td>
           	<td><input size="20" type="text" name="cxpanel_api_port" value="<?php echo htmlspecialchars($serverInformation['api_port']); ?>" /></td>
        </tr>
        <tr>
            <td><a href="#" class="info">Username:<span>Username used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the <?php echo $cxpanelBrandName; ?> server config directory under the communication_manager servlet security settings.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_api_username" value="<?php echo htmlspecialchars($serverInformation['api_username']); ?>" /></td>
        </tr>
        <tr>
            <td><a href="#" class="info">Password:<span>Password used to authenticate with the server API. The realm auth user credentials can be found in the security.xml file in the <?php echo $cxpanelBrandName; ?> server config directory under the communication_manager servlet security settings.</span></a></td>
            <td><input size="20" type="password" name="cxpanel_api_password" value="<?php echo htmlspecialchars($serverInformation['api_password']); ?>" /></td>
        </tr>
        <tr>
            <td><a href="#" class="info">Use SSL:<span>If checked https will be used to communicate with the <?php echo $cxpanelBrandName; ?> server API.<br/><br/>NOTE: If checked your <?php echo $cxpanelBrandName; ?> server must have an SSL keystore configured and the communication_manager servlet security context must have SSL enabled in the security.xml file.</br>You will also need to specify the SSL port number in the API port field above.</span></a></td>
            <td><input type="checkbox" name="cxpanel_api_use_ssl" value="1" <?php echo ($serverInformation['api_use_ssl'] == '1' ? 'checked' : '') ?> /></td>
        </tr>        
        <tr><td colspan="2"><h5>Asterisk Connection Settings<hr></h5></td></tr>
        <tr>
            <td><a href="#" class="info">Asterisk Server Host:<span>The ip or hostname of the Asterisk server. This is used to tell the <?php echo $cxpanelBrandName; ?> server how to connect to Asterisk. If the <?php echo $cxpanelBrandName; ?> server and Asterisk are on the same machine this field should be set to "localhost".</span></a></td>
            <td><input size="20" type="text" name="cxpanel_asterisk_host" value="<?php echo htmlspecialchars($serverInformation['asterisk_host']); ?>" /></td>
        </tr>
        <tr><td colspan="2"><h5>Module Client Link Settings<hr></h5></td></tr>
        <tr>
            <td><a href="#" class="info">Client Host:<span>IP Address or host name of the <?php echo $cxpanelBrandName; ?> client. This setting is used when accessing the <?php echo $cxpanelBrandName; ?> client via the links in this GUI and for client links in password emails. If not set the ip or host name from the current URL will be utilized. Normally this should remain blank unless you have a remote <?php echo $cxpanelBrandName; ?> Server install.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_client_host" value="<?php echo htmlspecialchars($serverInformation['client_host']); ?>" /></td>
       	</tr>
        <tr>
            <td><a href="#" class="info">Client Port:<span>Web port of the <?php echo $cxpanelBrandName; ?> client.</span></a></td>
           	<td><input size="20" type="text" name="cxpanel_client_port" value="<?php echo htmlspecialchars($serverInformation['client_port']); ?>" /></td>
        </tr> 
        <tr><td colspan="2"><h5>Voicemail Agent Settings<hr></h5></td></tr>
        <tr>
        	<td><a href="#" class="info">Identifier:<span>Identifier of the voicemail agent to bind and configure.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_voicemail_agent_identifier" value="<?php echo htmlspecialchars($voicemailAgentInformation['identifier']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Directory:<span>Path to the root voicemail directory.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_voicemail_agent_directory" value="<?php echo htmlspecialchars($voicemailAgentInformation['directory']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Resource Host:<span>Hostname or IP used to build voicemail playback URLs.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_voicemail_agent_resource_host" value="<?php echo htmlspecialchars($voicemailAgentInformation['resource_host']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Resource Extension:<span>File extension used to build voicemail playback URLs.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_voicemail_agent_resource_extension" value="<?php echo htmlspecialchars($voicemailAgentInformation['resource_extension']); ?>"></td>
       	</tr>
        <tr><td colspan="2"><h5>Recording Agent Settings<hr></h5></td></tr>
        <tr>
        	<td><a href="#" class="info">Identifier:<span>Identifier of the recording agent to bind and configure.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_recording_agent_identifier" value="<?php echo htmlspecialchars($recordingAgentInformation['identifier']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Directory:<span>Path to the root recording directory.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_recording_agent_directory" value="<?php echo htmlspecialchars($recordingAgentInformation['directory']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Resource Host:<span>Hostname or IP used to build recording playback URLs.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_recording_agent_resource_host" value="<?php echo htmlspecialchars($recordingAgentInformation['resource_host']); ?>"></td>
       	</tr>
       	<tr>
        	<td><a href="#" class="info">Resource Extension:<span>File extension used to build recording playback URLs. Also used as the file type when on demand recordings are made in the panel.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_recording_agent_resource_extension" value="<?php echo htmlspecialchars($recordingAgentInformation['resource_extension']); ?>"></td>
       	</tr>
        <tr>
        	<td><a href="#" class="info">File Mask:<span>File name mask used to parse recording file names and create recording files when on demand recordings are made in the panel.</span></a></td>
            <td><input size="20" type="text" name="cxpanel_recording_agent_filename_mask" value="<?php echo htmlspecialchars($recordingAgentInformation['file_name_mask']); ?>"></td>
       	</tr>
        <tr><td colspan="2"><h5>Password Email Settings<hr></h5></td></tr>
       	<tr>
            <td><a href="#" class="info">Subject:<span>The subject text of the email. You can specify the following variables in the email:<br/><br/>%%userId%% = The the username that the password belongs to.<br/>%%password%% = The password value.<br/>%%clientURL%% = The URL used to log into the client. Built using the Client Host and Client Port fields above.</span></a></td>
            <td><input size="50" type="text" name="cxpanel_email_subject" value="<?php echo htmlspecialchars($emailSettings['subject']); ?>" /></td>
       	</tr>
       	<tr>
            <td><a href="#" class="info">Body:<span>The body text of the email. If HTML is selected as the type you can include HTML tags. You can specify the following variables in the email:<br/><br/>%%userId%% = The the username that the password belongs to.<br/>%%password%% = The password value.<br/>%%clientURL%% = The URL used to log into the client. Built using the Client Host and Client Port fields above.<br/>%%logo%% = The <?php echo $cxpanelBrandName; ?> logo image.</span></a></td>
            <td><textarea name="cxpanel_email_body" cols="49" rows="10"><?php echo htmlspecialchars($emailSettings['body']); ?></textarea></td>
       	</tr>
        <tr>
			<td colspan="2"><input type="Submit" name="cxpanel_settings" value="Submit Changes"></td>
		</tr>
		</form>
	</table>
</div>

