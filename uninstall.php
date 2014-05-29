<?php
/*
 *Name         : uninstall.php
 *Author       : Michael Yara
 *Created      : August 15, 2008
 *Last Updated : April 12, 2013
 *Version      : 3.0
 *Purpose      : Remove tables and perform cleanup
 */
 
global $db, $amp_conf;
 
//Drop server table
$query = "DROP TABLE IF EXISTS cxpanel_server";
echo "Removing \"cxpanel_server\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
} 

//Drop voicemail agent table
$query = "DROP TABLE IF EXISTS cxpanel_voicemail_agent";
echo "Removing \"cxpanel_voicemail_agent\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Drop recording agent table
$query = "DROP TABLE IF EXISTS cxpanel_recording_agent";
echo "Removing \"cxpanel_recording_agent\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}
 
//Drop email table
$query = "DROP TABLE IF EXISTS cxpanel_email";
echo "Removing \"cxpanel_email\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Drop phone number table
$query = "DROP TABLE IF EXISTS cxpanel_phone_number";
echo "Removing \"cxpanel_phone_number\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Drop users table
$query = "DROP TABLE IF EXISTS cxpanel_users";
echo "Removing \"cxpanel_users\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Drop queues table
$query = "DROP TABLE IF EXISTS cxpanel_queues";
echo "Removing \"cxpanel_queues\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Drop conference rooms table
$query = "DROP TABLE IF EXISTS cxpanel_conference_rooms";
echo "Removing \"cxpanel_conference_rooms\" Table....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove table.<br>";
}

//Remove manager entry
$query = "DELETE FROM manager WHERE name = 'cxpanel'";
echo "Removing manager entry....<br>";
$results = $db->query($query);
if(DB::IsError($results)) {
	echo "ERROR: could not remove manager entry.<br>";
}

