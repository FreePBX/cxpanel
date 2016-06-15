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
out("Removing \"cxpanel_server\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop voicemail agent table
$query = "DROP TABLE IF EXISTS cxpanel_voicemail_agent";
out("Removing \"cxpanel_voicemail_agent\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop recording agent table
$query = "DROP TABLE IF EXISTS cxpanel_recording_agent";
out("Removing \"cxpanel_recording_agent\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop email table
$query = "DROP TABLE IF EXISTS cxpanel_email";
out("Removing \"cxpanel_email\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop phone number table
$query = "DROP TABLE IF EXISTS cxpanel_phone_number";
out("Removing \"cxpanel_phone_number\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop users table
$query = "DROP TABLE IF EXISTS cxpanel_users";
out("Removing \"cxpanel_users\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop queues table
$query = "DROP TABLE IF EXISTS cxpanel_queues";
out("Removing \"cxpanel_queues\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop conference rooms table
$query = "DROP TABLE IF EXISTS cxpanel_conference_rooms";
out("Removing \"cxpanel_conference_rooms\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Drop managed items table
$query = "DROP TABLE IF EXISTS cxpanel_managed_items";
out("Removing \"cxpanel_managed_items\" Table....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove table.");
}

//Remove manager entry
$query = "DELETE FROM manager WHERE name = 'cxpanel'";
out("Removing manager entry....");
$results = $db->query($query);
if(DB::IsError($results)) {
	out("ERROR: could not remove manager entry.");
}
