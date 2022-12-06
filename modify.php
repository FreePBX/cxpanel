<?php
/*
 *Name         : modify.php
 *Author       : Michael Yara
 *Created      : May 10, 2011
 *Last Updated : April 25, 2014
 *Version      : 3.0
 *Purpose      : Allows running the main sync code in a separate thread
 */



//Bootstrap FreePBX
include '/etc/freepbx.conf';

require_once(dirname(__FILE__)."/lib/CXPestJSON.php");
require_once(dirname(__FILE__)."/lib/cxpanel.class.php");

//Multiplier used to determine the execution timeout based on the number of extensions
$executionTimeoutMultiplier = 3;



$cxpanel = \FreePBX::Cxpanel();
$logger  = $cxpanel->logInit("log_modify");

//$logger->echoLog = true;
$logger->open();


/*
 * Set execution timeout to a large value so that we can wait for a lock if another instance of the script is running.
 * The timeout will be modified later in the script based on the number of extension elements.
 */
set_time_limit(6000);

//Attempt to acquire script lock and block until we have it. This prevents multiple instances of this script from running at the same time.
$lock = fopen($cxpanel->getpath("lock"), "w");
if(!flock($lock, LOCK_EX)) {
	$logger->error(_("Failed to acquire script lock."));
	cleanup();
}

/*
 * Reset execution timeout so that the script does not die if a small amount of time
 * was left after the wait for the script lock. This is to prevent the script from
 * dying without releasing the lock.
 */
set_time_limit(60);

//Create running time tracker
$runningTimeStart = microtime(true);

$logger->debug(_("Starting modify script"));

//Synchronize tables before we synchronize the server configuration
sync_database();

//Get the agent interface type
$agentInterfaceType = $cxpanel->get_agent_interface_type();
$logger->debug(sprintf(_("Agent interface type: %s"), $agentInterfaceType));

//Get the agent login context
$agentLoginContext = $cxpanel->get_agent_login_context();
$logger->debug(sprintf(_("Agent login context: %s"), $agentLoginContext));

//Grab the server info
if(($serverInformation = $cxpanel->server_get()) === null) {
	$logger->error(sprintf(_("Failed to query server information: %s"), $db->getMessage()));
	cleanup();
}

//Grab the voicemail agent info
if(($voicemailAgentInformation = $cxpanel->voicemail_agent_get()) === null) {
	$logger->error(sprintf(_("Failed to query voicemail agent information: %s"), $db->getMessage()));
	cleanup();
}

//Grab the recording agent info
if(($recordingAgentInformation = $cxpanel->recording_agent_get()) === null) {
	$logger->error(sprintf(_("Failed to query recording agent information: %s"), $db->getMessage()));
	cleanup();
}

//Grab the user info
if(($userInformation = $cxpanel->user_list()) === null) {
	$logger->error(sprintf(_("Failed to query user information: %s"), $db->getMessage()));
	cleanup();
}

//Grab the queue info
if(($queueInformation = $cxpanel->queue_list()) === null) {
	$logger->error(sprintf(_("Failed to query queue information: %s"), $db->getMessage()));
	cleanup();
}

//Grab the conference room info
if(($conferenceRoomInformation = $cxpanel->conference_room_list()) === null) {
	$logger->error(sprintf(_("Failed to query conference room information: %s"), $db->getMessage()));
	cleanup();
}

//Set execution timeout based on the number of extension elements
set_time_limit(30 + (count($userInformation) * $executionTimeoutMultiplier));

//Set up the REST connection
$pest = $cxpanel->getApiREST($logger);

//Check if sync_with_userman is enabled
$syncWithUsermanEnabled = $serverInformation['sync_with_userman'] == '1' && function_exists('setup_userman');

//Check the core server
check_core_server();

//Get the core server id
$coreServerId = get_core_server_id();

//Sync the core server
sync_core_server();

//Sync voicemail agent
sync_voicemail_agent();

//Sync recording agent
sync_recording_agent();

//Sync the pbx server
sync_pbx_server();

//Sync administrators
sync_administrators();

//Sync extensions
sync_extensions();

//Sync users
if($syncWithUsermanEnabled)
{
	sync_users_userman();
}
else
{
	sync_users();
}

//Sync user contact information
if($syncWithUsermanEnabled)
{
	sync_user_contacts_userman();
}
else
{
	sync_user_contacts();
}

//Sync extension users
if($syncWithUsermanEnabled)
{
	sync_extension_users_userman();
}
else
{
	sync_extension_users();
}

//Sync queues
sync_queues();

//Sync conference rooms
sync_conference_rooms();

//Sync parking lot
sync_parking_lot();

//Log running time
$runningTimeStop = microtime(true);
$logger->debug(sprintf(_("Total Running Time: %ss"), ($runningTimeStop - $runningTimeStart)));

//Cleanup
cleanup();

/**
 * Synchronizes the user, queue, and conference room cxpanel tables with the values
 * stored in the FreePBX tables, adding missing items and removing non existent items.
 *
 * This function ensures that the cxpanel tables match up with the values stored in FreePBX
 * before we synchronize configuration with the server.
 */
function sync_database()
{
	global $logger, $cxpanel;

	$logger->debug(_('Synchronizing database'));

	//Synchronize the user table
	if(\FreePBX::Modules()->checkStatus('core'))
	{
		$mod_core = \FreePBX::Core();
		$freePBXUserIds = array();
		if (($freePBXUsers = $mod_core->listUsers()) !== null)
		{
			foreach($freePBXUsers as $freePBXUser)
			{
				$userId = $freePBXUser[0];
				array_push($freePBXUserIds, $userId);
				if($cxpanel->user_get($userId) === null)
				{
					//Determine user info
					$userDevice = $mod_core->getDevice($userId);
					$peer = ($userDevice['dial'] != "") ? $userDevice['dial'] : "SIP/$userId";
					$displayName = $freePBXUser[1] == "" ? $freePBXUser[0] : $freePBXUser[1];

					//Generate a password for the user
					$password = $cxpanel->generate_password(10);

					//Add user
					$logger->debug(sprintf(_('Adding missing user to database %s'), $userId));
					$cxpanel->user_add_with_initial_password($userId, true, true, $password, false, $peer, $displayName, true, $userId);
				}
			}

			//Remove users that do not exist
			foreach($cxpanel->user_list() as $cxpanelUser)
			{
				$userId = $cxpanelUser['user_id'];
				if(!in_array($userId, $freePBXUserIds))
				{
					$logger->debug(sprintf(_('Removing non existent user from database %s'), $userId));
					$cxpanel->user_del($userId);
				}
			}
		}
	}

	//Synchronize the queue table
	if(function_exists('queues_list') && (($freePBXQueues = queues_list()) !== null))
	{
		//Add missing queues
		$freePBXQueueIds = array();
		foreach($freePBXQueues as $freePBXQueue)
		{
			$queueId = $freePBXQueue[0];
			array_push($freePBXQueueIds, $queueId);
			if($cxpanel->queue_get($queueId) === null)
			{
				$logger->debug(sprintf(_('Adding missing queue to database %s'), $queueId));
				$cxpanel->queue_add($queueId, true, $freePBXQueue[1]);
			}
		}

		//Remove queues that do not exist
		foreach($cxpanel->queue_list() as $cxpanelQueue)
		{
			$queueId = $cxpanelQueue['queue_id'];
			if(!in_array($queueId, $freePBXQueueIds))
			{
				$logger->debug(sprintf(_('Removing non existent queue from database %s'), $queueId));
				$cxpanel->queue_del($queueId);
			}
		}
	}

	//Synchronize the conference room table
	if(\FreePBX::Modules()->checkStatus('conferences'))
	{
		$mod_conferences = \FreePBX::Conferences();
		$freePBXConferences = array();
			if(($freePBXConferences = $mod_conferences->listConferences()) !== null)
		{
			//Add missing conference rooms
			$freePBXConferenceIds = array();
			foreach($freePBXConferences as $freePBXConference)
			{
				$conferenceId = $freePBXConference[0];
				array_push($freePBXConferenceIds, $conferenceId);
				if($cxpanel->conference_room_get($conferenceId) === null)
				{
					$logger->debug(sprintf(_('Adding missing conference room to database %s'), $conferenceId));
					$cxpanel->conference_room_add($conferenceId, true, $freePBXConference[1]);
				}
			}

			//Remove conference rooms that do not exist
			foreach($cxpanel->conference_room_list() as $cxpanelConference)
			{
				$conferenceId = $cxpanelConference['conference_room_id'];
				if(!in_array($conferenceId, $freePBXConferenceIds))
				{
					$logger->debug(sprintf(_('Removing non existent conference room from database %s'), $conferenceId));
					$cxpanel->conference_room_del($conferenceId);
				}
			}
		}
	}
}

/**
 *
 * Gets the core server id relating the stored core server slug
 *
 */
function get_core_server_id()
{
	global $logger, $pest, $serverInformation;
	$logger->debug(sprintf(_("Looking up core server id for slug %s"), $serverInformation['name']));
	try
	{
		$coreServer = $pest->get("server/coreServers/getBySlug/" . $serverInformation['name']);
		$logger->debug(sprintf(_("Core server id: %s"), $coreServer->id));
		return $coreServer->id;
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to lookup core server id"), $e);
		cleanup();
	}
}

/**
 *
 * Checks if a core server with the stored core server slug exists.
 * If it does not exist it is created.
 *
 */
function check_core_server()
{
	global $logger, $pest, $serverInformation;
	$logger->debug(sprintf(_("Checking core server %s"), $serverInformation['name']));
	//Check if the core server exists if not create it
	try
	{
		$pest->get("server/coreServers/getBySlug/" . $serverInformation['name']);
	}
	catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
	{
		$logger->debug(_("Core server not found. Creating"));
		$coreServer = new \FreePBX\modules\Cxpanel\cxpanel_core_server($serverInformation['name'], $serverInformation['name'], "localhost", "50000");
		try
		{
			$pest->post("server/coreServers/", $coreServer);
		}
		catch (Exception $e)
		{
			$logger->error_exception(_("Failed to create core server"), $e);
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to check for core server"), $e);
	}
}

/**
 *
 * Syncs the Asterisk core server information.
 *
 */
function sync_core_server()
{
	global $coreServerId, $logger, $pest, $serverInformation;
	$logger->debug(_("Syncing core server"));
	try
	{
		$coreServer = $pest->get("asterisk/" . $coreServerId);
		//Check if the asterisk server information matches if not update the asterisk server properties
		if(	$coreServer->originatingContext != "from-internal" || $coreServer->redirectingContext != "from-internal" || $coreServer->musicOnHoldClass != "default")
		{
			$logger->debug("Core server info does not match. Updating");
			$coreServer->originatingContext = "from-internal";
			$coreServer->redirectingContext = "from-internal";
			$coreServer->musicOnHoldClass = "default";
			$pest->put("asterisk/" . $coreServerId, $coreServer);
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync core server"), $e);
	}
}

/**
 *
 * Syncs the voicemail agent information.
 *
 */
function sync_voicemail_agent()
{
	global $coreServerId, $logger, $pest, $voicemailAgentInformation, $serverInformation;
	$logger->debug(_("Syncing voicemail agent"));
	try
	{
		$voicemailAgent = $pest->get("asterisk/voicemailAgents/" . $voicemailAgentInformation['identifier']);
		//Check if the voicemail agent config information matches if not update the voicemail agent properties.
		if(	$voicemailAgent->rootPath != $voicemailAgentInformation['directory'] || $voicemailAgent->resourceHost != $voicemailAgentInformation['resource_host'] || $voicemailAgent->resourceExtension != $voicemailAgentInformation['resource_extension'])
		{
			$logger->debug("Voicemail agent info does not match. Updating");
			$voicemailAgent->rootPath = $voicemailAgentInformation['directory'];
			$voicemailAgent->resourceHost = $voicemailAgentInformation['resource_host'];
			$voicemailAgent->resourceExtension = $voicemailAgentInformation['resource_extension'];
			$pest->put("asterisk/voicemailAgents/" . $voicemailAgentInformation['identifier'], $voicemailAgent);
		}

		/*
		 * Check to see if the configured voicemail agent identifier is bound
		 * on the server. If not bind it. If clean_unknown_items is enabled
		 * remove all other agent identifiers that do not match the
		 * configured one.
		 */
		$found = false;
		$voicemailAgentIdentifiers = $pest->get("asterisk/" . $coreServerId . "/voicemailAgentIdentifiers");
		foreach($voicemailAgentIdentifiers as $voicemailAgentIdentifier)
		{
			if($voicemailAgentIdentifier == $voicemailAgentInformation['identifier'])
			{
				$found = true;
			}
			else if($serverInformation['clean_unknown_items'] == '1')
			{
				$found = true;
				$logger->debug(sprintf(_("Removing voicemail agent identifier: %s"), $voicemailAgentIdentifier));
				$pest->delete("asterisk/" . $coreServerId . "/voicemailAgentIdentifiers/" . $voicemailAgentIdentifier);
			}
		}
		if(!$found)
		{
			$logger->debug(sprintf(_("Adding voicemail agent identifier: %s"), $voicemailAgentInformation['identifier']));
			$pest->post("asterisk/" . $coreServerId . "/voicemailAgentIdentifiers/", $voicemailAgentInformation['identifier']);
		}

	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync voicemail agent"), $e);
	}
}

/**
 *
 * Syncs the recording agent information.
 *
 */
function sync_recording_agent()
{
	global $logger, $pest, $recordingAgentInformation;
	$logger->debug(_("Syncing recording agent"));
	try
	{
		$recordingAgent = $pest->get("asterisk/recordingAgents/" . $recordingAgentInformation['identifier']);

		//Check if the recording agent config information matches if not update the recording agent properties.
		if(	$recordingAgent->rootPath != $recordingAgentInformation['directory'] || $recordingAgent->resourceHost != $recordingAgentInformation['resource_host'] || $recordingAgent->resourceExtension != $recordingAgentInformation['resource_extension'] || $recordingAgent->fileNameMask != $recordingAgentInformation['file_name_mask'])
		{
			$logger->debug("Recording agent info does not match. Updating");
			$recordingAgent->rootPath = $recordingAgentInformation['directory'];
			$recordingAgent->resourceHost = $recordingAgentInformation['resource_host'];
			$recordingAgent->resourceExtension = $recordingAgentInformation['resource_extension'];
			$recordingAgent->fileNameMask = $recordingAgentInformation['file_name_mask'];
			$pest->put("asterisk/recordingAgents/" . $recordingAgentInformation['identifier'], $recordingAgent);
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync recording agent"), $e);
	}
}

/**
 *
 * Checks if there is a PBX server connection that matches the info in the database.
 * If no matching PBX server connection is found one is created. If it is found and the
 * CDR database information does not match it is updated.
 *
 * If clean_unknown_items is enabled all other PBX server connections that are found are removed.
 *
 */
function sync_pbx_server()
{
	global $coreServerId, $logger, $pest, $serverInformation, $amp_conf, $recordingAgentInformation;
	$logger->debug(_("Checking PBX server connection"));
	//Check if the pbx server connection exists if not create it
	try
	{
		$pbxConnections = $pest->get("asterisk/" . $coreServerId . "/pbxServers");
		/*
		 * Check to see if any of the pbx connections match the info stored by the module.
		 * If not create one. If clean_unknown_items is disabled remove all others
		 * that do not match.
		 */
		foreach($pbxConnections as $pbxConnection)
		{
			if(	$pbxConnection->host == $serverInformation['asterisk_host'] && $pbxConnection->port == "5038" && $pbxConnection->username == "cxpanel" && $pbxConnection->password == "cxmanager*con" && !isset($foundPBXConnection))
			{
				$logger->debug(sprintf(_("Found matching PBX server connection with id: %s"), $pbxConnection->id));
				$foundPBXConnection = $pbxConnection;
			}
			else if($serverInformation['clean_unknown_items'] == '1')
			{
				$logger->debug(sprintf(_("Removing PBX server connection with id: %s"), $pbxConnection->id));
				$pest->delete("asterisk/" . $coreServerId . "/pbxServers/" . $pbxConnection->id);
			}
		}

		/*
		 * If no pbx server connection was found that matches the database
		 * create a new one else verify the cdr info is correct if not update it.
		 */
		$brand 		 = !empty($amp_conf['DASHBOARD_FREEPBX_BRAND']) ? $amp_conf['DASHBOARD_FREEPBX_BRAND'] : 'FreePBX';
		$displayName = sprintf("%s - %s", $brand, $serverInformation['asterisk_host']);
		if(!isset($foundPBXConnection))
		{
			$logger->debug(_("No matching PBX server connection found. Creating"));
			$pbxConnection = new \FreePBX\modules\Cxpanel\cxpanel_pbx_server($displayName, $serverInformation['asterisk_host'], "5038", "cxpanel", "cxmanager*con", $serverInformation['asterisk_host'], "3306", $amp_conf['AMPDBUSER'], $amp_conf['AMPDBPASS'], true, $recordingAgentInformation['identifier']);
			$pest->post("asterisk/" . $coreServerId . "/pbxServers", $pbxConnection);
		}
		else if($foundPBXConnection->cdrHost != $serverInformation['asterisk_host'] || $foundPBXConnection->cdrPort != "3306" || $foundPBXConnection->cdrUsername != $amp_conf['AMPDBUSER'] || $foundPBXConnection->cdrPassword != $amp_conf['AMPDBPASS'] || $foundPBXConnection->recordingAgentIdentifier != $recordingAgentInformation['identifier'])
		{
			$logger->debug(_("PBX server info does not match. Updating"));
			$foundPBXConnection->displayName = $displayName;
			$foundPBXConnection->cdrHost = $serverInformation['asterisk_host'];
			$foundPBXConnection->cdrPort = "3306";
			$foundPBXConnection->cdrUsername = $amp_conf['AMPDBUSER'];
			$foundPBXConnection->cdrPassword = $amp_conf['AMPDBPASS'];
			$foundPBXConnection->recordingAgentIdentifier = $recordingAgentInformation['identifier'];
			$pest->put("asterisk/" . $coreServerId . "/pbxServers/" . $pbxConnection->id, $foundPBXConnection);
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to check for PBX server connection"), $e);
	}
}

/**
 *
 * Syncs administrators
 *
 */
function sync_administrators()
{
	global $coreServerId, $logger, $pest, $cxpanel;
	$logger->debug(_("Syncing administrators"));
	try
	{
		//Get the server administrators
		$serverAdministrators = $pest->get("server/administrators");
		//Create associative array of the server admin usernames to the administrator objects for quick indexing
		$serverAdminAssoc = array();
		foreach($serverAdministrators as $serverAdministrator)
		{
			$serverAdminAssoc[$serverAdministrator->userName] = $serverAdministrator;
		}

		//Grab the administrators
        $administrators = $cxpanel->get_combined_administrators();

		//Filter list to exclude administrators that do not have access to the cxpanel module while creating an associative array for quick indexing
		foreach($administrators as $admin)
		{
			if(strpos($admin["sections"],"*") !== false || strpos($admin["sections"], "cxpanel") !== false)
			{
				$administratorsAccoc[$admin['username']] = $admin;
			}
		}

		/*
		* Remove all admins from the server that are not stored in the database or do not have access to the cxpanel module.
		* Only remove items that this module manages.
		*/
		foreach($serverAdminAssoc as $username => $admin)
		{
			if(!array_key_exists($username, $administratorsAccoc) && $cxpanel->has_managed_item('admin', $admin->id))
			{
				$logger->debug(sprintf(_("Removing administrator: %s"), $username));

				//Remove the managed entry
				$cxpanel->managed_item_del('admin', $admin->id);

				//Remove the object from the server
				try
				{
					$pest->delete("server/administrators/" . $admin->id);
					unset($serverAdminAssoc[$username]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove administrator: %s"), $username), $e);
				}
			}
		}

		//Add admins that are missing on the server and update ones that are not up to date
		foreach($administratorsAccoc as $admin)
		{
			//Add administrator
			if(!array_key_exists($admin['username'], $serverAdminAssoc))
			{
				$logger->debug(sprintf(_("Adding administrator: %s"), $admin['username']));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('admin', $admin['username']);

				if (empty($admin['password_sha1']))
				{
					//Generate a password for the user
					$password = $cxpanel->generate_password(10);
					$admin['password_sha1'] = $password;
				}

				//Add object from server
				try
				{
					$adminObj = new \FreePBX\modules\Cxpanel\cxpanel_administrator($admin['username'], $admin['password_sha1'], true);
					$adminObj->id = $uuid;
					$pest->post("server/administrators/noHash", $adminObj);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add administrator: %s"), $admin['username']), $e);
				}

				//Update administrator
			}
			else
			{
				$serverAdmin = $serverAdminAssoc[$admin['username']];
				
				//Update the managed item uuid
				$cxpanel->managed_item_update('admin', $admin['username'], $serverAdmin->id);

				//Update the administrator if needed
				if(!empty($admin['password_sha1']) && $serverAdmin->password != strtolower($admin['password_sha1']))
				{
					$logger->debug(sprintf(_("Updating administrator: %s"), $admin['username']));
					try
					{
						$serverAdmin->password = $admin['password_sha1'];
						$pest->put("server/administrators/noHash/" . $serverAdmin->id, $serverAdmin);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update administrator: %s"), $admin['username']), $e);
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync administrators"), $e);
	}
}

/**
 *
 * Syncs extensions.
 *
 */
function sync_extensions()
{
	global $coreServerId, $logger, $pest, $userInformation, $agentLoginContext, $cxpanel;
	$logger->debug(_("Syncing extensions"));
	try {
		//Grab the extension list from the server
		$serverExtensions = $pest->get("asterisk/" . $coreServerId . "/extensions");

		//Create associative array of the server extension numbers to the extension objects for quick indexing
		$serverExtensionAssoc = array();
		foreach($serverExtensions as $serverExtension)
		{
			$serverExtensionAssoc[$serverExtension->extension] = $serverExtension;
		}

		//Filter list to exclude extensions that are not marked for addition while creating an associative array for quick indexing
		$extensions = array();
		foreach($userInformation as $user)
		{
			if($user["add_extension"] == "1")
			{
				$extensions[$user['user_id']] = $user;
			}
		}

		/*
		 * Remove all extensions from the server that are not stored in the database
		 * Only remove items that this module manages.
		 */
		foreach($serverExtensionAssoc as $extensionNumber => $extension)
		{
			if(	!array_key_exists($extensionNumber, $extensions) && $cxpanel->has_managed_item('extension', $extension->id))
			{
				$logger->debug(sprintf(_("Removing extension: %s"), $extensionNumber));

				//Remove the managed entry
				$cxpanel->managed_item_del('extension', $extension->id);

				//Remove the extension from the server
				try
				{
					$pest->delete("asterisk/" . $coreServerId . "/extensions/" . $extension->id);
					unset($serverExtensionAssoc[$extensionNumber]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove extension: %s"), $extensionNumber), $e);
				}
			}
		}

		//Build associative array of user ids to voicemail contexts
		$voicemailContexts = array();
		foreach(\FreePBX::Core()->listUsers() as $user)
		{
			$voicemailContexts[$user[0]] = $user[2];
		}

		//Add extensions that are missing on the server and update ones that are not up to date
		foreach($extensions as $extension)
		{
			//Grab values from database
			$extensionNumber 	 = $extension['user_id'];
			$displayName 		 = $extension['display_name'];
			$autoAnswer 		 = $extension['auto_answer'] == "1";
			$peer 				 = $extension['peer'];
			$agentLoginLocation  = sprintf("Local/%s@%s/n", $extensionNumber, $agentLoginContext);
			$agentLoginInterface = get_agent_login_interface($extension);
			$voicemailContext 	 = array_key_exists($extensionNumber, $voicemailContexts) && $voicemailContexts[$extensionNumber] != 'novm' ? $voicemailContexts[$extensionNumber] : 'default';

			//Add extension
			if(!array_key_exists($extensionNumber, $serverExtensionAssoc))
			{
				$logger->debug(sprintf(_("Adding extension: %s"), $extensionNumber));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('extension', $extensionNumber);

				//Add the extension to the server
				try
				{
					$extensionObj = new \FreePBX\modules\Cxpanel\cxpanel_extension(	false, $extensionNumber, $displayName, $autoAnswer, $peer, "", $displayName, $agentLoginLocation,$agentLoginInterface, 0, false, "", "", 0, $voicemailContext, $extensionNumber);
					$extensionObj->id = $uuid;
					$pest->post("asterisk/" . $coreServerId . "/extensions/", $extensionObj);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add extension: %s"), $extensionNumber), $e);
				}
			//Update extension
			}
			else
			{
				$serverExtension = $serverExtensionAssoc[$extensionNumber];

				//Update the managed item uuid
				$cxpanel->managed_item_update('extension', $extensionNumber, $serverExtension->id);

				//Update the extension item if needed
				if(	$serverExtension->displayName != $displayName ||
					$serverExtension->peer != $peer ||
					$serverExtension->autoAnswer != $autoAnswer ||
					$serverExtension->agentName != $displayName ||
					$serverExtension->agentLocation != $agentLoginLocation ||
					$serverExtension->agentInterface != $agentLoginInterface ||
					$serverExtension->originatingContextOverride != "" ||
					$serverExtension->redirectingContextOverride != "" ||
					$serverExtension->voiceMailContext != $voicemailContext ||
					$serverExtension->voiceMailBox != $extensionNumber) {

					try
					{
						$logger->debug(sprintf(_("Updating extension: %s"), $extensionNumber));
						$serverExtension->displayName = $displayName;
						$serverExtension->peer = $peer;
						$serverExtension->autoAnswer = $autoAnswer;
						$serverExtension->agentName = $displayName;
						$serverExtension->agentLocation = $agentLoginLocation;
						$serverExtension->agentInterface = $agentLoginInterface;
						$serverExtension->originatingContextOverride = "";
						$serverExtension->redirectingContextOverride = "";
						$serverExtension->voiceMailContext = $voicemailContext;
						$serverExtension->voiceMailBox = $extensionNumber;
						$pest->put("asterisk/" . $coreServerId . "/extensions/" . $serverExtension->id, $serverExtension);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update extension: %s"), $extensionNumber), $e);
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync extensions"), $e);
	}
}

/**
 *
 * Sync users
 *
 */
function sync_users()
{
	global $coreServerId, $logger, $pest, $userInformation, $serverInformation, $cxpanel;
	$logger->debug(_("Syncing users"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Add users from cxpanel_users
		$users = array();
		foreach($userInformation as $user)
		{
			if($user["add_user"] == "1")
			{
				$users[$user['user_id']] = $user;
			}
		}

		/*
		 * Remove all users from the server that are not stored in the database.
		 * Only remove users that are managed by this module.
		 *
		 * Removes all known users managed by userman as well
		 */
		foreach($serverUserAssoc as $username => $user)
		{
			if((!array_key_exists($username, $users) && $cxpanel->has_managed_item('user', $user->id)))
			{
				$logger->debug(sprintf(_("Removing user: %s"), $username));

				//Remove the managed entry
				$cxpanel->managed_item_del('user', $user->id);

				//Remove the user from the server
				try
				{
					$pest->delete("core/" . $coreServerId . "/users/" . $user->id);
					unset($serverUserAssoc[$username]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove user: %s"), $username), $e);
				}
			}
		}

		//Add users that are missing on the server and update ones that are not up to date
		foreach($users as $user)
		{
			//Format the full flag
			$full = ($user['full'] == "1");

			//Add user
			if(!array_key_exists($user['user_id'], $serverUserAssoc))
			{
				$logger->debug(sprintf(_("Adding user: %s"), $user['user_id']));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('user', $user['user_id']);

				if (empty($user['hashed_password']))
				{
					//Generate a password for the user
					$password = $cxpanel->generate_password(10);
					$user['hashed_password'] = $password;
				}

				//Add the user to the server
				try
				{
					$serverUser = new \FreePBX\modules\Cxpanel\cxpanel_user(false, $user['user_id'], $user['hashed_password'], true, $full);
					$serverUser->id = $uuid;
					$pest->post("core/" . $coreServerId . "/users/noHash", $serverUser);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add user: %s"), $user['user_id']), $e);
				}

			//Update user
			}
			else
			{
				$serverUser = $serverUserAssoc[$user['user_id']];

				//Update the managed item uuid
				$cxpanel->managed_item_update('user', $user['user_id'], $serverUser->id);

				//Check if the user password needs updating
				$passwordUpdated = false;
				if(!empty($user['hashed_password']) && $serverUser->password != strtolower($user['hashed_password']) && $user["password_dirty"] == "1")
				{
					$serverUser->password = $user['hashed_password'];
					$passwordUpdated = true;
				}

				//If the user password or the full flag on the user has changed update the user
				if($passwordUpdated || ($full != $serverUser->full))
				{
					try
					{
						$logger->debug(sprintf(_("Updating user: %s"), $user['user_id']));
						$serverUser->full = $full;
						$pest->put("core/" . $coreServerId . "/users/noHash/" . $serverUser->id, $serverUser);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update user: %s"), $user['user_id']), $e);
					}
				}
			}
		}

		//Clean all password dirty flags
		$cxpanel->mark_all_user_passwords_dirty(false);
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync users"), $e);
	}
}

/**
 *
 * Sync users when sync_with_userman is enabled
 *
 */
function sync_users_userman()
{
	global $coreServerId, $logger, $pest, $serverInformation, $cxpanel;

	$logger->debug(_("Syncing users (userman)"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Add users from userman
		$users = array();
		$freePBXUsers = $cxpanel->Userman->getAllUsers();
		foreach($freePBXUsers as $freePBXUser)
		{
			/*
			 * Determine the add property.
			 *
			 * If no add property can be found assume true in case there are
			 * FreePBX users that existed before the module was installed.
			 */
			if(\FreePBX::Modules()->moduleHasMethod("userman", "getCombinedModuleSettingByID"))
			{
				$add = $cxpanel->Userman->getCombinedModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			else
			{
				$add = $cxpanel->Userman->getModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			if($add === false)
			{
				$add = '1';
				$cxpanel->Userman->setModuleSettingByID($freePBXUser['id'], 'cxpanel', 'add', '1');
			}

			//If the add property is set add the user
			if($add == '1')
			{
				//Create a panel user entry for the freePBX user
				$fauxUser['user_id'] 		 = $freePBXUser['id'];
				$fauxUser['username'] 		 = $freePBXUser['username'];
				$fauxUser['hashed_password'] = $freePBXUser['password'];
				$fauxUser['full'] 			 = '1';

				/*
				 * Determine the value of the password_dirty property.
				 *
				 * If no password_dirty property can be found assume true in case there are
			 	 * FreePBX users that existed before the module was installed.
				 */
				$passwordDirty = $cxpanel->Userman->getModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'password_dirty');
				if($passwordDirty === false)
				{
					$passwordDirty = '1';
					$cxpanel->Userman->setModuleSettingByID($freePBXUser['id'], 'cxpanel', 'password_dirty', '1');
				}

				//Set the password dirty flag
				$fauxUser['password_dirty'] = $passwordDirty;

				//Add the user to the list
				$users[$freePBXUser['username']] = $fauxUser;
			}
		}

		/*
		 * Remove all users from the server that are not stored in the database.
		 * Only remove items that this module manages.
		 *
		 * Removes all known users not managed by userman as well
		 */
		foreach($serverUserAssoc as $userid => $user)
		{
			if((!array_key_exists($userid, $users) && $cxpanel->has_managed_item('user', $user->id)))
			{
				$logger->debug(sprintf(_("Removing user: %s"), $user->username));

				//Remove the managed entry
				$cxpanel->managed_item_del('user', $user->id);

				try
				{
					$pest->delete("core/" . $coreServerId . "/users/" . $user->id);
					unset($serverUserAssoc[$userid]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove user: %s"), $user->username), $e);
				}
			}
		}

		//Add users that are missing on the server and update ones that are not up to date
		foreach($users as $user)
		{
			//Format the full flag
			$full = ($user['full'] == "1");

			//Add user
			if(!array_key_exists($user['username'], $serverUserAssoc))
			{
				$logger->debug(sprintf(_("Adding user: %s"), $user['username']));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('user', $user['username']);

				if (empty($user['hashed_password']))
				{
					//Generate a password for the user
					$password = $cxpanel->generate_password(10);
					$user['hashed_password'] = $password;
				}

				//Add user to the server
				try
				{
					$serverUser = new \FreePBX\modules\Cxpanel\cxpanel_user(false, $user['username'], $user['hashed_password'], true, $full);
					$serverUser->id = $uuid;
					$pest->post("core/" . $coreServerId . "/users/noHash", $serverUser);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add user: %s"), $user['username']), $e);
				}

			//Update user
			}
			else
			{
				$serverUser = $serverUserAssoc[$user['username']];

				//Update the managed item uuid
				$cxpanel->managed_item_update('user', $user['username'], $serverUser->id);

				//Check if the user password needs updating
				$passwordUpdated = false;
				if(!empty($user['hashed_password']) && $serverUser->password != strtolower($user['hashed_password']) && $user["password_dirty"] == "1")
				{
					$serverUser->password = $user['hashed_password'];
					$passwordUpdated = true;
				}

				//Check if the user needs to be updated
				if($passwordUpdated || ($full != $serverUser->full) || ($serverUser->username != $user['username']))
				{
					try
					{
						$logger->debug(sprintf(_("Updating user: %s"), $user['username']));
						$serverUser->full = $full;
						$serverUser->username = $user['username'];
						$pest->put("core/" . $coreServerId . "/users/noHash/" . $serverUser->id, $serverUser);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update user: %s"), $user['username']), $e);
					}
				}
			}
		}

		//Clean all password dirty flags
		$cxpanel->mark_all_user_passwords_dirty(false);
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync users"), $e);
	}
}

/**
 *
 * Sync user contacts
 *
 */
function sync_user_contacts()
{
	global $coreServerId, $logger, $pest, $userInformation, $cxpanel;
	$logger->debug(_("Syncing user contact information"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Filter list to exclude users that are not marked for addition while creating an associative array for quick indexing
		$users = array();
		foreach($userInformation as $user)
		{
			if($user["add_user"] == "1")
			{
				$users[$user['user_id']] = $user;
			}
		}

		//Upate the contact information on each of the server users
		foreach($users as $user)
		{
			//Grab the server user
			$serverUser = $serverUserAssoc[$user['user_id']];

			//Grab the users email
			$email = "";
			$voiceMailBox = $cxpanel->hook_voicemail_getMailBox($user['user_id']);
			if(	$voiceMailBox != null && ! empty($voiceMailBox['email']))
			{
				$email = $voiceMailBox['email'];
			}

			//Split the user display name into first and last name
			$userDisplayNameArray = explode(" ", $user['display_name']);
			$firstName 			  = $userDisplayNameArray[0];
			$lastName 			  = "";
			for($i = 1; $i < count($userDisplayNameArray); $i++)
			{
				$lastName .= $userDisplayNameArray[$i];
				if($i != (count($userDisplayNameArray) - 1))
				{
					$lastName .= " ";
				}
			}

			//Get the current user contact information
			try
			{
				$serverUserContact = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id);
			}
			catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
			{
				//Eat exception
			}

			//Check if the user contact information needs to be updated
			if(	!isset($serverUserContact) || $serverUserContact->firstName != $firstName || $serverUserContact->lastName != $lastName)
			{
				$logger->debug(sprintf(_("Updating user contact information: %s"), $user['user_id']));
				try
				{
					$serverUserContact = new \FreePBX\modules\Cxpanel\cxpanel_user_contact($firstName, $lastName);
					$pest->put("contact/" . $coreServerId . "/users/" . $serverUser->id, $serverUserContact);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to update user contact information: %s"), $user['user_id']), $e);
				}
			}

			//Check if the user email address should be added
			if($email != "")
			{
				try
				{
					$serverUserEmailAddresses = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id . "/emailAddresses");
					//Search for the current email
					$emailFound = false;
					foreach($serverUserEmailAddresses as $serverUserEmailAddress)
					{
						if($serverUserEmailAddress->address == $email)
						{
							$emailFound = true;
							break;
						}
					}

					//If the email was not found add it
					if(!$emailFound)
					{
						$logger->debug(sprintf(_("Adding user contact email address: %s"), $user['user_id']));
						$serverUserEmailAddress = new \FreePBX\modules\Cxpanel\cxpanel_user_contact_email_address("Work", $email);
						$pest->post("contact/" . $coreServerId . "/users/" . $serverUser->id . "/emailAddresses", $serverUserEmailAddress);
					}
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to update user contact email address information: %s"), $user['user_id']), $e);
				}
			}

			//Create associative array of the server user phone number strings to the user objects for quick indexing
			$serverUserPhoneNumberAssoc = array();
			$serverUserPhoneNumbers = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers");
			foreach($serverUserPhoneNumbers as $serverUserPhoneNumber)
			{
				$serverUserPhoneNumberAssoc[$serverUserPhoneNumber->number . '@#' . $serverUserPhoneNumber->type] = $serverUserPhoneNumber;
			}

			//Look for phone numbers to add while bilding the local user phone number associative array
			$userPhoneNumbers = $cxpanel->phone_number_list($user['user_id']);
			$userPhoneNumberAssoc = array();
			foreach($userPhoneNumbers as $userPhoneNumber)
			{
				$idString = $userPhoneNumber['phone_number'] . '@#' . $userPhoneNumber['type'];
				array_push($userPhoneNumberAssoc, $idString);
				if(!array_key_exists($idString, $serverUserPhoneNumberAssoc))
				{
					$logger->debug(sprintf(_("Adding user contact phone number: %s (%s) to user: %s"), $userPhoneNumber['phone_number'], $userPhoneNumber['type'], $user['user_id']));
					$userPhoneNumberObj = new \FreePBX\modules\Cxpanel\cxpanel_user_contact_phone_number($userPhoneNumber['type'], $userPhoneNumber['phone_number']);
					$pest->post("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers", $userPhoneNumberObj);
				}
			}

			//Look for phone numbers to remove
			foreach($serverUserPhoneNumbers as $serverUserPhoneNumber)
			{
				if(!in_array($serverUserPhoneNumber->number . '@#' . $serverUserPhoneNumber->type, $userPhoneNumberAssoc))
				{
					$logger->debug(sprintf(_("Removing user contact phone number: %s (%s) from user: %s"), $serverUserPhoneNumber->number, $serverUserPhoneNumber->type, $user['user_id']));
					$pest->delete("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers/" . $serverUserPhoneNumber->id);
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync user contact information"), $e);
	}
}

/**
 *
 * Sync user contacts when sync_with_userman is enabled
 *
 */
function sync_user_contacts_userman()
{
	global $coreServerId, $logger, $pest, $cxpanel;
	$logger->debug(_("Syncing user contact information (userman)"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Add users from userman
		$users = array();
		$freePBXUsers = $cxpanel->Userman->getAllUsers();
		foreach($freePBXUsers as $freePBXUser)
		{
			/*
			 * If the add property is set add the user.
			 *
			 * If no add property can be found assume true in case there are
			 * FreePBX users that existed before the module was installed.
			 */
			if(\FreePBX::Modules()->moduleHasMethod("userman", "getCombinedModuleSettingByID"))
			{
				$add = $cxpanel->Userman->getCombinedModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			else
			{
				$add = $cxpanel->Userman->getModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			$add = $add === false ? '1' : $add;
			if($add == '1')
			{
				//Add the user to the list
				$users[$freePBXUser['username']] = $freePBXUser;
			}
		}

		//Upate the contact information on each of the server users
		foreach($users as $user)
		{
			//Grab the server user
			$serverUser = $serverUserAssoc[$user['username']];

			//Get the current user contact information
			try
			{
				$serverUserContact = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id);
			}
			catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
			{
				//Eat exception
			}

			//Get the user's first and last name
			if(!empty($user['displayname']))
			{
				$parts = explode(" ", $user['displayname'], 1);
				$firstName = $parts[0];
				$lastName = isset($parts[1]) ? $parts[1] : '';
			}
			else
			{
				$firstName = $user['fname'] === null ? '' : $user['fname'];
				$lastName = $user['lname'] === null ? '' : $user['lname'];
			}

			//Check if the user contact information needs to be updated
			if(	!isset($serverUserContact) || $serverUserContact->firstName != $firstName || $serverUserContact->lastName != $lastName)
			{
				$logger->debug(sprintf(_("Updating user contact information: %s"), $user['username']));
				try
				{
					$serverUserContact = new \FreePBX\modules\Cxpanel\cxpanel_user_contact($firstName, $lastName);
					$pest->put("contact/" . $coreServerId . "/users/" . $serverUser->id, $serverUserContact);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to update user contact information: %s"), $user['username']), $e);
				}
			}

			//Get the user's email address
			$email = $user['email'] === null ? '' : $user['email'];

			//Check if the user email address should be added
			if($email != "")
			{
				try
				{
					$serverUserEmailAddresses = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id . "/emailAddresses");

					//Search for the current email
					$emailFound = false;
					foreach($serverUserEmailAddresses as $serverUserEmailAddress)
					{
						if($serverUserEmailAddress->address == $email)
						{
							$emailFound = true;
							break;
						}
					}

					//If the email was not found add it
					if(!$emailFound)
					{
						$logger->debug(sprintf(_("Adding user contact email address: %s"), $user['username']));
						$serverUserEmailAddress = new \FreePBX\modules\Cxpanel\cxpanel_user_contact_email_address("Work", $email);
						$pest->post("contact/" . $coreServerId . "/users/" . $serverUser->id . "/emailAddresses", $serverUserEmailAddress);
					}
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to update user contact email address information: %s"), $user['username']), $e);
				}
			}

			//Create associative array of the server user phone number strings to the user objects for quick indexing
			$serverUserPhoneNumberAssoc = array();
			$serverUserPhoneNumbers = $pest->get("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers");
			foreach($serverUserPhoneNumbers as $serverUserPhoneNumber)
			{
				$serverUserPhoneNumberAssoc[$serverUserPhoneNumber->number . '@#' . $serverUserPhoneNumber->type] = $serverUserPhoneNumber;
			}

			//Build an array of the user's phone numbers
			$userPhoneNumbers = array();
			if($user['cell'] !== null && !empty($user['cell']))
			{
				$userPhoneNumber['phone_number'] = $user['cell'];
				$userPhoneNumber['type'] = 'Cell';
				array_push($userPhoneNumbers, $userPhoneNumber);
			}

			if($user['work'] !== null && !empty($user['work']))
			{
				$userPhoneNumber['phone_number'] = $user['work'];
				$userPhoneNumber['type'] = 'Work';
				array_push($userPhoneNumbers, $userPhoneNumber);
			}

			if($user['home'] !== null && !empty($user['home']))
			{
				$userPhoneNumber['phone_number'] = $user['home'];
				$userPhoneNumber['type'] = 'Home';
				array_push($userPhoneNumbers, $userPhoneNumber);
			}

			//Look for phone numbers to add while building the local user phone number associative array
			$userPhoneNumberAssoc = array();
			foreach($userPhoneNumbers as $userPhoneNumber)
			{
				$idString = $userPhoneNumber['phone_number'] . '@#' . $userPhoneNumber['type'];
				array_push($userPhoneNumberAssoc, $idString);

				if(!array_key_exists($idString, $serverUserPhoneNumberAssoc))
				{
					$logger->debug(sprintf(_("Adding user contact phone number: %s (%s) to user: %s"), $userPhoneNumber['phone_number'], $userPhoneNumber['type'], $user['username']));
					$userPhoneNumberObj = new \FreePBX\modules\Cxpanel\cxpanel_user_contact_phone_number($userPhoneNumber['type'], $userPhoneNumber['phone_number']);
					$pest->post("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers", $userPhoneNumberObj);
				}
			}

			//Look for phone numbers to remove
			foreach($serverUserPhoneNumbers as $serverUserPhoneNumber)
			{
				if(!in_array($serverUserPhoneNumber->number . '@#' . $serverUserPhoneNumber->type, $userPhoneNumberAssoc))
				{
					$logger->debug(sprintf(_("Removing user contact phone number: %s (%s) from user: %s"), $serverUserPhoneNumber->number, $serverUserPhoneNumber->type, $user['username']));
					$pest->delete("contact/" . $coreServerId . "/users/" . $serverUser->id . "/phoneNumbers/" . $serverUserPhoneNumber->id);
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync user contact information"), $e);
	}
}

/**
 *
 * Sync the extensions that are bound to the users.
 *
 */
function sync_extension_users()
{
	global $coreServerId, $logger, $pest, $userInformation, $cxpanel;
	$logger->debug(_("Syncing extension users"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Grab the extension list from the server
		$serverExtensions = $pest->get("asterisk/" . $coreServerId . "/extensions");

		/*
		 * Create associative array of the server extension numbers to the extension objects for quick indexing.
		 *
		 * Only add extensions that this module manages.
		 */
		$serverExtensionAssoc = array();
		foreach($serverExtensions as $serverExtension)
		{
			if($cxpanel->has_managed_item('extension', $serverExtension->id))
			{
				$serverExtensionAssoc[$serverExtension->extension] = $serverExtension;
			}
		}

		//Remove users from extensions that are currently not bound in the module
		foreach($serverExtensionAssoc as $serverExtension)
		{
			try
			{
				//Get the current user set on this extension
				$extensionUser = $pest->get("asterisk/" . $coreServerId . "/extensions/" . $serverExtension->id . "/managingUser");

				//Get the current bound extensions for the user
				$boundExtensionObjs = $cxpanel->user_extension_list($extensionUser->username);

				//Create list of just the bound extension names
				$boundExtensions = array();
				foreach($boundExtensionObjs as $boundExtensionObj)
				{
					array_push($boundExtensions, $boundExtensionObj['user_id']);
				}

				//If this extension is no longer in the users bound extension list remove it
				if(!in_array($serverExtension->extension, $boundExtensions))
				{
					try
					{
						// TODO: $freePBXUser['username'] unidefinde
						$logger->debug("Unsetting extension user:" . $freePBXUser['username'] . " from extension:" . $serverExtension->extension);
						$pest->delete("asterisk/" . $coreServerId . "/extensions/" . $serverExtension->id . "/managingUser");
					}
					catch (Exception $e)
					{
						// TODO: $boundExtension is array or is undefined?
						$logger->error_exception("Failed to set user on extension:" . $boundExtension, $e);
					}
				}
			}
			catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
			{
				//Do Nothing
			}
			catch (Exception $e)
			{
				$logger->error_exception(sprintf(_("Failed to unsync extension user for extension: %s"), $serverExtension->extension), $e);
			}
		}

		//Cycle through all the users and see if they need to have an extensions bound to them
		foreach($userInformation as $user)
		{
			if($user['add_user'] == "1")
			{
				//Get the users bound extensions
				$boundExtensions = $cxpanel->user_extension_list($user['user_id']);
				foreach($boundExtensions as $boundExtension)
				{
					if($boundExtension['add_extension'] == "1")
					{
						$boundUserFound = true;
						try
						{
							//Get the extension
							$relativeExtension = $serverExtensionAssoc[$boundExtension['user_id']];

							//Get the user that is currently set on the relative extension
							$extensionUser = $pest->get("asterisk/" . $coreServerId . "/extensions/" . $relativeExtension->id . "/managingUser");

							//If the extension user that is set is not the proper one flag for a set
							if($extensionUser->username != $user['user_id'])
							{
								$boundUserFound = false;
							}
						}
						catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
						{
							$boundUserFound = false;
						}
						catch (Exception $e)
						{
							$logger->error_exception(sprintf(_("Failed to sync extension user for extension: %s"), $boundExtension['user_id']), $e);
						}

						//If no bound user was found set the proper user
						if(!$boundUserFound)
						{
							try
							{
								$logger->debug(sprintf(_("Setting extension user on extension: %s to user: %s"), $boundExtension['user_id'], $user['user_id']));
								$pest->post("asterisk/" . $coreServerId . "/extensions/" . $relativeExtension->id . "/managingUser", $serverUserAssoc[$user['user_id']]);
							}
							catch (Exception $e)
							{
								$logger->error_exception(sprintf(_("Failed to set user on extension: %s"), $boundExtension['user_id']), $e);
							}
						}
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync extension users"), $e);
	}
}

/**
 *
 * Sync the extensions that are bound to the users when sync_with_userman is enabled.
 *
 */
function sync_extension_users_userman()
{
	global $coreServerId, $logger, $pest, $cxpanel;
	$logger->debug(_("Syncing extension users (userman)"));
	try
	{
		//Grab the list of users from the server
		$serverUsers = $pest->get("core/" . $coreServerId . "/users");

		//Create associative array of the server usernames to the user objects for quick indexing
		$serverUserAssoc = array();
		foreach($serverUsers as $serverUser)
		{
			$serverUserAssoc[$serverUser->username] = $serverUser;
		}

		//Grab the extension list from the server
		$serverExtensions = $pest->get("asterisk/" . $coreServerId . "/extensions");

		/*
		 * Create associative array of the server extension numbers to the extension objects for quick indexing.
		 *
		 * Only add extensions that this module manages
		 */
		$serverExtensionAssoc = array();
		foreach($serverExtensions as $serverExtension)
		{
			if($cxpanel->has_managed_item('extension', $serverExtension->id))
			{
				$serverExtensionAssoc[$serverExtension->extension] = $serverExtension;
			}
		}

		//Remove users from extensions that are currently not bound in the module
		foreach($serverExtensionAssoc as $serverExtension)
		{
			try
			{
				//Get the current user set on this extension
				$extensionUser = $pest->get("asterisk/" . $coreServerId . "/extensions/" . $serverExtension->id . "/managingUser");

				//Get the FreePBX user for the extensions's panel user
				$freePBXUser = $cxpanel->Userman->getUserByUsername($extensionUser->username);

				//Get the freePBX user's bound extensions
				$boundExtensions = $cxpanel->Userman->getAssignedDevices($freePBXUser['id']);

				//If this extension is no longer in the users bound extension list remove it
				if(!in_array($serverExtension->extension, $boundExtensions))
				{
					try
					{
						$logger->debug(sprintf(_("Unsetting extension user: %s from extension: %s"), $freePBXUser['username'], $serverExtension->extension));
						$pest->delete("asterisk/" . $coreServerId . "/extensions/" . $serverExtension->id . "/managingUser");
					}
					catch (Exception $e)
					{
						//TODO: $brandException???
						$logger->error_exception(sprintf(_("Failed to set user on extension: %s"), $boundExtension), $e);
					}
				}
			}
			catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
			{
				//Do Nothing
			}
			catch (Exception $e)
			{
				$logger->error_exception(sprintf(_("Failed to unsync extension user for extension: %s"), $serverExtension->extension), $e);
			}
		}

		//Cycle through all the users and see if they need to have extensions bound to them
		$freePBXUsers = $cxpanel->Userman->getAllUsers();
		foreach($freePBXUsers as $freePBXUser)
		{
			//Only look at users that are flaged to be added
			if(\FreePBX::Modules()->moduleHasMethod("userman", "getCombinedModuleSettingByID"))
			{
				$add = $cxpanel->Userman->getCombinedModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			else
			{
				$add = $cxpanel->Userman->getModuleSettingByID($freePBXUser['id'], 'cxpanel' , 'add');
			}
			$add = $add === false ? '1' : $add;
			if($add == '1')
			{
				//Get the users bound extensions
				$boundExtensions = $cxpanel->Userman->getAssignedDevices($freePBXUser['id']);
				foreach($boundExtensions as $boundExtension)
				{
					//Do not pay attention to extensions that are not added
					$dbExtension = $cxpanel->user_get($boundExtension);
					if($dbExtension['add_extension'] == "1")
					{
						$boundUserFound = true;
						try
						{
							//Get the extension
							$relativeExtension = $serverExtensionAssoc[$boundExtension];

							//Get the user that is currently set on the relative extension
							$extensionUser = $pest->get("asterisk/" . $coreServerId . "/extensions/" . $relativeExtension->id . "/managingUser");

							//If the extension user that is set is not the proper one flag for a set
							if($extensionUser->username != $freePBXUser['username'])
							{
								$boundUserFound = false;
							}

						}
						catch (\FreePBX\modules\Cxpanel\CXPest_NotFound $e)
						{
							$boundUserFound = false;
						}
						catch (Exception $e)
						{
							$logger->error_exception(sprintf(_("Failed to sync extension user for extension: %s"), $boundExtension), $e);
						}

						//If no bound user was found set the proper user
						if (!$boundUserFound)
						{
							try
							{
								$logger->debug(sprintf(_("Setting extension user on extension: %s to user: %s"), $boundExtension, $freePBXUser['username']));
								$pest->post("asterisk/" . $coreServerId . "/extensions/" . $relativeExtension->id . "/managingUser", $serverUserAssoc[$freePBXUser['username']]);
							}
							catch (Exception $e)
							{
								$logger->error_exception(sprintf(_("Failed to set user on extension: %s"), $boundExtension), $e);
							}
						}
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync extension users"), $e);
	}
}

/**
 *
 * Sync queues
 *
 */
function sync_queues()
{
	global $coreServerId, $logger, $pest, $queueInformation, $cxpanel;
	$logger->debug(_("Syncing queues"));
	try
	{
		//Grab the queue list from the server
		$serverQueues = $pest->get("asterisk/" . $coreServerId . "/queues");

		//Create associative array of the server queue identifier to the queue objects for quick indexing
		$serverQueueAssoc = array();
		foreach($serverQueues as $serverQueue)
		{
			$serverQueueAssoc[$serverQueue->identifier] = $serverQueue;
		}

		//Filter list to exclude queues that are not marked for addition while creating an associative array for quick indexing
		$queues = array();
		foreach($queueInformation as $queue)
		{
			if($queue["add_queue"] == "1") {
				$queues[$queue['queue_id']] = $queue;
			}
		}

		//Remove all queues from the server that are not stored in the database
		foreach($serverQueueAssoc as $queueId => $queue)
		{
			if(	!array_key_exists($queueId, $queues) && $cxpanel->has_managed_item('queue', $queue->id))
			{
				$logger->debug(sprintf(_("Removing queue: %s"), $queueId));

				//Remove the managed entry
				$cxpanel->managed_item_del('queue', $queue->id);

				//Remove the queue from the server
				try
				{
					$pest->delete("asterisk/" . $coreServerId . "/queues/" . $queue->id);
					unset($serverQueueAssoc[$queueId]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove queue: %s"), $queueId), $e);
				}
			}
		}

		//Add queues that are missing on the server and update ones that are not up to date
		foreach($queues as $queue)
		{
			//Add queue
			if(!array_key_exists($queue['queue_id'], $serverQueueAssoc))
			{
				$logger->debug(sprintf(_("Adding queue: %s"), $queue['queue_id']));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('queue', $queue['queue_id']);

				//Add the queue to the server
				try
				{
					$queueObj = new \FreePBX\modules\Cxpanel\cxpanel_queue(false, $queue['display_name'], $queue['queue_id'], $queue['queue_id'], "from-internal", true);
					$queueObj->id = $uuid;
					$pest->post("asterisk/" . $coreServerId . "/queues/", $queueObj);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add queue: %s"), $queue['queue_id']), $e);
				}
			//Update queue
			}
			else
			{
				$serverQueue = $serverQueueAssoc[$queue['queue_id']];

				//Update the managed item uuid
				$cxpanel->managed_item_update('queue', $queue['queue_id'], $serverQueue->id);

				//Update the queue if needed
				if(	$serverQueue->displayName != $queue['display_name'] || $serverQueue->destinationExtension != $queue['queue_id'] || $serverQueue->destinationContext != "from-internal" || $serverQueue->enabled !== true)
				{
					try
					{
						$logger->debug(sprintf(_("Updating queue: %s"), $queue['queue_id']));
						$serverQueue->displayName = $queue['display_name'];
						$serverQueue->destinationExtension = $queue['queue_id'];
						$serverQueue->destinationContext = "from-internal";
						$serverQueue->enabled = true;
						$pest->put("asterisk/" . $coreServerId . "/queues/" . $serverQueue->id , $serverQueue);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update queue: %s"), $queue['queue_id']), $e);
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync queues"), $e);
	}
}

/**
 *
 * Sync conference rooms
 *
 */
function sync_conference_rooms()
{
	global $coreServerId, $logger, $pest, $conferenceRoomInformation, $cxpanel;
	$logger->debug(_("Syncing conference rooms"));
	try
	{
		//Grab the room list from the server
		$serverRooms = $pest->get("asterisk/" . $coreServerId . "/conferenceRooms");

		//Create associative array of the server conference room identifier to the conference room objects for quick indexing
		$serverRoomAssoc = array();
		foreach($serverRooms as $serverRoom)
		{
			$serverRoomAssoc[$serverRoom->identifier] = $serverRoom;
		}

		//Filter list to exclude conference rooms that are not marked for addition while creating an associative array for quick indexing
		$rooms = array();
		foreach($conferenceRoomInformation as $room)
		{
			if($room["add_conference_room"] == "1")
			{
				$rooms[$room['conference_room_id']] = $room;
			}
		}

		//Remove all conference rooms from the server that are not stored in the database
		foreach($serverRoomAssoc as $roomId => $room)
		{
			if(	!array_key_exists($roomId, $rooms) && $cxpanel->has_managed_item('conference_room', $room->id))
			{
				$logger->debug(sprintf(_("Removing conference room: %s"), $roomId));

				//Remove the managed entry
				$cxpanel->managed_item_del('conference_room', $room->id);

				//Remove the conference room from the server
				try
				{
					$pest->delete("asterisk/" . $coreServerId . "/conferenceRooms/" . $room->id);
					unset($serverRoomAssoc[$roomId]);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to remove conference room: %s"), $roomId), $e);
				}
			}
		}

		//Add conference rooms that are missing on the server and update ones that are not up to date
		foreach($rooms as $room)
		{
			//Add conference room
			if(!array_key_exists($room['conference_room_id'], $serverRoomAssoc))
			{
				$logger->debug(sprintf(_("Adding conference room: %s"), $room['conference_room_id']));

				//Generate uuid and a managed item entry
				$uuid = $cxpanel->gen_managed_uuid('conference_room', $room['conference_room_id']);

				//Add the conference room to the server
				try
				{
					$roomObj = new \FreePBX\modules\Cxpanel\cxpanel_conference_room(false, $room['display_name'], $room['conference_room_id'], $room['conference_room_id'], "from-internal");
					$roomObj->id = $uuid;
					$pest->post("asterisk/" . $coreServerId . "/conferenceRooms/", $roomObj);
				}
				catch (Exception $e)
				{
					$logger->error_exception(sprintf(_("Failed to add conference room: %s"), $room['conference_room_id']), $e);
				}

			//Update conference room
			}
			else
			{
				$serverRoom = $serverRoomAssoc[$room['conference_room_id']];

				//Update the managed item uuid
				$cxpanel->managed_item_update('conference_room', $room['conference_room_id'], $serverRoom->id);

				//Update the conference room if needed
				if(	$serverRoom->name != $room['display_name'] || $serverRoom->destinationExtension != $room['conference_room_id'] || $serverRoom->destinationContext != "from-internal")
				{
					try
					{
						$logger->debug(sprintf(_("Updating conference room: %s"), $room['conference_room_id']));
						$serverRoom->name = $room['display_name'];
						$serverRoom->destinationExtension = $room['conference_room_id'];
						$serverRoom->destinationContext = "from-internal";
						$pest->put("asterisk/" . $coreServerId . "/conferenceRooms/" . $serverRoom->id , $serverRoom);
					}
					catch (Exception $e)
					{
						$logger->error_exception(sprintf(_("Failed to update conference room: %s") , $room['conference_room_id']), $e);
					}
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync conference rooms"), $e);
	}
}

/**
 *
 * Sync parking lot
 *
 */
function sync_parking_lot()
{
	global $coreServerId, $logger, $pest, $serverInformation;
	$logger->debug(_("Syncing parking lot"));
	try
	{
		/*
		 * Check if parking is enabled and query the parking info.
		 *
		 * If the FreePBX 10 parking module is installed the parking config
		 * method will be parking_getconfig() and we will need to check
		 * the parkingenabled setting to see if parking is enabled or not.
		 *
		 * If the FreePBX 11 parking module is installed the parking config
		 * method will be parking_get() and the parking lot will always be enabled
		 * if the moduel is installed.
		 */
		$parkingEnabled = false;
		if(function_exists("parking_getconfig"))
		{
			$parkingConfig = parking_getconfig();
			$parkingEnabled = $parkingConfig['parkingenabled'] != "";
			$parkingExten = $parkingConfig['parkext'];
		}
		else if(function_exists("parking_get"))
		{
			$parkingConfig = parking_get();
			$parkingEnabled = true;
			$parkingExten = $parkingConfig['parkext'];
		}

		//Grab the parking lot list from the server
		$serverParkingLots = $pest->get("asterisk/" . $coreServerId . "/parkingLots");

		//Create associative array of the server parking lot identifier to the parking lot objects for quick indexing
		$serverParkingLotsAssoc = array();
		foreach($serverParkingLots as $serverParkingLot)
		{
			$serverParkingLotsAssoc[$serverParkingLot->identifier] = $serverParkingLot;
		}

		/*
		 * If the server has the default parking lot configured, and FreePBX has parking disabled,
		 * remove the default parking lot.
		 *
		 * If other parking lots are found on the server, and clean unknown items
		 * is enabled, remove those parking lots.
		 */
		foreach($serverParkingLotsAssoc as $parkingLotIdentifier => $parkingLot)
		{
			if(($parkingLotIdentifier == "default" && !$parkingEnabled) || ($parkingLotIdentifier != "default" && $serverInformation['clean_unknown_items'] == '1'))
			{
				$logger->debug(sprintf(_("Removing parking lot: %s"), $parkingLotIdentifier));
				$pest->delete("asterisk/" . $coreServerId . "/parkingLots/" . $parkingLot->id);
				unset($serverParkingLotsAssoc[$parkingLotIdentifier]);
			}
		}

		//If parking is enabled and the default parking lot was not found on the server create it else check to see if it needs to be updated
		if($parkingEnabled)
		{
			if(!array_key_exists("default", $serverParkingLotsAssoc))
			{
				$logger->debug(_("Adding parking lot: default"));
				$parkingLot = new \FreePBX\modules\Cxpanel\cxpanel_parking_lot(false, "Main", "default", $parkingExten, "from-internal");
				$pest->post("asterisk/" . $coreServerId . "/parkingLots/", $parkingLot);
			}
			else
			{
				$parkingLot = $serverParkingLotsAssoc["default"];
				if(	$parkingLot->name != "Main" || $parkingLot->destinationExtension != $parkingExten || $parkingLot->destinationContext != "from-internal")
				{
					$logger->debug(_("Updating parking lot: default"));
					$parkingLot->name = "Main";
					$parkingLot->destinationExtension = $parkingExten;
					$parkingLot->destinationContext = "from-internal";
					$pest->put("asterisk/" . $coreServerId . "/parkingLots/" . $parkingLot->id, $parkingLot);
				}
			}
		}
	}
	catch (Exception $e)
	{
		$logger->error_exception(_("Failed to sync parking lot"), $e);
	}
}

/**
 *
 * Gets the agent login interface value for the given user
 * @param User $user
 *
 */
function get_agent_login_interface($user)
{
	global $agentInterfaceType;
	switch($agentInterfaceType)
	{
		case "peer":
			return $user["peer"];

		case "hint":
			return sprintf("hint:%s@ext-local", $user["user_id"]);

		case "none";
			return "";
	}
}

/**
 *
 * Performs cleanup when stopping the script
 *
 */
function cleanup()
{
	global $logger, $lock, $cxpanel;

	//Close logger
	if(isset($logger))
	{
		$logger->close();
	}

	//Close lock file and remove lock
	if(isset($lock))
	{
		flock($lock, LOCK_UN);
		fclose($lock);
	}

	// Remove file lock
	// If we run "fwconsole reload" as root on the console and the lock file is not removed, 
	// trying to reload from the web will result in a Permissible Denied error reading the 
	// lock file since the owner is root and the user asterisk does not have permission.
	// Whoops\Exception\ErrorException: fopen(/var/spool/asterisk/cxpanel/lock): failed to open stream: Permiso denegado
	$file_lock = $cxpanel->getpath("lock");
	if ( file_exists($file_lock) )
	{
		unlink($file_lock);
	}

	//Kill the script
	die;
}
