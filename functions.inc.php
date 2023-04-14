<?php
/*
 *Name         : functions.inc.php
 *Author       : Michael Yara
 *Created      : June 27, 2008
 *Last Updated : April 24, 2014
 *Version      : 3.0
 *Purpose      : Handles syncing of the FreePBX config with the server
 *Copyright    : 2014 HEHE Enterprises, LLC
 *
 *	The following files in this module are subject to the above copyright:
 *	./functions.inc.php
 *	./index.php
  *	./modify.php
 *	./page.cxpanel_menu.php
 *	./page.cxpanel.php
  *	./lib/cxpanel.class.php
 *	./lib/dialplan.class.php
 *	./lib/logger.class.php
 */


 //Includes
require_once(dirname(__FILE__)."/vendor/autoload.php");

global $amp_conf;

//Setup userman hooks
if(!function_exists('setup_userman')){
	if(\FreePBX::Modules()->checkStatus('userman')) {
		if (file_exists($amp_conf['AMPWEBROOT'].'/admin/modules/userman/functions.inc.php')) {
			include_once($amp_conf['AMPWEBROOT'].'/admin/modules/userman/functions.inc.php');
		}
	}
}

//Ensure that the manager module has loaded. If not, load it.
if(!function_exists('manager_add')){
	if(\FreePBX::Modules()->checkStatus('manager')) {
		if (file_exists($amp_conf['AMPWEBROOT'].'/admin/modules/manager/functions.inc.php')) {
			include_once($amp_conf['AMPWEBROOT'].'/admin/modules/manager/functions.inc.php');
		}
	}	
}

/**
 *
 * Main module function.
 * Gets called by the framework
 * @param String $engine
 *
 */
function cxpanel_get_config($engine)
{
	// \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_config($engine);
}

/**
 * Hook that provides the panel settings UI section on the user managemnet page.
 */
function cxpanel_hook_userman()
{
	// \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->hook_userman();
}

/**
 *
 * Function used to hook the extension/user page in FreePBX
 * @param String $pagename the name of the page being loaded
 *
 */
function cxpanel_configpageinit($pagename)
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->configpageinit($pagename);
}

/**
 *
 * Applies hooks to the extension page
 *
 */
function cxpanel_extension_applyhooks()
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->extension_applyhooks();
}

/**
 *
 * Contributes the panel gui elements to the extension page
 *
 */
function cxpanel_extension_configpageload()
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->extension_configpageload();
}

/**
 *
 * Handles additions removals and updates of extensions.
 *
 */
function cxpanel_extension_configprocess()
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->extension_configprocess();
}

/**
 *
 * Contributes the panel gui elements to the queue page
 * @param String $viewing_itemid the id of the item being viewed
 * @param String $target_menuid the menu id of the page being loaded
 *
 */
function cxpanel_hook_queues($viewing_itemid, $target_menuid)
{
	// \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->hook_queues($viewing_itemid, $target_menuid);
}

/**
 *
 * Handles additions removals and updates of queues.
 *
 */
function cxpanel_hookProcess_queues($viewing_itemid, $request)
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->hookProcess_queues($viewing_itemid, $request);
}

/**
 *
 * Contributes the panel gui elements to the conference room page
 * @param String $viewing_itemid the id of the item being viewed
 * @param String $target_menuid the menu id of the page being loaded
 *
 */
function cxpanel_hook_conferences($viewing_itemid, $target_menuid)
{
	// \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->hook_conferences($viewing_itemid, $target_menuid);
}

/**
 *
 * Handles additions removals and updates of queues.
 *
 */
function cxpanel_hookProcess_conferences($viewing_itemid, $request)
{
	// \FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->hookProcess_conferences($viewing_itemid, $request);
}

function cxpanel_server_update($name, $asteriskHost, $clientHost, $clientPort, $clientUseSSL, $apiHost, $apiPort, $apiUserName, $apiPassword, $apiUseSSL, $syncWithUserman, $cleanUnknownItems)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->server_update($name, $asteriskHost, $clientHost, $clientPort, $clientUseSSL, $apiHost, $apiPort, $apiUserName, $apiPassword, $apiUseSSL, $syncWithUserman, $cleanUnknownItems);
}

function cxpanel_server_get()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->server_get();
}

function cxpanel_voicemail_agent_update($identifier, $directory, $resourceHost, $resourceExtension)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->voicemail_agent_update($identifier, $directory, $resourceHost, $resourceExtension);
}

function cxpanel_voicemail_agent_get()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->voicemail_agent_get();
}

function cxpanel_recording_agent_update($identifier, $directory, $resourceHost, $resourceExtension, $fileNameMask)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->recording_agent_update($identifier, $directory, $resourceHost, $resourceExtension, $fileNameMask);
}

function cxpanel_recording_agent_get()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->recording_agent_get();
}

function cxpanel_email_get()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->email_get();
}

function cxpanel_email_update($subject, $body)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->email_update($subject, $body);
}

function cxpanel_user_add($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->user_add($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full);
}

function cxpanel_user_add_with_initial_password($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full, $parentUserId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->user_add_with_initial_password($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full, $parentUserId);
}

function cxpanel_user_update($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->user_update($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full);
}

function cxpanel_extension_update($userId, $addExtension, $autoAnswer, $peer, $displayName)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->extension_update($userId, $addExtension, $autoAnswer, $peer, $displayName);
}

function cxpanel_user_set_parent_user_id($userId, $parentUserId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->user_set_parent_user_id($userId, $parentUserId);
}

function cxpanel_user_del($userId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->user_del($userId);
}

function cxpanel_user_list()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->user_list();
}

function cxpanel_user_get($userId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->user_get($userId);
}

function cxpanel_user_extension_list($userId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->user_extension_list($userId);
}

function cxpanel_mark_user_password_dirty($userId, $dirty)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->mark_user_password_dirty($userId, $dirty);
}

function cxpanel_mark_all_user_passwords_dirty($dirty)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->mark_all_user_passwords_dirty($dirty);
}

function cxpanel_phone_number_list_all()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->phone_number_list_all();
}

function cxpanel_phone_number_list($userId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->phone_number_list($userId);
}

function cxpanel_phone_number_del($userId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->phone_number_del($userId);
}

function cxpanel_phone_number_add($userId, $phoneNumber, $type)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->phone_number_add($userId, $phoneNumber, $type);
}

function cxpanel_queue_add($queueId, $addQueue, $displayName)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->queue_add($queueId, $addQueue, $displayName);
}

function cxpanel_queue_update($queueId, $addQueue, $displayName)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->queue_update($queueId, $addQueue, $displayName);
}

function cxpanel_queue_del($queueId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->queue_del($queueId);
}

function cxpanel_queue_list()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->queue_list();
}

function cxpanel_queue_get($queueId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->queue_get($queueId);
}

function cxpanel_conference_room_add($conferenceRoomId, $addConferenceRoom, $displayName)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->conference_room_add($conferenceRoomId, $addConferenceRoom, $displayName);
}

function cxpanel_conference_room_update($conferenceRoomId, $addConferenceRoom, $displayName)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->conference_room_update($conferenceRoomId, $addConferenceRoom, $displayName);
}

function cxpanel_conference_room_del($conferenceRoomId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->conference_room_del($conferenceRoomId);
}

function cxpanel_conference_room_list()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->conference_room_list();
}

function cxpanel_conference_room_get($conferenceRoomId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->conference_room_get($conferenceRoomId);
}

function cxpanel_has_managed_item($type, $cxpanelId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->has_managed_item($type, $cxpanelId);
}

function cxpanel_managed_item_get_all()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->managed_item_get_all();
}

function cxpanel_managed_item_get($type, $cxpanelId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->managed_item_get($type, $cxpanelId);
}

function cxpanel_managed_item_add($type, $fpbxId, $cxpanelId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->managed_item_add($type, $fpbxId, $cxpanelId);
}

function cxpanel_managed_item_del($type, $cxpanelId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->managed_item_del($type, $cxpanelId);
}

function cxpanel_managed_item_update($type, $fpbxId, $cxpanelId)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->managed_item_update($type, $fpbxId, $cxpanelId);
}

function cxpanel_gen_managed_uuid($type, $fpbxId)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->gen_managed_uuid($type, $fpbxId);
}

function cxpanel_queue_eventwhencalled_modify($addQueue)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->queue_eventwhencalled_modify($addQueue);
}

function cxpanel_queue_eventmemberstatus_modify($addQueue)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->queue_eventmemberstatus_modify($addQueue);
}

function cxpanel_create_manager()
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->create_manager();
}

function cxpanel_get_agent_login_context()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_agent_login_context();
}

function cxpanel_get_agent_interface_type()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_agent_interface_type();
}

function cxpanel_get_parking_timeout()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_parking_timeout();
}

function cxpanel_add_contexts($contextPrefix, $variablePrefix, $parkingTimeout)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->add_contexts($contextPrefix, $variablePrefix, $parkingTimeout);
}

function cxpanel_sync_user_extensions($userId, $userExtensions)
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->sync_user_extensions($userId, $userExtensions);
}

function cxpanel_get_freepbx_users_from_extension($extension)
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_freepbx_users_from_extension($extension);
}

function cxpanel_send_password_email($userId, $pass = "", $email = "")
{
	\FreePBX::Modules()->deprecatedFunction();
	\FreePBX::Cxpanel()->send_password_email($userId, $pass, $email);
}

function cxpanel_get_core_ampusers_list()
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_core_ampusers_list();
}


function cxpanel_get_userman_administrators() {
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_userman_administrators();
}

function cxpanel_get_combined_administrators() {
    \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Cxpanel()->get_combined_administrators();
}
