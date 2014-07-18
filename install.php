<?php
/*
 *Name         : install.php
 *Author       : Michael Yara
 *Created      : August 15, 2008
 *Last Updated : April 24, 2014
 *Version      : 3.0
 *Purpose      : Create, upgrade, and populate tables
 */

global $db, $amp_conf, $active_modules;

//Check if the manager module is installed. If not stop installation.
$mod_keys = array_keys($active_modules);
if(!in_array("manager", $mod_keys)) {
	echo 'Failed to install due to the following missing required module(s):<br /><br />manager<br /><br />';
	return;
}

//Includes
require_once(dirname(__FILE__)."/lib/table.class.php");
require_once(dirname(__FILE__)."/lib/util.php");
require_once(dirname(__FILE__)."/brand.php");

//Set operator panel web root and enable dev state
if(class_exists("freepbx_conf")) {
	echo "Setting operator panel web root and enabling dev state....<br>";
	$set["FOPWEBROOT"] = "cxpanel";
	$set["USEDEVSTATE"] = true;
	$freepbx_conf =& freepbx_conf::create();
	$freepbx_conf->set_conf_values($set, true, true);
	echo "Done<br>";
}

//Set callevents = yes for hold events
if(function_exists("sipsettings_edit") && function_exists("sipsettings_get")) {
	echo "Setting callevents = yes....<br>";
	$sip_settings = sipsettings_get();
	$sip_settings['callevents'] = 'yes';
	sipsettings_edit($sip_settings);
}

//Create symlink that points to the module directory in order to run the client redirect script
echo "Creating client symlink....<br>";
if(file_exists($amp_conf['AMPWEBROOT'] . '/cxpanel')) {
	unlink($amp_conf['AMPWEBROOT'] . '/cxpanel');
}
symlink($amp_conf['AMPWEBROOT'] .'/admin/modules/cxpanel/', $amp_conf['AMPWEBROOT'] . '/cxpanel');

if(file_exists($amp_conf['AMPWEBROOT'] . '/admin/cxpanel')) {
	unlink($amp_conf['AMPWEBROOT'] . '/admin/cxpanel');
}
symlink($amp_conf['AMPWEBROOT'] .'/admin/modules/cxpanel/', $amp_conf['AMPWEBROOT'] . '/admin/cxpanel');

echo "Done<br>";

//Turn on voicemail polling if not already on
if(function_exists("voicemail_get_settings")) {
	$vmSettings = voicemail_get_settings(voicemail_getVoicemail(), "settings");
	if($vmSettings["pollmailboxes"] != "yes" || empty($vmSettings["pollfreq"])) {
		echo "Enabling voicemail box polling<br/>";
		if(function_exists("voicemail_update_settings")) {
			voicemail_update_settings("settings", "", "", array("gen__pollfreq" => "15", "gen__pollmailboxes" => "yes"));	
		}
	}
}

//If userman is installed and this is not an upgrade default sycn_with_userman to true
$results = $db->query("select * from cxpanel_server");
if(function_exists('setup_userman') && (DB::IsError($results) || empty($results))) {
	$syncWithUserman = 1;
} else {
	$syncWithUserman = 0;
}

//Build server table
$columns = array(	new cxpanel_column("name", "string", "default", "", false, true),
					new cxpanel_column("asterisk_host", "string", "localhost", "", false, true),
					new cxpanel_column("client_host", "string", "", "", false, true),
					new cxpanel_column("client_port", "integer", 58080, "", false, true),
					new cxpanel_column("api_host", "string", "localhost", "", false, true),
					new cxpanel_column("api_port", "integer", 58080, "", false, true),
					new cxpanel_column("api_username", "string", "manager", "", false, true),
					new cxpanel_column("api_password", "string", "manag3rpa55word", "", false, true),
					new cxpanel_column("api_use_ssl", "boolean", 0, "", false, true),
					new cxpanel_column("sync_with_userman", "boolean", $syncWithUserman, "", false, true));

$table = new cxpanel_table("cxpanel_server", $columns);
$builder = new cxpanel_table_builder($table);
$builder->build(array(array("junk")));

//Build voicemail agent table
$columns = array(	new cxpanel_column("identifier", "string", "local-vm", "", false, true),
					new cxpanel_column("directory", "string", "/var/spool/asterisk/voicemail", "", false, true),
					new cxpanel_column("resource_host", "string", php_uname('n'), "", false, true),
					new cxpanel_column("resource_extension", "string", "wav", "", false, true));

$table = new cxpanel_table("cxpanel_voicemail_agent", $columns);
$builder = new cxpanel_table_builder($table);
$builder->build(array(array("junk")));

//Build recording agnet table
$columns = array(	new cxpanel_column("identifier", "string", "local-rec", "", false, true),
					new cxpanel_column("directory", "string", "/var/spool/asterisk/monitor", "", false, true),
					new cxpanel_column("resource_host", "string", php_uname('n'), "", false, true),
					new cxpanel_column("resource_extension", "string", "wav", "", false, true),
					new cxpanel_column("file_name_mask", "string", "\${Tag(exten)}-\${DstExtension}-\${SrcExtension}-\${Date(yyyyMMdd)}-\${Time(HHmmss)}-\${CDRUniqueId}", "", false, true));

$table = new cxpanel_table("cxpanel_recording_agent", $columns);
$builder = new cxpanel_table_builder($table);
$builder->build(array(array("junk")));

//Build email table
$defaultEmailBody = "<img src=\"%%logo%%\">" .
					"<br/><br/>" .
					"Hello," .
					"<br/><br/> ". 
					"This email is to inform you of your " . $cxpanelBrandName ." login credentials:" .
					"<br/><br/>" .
					"<b>Username:</b> %%userId%%" .
					"<br/><br/> ".
					"<b>Password:</b> %%password%%" .
					"<br/><br/> ".
					"<a href=\"%%clientURL%%\">Click Here To Login</a>";

$columns = array(	new cxpanel_column("subject", "string", $cxpanelBrandName . " user login password", "", false, true),
					new cxpanel_column("body", "string", $defaultEmailBody, "", false, true));

$table = new cxpanel_table("cxpanel_email", $columns);
$builder = new cxpanel_table_builder($table);
$builder->build(array(array("junk")));

//Build phone number table
$columns = array(	new cxpanel_column("cxpanel_phone_number_id", "primary", "", "", true, true),
					new cxpanel_column("user_id", "string", "", "", false, true),
					new cxpanel_column("phone_number", "string", "", "", false, true),
					new cxpanel_column("type", "string", "", "", false, true));

$table = new cxpanel_table("cxpanel_phone_number", $columns);
$builder = new cxpanel_table_builder($table);
$builder->build();

//Build users table
$columns = array(	new cxpanel_column("cxpanel_user_id", "primary", "", "", true, true),
					new cxpanel_column("user_id", "string", "", "user_id", true, true),
					new cxpanel_column("display_name", "string", "", "display_name", false, true),
					new cxpanel_column("peer", "string", "", "peer", false, true),
					new cxpanel_column("add_extension", "boolean", 1, "", false, true),
					new cxpanel_column("full", "boolean", 1, "", false, true),
					new cxpanel_column("add_user", "boolean", 1, "", false, true),
					new cxpanel_column("hashed_password", "string", "", "hashed_password", false, true),
					new cxpanel_column("initial_password", "string", "", "initial_password", false, true),
					new cxpanel_column("auto_answer", "boolean", 0, "", false, true),
					new cxpanel_column("parent_user_id", "string", "", "parent_user_id", false, true),
					new cxpanel_column("password_dirty", "boolean", 1, "", false, true));

$table = new cxpanel_table("cxpanel_users", $columns);
$builder = new cxpanel_table_builder($table);

//Gather user info
$entries = array();
if((function_exists("core_users_list")) && (($freePBXUsers = core_users_list()) !== null)){
	foreach($freePBXUsers as $freePBXUser) {
		if(function_exists("core_devices_get")) {
			
			//Determine user info
			$userId = $freePBXUser[0];
			$peer = ($freePBXDeviceInfo['dial'] != "") ? $freePBXDeviceInfo['dial'] : "SIP/$userId";
			$displayName = $freePBXUser[1] == "" ? $freePBXUser[0] : $freePBXUser[1];
			
			//Generate a password for the user
			$password = cxpanel_generate_password(10);
			$passwordSHA1 = sha1($password);
						
			//Add user
			array_push($entries, array("user_id" => $userId, "display_name" => $displayName, "peer" => $peer, "hashed_password" => $passwordSHA1, "initial_password" => $password, "parent_user_id" => $userId));
		}
	}
}

$builder->build($entries);					

//Build queues table
$columns = array(	new cxpanel_column("cxpanel_queue_id", "primary", "", "", true, true),
					new cxpanel_column("queue_id", "string", "", "queue_id", true, true),
					new cxpanel_column("display_name", "string", "", "display_name", false, true),
					new cxpanel_column("add_queue", "boolean", 1, "", false, true));
$table = new cxpanel_table("cxpanel_queues", $columns);
$builder = new cxpanel_table_builder($table);

//Gather queue info
$entries = array();
if((function_exists("queues_list")) && (($freePBXQueues = queues_list()) !== null)) {
	foreach($freePBXQueues as $freePBXQueue) {
		$queueId = $freePBXQueue[0];
		$displayName = $freePBXQueue[1] == "" ? $freePBXQueue[0] : $freePBXQueue[1];
		array_push($entries, array("queue_id" => $queueId, "display_name" => $displayName));
	}
}

$builder->build($entries);

//Build conference rooms table
$columns = array(	new cxpanel_column("cxpanel_conference_room_id", "primary", "", "", true, true),
					new cxpanel_column("conference_room_id", "string", "", "conference_room_id", true, true),
					new cxpanel_column("display_name", "string", "", "display_name", false, true),
					new cxpanel_column("add_conference_room", "boolean", 1, "", false, true));
$table = new cxpanel_table("cxpanel_conference_rooms", $columns);
$builder = new cxpanel_table_builder($table);

//Gather queue info
$entries = array();
if((function_exists("conferences_list")) && (($freePBXConferenceRooms = conferences_list()) !== null)) {
	foreach($freePBXConferenceRooms as $freePBXConferenceRoom) {
		$conferenceRoomId = $freePBXConferenceRoom[0];
		$displayName = $freePBXConferenceRoom[1] == "" ? $freePBXConferenceRoom[0] : $freePBXConferenceRoom[1];
		array_push($entries, array("conference_room_id" => $conferenceRoomId, "display_name" => $displayName));
	}
}

$builder->build($entries);


