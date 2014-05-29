<?php 
/*
 *Name         : cxpanel.php
 *Author       : Michael Yara
 *Created      : Jan 28, 2013
 *Last Updated : Feb 21, 2014
 *Version      : 3.0
 *Purpose      : Defines classes used to communicate with the rest service via JSON
 */

/**
 * 
 * Describes a core server
 * @author michaely
 *
 */
class cxpanel_core_server {
	var $slug;
	var $name;
	var $brokerHost;
	var $brokerPort;
	
	function cxpanel_core_server($slug, $name, $brokerHost, $brokerPort) {
		$this->slug = $slug;
		$this->name = $name;
		$this->brokerHost = $brokerHost;
		$this->brokerPort = $brokerPort;
	}
}

/**
 * 
 * Describes a voicemail agent
 * @author michaely
 *
 */
class cxpanel_voicemail_agent {
	var $identifier;
	var $rootPath;
	var $resourceHost;
	var $resourceExtension;

	function cxpanel_voicemail_agent($identifier, $rootPath, $resourceHost, $resourceExtension) {
		$this->identifier = $identifier;
		$this->rootPath = $rootPath;
		$this->resourceHost = $resourceHost;
		$this->resourceExtension = $resourceExtension;
	}
}

/**
 *
 * Describes a recording agent
 * @author michaely
 *
 */
class cxpanel_recording_agent {
	var $identifier;
	var $rootPath;
	var $resourceHost;
	var $resourceExtension;
	var $fileNameMask;

	function cxpanel_recording_agent($identifier, $rootPath, $resourceHost, $resourceExtension, $fileNameMask) {
		$this->identifier = $identifier;
		$this->rootPath = $rootPath;
		$this->resourceHost = $resourceHost;
		$this->resourceExtension = $resourceExtension;
		$this->fileNameMask = $fileNameMask;
	}
}

/**
 * 
 * Describes a PBX server connection
 * @author michaely
 *
 */
class cxpanel_pbx_server {
	var $displayName;
	var $host;
	var $port;
	var $username;
	var $password;
	var $enabled;
	var $cdrHost;
	var $cdrPort;
	var $cdrUsername;
	var $cdrPassword;
	var $recordingAgentIdentifier;
	
	function cxpanel_pbx_server($displayName, $host, $port, $username,
								$password, $cdrHost, $cdrPort,
								$cdrUsername, $cdrPassword, $enabled, $recordingAgentIdentifier) {
		$this->displayName = $displayName;
		$this->host = $host;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
		$this->enabled = $enabled;
		$this->cdrHost = $cdrHost;
		$this->cdrPort = $cdrPort;
		$this->cdrUsername = $cdrUsername;
		$this->cdrPassword = $cdrPassword;
		$this->recordingAgentIdentifier = $recordingAgentIdentifier;
	}
}

/**
 *
 * Describes an administrator
 * @author michaely
 *
 */
class cxpanel_administrator {
	
	var $userName;
	var $password;
	var $superUser;
	
	function cxpanel_administrator($userName, $password, $superUser) {
		$this->userName = $userName;
		$this->password = $password;
		$this->superUser = $superUser;
	}
}

/**
 *
 * Describes an extension
 * @author michaely
 *
 */
class cxpanel_extension {
	
	var $restricted;
	var $extension;
	var $displayName;
	var $autoAnswer;
	var $peer;
	var $altOriginationMethod;
	var $agentName;
	var $agentLocation;
	var $agentInterface;
	var $agentPenalty;
	var $agentPaused;
	var $originatingContextOverride;
	var $redirectingContextOverride;
	var $originateTimeoutOverride;
	var $voiceMailContext;
	var $voiceMailBox;
	
	function cxpanel_extension(	$restricted, $extension, $displayName, $autoAnswer,
								$peer, $altOriginationMethod, $agentName,
								$agentLocation, $agentInterface, $agentPenalty,
								$agentPaused, $originatingContextOverride,
								$redirectingContextOverride, $originateTimeoutOverride,
								$voiceMailContext, $voiceMailBox) {
		$this->restricted = $restricted;
		$this->extension = $extension;
		$this->displayName = $displayName;
		$this->autoAnswer = $autoAnswer;
		$this->peer = $peer;
		$this->altOriginationMethod = $altOriginationMethod;
		$this->agentName = $agentName;
		$this->agentLocation = $agentLocation;
		$this->agentInterface = $agentInterface;
		$this->agentPenalty = $agentPenalty;
		$this->agentPaused = $agentPaused;
		$this->originatingContextOverride = $originatingContextOverride;
		$this->redirectingContextOverride = $redirectingContextOverride;
		$this->originateTimeoutOverride = $originateTimeoutOverride;
		$this->voiceMailContext = $voiceMailContext;
		$this->voiceMailBox = $voiceMailBox;
	}
}

/**
 *
 * Describes a user
 * @author michaely
 *
 */
class cxpanel_user {
	
	var $restricted;
	var $username;
	var $password;
	var $enabled;
	var $full;
	
	function cxpanel_user($restricted, $username, $password, $enabled, $full) {
		$this->restricted = $restricted;
		$this->username = $username;
		$this->password = $password;
		$this->enabled = $enabled;
		$this->full = $full;
	}
}

/**
 *
 * Describes a queue
 * @author michaely
 *
 */
class cxpanel_queue {
	
	var $restricted;
	var $displayName;
	var $identifier;
	var $destinationExtension;
	var $destinationContext;
	var $enabled;
	
	function cxpanel_queue($restricted, $displayName, $identifier, $destinationExtension, $destinationContext, $enabled) {
		$this->restricted = $restricted;
		$this->displayName = $displayName;
		$this->identifier = $identifier;
		$this->destinationExtension = $destinationExtension;
		$this->destinationContext = $destinationContext;
		$this->enabled = $enabled;
	}
}

/**
 *
 * Describes a conference room
 * @author michaely
 *
 */
class cxpanel_conference_room {

	var $restricted;
	var $name;
	var $identifier;
	var $destinationExtension;
	var $destinationContext;

	function cxpanel_conference_room($restricted, $name, $identifier, $destinationExtension, $destinationContext) {
		$this->restricted = $restricted;
		$this->name = $name;
		$this->identifier = $identifier;
		$this->destinationExtension = $destinationExtension;
		$this->destinationContext = $destinationContext;
	}
}

/**
 *
 * Describes a parking lot
 * @author michaely
 *
 */
class cxpanel_parking_lot {

	var $restricted;
	var $name;
	var $identifier;
	var $destinationExtension;
	var $destinationContext;

	function cxpanel_parking_lot($restricted, $name, $identifier, $destinationExtension, $destinationContext) {
		$this->restricted = $restricted;
		$this->name = $name;
		$this->identifier = $identifier;
		$this->destinationExtension = $destinationExtension;
		$this->destinationContext = $destinationContext;
	}
}

/**
 * 
 * Describes a user contact
 * @author michaely
 *
 */
class cxpanel_user_contact {
	
	var $firstName;
	var $lastName;
	
	function cxpanel_user_contact($firstName, $lastName) {
		$this->firstName = $firstName;
		$this->lastName = $lastName;
	}
}

/**
 * 
 * Describes a user contact email address
 * @author michaely
 *
 */
class cxpanel_user_contact_email_address {
	
	var $type;
	var $address;
	
	function cxpanel_user_contact_email_address($type, $address) {
		$this->type = $type;
		$this->address = $address;
	}
}

/**
 *
 * Describes a user contact phone number
 * @author michaely
 *
 */
class cxpanel_user_contact_phone_number {

	var $type;
	var $number;

	function cxpanel_user_contact_phone_number($type, $number) {
		$this->type = $type;
		$this->number = $number;
	}
}

/**
 *
 * Class used for sending license bind requests
 * @author michaely
 *
 */
class cxpanel_bind_request {
	var $cancel;
	var $licensedTo;
	var $email;

	function cxpanel_bind_request($cancel, $licensedTo, $email) {
		$this->cancel = $cancel;
		$this->licensedTo = $licensedTo;
		$this->email = $email;
	}
}


