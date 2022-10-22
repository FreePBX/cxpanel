<?php
/*
 *Name         : cxpanel.php
 *Author       : Michael Yara
 *Created      : Jan 28, 2013
 *Last Updated : Feb 21, 2014
 *Version      : 3.0
 *Purpose      : Defines classes used to communicate with the rest service via JSON
 */

namespace FreePBX\modules\Cxpanel;
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 *
 * Describes a core server
 * @author michaely
 *
 */
class cxpanel_core_server {
	public $slug;
	public $name;
	public $brokerHost;
	public $brokerPort;

	function __construct($slug, $name, $brokerHost, $brokerPort) {
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
	public $identifier;
	public $rootPath;
	public $resourceHost;
	public $resourceExtension;

	function __construct($identifier, $rootPath, $resourceHost, $resourceExtension) {
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
	public $identifier;
	public $rootPath;
	public $resourceHost;
	public $resourceExtension;
	public $fileNameMask;

	function __construct($identifier, $rootPath, $resourceHost, $resourceExtension, $fileNameMask) {
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
	public $displayName;
	public $host;
	public $port;
	public $username;
	public $password;
	public $enabled;
	public $cdrHost;
	public $cdrPort;
	public $cdrUsername;
	public $cdrPassword;
	public $recordingAgentIdentifier;

	function __construct($displayName, $host, $port, $username,
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

	public $userName;
	public $password;
	public $superUser;

	function __construct($userName, $password, $superUser) {
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

	public $restricted;
	public $extension;
	public $displayName;
	public $autoAnswer;
	public $peer;
	public $altOriginationMethod;
	public $agentName;
	public $agentLocation;
	public $agentInterface;
	public $agentPenalty;
	public $agentPaused;
	public $originatingContextOverride;
	public $redirectingContextOverride;
	public $originateTimeoutOverride;
	public $voiceMailContext;
	public $voiceMailBox;

	function __construct(	$restricted, $extension, $displayName, $autoAnswer,
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

	public $restricted;
	public $username;
	public $password;
	public $enabled;
	public $full;

	function __construct($restricted, $username, $password, $enabled, $full) {
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

	public $restricted;
	public $displayName;
	public $identifier;
	public $destinationExtension;
	public $destinationContext;
	public $enabled;

	function __construct($restricted, $displayName, $identifier, $destinationExtension, $destinationContext, $enabled) {
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

	public $restricted;
	public $name;
	public $identifier;
	public $destinationExtension;
	public $destinationContext;

	function __construct($restricted, $name, $identifier, $destinationExtension, $destinationContext) {
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

	public $restricted;
	public $name;
	public $identifier;
	public $destinationExtension;
	public $destinationContext;

	function __construct($restricted, $name, $identifier, $destinationExtension, $destinationContext) {
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

	public $firstName;
	public $lastName;

	function __construct($firstName, $lastName) {
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

	public $type;
	public $address;

	function __construct($type, $address) {
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

	public $type;
	public $number;

	function __construct($type, $number) {
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
	public $cancel;
	public $licensedTo;
	public $email;

	function __construct($cancel, $licensedTo, $email) {
		$this->cancel = $cancel;
		$this->licensedTo = $licensedTo;
		$this->email = $email;
	}
}


