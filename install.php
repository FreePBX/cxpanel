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

//Includes
require_once(dirname(__FILE__)."/lib/table.class.php");
require_once(dirname(__FILE__)."/lib/util.php");
require_once(dirname(__FILE__)."/brand.php");

//Set operator panel web root and enable dev state
if(class_exists("freepbx_conf")) {
	outn("Setting operator panel web root and enabling dev state....");
	$set["FOPWEBROOT"] = "cxpanel";
	$set["USEDEVSTATE"] = true;
	$freepbx_conf =& freepbx_conf::create();
	$freepbx_conf->set_conf_values($set, true, true);
	out("Done");
}

//Set callevents = yes for hold events
if(function_exists("sipsettings_edit") && function_exists("sipsettings_get")) {
	outn("Setting callevents = yes....");
	$sip_settings = sipsettings_get();
	$sip_settings['callevents'] = 'yes';
	sipsettings_edit($sip_settings);
	out("Done");
}

//Create symlink that points to the module directory in order to run the client redirect script
outn("Creating client symlink....");
if(file_exists($amp_conf['AMPWEBROOT'] . '/cxpanel')) {
	unlink($amp_conf['AMPWEBROOT'] . '/cxpanel');
}
symlink($amp_conf['AMPWEBROOT'] .'/admin/modules/cxpanel/', $amp_conf['AMPWEBROOT'] . '/cxpanel');

if(file_exists($amp_conf['AMPWEBROOT'] . '/admin/cxpanel')) {
	unlink($amp_conf['AMPWEBROOT'] . '/admin/cxpanel');
}
symlink($amp_conf['AMPWEBROOT'] .'/admin/modules/cxpanel/', $amp_conf['AMPWEBROOT'] . '/admin/cxpanel');

out("Done");

//Turn on voicemail polling if not already on
if(function_exists("voicemail_get_settings")) {
	$vmSettings = voicemail_get_settings(voicemail_getVoicemail(), "settings");
	if($vmSettings["pollmailboxes"] != "yes" || empty($vmSettings["pollfreq"])) {
		outn("Enabling voicemail box polling...");
		if(function_exists("voicemail_update_settings")) {
			voicemail_update_settings("settings", "", "", array("gen__pollfreq" => "15", "gen__pollmailboxes" => "yes"));
		}
		out("Done");
	}
}
outn("Build server table...");
$table = \FreePBX::Database()->migrate("cxpanel_server");
$cols = array (
  'name' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'asterisk_host' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'client_host' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'client_port' =>
  array (
    'type' => 'integer',
  ),
  'client_use_ssl' =>
  array (
    'type' => 'integer',
  ),
  'api_host' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'api_port' =>
  array (
    'type' => 'integer',
  ),
  'api_username' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'api_password' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'api_use_ssl' =>
  array (
    'type' => 'integer',
  ),
  'sync_with_userman' =>
  array (
    'type' => 'integer',
  ),
  'clean_unknown_items' =>
  array (
    'type' => 'integer',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

out("Done");

$results = $db->getAll("SELECT * FROM cxpanel_server");
if(empty($results)) {
	outn("New installed detected, adding default server...");
	$db->query("INSERT INTO cxpanel_server (`name`, `asterisk_host`, `client_host`, `client_port`, `client_use_ssl`, `api_host`, `api_port`, `api_username`, `api_password`, `api_use_ssl`, `sync_with_userman`, `clean_unknown_items`) VALUES ('default', 'localhost', '', 58080, 0, 'localhost', 58080, 'manager', 'manag3rpa55word', 0, 1, 1)");
	out("Done");
} else {
	//If userman is installed and this is not an upgrade default sycn_with_userman to true
	outn("Upgrade detected, checking userman mode...");
	$results = $db->getAll("SELECT * FROM cxpanel_users");
	$results2 = $db->getAll("SELECT * FROM cxpanel_server WHERE sync_with_userman = 1");
	if(empty($results) && !empty($results2)) {
		$syncWithUserman = 1;
		outn("Needs to sync with userman...");
		$db->query("UPDATE cxpanel_server SET sync_with_userman = ".$syncWithUserman);
	} else {
		outn("Leaving userman mode unchanged...");
	}
	out("Done");
}


outn("Build voicemail agent table...");
//Build voicemail agent table
$table = \FreePBX::Database()->migrate("cxpanel_voicemail_agent");
$cols = array (
  'identifier' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'directory' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'resource_host' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'resource_extension' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

$results = $db->getAll("SELECT * FROM cxpanel_voicemail_agent");
if(empty($results)) {
	$db->query("INSERT INTO cxpanel_voicemail_agent (`identifier`, `directory`, `resource_host`, `resource_extension`) VALUES ('local-vm', '/var/spool/asterisk/voicemail', '".php_uname('n')."', 'wav')");
}
out("Done");

outn("Build recording agent table...");

$table = \FreePBX::Database()->migrate("cxpanel_recording_agent");
$cols = array (
  'identifier' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'directory' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'resource_host' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'resource_extension' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'file_name_mask' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

$results = $db->getAll("SELECT * FROM cxpanel_recording_agent");
if(empty($results)) {
	$db->query("INSERT INTO cxpanel_recording_agent (`identifier`, `directory`, `resource_host`, `resource_extension`, `file_name_mask`) VALUES ('local-rec', '/var/spool/asterisk/monitor', '".php_uname('n')."', 'wav', '\${Tag(exten)}-\${DstExtension}-\${SrcExtension}-\${Date(yyyyMMdd)}-\${Time(HHmmss)}-\${CDRUniqueId}')");
}
out("Done");

outn("Build email table...");

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


$table = \FreePBX::Database()->migrate("cxpanel_email");
$cols = array (
  'subject' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'body' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

$results = $db->getAll("SELECT * FROM cxpanel_email");
if(empty($results)) {
	$db->query("INSERT INTO cxpanel_email (`subject`, `body`) VALUES ('".$cxpanelBrandName." user login password', '".$defaultEmailBody."')");
}

out("Done");

outn("Build phone number table...");

$table = \FreePBX::Database()->migrate("cxpanel_phone_number");
$cols = array (
  'cxpanel_phone_number_id' =>
  array (
    'type' => 'integer',
    'primaryKey' => true,
    'autoincrement' => true,
  ),
  'user_id' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'phone_number' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'type' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

$results = $db->getAll("SELECT * FROM cxpanel_phone_number");
if(empty($results)) {
	$db->query("INSERT INTO cxpanel_phone_number (`subject`, `body`) VALUES ('".$cxpanelBrandName." user login password', '".$defaultEmailBody."')");
}

out("Done");

outn("Build users items table...");
//Build users table
$table = \FreePBX::Database()->migrate("cxpanel_users");
$cols = array (
  'cxpanel_user_id' =>
  array (
    'type' => 'integer',
    'primaryKey' => true,
    'autoincrement' => true,
  ),
  'user_id' =>
  array (
    'type' => 'string',
    'length' => '190',
  ),
  'display_name' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'peer' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'add_extension' =>
  array (
    'type' => 'integer',
  ),
  'full' =>
  array (
    'type' => 'integer',
  ),
  'add_user' =>
  array (
    'type' => 'integer',
  ),
  'hashed_password' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'initial_password' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'auto_answer' =>
  array (
    'type' => 'integer',
  ),
  'parent_user_id' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'password_dirty' =>
  array (
    'type' => 'integer',
  ),
);


$indexes = array (
  'user_id' =>
  array (
    'type' => 'unique',
    'cols' =>
    array (
      0 => 'user_id',
    ),
  ),
);
$table->modify($cols, $indexes);
unset($table);

//Gather user info
$entries = array();
if((function_exists("core_users_list")) && (($freePBXUsers = core_users_list()) !== null)){
	foreach($freePBXUsers as $freePBXUser) {
		if(function_exists("core_devices_get")) {

			//Determine user info
			$userId = $freePBXUser[0];
			$userDevice = core_devices_get($userId);
			$peer = ($userDevice['dial'] != "") ? $userDevice['dial'] : "SIP/$userId";
			$displayName = $freePBXUser[1] == "" ? $freePBXUser[0] : $freePBXUser[1];

			//Generate a password for the user
			$password = cxpanel_generate_password(10);
			$passwordSHA1 = sha1($password);

			//Add user
			array_push($entries, array("user_id" => $userId, "display_name" => $displayName, "peer" => $peer, "hashed_password" => $passwordSHA1, "initial_password" => $password, "parent_user_id" => $userId));
		}
	}
}

foreach($entries as $entry) {
	$sql = "REPLACE INTO cxpanel_users (`user_id`, `display_name`, `peer`, `hashed_password`, `initial_password`, `parent_user_id`, `add_extension`, `add_user`, `full`) VALUES (?,?,?,?,?,?,1,1,1)";
	$sth = FreePBX::Database()->prepare($sql);
	$sth->execute(array($entry['user_id'], $entry['display_name'], $entry['peer'], $entry['hashed_password'], $entry['initial_password'], $entry['parent_user_id']));
}

out("Done");

outn("Build queues table...");
//Build queues table
$table = \FreePBX::Database()->migrate("cxpanel_queues");
$cols = array (
  'cxpanel_queue_id' =>
  array (
    'type' => 'integer',
    'primaryKey' => true,
    'autoincrement' => true,
  ),
  'queue_id' =>
  array (
    'type' => 'string',
    'length' => '190',
  ),
  'display_name' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'add_queue' =>
  array (
    'type' => 'integer',
  ),
);


$indexes = array (
  'queue_id' =>
  array (
    'type' => 'unique',
    'cols' =>
    array (
      0 => 'queue_id',
    ),
  ),
);
$table->modify($cols, $indexes);
unset($table);

//Gather queue info
$entries = array();
if((function_exists("queues_list")) && (($freePBXQueues = queues_list()) !== null)) {
	foreach($freePBXQueues as $freePBXQueue) {
		$queueId = $freePBXQueue[0];
		$displayName = $freePBXQueue[1] == "" ? $freePBXQueue[0] : $freePBXQueue[1];
		array_push($entries, array("queue_id" => $queueId, "display_name" => $displayName));
	}
}

foreach($entries as $entry) {
	$sql = "REPLACE INTO cxpanel_queues (`queue_id`, `display_name`, `add_queue`) VALUES (?,?, 1)";
	$sth = FreePBX::Database()->prepare($sql);
	$sth->execute(array($entry['queue_id'], $entry['display_name']));
}

out("Done");

outn("Build conference rooms table...");
//Build conference rooms table
$table = \FreePBX::Database()->migrate("cxpanel_conference_rooms");
$cols = array (
  'cxpanel_conference_room_id' =>
  array (
    'type' => 'integer',
    'primaryKey' => true,
    'autoincrement' => true,
  ),
  'conference_room_id' =>
  array (
    'type' => 'string',
    'length' => '190',
  ),
  'display_name' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'add_conference_room' =>
  array (
    'type' => 'integer',
  ),
);


$indexes = array (
  'conference_room_id' =>
  array (
    'type' => 'unique',
    'cols' =>
    array (
      0 => 'conference_room_id',
    ),
  ),
);
$table->modify($cols, $indexes);
unset($table);

//Gather conference room info
$entries = array();
if((function_exists("conferences_list")) && (($freePBXConferenceRooms = conferences_list()) !== null)) {
	foreach($freePBXConferenceRooms as $freePBXConferenceRoom) {
		$conferenceRoomId = $freePBXConferenceRoom[0];
		$displayName = $freePBXConferenceRoom[1] == "" ? $freePBXConferenceRoom[0] : $freePBXConferenceRoom[1];
		array_push($entries, array("conference_room_id" => $conferenceRoomId, "display_name" => $displayName));
	}
}

foreach($entries as $entry) {
	$sql = "REPLACE INTO cxpanel_conference_rooms (`conference_room_id`, `display_name`, `add_conference_room`) VALUES (?,?,1)";
	$sth = FreePBX::Database()->prepare($sql);
	$sth->execute(array($entry['conference_room_id'], $entry['display_name']));
}

out("Done");

outn("Build managed items table...");
//Build managed items table
$table = \FreePBX::Database()->migrate("cxpanel_managed_items");
$cols = array (
  'cxpanel_id' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'fpbx_id' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
  'type' =>
  array (
    'type' => 'string',
    'length' => '1000',
  ),
);


$indexes = array (
);
$table->modify($cols, $indexes);
unset($table);

out("Done");
