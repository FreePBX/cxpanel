<?php
// vim: set ai ts=4 sw=4 ft=php:
//
/**
 *Name         : Cxpanel.class.php
 *Author       : Andrew Nagy
 *Author       : Michael Yara
 *Created      : February 3, 2015
 *Last Updated : February 4, 2015
 *Version      : 4.1
 *Purpose      : BMO Class definition for the cxpanel module.
 *Copyright    : 2015 HEHE Enterprises, LLC
 *
 *	The following files in this module are subject to the above copyright:
 *	./functions.inc.php
 *	./index.php
 *	./install.php
 *	./modify.php
 *	./page.cxpanel_menu.php
 *	./page.cxpanel.php
 *	./uninstall.php
 *	./lib/cxpanel.class.php
 *	./lib/dialplan.class.php
 *	./lib/logger.class.php
 *	./lib/table.class.php
 *	./lib/util.php
 */
namespace FreePBX\modules;

use BMO;
use DB_Helper;

require_once(dirname(__FILE__)."/vendor/autoload.php");
require_once(dirname(__FILE__)."/lib/dialplan.class.php");
require_once(dirname(__FILE__)."/lib/CXPestJSON.php");
require_once(dirname(__FILE__)."/lib/cxpanel.class.php");
require_once(dirname(__FILE__)."/lib/util.php");
require_once(dirname(__FILE__)."/lib/ui/cxpanel_radio.class.php");
require_once(dirname(__FILE__)."/lib/ui/cxpanel_multi_selectbox.class.php");
require_once(dirname(__FILE__)."/lib/ui/gui_checkbox.class.php");

class Cxpanel extends DB_Helper implements BMO
{
	public $brandName;
	public $log = null;
	private $tables = array(
		'server' 	=> 'cxpanel_server',
		'users'  	=> "cxpanel_users",
		'queues' 	=> "cxpanel_queues",
		'rooms'  	=> "cxpanel_conference_rooms",
		'items'  	=> "cxpanel_managed_items",
		'voicemail' => "cxpanel_voicemail_agent",
		'recording' => "cxpanel_recording_agent",
		'email' 	=> "cxpanel_email",
		'phone' 	=> "cxpanel_phone_number",
	);

	public $defaultVal = array(
		//"read" and "write" permission for the AMI manager entry.
		//This list should be kept up to date, for supported versions of Asterisk.
		'amiPermissions' 	=> 'system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan,originate',
		'UserPasswordMask' 	=> "********",
		'api' => array(
			'name' 		=> 'default',
			'host' 		=> 'localhost',
			'port' 		=> '58080',
			'username'  => 'manager',
			'password'  => 'manag3rpa55word',
		),
		'asterisk' => array(
			'host' => 'localhost',
		),
		'client' => array(
			'host' => '',
			'port' => '58080',
		),
		'voicemail' => array(
			'identifier' 		 => 'local-vm',
			'directory' 		 => '',		// << Set in __construct
			'resource_host' 	 => '',		// << Set in __construct
			'resource_extension' => 'wav',
		),
		'recording' => array(
			'identifier' 		 => 'local-rec',
			'directory' 		 => '/var/spool/asterisk/monitor',
			'resource_host' 	 => '',		// << Set in __construct
			'resource_extension' => 'wav',
			'file_name_mask' 	 => '\${Tag(exten)}-\${DstExtension}-\${SrcExtension}-\${Date(yyyyMMdd)}-\${Time(HHmmss)}-\${CDRUniqueId}',
		),
		'email' => array(
			'from' 	  => '',				// << Set in __construct
			'subject' => '',				// << Set in __construct
			'body' 	  => '',				// << Set in __construct
		),
	);

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->FreePBX 	= $freepbx;
		$this->db 		= $freepbx->Database;
		$this->cfg 		= $freepbx->Config;
		$this->Userman 	= &$freepbx->Userman;

		$this->brandName = $this->getBrandName();

		$this->defaultVal['voicemail']['directory'] 	= $this->getPath("voicemail");
		$this->defaultVal['voicemail']['resource_host'] = php_uname('n');

		$this->defaultVal['recording']['directory'] 	= $this->getPath("monitor");
		$this->defaultVal['recording']['resource_host'] = php_uname('n');
		
		$this->defaultVal['email']['from'] 		= sprintf("%s@%s", get_current_user(), gethostname());
		$this->defaultVal['email']['subject'] 	= _("%%brandName%% user login password");
		$this->defaultVal['email']['body'] 		= implode("<br/>", array(
													"%%logo%%",
													"",
													_("Hello,"),
													"",
													_("This email is to inform you of your %%brandName%% login credentials:"),
													"",
													sprintf("<b>&nbsp;&nbsp;&nbsp;%s</b> %%userId%%", _("Username:")),
													sprintf("<b>&nbsp;&nbsp;&nbsp;%s</b> %%password%%", _("Password:")),
													"",
													sprintf('<a href="%%clientURL%%">%s</a>', _("Click Here To Login"))
												));
		$this->log = $this->logInit();
	}

	/**
	 * FreePBX chown hooks
	 * @return array
	*/
	public function chownFreepbx() {
		$files = array();
		$files[] = array(
			'type' => 'file',
			'path'  => $this->getPath("log"),
			'perms' => 0775
		);
		return $files;
	}

	public function getPath($value)
	{
		switch (strtolower($value))
		{
			case "brand_file":
				$data_return = '/etc/schmooze/operator-panel-brand';
				break;

			case "log":
				$data_return = sprintf("%s/main.log",  $this->getPath('cxpanel_spool'));
				break;

			case "log_modify":
				$data_return = sprintf("%s/modify.log",  $this->getPath('cxpanel_spool'));
				break;

			case "lock":
				$data_return = sprintf("%s/lock",  $this->getPath('cxpanel_spool'));
				break;

			case "module":
				$data_return = sprintf("%s/admin/modules/cxpanel",  $this->cfg->get('AMPWEBROOT'));
				break;

			case "cxpanel":
				$data_return = sprintf("%s/admin/cxpanel",  $this->cfg->get('AMPWEBROOT'));
				break;

			case "cxpanel_old":
				$data_return = sprintf("%s/cxpanel",  $this->cfg->get('AMPWEBROOT'));
				break;

			case "cxpanel_spool":
				$data_return = sprintf("%s/cxpanel",  $this->cfg->get('ASTSPOOLDIR'));
				break;

			case "voicemail":
				$data_return = sprintf("%s/voicemail",  $this->cfg->get('ASTSPOOLDIR'));
				break;
			
			case "monitor":
				$data_return = sprintf("%s/monitor",  $this->cfg->get('ASTSPOOLDIR'));
				break;

			case "modify":
				$data_return = sprintf("%s/admin/modules/cxpanel/modify.php",  $this->cfg->get('AMPWEBROOT'));
				break;

			default:
				$data_return = "";
		}
		return $data_return;
	}

	private function getBrandName(){
		$cxpanelBrandName = FALSE;
		$brandFile = $this->getPath("brand_file");

		if (file_exists($brandFile)) {
			$cxpanelBrandName = file_get_contents($brandFile);
		}

		if($cxpanelBrandName === FALSE || trim($cxpanelBrandName) == "") {
			$cxpanelBrandName = 'iSymphony';
		}

		$cxpanelBrandName = trim($cxpanelBrandName);
		return $cxpanelBrandName;
	}

	public function install()
	{
		//Set operator panel web root and enable dev state
		outn(_("Setting operator panel web root and enabling dev state...."));
		$set["FOPWEBROOT"] = "cxpanel";
		$set["USEDEVSTATE"] = true;
		$this->cfg->set_conf_values($set, true, true);
		out(_("Done"));


		//Set callevents = yes for hold events
		if($this->FreePBX->Modules->checkStatus("sipsettings"))
		{
			if(function_exists("sipsettings_edit") && function_exists("sipsettings_get"))
			{
				outn(_("Setting callevents = yes...."));
				$sip_settings = sipsettings_get();
				$sip_settings['callevents'] = 'yes';
				sipsettings_edit($sip_settings);
				out(_("Done"));
			}
		}


		//Create symlink that points to the module directory in order to run the client redirect script
		outn(_("Creating client symlink...."));
		$path_module 	  = $this->getPath("module")."/";
		$path_cxpanel 	  = $this->getPath("cxpanel");
		$path_cxpanel_old = $this->getPath("cxpanel_old");

		if(file_exists($path_cxpanel)) {
			unlink($path_cxpanel);
		}
		symlink($path_module, $path_cxpanel);

		if(file_exists($path_cxpanel_old) && is_link($path_cxpanel_old)) {
			unlink($path_cxpanel_old);
		}
		out(_("Done"));


		//Turn on voicemail polling if not already on
		if($this->FreePBX->Modules->checkStatus("voicemail"))
		{
			if(function_exists("voicemail_get_settings"))
			{
				$vmSettings = voicemail_get_settings(voicemail_getVoicemail(), "settings");
				if($vmSettings["pollmailboxes"] != "yes" || empty($vmSettings["pollfreq"]))
				{
					outn(_("Enabling voicemail box polling..."));
					if(function_exists("voicemail_update_settings"))
					{
						voicemail_update_settings("settings", "", "", array("gen__pollfreq" => "15", "gen__pollmailboxes" => "yes"));
					}
					out(_("Done"));
				}
			}
		}


		// Start - All Global Settings
		$all_settings = array(
			'server' 	=> array(
				'text'  	 => _('Server'),
				'table' 	 => $this->tables['server'],
				'default' 	 => array(
					'name' 					=> $this->defaultVal['api']['name'],
					'asterisk_host' 		=> $this->defaultVal['asterisk']['host'],
					'client_host' 			=> $this->defaultVal['client']['host'],
					'client_port' 			=> $this->defaultVal['client']['port'],
					'client_use_ssl' 		=> 0,
					'api_host' 				=> $this->defaultVal['api']['host'],
					'api_port' 				=> $this->defaultVal['api']['port'],
					'api_username' 			=> $this->defaultVal['api']['username'],
					'api_password' 			=> $this->defaultVal['api']['password'],
					'api_use_ssl' 			=> 0,
					'sync_with_userman'		=> 1,
					'clean_unknown_items'	=> 1,
				),
				'isNull' 	 => is_null($this->voicemail_agent_get()),
				'callUpdate' => 'server_update_key',
				'callNull' 	 => function() {
					outn(_("Upgrade detected, checking userman mode..."));
					if(empty($this->user_list()) && !empty($this->server_get("sync_with_userman")))
					{
						outn(_("Needs to sync with userman..."));
						$this->server_update_key('sync_with_userman', 1);
					}
					else
					{
						outn(_("Leaving userman mode unchanged..."));
					}
					out(_("Done"));
				},
			),
			'voicemail' => array(
				'text'  	 => _('Voicemail Agent'),
				'table' 	 => $this->tables['voicemail'],
				'default' 	 => $this->defaultVal['voicemail'],
				'isNull' 	 => is_null($this->voicemail_agent_get()),
				'callUpdate' => 'voicemail_agent_update_key',
			),
			'recording' => array(
				'text'  	 => _('Recording Agent'),
				'table' 	 => $this->tables['recording'],
				'default' 	 => $this->defaultVal['recording'],
				'isNull' 	 => is_null($this->recording_agent_get()),
				'callUpdate' => 'recording_agent_update_key',
			),
			'email' 	=> array(
				'text'  	 => _('Email'),
				'table' 	 => $this->tables['email'],
				'default' 	 => array(
					"from" 	  => "",
					"subject" => $this->defaultVal['email']['subject'],
					"body" 	  => $this->defaultVal['email']['body'],
				),
				'isNull' 	 => is_null($this->email_get()),
				'callUpdate' => 'email_update_key',
			),
			// TODO: Disabled cxpanel_phone_number - error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'subject' in 'field list'
			// $this->tables['phone'] => array(
			// 	'text' 	 => _('phone number'),
			// 	'insert' => '(`subject`, `body`) values (?,?)',
			// 	'values' => array(
			// 		sprintf(_("%s user login password"), $this->brandName),
			// 		$this->defaultEmailBody,
			// 	),
			// ),
		);
		foreach ($all_settings as $key => $val)
		{
			if ($val['isNull'])
			{
				$table 		 = $val['table'];
				$source_data = array();
				$table_drop  = false;
				
				$sql 		 = sprintf("SHOW TABLES LIKE '%s'", $table);
				$result 	 = $this->db->query($sql);
				$tableExists = (! empty($result) && $result->rowCount() > 0);

				if ($tableExists)
				{
					outn( sprintf(_("Migrating %s settings... "), $val['text']));
					
					$sql = sprintf("SELECT * FROM `%s`", $table);
					$sth = $this->db->prepare($sql);
					$sth->execute();

					$source_data = $sth->fetch(\PDO::FETCH_ASSOC);
					$table_drop  = true;
				}
				else
				{
					$source_data = $val['default'];
					outn( sprintf(_("Creating %s settings... "), $val['text']));
				}

				if (method_exists($this, $val['callUpdate']))
				{
					foreach ($source_data as $key_opt => $val_opt)
					{
						call_user_func(array($this, $val['callUpdate']), $key_opt, $val_opt);
					}
					out(_("Done"));
				}
				else
				{
					out(_("Skip!"));
					out(sprinft(_("Filed to create %s settings, %s method not found!"), $val['text'], $val['callUpdate']));
					$table_drop = false;
				}

				if ($tableExists && $table_drop)
				{
					outn( sprintf(_("Remove old %s settings table... "), $val['text']));
					$sql = sprintf("DROP TABLE `%s`", $table);
					$this->db->prepare($sql)->execute();
					out(_("Done"));
				}
			}
			else
			{
				if (isset($val['callNull']) && is_callable($val['callNull']) && $val['callNull'] instanceof \Closure)
				{
					$val['callNull']();
				}
			}
		}
		// End - All Global Settings


		outn(_("Build users items table..."));
		//Gather user info
		if($this->FreePBX->Modules->checkStatus("core"))
		{
			$entries  = array();
			$mod_core = $this->FreePBX->Core();
			if (($freePBXUsers = $mod_core->listUsers()) !== null)
			{
				foreach($freePBXUsers as $freePBXUser)
				{
					//Determine user info
					$userId 	 = $freePBXUser[0];
					$userDevice  = $mod_core->getDevice($userId);
					$peer 		 = ($userDevice['dial'] != "") 	? $userDevice['dial'] : "SIP/$userId";
					$displayName = $freePBXUser[1] == "" 		? $freePBXUser[0] : $freePBXUser[1];

					//Generate a password for the user
					$password 	  = $this->generate_password(10);
					$passwordSHA1 = sha1($password);

					//Add user
					array_push($entries, array(
						"user_id"			=> $userId,
						"display_name"		=> $displayName,
						"peer" 				=> $peer,
						"hashed_password"	=> $passwordSHA1,
						"initial_password" 	=> $password,
						"parent_user_id" 	=> $userId
					));
				}
			}
			foreach($entries as $entry)
			{
				$sql = sprintf("REPLACE INTO `%s` (`user_id`, `display_name`, `peer`, `hashed_password`, `initial_password`, `parent_user_id`, `add_extension`, `add_user`, `full`) VALUES (?,?,?,?,?,?,1,1,1)", $this->tables['users']); 
				$sth = $this->db->prepare($sql);
				$sth->execute(array(
					$entry['user_id'],
					$entry['display_name'],
					$entry['peer'],
					$entry['hashed_password'],
					$entry['initial_password'],
					$entry['parent_user_id'],
				));
			}
			out(_("Done"));
		}
		else
		{
			out(_("Skip, module status check failed!"));
		}


		outn(_("Build queues table..."));
		//Gather queue info
		if($this->FreePBX->Modules->checkStatus("queues"))
		{
			$entries = array();
			if((function_exists("queues_list")) && (($freePBXQueues = queues_list()) !== null))
			{
				foreach($freePBXQueues as $freePBXQueue)
				{
					$queueId 	 = $freePBXQueue[0];
					$displayName = $freePBXQueue[1] == "" ? $freePBXQueue[0] : $freePBXQueue[1];
					array_push($entries, array(
						"queue_id" 		=> $queueId,
						"display_name" 	=> $displayName,
					));
				}
			}
			foreach($entries as $entry)
			{
				$sql = sprintf("REPLACE INTO `%s` (`queue_id`, `display_name`, `add_queue`) VALUES (?,?, 1)", $this->tables['queues']);
				$sth = $this->db->prepare($sql);
				$sth->execute(array(
					$entry['queue_id'],
					$entry['display_name'],
				));
			}
			out(_("Done"));
		}
		else 
		{
			out(_("Skip, module status check failed!"));
		}
		

		outn(_("Build conference rooms table..."));
		//Gather conference room info
		if($this->FreePBX->Modules->checkStatus("conferences"))
		{
			$entries 		 = array();
			$mod_conferences = $this->FreePBX->Conferences();
			if (($freePBXConferenceRooms = $mod_conferences->listConferences()) !== null)
			{
				foreach($freePBXConferenceRooms as $freePBXConferenceRoom)
				{
					$conferenceRoomId = $freePBXConferenceRoom[0];
					$displayName 	  = $freePBXConferenceRoom[1] == "" ? $freePBXConferenceRoom[0] : $freePBXConferenceRoom[1];
					array_push($entries, array(
						"conference_room_id" => $conferenceRoomId,
						"display_name" 		 => $displayName,
					));
				}
			}
			foreach($entries as $entry)
			{
				$sql = sprintf("REPLACE INTO `%s` (`conference_room_id`, `display_name`, `add_conference_room`) VALUES (?,?,1)", $this->tables['rooms']);
				$sth = $this->db->prepare($sql);
				$sth->execute(array(
					$entry['conference_room_id'],
					$entry['display_name'],
				));
			}
			out(_("Done"));
		}
		else 
		{
			out(_("Skip, module status check failed!"));
		}
	}
	

	public function uninstall()
	{
		//Remove manager entry
		$sql = "DELETE FROM `manager` WHERE `name` = ?";
		$stmt = $this->db->prepare($sql);
		out(_("Removing manager entry...."));
		try {
			$stmt->execute(array("cxpanel"));
		} catch(\Exception $e) {
			out(_("ERROR: could not remove manager entry."));
		}

		outn(_("Remove Setting operator panel web root and enabling dev state...."));
		$set["FOPWEBROOT"] = "";
		$set["USEDEVSTATE"] = false;
		$this->cfg->set_conf_values($set, true, true);
		out(_("Done"));

	}

	public function backup() {}
	public function restore($backup) {}

	public function genConfig() {
		global $active_modules;
		$conf = null;
		$files = array(
			'/opt/isymphony3/server/jvm.args',
			'/opt/xactview/server/jvm.args'
		);
		foreach($files as $file) {
			if(file_exists($file)) {
				$filename = $file;
			}
		}

		if(!empty($filename)) {
			if(isset($active_modules['sysadmin'])) {
				$ports = \FreePBX::Sysadmin()->getPorts();
				$portnum = !empty($ports['acp']) ? $ports['acp'] : $ports['sslacp'];
			}

			$contents = file_get_contents($filename);
			$lines = parse_ini_string($contents, INI_SCANNER_RAW);
			$unset_value = "";
			if(isset($lines['-Dcom.xmlnamespace.panel.freepbx.auth.port'])) {
				$unset_value = "-Dcom.xmlnamespace.panel.freepbx.auth.port=".$lines['-Dcom.xmlnamespace.panel.freepbx.auth.port'];
			}
			$line_value = explode(PHP_EOL, $contents);
			$key = array_search($unset_value, $line_value);
			if(!empty($key)) {
				unset($line_value[$key]);
			}
			file_put_contents($filename, '');
			if(!empty($portnum)) {
				$add_value =  array('-Dcom.xmlnamespace.panel.freepbx.auth.port='.$portnum);
				$line_value = array_merge($line_value, $add_value);
			}
			$line_value = array_filter($line_value);
			$conf[$filename][] = $line_value;
		}
		return $conf;
	}
	public function writeConfig($conf) {
		$this->FreePBX->WriteConfig()->writeCustomFile($conf);
	}

	public function ajaxRequest($req, &$setting)
	{
		// ** Allow remote consultation with Postman **
		// ********************************************
		// $setting['authenticate'] = false;
		// $setting['allowremote'] = true;
		// return true;
		// ********************************************
		switch ($req)
		{
			case 'getUser':
			case 'checkAuth':
			case 'checkAuthAdmin':
				$serverSettings = $this->server_get();

				if(gethostbyname($serverSettings['api_host']) == $_SERVER['REMOTE_ADDR'])
				{
					$setting['authenticate'] = false;
					$setting['allowremote'] = true;
					return true;
				}
				else
				{
					return false;
				}
			break;
			case 'sendPassword':
			case 'initialUserPassword':
			case 'download_password_csv':
			case 'debug':
				return true;
		}
		return false;
	}

	public function ajaxHandler()
	{
		$command = $_REQUEST['command'];
		switch ($command)
		{
			case 'getUser':
				// GET admin/ajax.php?module=cxpanel&command=getUser&username=username
				/**
				 * @return
				 * {
				 *    "status": true,
				 *    "user": {
				 *        "id": "340",
				 *        "auth": "1",
				 *        "authid": "freepbx",
				 *        "username": "username",
				 *        "description": "test",
				 *        "password": "6be444686d6cb8a9b60fa788d3111af4e3e4807e",
				 *        "default_extension": "2002",
				 *        "primary_group": null,
				 *        "permissions": null,
				 *        "fname": "",
				 *        "lname": "",
				 *        "displayname": "",
				 *        "title": "",
				 *        "company": "",
				 *        "department": "",
				 *        "email": "email@domain.tld",
				 *        "cell": "",
				 *        "work": "",
				 *        "home": "",
				 *        "fax": ""
				 *    }
				 * }
				 */
				$user = $this->Userman->getUserByUsername($_GET['username']);
				return !empty($user) ? array("status" => true, "user" => $user) : array("status" => false);
			break;
			case 'checkAuth':
				// POST admin/ajax.php?module=cxpanel&command=checkAuth
				/**
				 * {
				 *	"username": "username",
				 *	"password": "thepassword"
				 * }
				 * @return
				 * {
				 *    "status": true
				 * }
				 */
				$data = json_decode(file_get_contents("php://input"),true);
				$user = $this->Userman->checkCredentials($data['username'], $data['password']);
				return !empty($user) ? array("status" => true) : array("status" => false);
			break;
            case 'checkAuthAdmin':
                $data = json_decode(file_get_contents("php://input"),true);

                //get the amp administrators
                $amp_administrators = $this->get_core_ampusers_list();
                foreach($amp_administrators as $admin) {
                    //find the admin that matches provided username, if it exists
                    if($admin['username'] == $data['username']) {
                        //verify the admin has * or cxpanel role
                        if (strpos($admin["sections"], "*") !== false || strpos($admin["sections"], "cxpanel") !== false) {
                            //admin with correct role was found, check sha1 of input password against the admin password
                            return $admin['password_sha1'] == sha1($data['password']) ? array("status" => true) : array("status" => false);
                        }
                    }
                }

                //get the userman FreePBX administrators
                $administrators = $this->get_userman_administrators();
                foreach($administrators as $admin) {
                    //find the admin that matches provided username, if it exists
                    if($admin['username'] == $data['username']) {
                        //verify the admin has * or cxpanel role
                        if(strpos($admin["sections"],"*") !== false || strpos($admin["sections"], "cxpanel") !== false) {
                            //admin with correct roles was found, check credentials and return result
                            $user = $this->Userman->checkCredentials($data['username'], $data['password']);
                            return !empty($user) ? array("status" => true) : array("status" => false);
                        }
                    }
                }
			break;
			case "download_password_csv":
				//Open the temp file
				$filepath =  '/tmp/' . uniqid() . ".csv";
				$output = fopen($filepath, 'w');
				
				//Generate password csv content
				fputcsv($output, array('user_id', 'initial_password'));
				foreach($this->user_list() as $user)
				{
					if(! empty($user['initial_password']))
					{
						if(sha1($user['initial_password']) == $user['hashed_password'])
						{
							fputcsv($output, array($user['user_id'], $user['initial_password']));
						}
					}
				}
				fclose($output);

				//Issue the downlaod to the user and cleanup
				download_file($filepath, "password.csv", "text/csv", true);
				unlink($filepath);
				exit();
			break;
			case 'sendPassword':
				$status_send = array(
					'error'   => 0,
					'success' => 0,
					'data'    => array(),
				);
				foreach($this->user_list() as $user)
				{
					$userId 	  = $user['user_id'];
					$valId 		  = (sha1($user['initial_password']) == $user['hashed_password']);
					$voiceMailBox = $this->hook_voicemail_getMailBox($userId);
					
					$s_status  = false;
					$s_message = _("Unknown");
					
					if(	$voiceMailBox == null || empty($voiceMailBox['email']))
					{
						$s_status  = false;
						$s_message = _("No email set on extension page.");
					}
					else if(! $valId)
					{
						$s_status  = false;
						$s_message = _("Initial password no longer valid.");
					}
					else if($user['add_user'] != "1")
					{
						$s_status  = false;
						$s_message = _("Extension not set to add user.");
					}
					else
					{
						if (! $this->send_password_email($userId))
						{
							$s_status  = false;
							$s_message = _("Error sending email.");
						}
						else
						{
							$s_status  = true;
							$s_message = _("Mail Sent Successfully.");
						}
					}
					$status_send['data'][] = array(
						'user_id' => $userId,
						'status'  => $s_status,
						'message' => $s_message,
					);
					$status_send[$s_status ? 'success' : 'error'] ++;
				}

				$data = array(
					"cxpanel" => $this,
					'request' => $_REQUEST,
					'data' => $status_send,
				);
				$html_code = load_view(__DIR__."/views/view.dialog.passwords.mailing.php", $data);
				return array("status" => true, "message" => _("Completed Process"), "data" => $status_send, "html" => $html_code);
			break;
			case "initialUserPassword":

				//Generate password list array
				$passwordList = array();
				foreach($this->user_list() as $user)
				{
					if(! empty($user['initial_password']))
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
				
				$data = array(
					"cxpanel" 	=> $this,
					'request' 	=> $_REQUEST,
					'list' 	 	=> $passwordList,
					'brandName' => $this->brandName,
				);
				$html_code = load_view(__DIR__."/views/view.dialog.passwords.list.php", $data);

				return array("status" => true, "list" => $passwordList, "html" => $html_code);
			break;
			case "debug":
				$debug = array(
					'main_log' 		  => array(
						'file' => $this->getPath("log"),
						'read' => htmlspecialchars(cxpanel_read_file($this->getPath("log"))),
					),
					'modify_log' 	  => array(
						'file' => $this->getPath("log_modify"),
						'read' => htmlspecialchars(cxpanel_read_file($this->getPath("log_modify"))),
					),
					'server' 		  => $this->server_get(),
					'queues' 		  => $this->queue_list(),
					'rooms' 		  => $this->conference_room_list(),
					'managed_items'   => $this->managed_item_get_all(),
					'voicemail_agent' => $this->voicemail_agent_get(),
					'recording_agent' => $this->recording_agent_get(),
					'users' 		  => $this->user_list(),
					'email' 		  => $this->email_get(),
					'phone_numbers'   => $this->phone_number_list_all(),
				);

				$data = array(
					"cxpanel" => $this,
					'request' => $_REQUEST,
					'debug'   => $debug,
				);
				$html_code = load_view(__DIR__."/views/view.dialog.debug.php", $data);
				return array("status" => true, "debug" => $debug, "html" => $html_code);
			break;
		}
		return false;
	}

	public function doConfigPageInit($page)
	{
		//Check for a server settings update action
		if(! empty($_REQUEST["cxpanel_settings"]))
		{
			$serverInformation = $this->server_get();

			//If we are changing synchronization methods, mark all passwords as dirty. 
			$syncWithUserMan = isset($_REQUEST['cxpanel_sync_with_userman']) ? $_REQUEST['cxpanel_sync_with_userman'] : '0';
			if($serverInformation['sync_with_userman'] !== $syncWithUserMan)
			{
				$this->mark_all_user_passwords_dirty(true);
			}

			$srv_name     = trim($_REQUEST["cxpanel_name"]);
			$srv_ast_host = trim($_REQUEST["cxpanel_asterisk_host"]);
			$srv_cli_host = trim($_REQUEST["cxpanel_client_host"]);
			$srv_cli_port = trim($_REQUEST["cxpanel_client_port"]);
			$srv_cli_ssl  = trim($_REQUEST["cxpanel_client_use_ssl"]);
			$srv_api_host = trim($_REQUEST["cxpanel_api_host"]);
			$srv_api_port = trim($_REQUEST["cxpanel_api_port"]);
			$srv_api_user = trim($_REQUEST["cxpanel_api_username"]);
			$srv_api_pass = trim($_REQUEST["cxpanel_api_password"]);
			$srv_api_ssl  = ($_REQUEST["cxpanel_api_use_ssl"] == "1");
			$srv_sync	  = ($_REQUEST["cxpanel_sync_with_userman"] == "1");
			$srv_clean 	  = ($_REQUEST["cxpanel_clean_unknown_items"] == "1");
			$this->server_update($srv_name, $srv_ast_host, $srv_cli_host, $srv_cli_port, $srv_cli_ssl, $srv_api_host, $srv_api_port, $srv_api_user, $srv_api_pass, $srv_api_ssl, $srv_sync, $srv_clean);

			$voicemail_id 	= trim($_REQUEST["cxpanel_voicemail_agent_identifier"]);
			$voicemail_dir  = trim($_REQUEST["cxpanel_voicemail_agent_directory"]);
			$voicemail_host = trim($_REQUEST["cxpanel_voicemail_agent_resource_host"]);
			$voicemail_ext 	= trim($_REQUEST["cxpanel_voicemail_agent_resource_extension"]);
			$this->voicemail_agent_update($voicemail_id, $voicemail_dir, $voicemail_host, $voicemail_ext);
			
			$rec_id   = trim($_REQUEST["cxpanel_recording_agent_identifier"]);
			$rec_dir  = trim($_REQUEST["cxpanel_recording_agent_directory"]);
			$rec_host = trim($_REQUEST["cxpanel_recording_agent_resource_host"]);
			$rec_ext  = trim($_REQUEST["cxpanel_recording_agent_resource_extension"]);
			$rec_mask = trim($_REQUEST["cxpanel_recording_agent_filename_mask"]);
			$this->recording_agent_update($rec_id, $rec_dir, $rec_host, $rec_ext, $rec_mask);
			
			$email_from 	= trim($_REQUEST["cxpanel_email_from"]);
			$email_subject 	= $_REQUEST["cxpanel_email_subject"];
			$email_body 	= $_REQUEST["cxpanel_email_body"];
			$this->email_update($email_from, $email_subject, $email_body);
			
			//Flag FreePBX for reload
			needreload();
		}
	}

	public function showPage($page, $params = array())
	{
		$data = array(
			"cxpanel" => $this,
			'request' => $_REQUEST,
			'page' 	  => $page,
		);
		$data = array_merge($data, $params);
		switch ($page) 
		{
			case "cxpanel":
				$data['serverInformation'] = $this->server_get();
				$data_return = load_view(__DIR__."/views/page.cxpanel.php", $data);

			break;

			case "cxpanel.menu":
				$data['redirectUrl'] = str_replace($this->cfg->get('AMPWEBROOT'), '', $this->cfg->get('FOPWEBROOT'));
				$data['url_check'] 	 = $this->getClientURL();
				$data['infoStatus']	 = $this->checkOnline($data['url_check'], true);
				$data['checkStatus'] = $data['infoStatus']['status'];

				$data_return = load_view(__DIR__."/views/page.cxpanel.menu.php", $data);
				break;

			default:
				$data_return = sprintf(_("Page Not Found (%s)!!!!"), $page);
		}
		return $data_return;
	}

	public static function myGuiHooks() {
		return array("INTERCEPT" => "modules/core/page.ampusers.php");
	}

	public function doGuiIntercept($filename, &$output) {
		if ($filename == "modules/core/page.ampusers.php" && !empty($_POST['action']) && ($_POST['action'] == "editampuser" || $_POST['action'] == "addampuser")) {
			needreload();
		}
	}

	public function usermanShowPage() {
		/**
		 * Add the cxpanel tab to the userman page if the following contitions are met:
		 * - The FreePBX verison is >= 13. The section will be added in older versions via cxpanel_hook_userman() in functions.inc.php.
		 * - Sync with user managment is enabled.
		 * - We are adding or editing a user.
		 */
		$serverSettings = $this->server_get();
		if(version_compare_freepbx(getVersion(), '13.0', '>=') && $serverSettings['sync_with_userman'] == '1') {
			if(isset($_REQUEST['action'])) {
				switch($_REQUEST['action']) {
					case 'showgroup':
						$mode = "group";
						$addUser = $this->Userman->getModuleSettingByGID($_REQUEST['group'], 'cxpanel', 'add');
					break;
					case 'showuser':
						$mode = "user";
						$addUser = $this->Userman->getModuleSettingByID($_REQUEST['user'], 'cxpanel', 'add',true);
					break;
					case 'addgroup':
						$mode = "group";
						$addUser = true;
					break;
					case 'adduser':
						$mode = "user";
						$addUser = null;
					break;
				}
			}

			return array(
					array(
						'title' => $this->brandName,
						'rawname' => 'cxpanel',
						'content' => load_view(dirname(__FILE__).'/views/userman_hook.php', array(
							'cxpanelBrandName' => $this->brandName,
							'addUser' => $addUser,
							'mode' => $mode
						)),
					)
			);
		}

		return array();
	}

	public function usermanAddGroup($id, $display, $data) {
		$this->usermanUpdateGroup($id,$display,$data);
	}

	public function usermanUpdateGroup($id,$display,$data) {
		$this->userman = $this->Userman;
		if(isset($_REQUEST['cxpanel_add_user'])) {
			if($_POST['cxpanel_add_user'] == '1') {
				//Set the add flag on the user
				$this->userman->setModuleSettingByGID($id, 'cxpanel', 'add', 1);
			} elseif($_POST['cxpanel_add_user'] == '0') {
				$this->userman->setModuleSettingByGID($id, 'cxpanel', 'add', 0);
			} else {
				$this->userman->setModuleSettingByGID($id, 'cxpanel', 'add', null);
			}
		}

		if($display == "userman") {
			//Flag FreePBX for reload
			needreload();
		}
	}

	/**
	 * Called when a FreePBX user is added to the system.
	 * @param Int $id The User Manager ID
	 * @param String $display The page in FreePBX that initiated this function
	 * @param Array $data an array of all relevant data returned from User Manager
	 */
	public function usermanAddUser($id, $display, $data) {
		$this->userman = $this->Userman;
		if(isset($_REQUEST['cxpanel_add_user'])) {
			if($_POST['cxpanel_add_user'] == '1') {
				//Set the add flag on the user
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', 1);
			} elseif($_POST['cxpanel_add_user'] == '0') {
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', 0);
			} else {
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', null);
			}
			//Mark the user's password as dirty
			$this->userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', '1');
		}

		if($display == "userman") {
			//Flag FreePBX for reload
			needreload();
		}
	}
	/**
	 * Hook functionality from userman when a user is updated
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanUpdateUser($id, $display, $data) {
		if(!function_exists('cxpanel_get_config')) {
			include(__DIR__.'/functions.inc.php');
		}

		$this->userman = $this->Userman;
		if(isset($_REQUEST['cxpanel_add_user'])) {
			if($_POST['cxpanel_add_user'] == '1') {
				//Set the add flag on the user
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', 1);
			} elseif($_POST['cxpanel_add_user'] == '0') {
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', 0);
			} else {
				$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', null);
			}
			//Mark the user's password as dirty
			$this->userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', '1');
		}

		if($this->FreePBX->Modules->moduleHasMethod("userman", "getCombinedModuleSettingByID")) {
			$add = $this->userman->getCombinedModuleSettingByID($id, 'cxpanel', 'add');
		} else {
			$add = $this->userman->getModuleSettingByID($id, 'cxpanel', 'add');
		}


		//If a new password was set mark the user's password as dirty
		$passwordDirty = !empty($data['password']) ? '1' : '0';
		$this->userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', $passwordDirty);

		$newUsername = ($data['prevUsername'] != $data['username']) ? '1' : '0';
		//Flag FreePBX for reload
		if($display == "userman") {
			needreload();
		}

		/*
		* If the following conditions are met, attempt to apply the password change immediately.
		*
		*  - sync_with_userman is enabled
		*  - The user is flagged to be added to the server
		*  - The password has changed
		*
		* If the server is not up, or the user does not yet exist, do nothing.
		*/

		if($add == '1' && ($passwordDirty == '1' || $newUsername == '1'))
		{
			$serverInformation = $this->server_get();

			if($serverInformation['sync_with_userman'] == '1') {

				try {

					//Set up the REST connection
					$pest = $this->getApiREST();

					//Lookup the core server id based on the slug specified in the database
					$coreServer = $pest->get("server/coreServers/getBySlug/" . $serverInformation['name']);
					$coreServerId = $coreServer->id;

					/*
					* Locate the user on the server. If found update the password.
					*
					* We cannot grab the exact user directly, as we do not know the
					* id of the user on the server, and not all versions of the server have
					* the getByUsername REST resource.
					*
					* prevUsername is always set, regardless if there was a username change or not
					*/
					$serverUsers = $pest->get("core/" . $coreServerId . "/users");
					foreach($serverUsers as $serverUser) {
						if((string)$serverUser->username == $data['prevUsername']) {

							/*
							* Send an event to update the password on the user.
							*
							* We only have the plain text version of the password here
							* so we do not call the "noHash" REST resource. The password
							* will be hashed by the server during the update.
							*/
							$serverUser->username = $data['username'];
							if(!empty($data['password'])) {
								$serverUser->password = $data['password'];
							}
							$pest->put("core/" . $coreServerId . "/users/" . $serverUser->id, $serverUser);

							//The password is no longer dirty
							$this->userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', '0');

							break;
						}
					}

				} catch (\Exception $e) {
					//The server may be down, or the configured core server is not available
				}
			}
		}
	}

	/**
	 * Hook functionality for sending an email from userman
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanSendEmail($id, $display, $data) {
		if(!function_exists('cxpanel_get_config')) {
			include(__DIR__.'/functions.inc.php');
		}		

		$isUser = $this->Userman->getModuleSettingByID($id, 'cxpanel', 'add');

		if(!$isUser) {
			return array();
		}
		
		$final = array();
		$final[] = "\t".sprintf(_('%s Login: %s'), $this->brandName, $this->getClientURL());
		return $final;
	}

	/**
	 * Initialize the logger object
	 * @param string $log_type Type of log that we want to initialize (log|log_modify).
	 * @return cxpanel_logger Object logger
	 */
	public function logInit($log_type = "")
	{
		$path_dir = $this->getPath("cxpanel_spool");
		if (! in_array($log_type, array("log", "log_modify")))
		{
			$log_type = "log";
		}
		$path_log = $this->getPath($log_type);
		if(! file_exists($path_dir))
		{
			mkdir($path_dir, 0755);
		}
		if(!file_exists($path_log))
		{
			touch($path_log);
		}
		
		//Set the group/owner of the logger if necessary
		$asterisk_user  = posix_getpwnam($this->cfg->get_conf_setting('AMPASTERISKUSER'));
		$asterisk_group = posix_getgrnam($this->cfg->get_conf_setting('AMPASTERISKGROUP'));

		if (fileowner($path_log) != $asterisk_user['uid'])
		{
			chown($path_log, $asterisk_user['name']);
		}
		if (filegroup($path_log) != $asterisk_group['gid'])
		{
			chgrp($path_log, $asterisk_group['name']);
		}

		$log 		= null;
		$class_log 	= '\\'.__NAMESPACE__.'\\Cxpanel\\Log\\cxpanel_logger';
		if (! class_exists($class_log))
		{
			include_once(dirname(__FILE__)."/lib/logger.class.php");
		}
		if (class_exists($class_log))
		{
			// Add "Try" to handle the exception when installing the module, since just after 
			// downloading the module a "fwconsole chown" is executed, and it calls the "chownFreepbx" 
			// method, generating an exception since the "cxpanel_logger" class does not exist.
			try
			{
				$log = new $class_log($path_log);
			}
			catch (\Exception $e)
			{
				dbug(_("Exception Create Log: %s"), $e->getMessage());
			}
		}
		return $log;
	}

	/**
	 * Get mailbox information via voicemail module.
	 * @param string $find The mailbox number
	 * @return array|null Returns mailbox info if module voicemail is installed or null if it is not installed or mailbox not exists.
	 */
	public function hook_voicemail_getMailBox($find)
	{
		$mailbox = null;
		if(\FreePBX::Modules()->checkStatus('voicemail'))
		{
			$mod_voicemail = \FreePBX::Voicemail();
			$mailbox = $mod_voicemail->getMailbox($find);
		}
		return $mailbox;
	}

	/**
	 * Main module function.
	 * Gets called by the framework
	 * @param String $engine
	 */
	public function get_config($engine)
	{
		global $ext;

		$runningTimeStart = microtime(true);

		//Open the logger
		$this->openLog();
		$this->debug(_("Starting CXPanel module"));

		//Create the manager entry if it does not exist
		$this->create_manager();

		//Get the agent login context
		$agentLoginContext = $this->get_agent_login_context();
		$this->debug(sprintf(_("Agent login context: %s"), $agentLoginContext));

		//Get the agent interface type
		$agentInterfaceType = $this->get_agent_interface_type();
		$this->debug(sprintf(_("Agent interface type: %s"), $agentInterfaceType));

		//Query the parking timeout
		$parkingTimeout = $this->get_parking_timeout();
		$this->debug(sprintf(_("Parking lot timeout: %s"), $parkingTimeout));

		//Generate the custom contexts
		$this->add_contexts("c-x-3-operator-panel", "XMLNamespace", $parkingTimeout);

		//Execute modify script and continue on without waiting for return
		$this->debug(_("Executing modify.php"));
		// exec("php " . $this->getPath("modify") . " > /dev/null 2>/dev/null &");

		exec("php " . $this->getPath("modify") , $output, $retval);
		if (! empty($output))
		{
			dbug($output);
		}
		
		$runningTimeStop = microtime(true);
		$this->debug(sprintf(_("Total Running Time: %s."), ($runningTimeStop - $runningTimeStart)));

		//Close the logger
		$this->closeLog();
	}
		
	/**
	 * Hook that provides the panel settings UI section on the user managemnet page.
	 * @return string Code HTML
	 */
	public function hook_userman()
	{
		global $currentcomponent;
		$html = '';

		//Do not show the UI addition if sync_with_userman is disabled
		$serverSettings = $this->server_get();
		if($serverSettings['sync_with_userman'] == '1')
		{
			//Query page state
			$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
			$user 	= isset($_REQUEST["user"]) 	 ? $_REQUEST["user"] : null;

			//Only show the gui elements if we are on the add or edit page for the user
			if($action == 'showuser' || $action == 'adduser')
			{
				//If the user is specified lookup the information for the UI
				if($user != null)
				{
					$addUser = $this->Userman->getModuleSettingByID($user, 'cxpanel', 'add');
					$addUser = $addUser === false ? '1' : $addUser;
				}
				else
				{
					$addUser = '1';
				}

				//Define the section
				$section = sprintf(_("%s Settings"), $this->brandName);

				//Create the add GUI element
				$yesNoValueArray = array(
					array(
						"text" => "yes",
						"value" => "1"
					),
					array(
						"text" => "no",
						"value" => "0"
					)
				);
				$addToPanel = new \cxpanel_radio(
					"cxpanel_add_user",
					$yesNoValueArray,
					$addUser,
					sprintf(_("Add to %s"), $this->brandName),
					sprintf(_("Makes this user available in %s"), $this->brandName)
				);

				//Create contents
				$html = 	'<table>' .
								'<tr class="guielToggle" data-toggle_class="cxpanel">' .
									'<td colspan="2" ><h4><span class="guielToggleBut">-  </span>' .$section . '</h4><hr></td>' .
								'</tr>'.
								'<tr>' .
									'<td colspan="2">' .
										'<div class="indent-div">' .
											'<table>' .
												'<tbody>' .
													'<tr class="cxpanel">' .
														'<td><table>' . $addToPanel->generatehtml() . '</table></td>' .
													'</tr>' .
												'</tbody>' .
											'</table>' .
										'</div>' .
									'</td>' .
								'</tr>' .
							'</table>';
			}
		}
		return $html;
	}

	/**
	 * Function used to hook the extension/user page in FreePBX
	 * @param String $pagename the name of the page being loaded
	 */
	public function configpageinit($pagename)
	{
		global $currentcomponent;

		//Query page state
		$action 		= isset($_REQUEST["action"]) 		? $_REQUEST["action"] : null;
		$extdisplay		= isset($_REQUEST["extdisplay"]) 	? $_REQUEST["extdisplay"] : null;
		$extension 		= isset($_REQUEST["extension"]) 	? $_REQUEST["extension"] : null;
		$tech_hardware 	= isset($_REQUEST["tech_hardware"]) ? $_REQUEST["tech_hardware"] : null;

		//Based on the page state determine if the display or process functions should be added
		if (($pagename != "users") && ($pagename != "extensions"))
		{
			return;
		}
		else if ($tech_hardware != null || $pagename == "users")
		{
			$this->extension_applyhooks();
			$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
		}
		else if ($action == "add")
		{
			$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
		}
		else if ($action == "edit")
		{
			$this->extension_applyhooks();
			$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
		}
		else if ($extdisplay != '')
		{
			$this->extension_applyhooks();
			$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
		}
	}

	/**
	 * Applies hooks to the extension page
	 */
	public function extension_applyhooks()
	{
		global $currentcomponent;
		$currentcomponent->addguifunc("cxpanel_extension_configpageload");
	}

	/**
	 * Contributes the panel gui elements to the extension page
	 */
	public function extension_configpageload()
	{
		global $currentcomponent;

		//Query page state
		$action 	= isset($_REQUEST["action"]) 	 ? $_REQUEST["action"] : null;
		$display 	= isset($_REQUEST["display"]) 	 ? $_REQUEST["display"] : null;
		$extension 	= isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;

		//Attempt to query element if not found set defaults
		if(($extension !== null) && (($cxpanelUser = $this->user_get($extension)) !== null))
		{
			$addExtension 	= $cxpanelUser["add_extension"];
			$full 			= $cxpanelUser["full"];
			$addUser 		= $cxpanelUser["add_user"];
			$autoAnswer 	= $cxpanelUser["auto_answer"];
			$password 		= $this->defaultVal['UserPasswordMask'];

			//Build list of bound extensions
			$extensionListValues = $this->user_extension_list($extension);
			$boundExtensionList  = array();
			foreach($extensionListValues as $extensionListValue)
			{
				if($extensionListValue['user_id'] == $extension)
				{
					array_push($boundExtensionList, "self");
				}
				else
				{
					array_push($boundExtensionList, $extensionListValue['user_id']);
				}
			}

			//Build list of phone numbers for the user
			$phoneNumberValues = $this->phone_number_list($extension);
			$phoneNumberList   = array();
			foreach($phoneNumberValues as $phoneNumber)
			{
				array_push($phoneNumberList, $phoneNumber['phone_number'] . "@#" . $phoneNumber['type']);
			}

			//If the user has an inital password set display the inital password and if it is still valid or not
			if($cxpanelUser["initial_password"] != "")
			{
				$valid = sha1($cxpanelUser['initial_password']) == $cxpanelUser['hashed_password'];
				if($valid)
				{
					$initalPasswordDisplay = sprintf(_("The inital password for this user is set to <b>%s</b>"), $cxpanelUser["initial_password"]);
				}
				else
				{
					$initalPasswordDisplay = _("The inital password for this user was never created or has been changed")."<br/>"._("If you do not know the password for this user you can change it in the User Password field above")."<br/>";
				}
			}
		}
		else
		{
			$addExtension 			= "1";
			$addUser 				= "0";
			$full 					= "0";
			$autoAnswer 			= "0";
			$password 				= "";
			$initalPasswordDisplay 	= "";
			$boundExtensionList 	= array("self");
			$phoneNumberList 		= array();
		}

		//Create GIU elements if not on delete page
		if ($action != "del")
		{
			$section = sprintf(_("%s Settings"),  $this->brandName);
			$yesNoValueArray = array(
				array(
					"text" => "yes", 
					"value" => "1"
				),
				array(
					"text" => "no",
					"value" => "0"
				),
			);
			$yesNoAddUserValueArray = array(
				array(
					"text" => "yes",
					"value" => "1",
					"onclick" => "document.getElementById('cxpanel_extensions').disabled = false; document.getElementById('cxpanel_password').disabled = false;"
				),
				array(
					"text" => "no",
					"value" => "0",
					"onclick" => "document.getElementById('cxpanel_extensions').disabled = true; document.getElementById('cxpanel_password').disabled = true;"
				),
			);

			//Build the extension properties
			$currentcomponent->addguielem($section,	new \cxpanel_radio(
				"cxpanel_add_extension", 
				$yesNoValueArray, 
				$addExtension, 
				sprintf(_("Add to %s"), $this->brandName),
				sprintf(_("Makes this extension available in %s"), $this->brandName)
			), 5, null, "other", "advanced");
			$currentcomponent->addguielem($section,	new \cxpanel_radio(
				"cxpanel_auto_answer",
				$yesNoValueArray,
				$autoAnswer,
				_("Auto Answer"),
				sprintf(_("Makes this extension automatically answer the initial call received from the system when performing an origination within %s. Only works with Aastra, Grandstream, Linksys, Polycom, and Snom phones."), $this->brandName)
			), 5, null, "other", "advanced");

			//If sync_with_userman is not enabled show the user settings
			$serverSettings = $this->server_get();
			if($serverSettings['sync_with_userman'] != '1' || !function_exists('setup_userman'))
			{
				//Build the user properties
				$currentcomponent->addguielem($section,	new \cxpanel_radio(
					"cxpanel_add_user",
					$yesNoAddUserValueArray,
					$addUser,
					_("Create User"),
					sprintf(_("Creates an %s user login which is associated with this extension."), $this->brandName)
				), 5, null, "other", "advanced");
				$currentcomponent->addguielem($section,	new \cxpanel_radio(
					"cxpanel_full_user",
					$yesNoValueArray,
					$full,
					_("Full User"),
					sprintf(_("Makes this extension a full user in %s. Full users have access to all the fuctionality in %s that the current license allows. The amount of full users allowed in %s is restricted via the license. If you mark this user as a full user and there are no more user licenes available the user will remain a lite user."), $this->brandName, $this->brandName, $this->brandName)
				), 5, null, "other", "advanced");
				$currentcomponent->addguielem($section,	new \cxpanel_radio(
					"cxpanel_email_new_pass",
					$yesNoValueArray,
					"0",
					_("Email Password"),
					_("When checked the new specified password will be sent to the email cofigured in the voicemail settings. No email will be sent if no email address is specified or the password is not changing.")
				), 5, null, "other", "advanced");
				$currentcomponent->addguielem($section, new \gui_password(
					"cxpanel_password", 
					$password, 
					_("User Password"), 
					sprintf(_("Specifies the password to be used for the %s User"), $this->brandName),
					"",
					"",
					true,
					"100",
					!$addUser
				), 5, null, "other", "advanced");

				//Build extension select
				$extensionListValues = $this->user_list();
				$sortedExtensionList = array();
				foreach($extensionListValues as $extensionListValue)
				{
					if($extensionListValue['user_id'] != $extension)
					{
						$sortedExtensionList[$extensionListValue["user_id"]] = array("text" => $extensionListValue["user_id"] . " (" . $extensionListValue["display_name"] . ")", "value" => $extensionListValue["user_id"]);
					}
				}
				ksort($sortedExtensionList, SORT_STRING);
				array_unshift($sortedExtensionList, array("text" => "Self", "value" => "self"));

				$extensionListToolTip = _('Specifies which extensions will be bound to the $cxpanelBrandName user created for this extension. "Self" refers to this extension');
				$currentcomponent->addguielem($section, new \cxpanel_multi_selectbox(
					"cxpanel_extensions",
					$sortedExtensionList,
					"10",
					$boundExtensionList,
					_("User Extensions"),
					$extensionListToolTip,
					false,
					"",
					!$addUser
				), 5, null, "other", "advanced");

				//TODO: Disabled Class "cxpanel_phone_number_list" not exist
				//Add list of phone numbers for the user
				// $currentcomponent->addguielem($section, new cxpanel_phone_number_list(
				// 	"cxpanel_phone_numbers", 
				// 	$phoneNumberList,
				// 	_("Alt. Phone Numbers"),
				// 	sprintf(_("Manages alternative phone numbers for this %s user."), $this->brandName)
				// ), 5, null, "other", "advanced");

				//If the user has an inital password set display the inital password and if it is still valid or not
				if($initalPasswordDisplay != "")
				{
					$currentcomponent->addguielem($section, new \gui_label("cxpanel_inital_password_display", $initalPasswordDisplay));

					//Check if there is a valid email address and password
					$voiceMailBox = $this->hook_voicemail_getMailBox($extension);
					$validPass 	  = (sha1($cxpanelUser['initial_password']) == $cxpanelUser['hashed_password']);
					$hasEmail 	  = $voiceMailBox != null && isset($voiceMailBox['email']) && $voiceMailBox['email'] != "";

					//If the password is still valid create a link that allows the password to be emailed
					if($validPass && $hasEmail)
					{
						$linkUrl = cxpanel_get_current_url() . "&cxpanel_email_pass=1";
						$currentcomponent->addguielem($section, new \gui_link("cxpanel_email_pass_link", _("Email Inital Password"), $linkUrl));
					}
				}

				//Create validation javascript that is called when the form is submited
				$js = " if($('input[name=cxpanel_add_user]:checked').val() == '1' &&
							document.getElementById('cxpanel_password').value == '') {
							warnInvalid(document.getElementById('cxpanel_password'), '".sprintf(_('Please specify a password for the %s user or uncheck "Create User" under "%s User Settings"'),$this->brandName, $this->brandName)."');
							return false;
						}";
				$currentcomponent->addjsfunc('onsubmit()', $js);
			}
		}
	}

	/**
	 * Handles additions removals and updates of extensions.
	 */
	public function extension_configprocess()
	{
		//Check if the action was aborted
		if(isset($GLOBALS['abort']) && $GLOBALS['abort']) {
			return;
		}

		//Query page state
		$action 	= isset($_REQUEST["action"]) 	 ? $_REQUEST["action"] : null;
		$ext 		= isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
		$extn 		= isset($_REQUEST["extension"])  ? $_REQUEST["extension"]: null;
		$name 		= isset($_REQUEST["name"]) 		 ? $_REQUEST["name"] : null;
		$extension 	= ($ext == "") 					 ? $extn : $ext;

		$UserPasswordMask_Default = $this->defaultVal['UserPasswordMask'];

		//Determine peer
		if(isset($_REQUEST["devinfo_dial"]) && ($_REQUEST["devinfo_dial"] != ""))
		{
			$peer = $_REQUEST["devinfo_dial"];
		}
		else if (isset($_REQUEST["tech"]))
		{
			$peer = strtoupper($_REQUEST["tech"]) . "/" . $extension;
		}
		else
		{
			$peer = "SIP/$extension";
		}

		$addExtension 	 = $_REQUEST["cxpanel_add_extension"] == "1";
		$autoAnswer 	 = $_REQUEST["cxpanel_auto_answer"] == "1";
		$addUser 		 = $_REQUEST["cxpanel_add_user"] == "1";
		$full 			 = $_REQUEST["cxpanel_full_user"] == "1";
		$emailPassword 	 = $_REQUEST["cxpanel_email_new_pass"] == "1";
		$password 		 = isset($_REQUEST['cxpanel_password']) 			? trim($_REQUEST["cxpanel_password"]) : $UserPasswordMask_Default;
		$extensionList 	 = isset($_REQUEST['cxpanel_extensions']) 			? $_REQUEST['cxpanel_extensions'] : array();
		$phoneNumberList = isset($_REQUEST['cxpanel_phone_numbers-values']) ? $_REQUEST['cxpanel_phone_numbers-values'] : array();

		//Modify DB
		if(($extension !== null) && ($extension != "") && ($action !== null))
		{
			//Check if this extension needs to be deleted, updated, or added
			if($action == "del")
			{
				//Clean up all extension relationships
				$this->sync_user_extensions($extension, array());

				//Delete the user
				$this->user_del($extension);

			}
			else if(($action == "add") || ($action == "edit") && ($name !== null))
			{
				//Check if this is an addition or edit
				$addition = $this->user_get($extension) === null;

				/*
				* If the cxpanel_full_user setting is not set we have hidded the user settings
				* due to the fact that sync_with_userman is enabled. If so handle the creation and
				* editing of the user differently.
				*/
				if(!isset($_REQUEST['cxpanel_full_user']))
				{
					//Add or update user
					if($addition)
					{
						//Check if a user is being created for this extension. If so get the password set for the extension's user else create an initial password.
						$password = $this->generate_password(10);
						if($_REQUEST['userman|assign'] == 'add' && !empty($_REQUEST['userman|password']))
						{
							$password = $_REQUEST['userman|password'];
						}

						//Add the user
						$this->user_add_with_initial_password($extension, $addExtension, true, $password, $autoAnswer, $peer, $name, true, $extension);

						//Mark the user's password as dirty
						$this->mark_user_password_dirty($extension, true);
					}
					else
					{
						//Edit just the extension settings on the user
						$this->extension_update($extension, $addExtension, $autoAnswer, $peer, $name);
					}
				}
				else
				{
					//Add or update user
					if($addition)
					{
						$this->user_add($extension, $addExtension, $addUser, $password, $autoAnswer, $peer, $name, $full);
					}
					else
					{
						$this->user_update($extension, $addExtension, $addUser, $password, $autoAnswer, $peer, $name, $full);
					}

					//Sync extension list
					$this->sync_user_extensions($extension, $extensionList);

					//Sync phone number list
					$this->phone_number_del($extension);
					foreach($phoneNumberList as $phoneNumber)
					{
						$phoneNumberParts = explode('@#', $phoneNumber);
						$this->phone_number_add($extension, $phoneNumberParts[0], $phoneNumberParts[1]);
					}

					//Check if the password needs to be sent
					if(	$password != $UserPasswordMask_Default && $emailPassword && isset($_REQUEST['email']) && $_REQUEST['email'] != "")
					{
						$this->send_password_email($extension, $password, $_REQUEST['email']);
					}

					//Check if the password needs to be marked as dirty
					if($password != $UserPasswordMask_Default)
					{
						$this->mark_user_password_dirty($extension, true);
					}
				}
			}
		}
	}

	/**
	 * Contributes the panel gui elements to the queue page
	 * @param String $viewing_itemid the id of the item being viewed
	 * @param String $target_menuid the menu id of the page being loaded
	 * @return String Code HTML
	 */
	public function hook_queues($viewing_itemid, $target_menuid)
	{
		//Query page state
		$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
		$display = "";

		//Only hook queues page
		if(($target_menuid == "queues") && ($action != "delete"))
		{
			//Query queue info
			if(($viewing_itemid != null) && ($queue = $this->queue_get($viewing_itemid)))
			{
				$checked = ($queue["add_queue"] == "1") ? "checked" : "";
			}
			else
			{
				$checked = "checked";
			}
			//Build display
			$display = "	<tr><td colspan=\"2\"><h5>" . $this->brandName . "<hr></h5></td></tr>
							<tr>
								<td><a href=\"#\" class=\"info\">" . sprintf(_("Add to %s"), $this->brandName) . "<span>" . sprintf(_("Makes this queue available in %s"), $this->brandName) . "</span></a></td>
								<td><input type=\"checkbox\" name=\"cxpanel_add_queue\" id=\"cxpanel_add_queue\" value=\"on\" $checked/></td>
							</tr>";
		}
		return $display;
	}

	/**
	 * Handles additions removals and updates of queues.
	 */
	public function hookProcess_queues($viewing_itemid, $request)
	{
		//Query page state
		$queue 	 = isset($request["extdisplay"]) ? $request["extdisplay"] : null;
		$account = isset($request["account"]) ? $request["account"] : null;
		$action  = isset($request["action"]) ? $request["action"] : null;
		$name 	 = isset($request["name"]) ? $request["name"] : null;
		$queue 	 = ($queue == null) ? $account : $queue;

		//Query add option
		$addQueue = isset($request["cxpanel_add_queue"]);

		//Update DB
		if(($queue != null) && ($queue != "") && ($action != null))
		{
			//Check if this queue needs to be deleted, updated, or added
			if($action == "delete")
			{
				$this->queue_del($queue);
			}
			else if(($action == "add") || ($action == "edit") && ($name !== null))
			{
				if($this->queue_get($queue) === null)
				{
					$this->queue_add($queue, $addQueue, $name);
				}
				else
				{
					$this->queue_update($queue, $addQueue, $name);
				}

				$this->queue_eventwhencalled_modify($addQueue);
				$this->queue_eventmemberstatus_modify($addQueue);
			}
		}
	}

	/**
	 * Contributes the panel gui elements to the conference room page
	 * @param String $viewing_itemid the id of the item being viewed
	 * @param String $target_menuid the menu id of the page being loaded
	 * @return String Code HTML
	 */
	public function hook_conferences($viewing_itemid, $target_menuid)
	{
		//Query page state
		$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
		$display = "";

		//Only hook conferences page
		if(($target_menuid == "conferences") && ($action != "delete"))
		{
			//Query conference info
			if(($viewing_itemid != null) && ($conferenceRoom = $this->conference_room_get($viewing_itemid)))
			{
				$checked = ($conferenceRoom["add_conference_room"] == "1") ? "checked" : "";
			}
			else
			{
				$checked = "checked";
			}

			//Build display
			$display = "	<tr><td colspan=\"2\"><h5>" . $this->brandName . "<hr></h5></td></tr>
							<tr>
								<td><a href=\"#\" class=\"info\">" . sprintf(_("Add to %s"), $this->brandName) . "<span>" . sprintf(_("Makes this conference room available in %s"), $this->brandName) . "</span></a></td>
								<td><input type=\"checkbox\" name=\"cxpanel_add_conference_room\" id=\"cxpanel_add_conference_room\" value=\"on\" $checked/></td>
							</tr>";
		}
		return $display;
	}

	/**
	 * Handles additions removals and updates of queues.
	 */
	public function hookProcess_conferences($viewing_itemid, $request)
	{
		//Query page state
		$conferenceRoom = isset($request["extdisplay"]) ? $request["extdisplay"] : null;
		$account 		= isset($request["account"]) ? $request["account"] : null;
		$action 		= isset($request["action"]) ? $request["action"] : null;
		$name 			= isset($request["name"]) ? $request["name"] : null;
		$conferenceRoom = ($conferenceRoom == null) ? $account : $conferenceRoom;

		//Query add option
		$addConferenceRoom = isset($request["cxpanel_add_conference_room"]);

		//Update DB
		if(($conferenceRoom != null) && ($conferenceRoom != "") && ($action != null))
		{
			//Check if this conference room needs to be deleted, updated, or added
			if($action == "delete")
			{
				$this->conference_room_del($conferenceRoom);
			}
			else if(($action == "add") || ($action == "edit") && ($name !== null))
			{
				if($this->conference_room_get($conferenceRoom) === null)
				{
					$this->conference_room_add($conferenceRoom, $addConferenceRoom, $name);
				}
				else
				{
					$this->conference_room_update($conferenceRoom, $addConferenceRoom, $name);
				}
			}
		}
	}

	/**
	 * API function to update the server information
	 * @param String $name the slug of the core server to edit
	 * @param String $asteriskHost ip or host name used by the panel to connect to the AMI
	 * @param String $clientHost ip or host name of the panel client
	 * @param Integer $clientPort web port of the panel client
	 * @param Boolean $clientUseSSL if true https will be used to construct client urls
	 * @param String $apiHost ip or host name of the panel server REST API
	 * @param Integer $apiPort web port of the panel server REST API
	 * @param String $apiUserName panel API username used for API authentication
	 * @param String $apiPassword panel API password used for API authentication
	 * @param Boolean $apiUseSSL if true https will be used for communication with the REST API
	 * @param Boolean $syncWithUserman if true the User Management module will control the users that are created in the panel
	 * @param Boolean $cleanUnknownItems if true the module will remove all items from the server that are not configured in FreePBX. If false only items that the module created will be removed if they are not configured in FreePBX.
	 */
	public function server_update($name, $asteriskHost, $clientHost, $clientPort, $clientUseSSL, $apiHost, $apiPort, $apiUserName, $apiPassword, $apiUseSSL, $syncWithUserman, $cleanUnknownItems)
	{
		$new_config  = array(
			"name"					=> $name,
			"asterisk_host"			=> $asteriskHost,
			"client_host"			=> $clientHost,
			"client_port"			=> $clientPort,
			"client_use_ssl"		=> $clientUseSSL,
			"api_host"				=> $apiHost,
			"api_port"				=> $apiPort,
			"api_username"			=> $apiUserName,
			"api_password"			=> $apiPassword,
			"api_use_ssl"			=> $apiUseSSL,
			"sync_with_userman"		=> $syncWithUserman,
			"clean_unknown_items"	=> $cleanUnknownItems,
		);
		foreach($new_config as $key => $value)
		{
			$this->server_update_key($key, $value);
		}
	}

	public function server_update_key($key, $val)
	{
		$id_settings = "settings_server";
		$old_config  = $this->server_get();

		if ($val != $old_config[$key])
		{
			$this->setConfig($key, $val, $id_settings);
		}
	}

	/**
	 * API fucntion to get the server information
	 * @return array|null
	 */
	public function server_get($option = null)
	{
		$id_settings = "settings_server";
		if (is_null($option))
		{
			$data = $this->getAll($id_settings);
		}
		else
		{
			$data = $this->getConfig($option, $id_settings);
		}
		return empty($data) ? null : $data;
	}

	/**
	 * API function to update the voicemail agent information
	 * @param String $identifier the agent identifier
	 * @param String $directory the root voicemail directory path
	 * @param String $resourceHost hostname or ip used to build voicemail playback urls
	 * @param String $resourceExtension file extension used to build voicemail playback urls
	 */
	public function voicemail_agent_update($identifier, $directory, $resourceHost, $resourceExtension)
	{
		$new_config  = array(
			"identifier"		 => $identifier,
			"directory"			 => $directory,
			"resource_host"		 => $resourceHost,
			"resource_extension" => $resourceExtension,
		);
		foreach($new_config as $key => $value)
		{
			$this->voicemail_agent_update_key($key, $value);
		}
	}

	public function voicemail_agent_update_key($key, $val)
	{
		$id_settings = "settings_voicemail_agent";
		$old_config  = $this->voicemail_agent_get();

		if ($val != $old_config[$key])
		{
			$this->setConfig($key, $val, $id_settings);
		}
	}

	/**
	 * API fucntion to get the voicemail agent information
	 * @return array|null
	 */
	public function voicemail_agent_get($option = null)
	{
		$id_settings = "settings_voicemail_agent";
		if (is_null($option))
		{
			$data = $this->getAll($id_settings);
		}
		else
		{
			$data = $this->getConfig($option, $id_settings);
		}
		return empty($data) ? null : $data;
	}
		
	/**
	 * API function to update the recording agent information
	 * @param String $identifier the agent identifier
	 * @param String $directory the root recording directory path
	 * @param String $resourceHost hostname or ip used to build recording playback urls
	 * @param String $resourceExtension file extension used to build voicemail playback urls
	 * @param String $fileNameMask file name mask used to parse recording file names and create recordings
	 */
	public function recording_agent_update($identifier, $directory, $resourceHost, $resourceExtension, $fileNameMask)
	{
		$new_config  = array(
			"identifier"		 => $identifier,
			"directory"			 => $directory,
			"resource_host"		 => $resourceHost,
			"resource_extension" => $resourceExtension,
			"file_name_mask" 	 => $fileNameMask,
		);
		foreach($new_config as $key => $value)
		{
			$this->recording_agent_update_key($key, $value);
		}
	}

	public function recording_agent_update_key($key, $val)
	{
		$id_settings = "settings_recording_agent";
		$old_config  = $this->recording_agent_get();

		if ($val != $old_config[$key])
		{
			$this->setConfig($key, $val, $id_settings);
		}
	}

	/**
	 * API fucntion to get the recording agent information
	 * @return array|null
	 */
	public function recording_agent_get($option = null)
	{
		$id_settings = "settings_recording_agent";
		if (is_null($option))
		{
			$data = $this->getAll($id_settings);
		}
		else
		{
			$data = $this->getConfig($option, $id_settings);
		}
		return empty($data) ? null : $data;
	}

	/**
	 * API fucntion to get the email information
	 * 
	 * @param string $option 	Option that we want to obtain the data, if it is left blank or null is passed, all the options will be returned.
	 * @return array|null|string
	 */
	public function email_get($option = null)
	{
		$id_settings = "settings_mail";
		if (is_null($option))
		{
			$data = $this->getAll($id_settings);
		}
		else
		{
			$data = $this->getConfig($id_settings);
		}
		return empty($data) ? null : $data;
	}

	public function email_update_key($key, $val)
	{
		$id_settings = "settings_mail";
		$old_config  = $this->email_get();

		if ($val != $old_config[$key])
		{
			$this->setConfig($key, $val, $id_settings);
		}
	}

	/**
	 * API function to update the email information
	 * @param String $from the from of the email address
	 * @param String $subject the subject of the email
	 * @param String $body the body of the email
	 */
	public function email_update($from, $subject, $body)
	{
		$new_config  = array(
			"from" 	  => $from,
			"subject" => $subject,
			"body" 	  => $body,
		);
		foreach($new_config as $key => $value)
		{
			$this->email_update_key($key, $value);
		}
	}

	/**
	 * API function to add a user
	 * @param String $userId the user id of the FreePBX user
	 * @param Boolean $addExtension true if an extension should be created for the user
	 * @param Boolean $addUser true if a user login should be created for the user
	 * @param String $password the user login password for the user
	 * @param Boolean $autoAnswer true if the user's extension should autoanswer origination callbacks in the panel
	 * @param String $peer the peer value for the extension
	 * @param String $displayName the user and extension display name
	 * @param Boolean $full true if the user should be a full user
	 */
	public function user_add($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full)
	{
		$addUser 	  = $addUser ? "1" : "0";
		$addExtension = $addExtension ? "1" : "0";
		$autoAnswer   = $autoAnswer ? "1" : "0";
		$full 		  = $full ? "1" : "0";

		//Hash the password
		$hashedPassword = sha1($password);

		$values = array($userId, $addExtension, $addUser, "", $hashedPassword, $autoAnswer, $displayName, $peer, $full);
		
		$sql = sprintf("INSERT INTO %s (user_id, add_extension, add_user, initial_password, hashed_password, auto_answer, display_name, peer, full) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute($values);
	}

	/**
	 * API function to add a user with an initial password
	 * @param String $userId the user id of the FreePBX user
	 * @param Boolean $addExtension true if an extension should be created for the user
	 * @param Boolean $addUser true if a user login should be created for the user
	 * @param String $password the user login password for the user
	 * @param Boolean $autoAnswer true if the user's extension should autoanswer origination callbacks in the panel
	 * @param String $peer the peer value for the extension
	 * @param String $displayName the user and extension display name
	 * @param Boolean $full true if the user should be a full user
	 * @param String $parentUserId the user id that this user's extension should be bound to
	 */
	public function user_add_with_initial_password($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full, $parentUserId)
	{
		$addUser 	  = $addUser ? "1" : "0";
		$addExtension = $addExtension ? "1" : "0";
		$autoAnswer   = $autoAnswer ? "1" : "0";
		$full 		  = $full ? "1" : "0";

		//Hash the password
		$hashedPassword = sha1($password);

		$values = array($userId, $addExtension, $addUser, $password, $hashedPassword, $autoAnswer, $displayName, $peer, $full, $parentUserId);

		$sql = sprintf("INSERT INTO %s (user_id, add_extension, add_user, initial_password, hashed_password, auto_answer, display_name, peer, full, parent_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute($values);
	}

	/**
	 * API function to update a user
	 * @param String $userId the user id of the FreePBX user
	 * @param Boolean $addExtension true if an extension should be created for the user
	 * @param Boolean $addUser true if a user login should be created for the user
	 * @param String $password the user login password for the user if this is equal to the global $this->defaultVal['UserPasswordMask'] the password will not be updated
	 * @param Boolean $autoAnswer true if the user's extension should autoanswer origination callbacks in the panel
	 * @param String $peer the peer value for the extension
	 * @param String $displayName the user and extension display name
	 * @param Boolean $full true if the user should be a full user
	 */
	public function user_update($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full)
	{
		$addUser 	  = $addUser ? "1" : "0";
		$addExtension = $addExtension ? "1" : "0";
		$autoAnswer   = $autoAnswer ? "1" : "0";
		$full 		  = $full ? "1" : "0";

		/**
		 * Check if the given password is equal to the password
		 * mask if it is not the password has been changed so we
		 * need to create a new hashed version of the password.
		 */
		$passModify = "";
		$hashedPassword = "";
		if($password != $this->defaultVal['UserPasswordMask']) {
			$passModify = ", hashed_password = ?";
			$hashedPassword = sha1($password);
		}

		$sql = sprintf("UPDATE %s SET add_extension = ?, add_user = ?, auto_answer = ?, peer = ?, display_name = ?, full = ? %s WHERE user_id = ?", $this->tables['users'], $passModify);
		if($hashedPassword == "")
		{
			$values = array($addExtension, $addUser, $autoAnswer, $peer, $displayName, $full, $userId);
		}
		else
		{
			$values = array($addExtension, $addUser, $autoAnswer, $peer, $displayName, $full, $hashedPassword, $userId);
		}

		$sth = $this->db->prepare($sql);
		$sth->execute($values);
	}

	/**
	 * API function to update only the extension properties on a user.
	 * @param String $userId of the record to update
	 * @param Boolean $addExtension true if the extension should be created
	 * @param Boolean $autoAnswer true if the extension should autoanswer origination callbacks in the panel
	 * @param String $peer the peer value for the extension
	 * @param String $displayName the display name of the extension
	 */
	public function extension_update($userId, $addExtension, $autoAnswer, $peer, $displayName)
	{
		$addExtension = $addExtension ? "1" : "0";
		$autoAnswer   = $autoAnswer ? "1" : "0";
		$values 	  = array($addExtension, $autoAnswer, $peer, $displayName, $userId);

		$sql = sprintf("UPDATE %s SET add_extension = ?, auto_answer = ?, peer = ?, display_name = ? WHERE user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute($values);
	}

	/**
	 * API function used to set the parent user id of a specified user
	 * @param String $userId the user id to set the parent user id on.
	 * @param String $parentUserId parent user id
	 */
	public function user_set_parent_user_id($userId, $parentUserId)
	{
		$values = array($parentUserId, $userId);
		$sql = sprintf("UPDATE %s SET parent_user_id = ? WHERE user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute($values);
	}

	/**
	 * API function to delete a user
	 * @param String $userId the FreePBX user id of the user to delete
	 */
	public function user_del($userId)
	{
		$sql = sprintf("DELETE FROM %s WHERE user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId));

		//Delete the user's associated phone numbers
		$this->phone_number_del($userId);
	}

	/**
	 * API function to get a list of users
	 * @return array list of users
	 */
	public function user_list()
	{
		$sql = sprintf("SELECT * FROM %s", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to get a specific user
	 * @param String $userId the FreePBX user id of the user to get
	 * @return array|null
	 */
	public function user_get($userId)
	{
		$sql = sprintf("SELECT * FROM %s WHERE user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId));
		$results = $sth->fetch(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return null;
		}
		return $results;
	}

	/**
	 * API function to get a list of the specified users bound extensions
	 * @param String $userId the parent user id
	 * @return array|null
	 */
	public function user_extension_list($userId)
	{
		$sql = sprintf("SELECT * FROM %s WHERE parent_user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}
	
	/**
	 * API function to mark a user's password as dirty or clean.
	 * Passwords that have been marked as dirty will be pushed to
	 * the server on reload.
	 * @param String $userId the user id to mark
	 * @param Boolean $dirty true to mark as dirty or false to mark as clean
	 */
	public function mark_user_password_dirty($userId, $dirty)
	{
		$dirtyString = $dirty ? "1" : "0";
		$sql = sprintf("UPDATE %s SET password_dirty = ? WHERE user_id = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($dirtyString, $userId));
	}

	/**
	 * API function to mark all user password as dirty or clean.
	 * Passwords that have been marked as dirty will be pushed to
	 * the server on reload.
	 *
	 * @param Boolean $dirty true to mark as dirty or false to mark as clean
	 */
	public function mark_all_user_passwords_dirty($dirty) {
		$dirtyString = $dirty ? "1" : "0";

		//Mark the cxpanel users
		$sql = sprintf("UPDATE %s SET password_dirty = ?", $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($dirtyString));

		//Mark the FreePBX users
		$freePBXUsers = $this->Userman->getAllUsers();
		foreach($freePBXUsers as $freePBXUser)
		{
			$this->Userman->setModuleSettingByID($freePBXUser['id'], 'cxpanel', 'password_dirty', $dirtyString);
		}
	}

	/**
	 * API function to get a list of all phone numbers
	 * @return array 
	 */
	public function phone_number_list_all()
	{
		$sql = sprintf("SELECT * FROM `%s`", $this->tables['phone']);
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to get a list of phone numbers associated with a user
	 * @param String $userId the user id to get the list of phone numbers for
	 * @return array
	 */
	public function phone_number_list($userId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE user_id = ?", $this->tables['phone']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to delete all phone numbers for a user
	 * @param String $userId the user id to delete the phone number for
	 */
	public function phone_number_del($userId)
	{
		$sql = sprintf("DELETE FROM %s WHERE user_id = ?", $this->tables['phone']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId));
	}

	/**
	 * API function to add a phone number
	 * @param String $userId the user id to add the phone number for
	 * @param String $phoneNumber the phone number
	 * @param String $type the type of the phone number
	 */
	public function phone_number_add($userId, $phoneNumber, $type)
	{
		$sql = sprintf("INSERT INTO %s (user_id, phone_number, type) VALUES (?, ?, ?)", $this->tables['phone']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($userId, $phoneNumber, $type));
	}

	/**
	 * API function to add a queue
	 * @param String $queueId the FreePBX queue id
	 * @param Boolean $addQueue true if the queue should be added to the panel
	 * @param String $displayName the display name of the queue
	 */
	public function queue_add($queueId, $addQueue, $displayName)
	{
		$addQueue = $addQueue ? "1" : "0";
		$sql = sprintf("INSERT INTO %s (queue_id, add_queue, display_name) VALUES (?, ?, ?)", $this->tables['queues']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($queueId, $addQueue, $displayName));
	}

	/**
	 * API function to update a queue
	 * @param String $queueId the FreePBX queue id to edit
	 * @param Boolean $addQueue true if the queue shoudl be added to the panel
	 * @param String $displayName the display name of the queue
	 */
	public function queue_update($queueId, $addQueue, $displayName)
	{
		$addQueue = $addQueue ? "1" : "0";
		$sql = sprintf("UPDATE %s SET add_queue = ?, display_name = ? WHERE queue_id = ?", $this->tables['queues']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($addQueue, $displayName, $queueId));
	}

	/**
	 * API function to delete a queue
	 * @param String $queueId the FreePBX queue id to delete
	 */
	public function queue_del($queueId)
	{
		$sql = sprintf("DELETE FROM `%s` WHERE queue_id = ?", $this->tables['queues']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($queueId));
	}

	/**
	 * API function to get the list of queues
	 * @return array
	 */
	public function queue_list()
	{
		$sql = sprintf("SELECT * FROM `%s`", $this->tables['queues']);
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to get a specific queue
	 * @param String $queueId the FreePBX queue id of the queue to get
	 * @return array|null
	 */
	public function queue_get($queueId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE queue_id = ?", $this->tables['queues']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($queueId));
		$results = $sth->fetch(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return null;
		}
		return $results;
	}

	/**
	 * API function to add a conference room
	 * @param String $conferenceRoomId the FreePBX conference room id
	 * @param Boolean $addConferenceRoom true if the conference room should be added to the panel
	 * @param String $displayName the display name of the conference room
	 */
	public function conference_room_add($conferenceRoomId, $addConferenceRoom, $displayName)
	{
		$addConferenceRoom = $addConferenceRoom ? "1" : "0";
		$sql = sprintf("INSERT INTO `%s` (conference_room_id, add_conference_room, display_name) VALUES (?, ?, ?)", $this->tables['rooms']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($conferenceRoomId, $addConferenceRoom, $displayName));
	}

	/**
	 * API function to update a conference room
	 * @param String $conferenceRoomId the FreePBX conference room id
	 * @param Boolean $addConferenceRoom true if the conference room should be added to the panel
	 * @param String $displayName the display name of the conference room
	 */
	public function conference_room_update($conferenceRoomId, $addConferenceRoom, $displayName)
	{
		$addConferenceRoom = $addConferenceRoom ? "1" : "0";
		$sql = sprintf("UPDATE `%s` SET add_conference_room = ?, display_name = ? WHERE conference_room_id = ?", $this->tables['rooms']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($addConferenceRoom, $displayName, $conferenceRoomId));
	}

	/**
	 * API function to delete a conference room
	 * @param String $conferenceRoomId the FreePBX conferenc room id to delete
	 */
	public function conference_room_del($conferenceRoomId)
	{
		$sql = sprintf("DELETE FROM `%s` WHERE conference_room_id = ?", $this->tables['rooms']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($conferenceRoomId));
	}

	/**
	 * API function to get the list of conference rooms
	 * @return array
	 */
	public function conference_room_list()
	{
		$sql = sprintf("SELECT * FROM `%s`", $this->tables['rooms']);
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results)) {
			return array();
		}
		return $results;
	}

	/**
	 * API function to get a specific conference room
	 * @param String $conferenceRoomId FreePBX id of the conference room to get
	 * @return array
	 */
	public function conference_room_get($conferenceRoomId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE conference_room_id = ?", $this->tables['rooms']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($conferenceRoomId));
		$results = $sth->fetch(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return null;
		}
		return $results;
	}

	/**
	 * API function to check if the object with the give type and cxpanel id are managed by this
	 * instance of the module.
	 *
	 * NOTE if the clean_unknown_items flag is enabled this method will always return true.
	 *
	 * @param String $type the type of object to check for [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param String $cxpanelId the uuid of the cxpanel configuration object to check for.
	 * @return Boolean true if this module instance manages the given item or clean_unknown_items is enabled.
	 */
	public function has_managed_item($type, $cxpanelId)
	{
		$serverInformation = $this->server_get();

		//Check if clean_unknown_items is enabled
		if($serverInformation['clean_unknown_items'] == '1')
		{
			return true;
		}
		return !empty($this->managed_item_get($type, $cxpanelId));
	}

	/**
	 * API function to get all managed items
	 * @return array
	 */
	public function managed_item_get_all()
	{
		$sql = sprintf("SELECT * FROM `%s`", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to get a managed item
	 * @param String $type the type of object to get [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param String $cxpanelId the cxpanle id to lookup the item with
	 * @return array
	 */
	public function managed_item_get($type, $cxpanelId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE `type` = ? AND cxpanel_id = ?", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($type, $cxpanelId));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			return array();
		}
		return $results;
	}

	/**
	 * API function to add a managed item.
	 * @param String $type the type of object [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param String $fpbxId the fpbx id of the object.
	 * @param String $cxpanelId the uuid of the cxpanel configuration object.
	 */
	public function managed_item_add($type, $fpbxId, $cxpanelId)
	{
		$sql = sprintf("INSERT INTO `%s` (`type`, fpbx_id, cxpanel_id) VALUES (?, ?, ?)", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($type, $fpbxId, $cxpanelId));
	}

	/**
	 * API function to remove a managed item.
	 * @param String $type the type of object [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param String $cxpanelId the uuid of the cxpanel configuration object.
	 */
	public function managed_item_del($type, $cxpanelId)
	{
		$sql = sprintf("DELETE FROM `%s` WHERE `type` = ? AND cxpanel_id = ?", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($type, $cxpanelId));
	}

	/**
	 * API function to update the cxpanel id for a managed item.
	 *
	 * If the managed item does not exist the entry is created.
	 *
	 * @param unknown $type the type of object to update [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param unknown $fpbxId the fpbx id of the object to update.
	 * @param unknown $cxpanelId the uuid to update with.
	 */
	public function managed_item_update($type, $fpbxId, $cxpanelId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE `type` = ? AND fpbx_id = ?", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($type, $fpbxId));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			$this->managed_item_add($type, $fpbxId, $cxpanelId);
		}
		else
		{
			$sql = sprintf("UPDATE `%s` SET cxpanel_id = ? WHERE type = ? AND fpbx_id = ?", $this->tables['items']);
			$sth = $this->db->prepare($sql);
			$sth->execute(array($cxpanelId, $type, $fpbxId));
		}
	}
	
	/**
	 * API function that generates a uuid for a managed object.
	 *
	 * If this is a new object a new uuid will be generated
	 * else the one from the existing record will be returned.
	 *
	 * If a new uuid is generated a new entry will be made into cxpanel_managed_items.
	 *
	 * @param String $type the type of object [admin|user|userman_user|extension|queue|conference_room|parking_lot].
	 * @param String $fpbxId the FreePBX id of the object
	 * @return String the uuid for the server end
	 */
	public function gen_managed_uuid($type, $fpbxId)
	{
		$sql = sprintf("SELECT * FROM `%s` WHERE `type` = ? AND fpbx_id = ?", $this->tables['items']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($type, $fpbxId));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);

		/*
		* If there was no match return a new UUID
		* else return the UUID in the record
		*/
		if (empty($results[0]['cxpanel_id']))
		{
			$uuid = cxpanel_gen_uuid();
			//Create an entry into cxpanel_managed_items
			$this->managed_item_add($type, $fpbxId, $uuid);
			return $uuid;
		}
		return $results[0]['cxpanel_id'];
	}

	/**
	 * Updates the request eventwhencalled flag when editing a queue.
	 * Used to force the eventwhencalled flag when adding a queue.
	 * @param Boolean $addQueue true if the queue is being added
	 */
	function queue_eventwhencalled_modify($addQueue)
	{
		$addQueue = $addQueue ? "1" : "0";
		if ($addQueue == "1")
		{
			$_REQUEST['eventwhencalled'] = 'yes';
		}
	}

	/**
	 * Updates the request eventmemberstatus flag when editing a queue.
	 * Used to force the eventmemberstatus flag when adding a queue.
	 * @param Boolean $addQueue true if the queue is being added
	 */
	function queue_eventmemberstatus_modify($addQueue)
	{
		$addQueue = $addQueue ? "1" : "0";
		if ($addQueue == "1")
		{
			$_REQUEST['eventmemberstatus'] = 'yes';
		}
	}

	/**
	 * Creates the manager connection if it does not exist
	 */
	public function create_manager()
	{
		$this->debug(_("Checking manager connection"));

		//Check if a manager profile exists for cxpanel if not create it.
		$managerFound = false;
		if((function_exists("manager_list")) && (($managers = manager_list()) !== null))
		{
			//Search for cxpanel manager
			foreach($managers as $manager)
			{
				if($manager['name'] == "cxpanel" )
				{
					$managerFound = true;
					break;
				}
			}
		}

		//If not found create a manager profile for cxpanel
		if((function_exists("manager_add")) && (!$managerFound))
		{
			$amiPermissions = $this->defaultVal['amiPermissions'];
			$this->debug(_("Creating manager connection"));
			manager_add("cxpanel", "cxmanager*con", "0.0.0.0/0.0.0.0", "127.0.0.1/255.255.255.0", $amiPermissions, $amiPermissions);

			if(function_exists("manager_gen_conf"))
			{
				manager_gen_conf();
			}
		}
	}

	/**
	 * Get the agent login context that should be
	 * used based on the version of FreePBX
	 * @return string
	 */
	public function get_agent_login_context()
	{
		$freepbxVersion = get_framework_version();
		$freepbxVersion = $freepbxVersion ? $freepbxVersion : getversion();
		$agentLoginContext = "from-internal";
		if(version_compare_freepbx($freepbxVersion, "2.6", ">="))
		{
			$agentLoginContext = "from-queue";
		}
		return $agentLoginContext;
	}

	/**
	 * Gets the agent interface type based on the
	 * version of FreePBX and if dev state is enabled
	 * @return string
	 */
	public function get_agent_interface_type()
	{
		$agentInterfaceType = "none";
		$info = engine_getinfo();

		$UseDevState 	= $this->cfg->get("USEDEVSTATE");
		$UseQueueState 	= $this->cfg->get("USEQUEUESTATE");

		$devStateEnabled = isset($UseDevState) && isset($UseQueueState) && $UseDevState === true && $UseQueueState === true;

		if(version_compare($info["version"], "1.6", ">=") || (version_compare($info["version"], "1.4.25", ">=") && !$devStateEnabled))
		{
			$agentInterfaceType = "peer";
		}
		else if(version_compare($info["version"], "1.4.25", ">=") && $devStateEnabled)
		{
			$agentInterfaceType = "hint";
		}
		else
		{
			$agentInterfaceType = "none";
		}
		return $agentInterfaceType;
	}

	/**
	 * Gets the parking lot timeout
	 * @return int
	 */
	public function get_parking_timeout()
	{
		$parkingTimeout = 200;
		// TODO: Error -   SQLSTATE[42S02]: Base table or view not found: 1146 Table 'asterisk.parkinglot' doesn't exist
		// $sql = "SELECT `keyword`, `data` FROM `parkinglot` WHERE id = '1'";
		// $sth = $this->db->prepare($sql);
		// $sth->execute();
		// $results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		// if (! empty($results) and ! empty($results['parkingtime']))
		// {
		// 	$parkingTimeout = $results['parkingtime'];
		// }
		return $parkingTimeout;
	}

	/**
	 * Creates the dialplan entries
	 * @param String $contextPrefix
	 * @param String $variablePrefix
	 * @param String $parkingTimeout
	 */
	public function add_contexts($contextPrefix, $variablePrefix, $parkingTimeout)
	{
		global $ext;

		$this->debug("Creating contexts ContextPrefix:" . $contextPrefix . " VariablePrefix:" . $variablePrefix);

		$id = $contextPrefix . "-hold";
		$c = '432111';
		$ext->add($id, $c, '', new \ext_musiconhold("\${{$variablePrefix}MusicOnHoldClass}"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-voice-mail";
		$c = '432112';
		$ext->add($id, $c, '', new \ext_vm("\${{$variablePrefix}VoiceMailBox}@\${{$variablePrefix}VoiceMailBoxContext},u"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-meetme";
		$c = '432113';
		$ext->add($id, $c, '', new \ext_meetme("\${{$variablePrefix}MeetMeRoomNumber}", "\${{$variablePrefix}MeetMeRoomOptions}", ""));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-confbridge";
		$c = '432113';
		$ext->add($id, $c, '', new \ext_meetme("\${{$variablePrefix}MeetMeRoomNumber}"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-park";
		$c = '432114';
		$ext->add($id, $c, '', new \FreePBX\modules\Cxpanel\dialplan\ext_cxpanel_parkandannounce("pbx-transfer:PARKED", "$parkingTimeout", "Local/432116@" . $contextPrefix . "-park-announce-answer", "\${{$variablePrefix}ParkContext},\${{$variablePrefix}ParkExtension},1"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-park-announce-answer";
		$c = '432116';
		$ext->add($id, $c, '', new \ext_answer());
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-listen-to-voice-mail";
		$c = '432115';
		$ext->add($id, $c, '', new \FreePBX\modules\Cxpanel\dialplan\ext_cxpanel_controlplayback("\${{$variablePrefix}VoiceMailPath}", "1000", "*", "#", "7", "8" , "9"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-listen-to-recording";
		$c = '432118';
		$ext->add($id, $c, '', new \FreePBX\modules\Cxpanel\dialplan\ext_cxpanel_controlplayback("\${{$variablePrefix}RecordingPath}", "1000", "*", "#", "7", "8" , "9"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . "-spy";
		$c = '432117';
		$ext->add($id, $c, '', new \FreePBX\modules\Cxpanel\dialplan\ext_cxpanel_chanspy("\${{$variablePrefix}ChanSpyChannel}", "\${{$variablePrefix}ChanSpyOptions}"));
		$ext->add($id, $c, '', new \ext_hangup());

		$id = $contextPrefix . '-pjsip-auto-answer-headers';
		$c = 'addheader';
		$ext->add($id, $c, '', new \ext_set('PJSIP_HEADER(add,Alert-Info)', '<http://www.notused.com>\;info=alert-autoanswer\;delay=0'));
		$ext->add($id, $c, '', new \ext_set('PJSIP_HEADER(add,Alert-Info)', 'Ring Answer'));
		$ext->add($id, $c, '', new \ext_set('PJSIP_HEADER(add,Alert-Info)', 'ring-answer'));
		$ext->add($id, $c, '', new \ext_set('PJSIP_HEADER(add,Call-Info)', '\;answer-after=0'));

		$id = $contextPrefix . '-pjsip-auto-answer-redirect';
		$c = '_X!';
		$ext->add($id, $c, '', new \ext_execif('$["${D_OPTIONS}"==""]', 'Set', 'D_OPTIONS=TtrI'));
		$ext->add($id, $c, '', new \ext_dial('${CX_AUTOANSWER_REDIRECT_PEER}', ',${D_OPTIONS}b(' .$contextPrefix . '-pjsip-auto-answer-headers^addheader^1)'));
	}

	/**
	 * Syncs the user and extension relationships
	 * @param String $userId the user id of the parent
	 * @param Array $userExtensions the proposed list of child extensions
	 */
	public function sync_user_extensions($userId, $userExtensions)
	{
		//Grab the user info
		$user = $this->user_get($userId);

		//Get the users current extension list
		$currentUserExtensionsAssoc = array();
		$currentUserExtensions = $this->user_extension_list($userId);
		foreach($currentUserExtensions as $currentUserExtension)
		{
			$currentUserExtensionsAssoc[$currentUserExtension['user_id']] = $currentUserExtension;
		}

		//Grab the list of all proposed user extensions
		$newUserExtensionsAssoc = array();
		foreach($userExtensions as $userExtension)
		{
			if($userExtension != "self")
			{
				$userExtension = $this->user_get($userExtension);
				$newUserExtensionsAssoc[$userExtension['user_id']] = $userExtension;
			}
			else
			{
				$newUserExtensionsAssoc[$user['user_id']] = $user;
			}
		}

		//Unbind all extensions that are no logner a part of the user
		foreach($currentUserExtensionsAssoc as $checkUserId => $checkUser)
		{
			if(!array_key_exists($checkUserId, $newUserExtensionsAssoc))
			{
				$this->user_set_parent_user_id($checkUserId, "");
			}
		}

		//Bind all extensions that are part of the user
		foreach($newUserExtensionsAssoc as $checkUserId => $checkUser)
		{
			//If the check user is not self, condition the user as a child.
			if($checkUserId != $userId)
			{
				//Cleanup any bound extensions the check user has since it is no longer a parent
				$cleanupListValues = $this->user_extension_list($checkUserId);
				foreach($cleanupListValues as $cleanupListValue)
				{
					$this->user_set_parent_user_id($cleanupListValue['user_id'], "");
				}

				//Make sure that the user has the add extension flag cheked
				$this->user_update($checkUser['user_id'],
									true, 
									false,
									$this->defaultVal['UserPasswordMask'],
									$checkUser['auto_answer'] == "1",
									$checkUser['peer'],
									$checkUser['display_name'],
									$checkUser['full'] == "1");
			}

			//Set the extension binding on the user
			$this->user_set_parent_user_id($checkUserId, $userId);
		}
	}

	/**
	 * Gets the list of users that are bound to the given extension based on the relationships
	 * managed by the userman module.
	 *
	 * If the userman module is not installed this function will return an empty array.
	 *
	 * @param String $extension the extension number
	 * @return array The list of all know users bound to the given extension
	 */
	public function get_freepbx_users_from_extension($extension)
	{
		$sql = 'SELECT id, username FROM freepbx_users, freepbx_users_settings WHERE freepbx_users.id = freepbx_users_settings.uid AND freepbx_users_settings.key = "assigned" AND freepbx_users_settings.val LIKE ?';
		$sth = $this->db->prepare($sql);
		$sth->execute(array("%$extension%"));
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			$results = array();
		}
		return $results;
	}

	/**
	 * Send a password email
	 * @param String $userId user id
	 * @param String $pass if specified will be used for the password else the inital password will be sent
	 * @param String $email if specified will be used for the email else the email will be queried from the vm module
	 * @return boolean True if the email was sent successfully and false if not sent successfully.
	 */
	public function send_password_email($userId, $pass = "", $email = "")
	{
		//Collect email settings and user data
		$emailSettings 	= $this->email_get();
		$cxpanelUser 	= $this->user_get($userId);
		$voiceMailBox 	= $this->hook_voicemail_getMailBox($userId);

		//Determine password to send
		$password = $pass != "" ? $pass : $cxpanelUser['initial_password'];

		//Determine the email
		$email 		 = !empty($email) ? $email : $voiceMailBox['email'];
		$email_array = explode(",", $email);

		//Prepare the values for the remplace in templace
		$var_remplace = array (
			'userId' 	=> $cxpanelUser['user_id'],
			'display' 	=> $cxpanelUser['display_name'],
			'password' 	=> $password,
			'clientURL' => $this->getClientURL(),
			'brandName' => $this->brandName,
		);

		//Prepare the subject and body contents
		$from 		  = empty($emailSettings['from']) ? $this->defaultVal['email']['from'] : $emailSettings['from'];
		$subject 	  = $emailSettings['subject'];
		$bodyContents = $emailSettings['body'];

		foreach($var_remplace as $key => $value)
		{
			$subject 	  = str_ireplace("%%" . $key . "%%", $value, $subject);
			$bodyContents = str_ireplace("%%" . $key . "%%", $value, $bodyContents);
		}

		//Prepare the logo
		//TODO: Create Config logo file
		$logo = $this->cfg->get('BRAND_IMAGE_FREEPBX_FOOT');
		$bodyContents = str_ireplace("%%logo%%", '<img src="cid:logo">', $bodyContents);

		//Format AltBody in text plane
		$bodyContentsText = $bodyContents;
		$bodyContentsText = preg_replace('/<br(\s+)?\/?>/i', "\n\r", $bodyContentsText);
		$bodyContentsText = strip_tags($bodyContents);

		//Create new mailer
		$phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
		try
		{
			$phpMailer->isMail();

			//Create the email
			$phpMailer->CharSet = 'UTF-8';
			$phpMailer->isHTML(true);
			$phpMailer->setFrom($from, $this->brandName);
			foreach ($email_array as $mail)
			{
				$phpMailer->addAddress($mail);
			}
			$phpMailer->AddEmbeddedImage($logo, 'logo');
			$phpMailer->Subject = $subject;
			$phpMailer->Body    = $bodyContents;
			$phpMailer->AltBody = $bodyContentsText;

			//Send the email
			$phpMailer->send();
			return true;
		}
		catch (\PHPMailer\PHPMailer\Exception $e)
		{
			dbug(sprintf(_("ERROR: Message could not be sent. Mailer Error -> %s"), $phpMailer->ErrorInfo));
			return false;
		}
		catch (\Exception $e)
		{
			dbug(sprintf(_("ERROR: Message could not be sent. Mailer Error -> %s"), $phpMailer->ErrorInfo));
			return false;
		}
	}

	/**
	 * Get the Client URL
	 * @return string Client URL
	 */
	public function getClientURL()
	{
		$serverInformation = $this->server_get();

		/*
		* If set utilize the client_host stored in the database else utilize the host
		* from the current URL.
		*/
		$clientHost = $serverInformation['client_host'];
		if($clientHost == "")
		{
			$httpHost = explode(':', $_SERVER['HTTP_HOST']);
			$clientHost = $httpHost[0];
		}

		//Check if the we need to use https
		$protocol = $serverInformation['client_use_ssl'] == '1' ? 'https' : 'http';

		$url = sprintf("%s://%s:%s/client/client", $protocol, $clientHost, $serverInformation['client_port']);

		return $url;
	}

	/**
	 * Gets the list of amp users
	 * @return array
	 */
	public function get_core_ampusers_list()
	{
		$sql = "SELECT * FROM `ampusers`";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($results))
		{
			$results = array();
		}
		return $results;
	}

	/**
	 * Returns an array of administrators defined in userman
	 * @return array of administrators
	 */
	public function get_userman_administrators()
	{
		$administrators = array();
		foreach($this->Userman->getAllUsers() as $user)
		{
			//if pbx_admin set, create admin
			if($this->Userman->getGlobalSettingByID($user['id'], 'pbx_admin'))
			{
				$admin = array(
					"username" => $user['username'],
					"password_sha1" => $user['password'],
					"extension_low" => "",
					"extension_high" => "",
					"deptname" => $user['department'],
					"sections" => "*"
				);
	
				$administrators[] = $admin;
				//if pbx_login set, check sections - will only add if * or cxpanel has been set
			}
			else if ($this->Userman->getGlobalSettingByID($user['id'], 'pbx_login'))
			{
				$sections = $this->Userman->getGlobalSettingByID($user['id'], 'pbx_modules');
				$sections = empty($sections) ? array() : $sections;
	
				$admin = array(
					"username" => $user['username'],
					"password_sha1" => $user['password'],
					"extension_low" => "",
					"extension_high" => "",
					"deptname" => $user['department'],
					"sections" => implode(";", $sections)
				);
	
				$administrators[] = $admin;
			}
		}
		return $administrators;
	}

	/**
	 * Returns an array of administrators defined in ampusers and userman.
	 * @return array of administrators
	 */
	public function get_combined_administrators()
	{
		return array_merge($this->get_core_ampusers_list(), $this->get_userman_administrators());
	}


	public function debug($message = "")
	{
		if ($this->isLogInit() && ! empty($message))
		{
			$this->log->debug($message);
		}
	}

	public function isLogInit()
	{
		return (!is_null($this->log));
	}

	public function openLog()
	{
		//Open the logger
		if ($this->isLogInit() && !$this->log->isOpen())
		{
			$this->log->open();
		}
	}

	public function closeLog() {
		//Close the logger
		if ($this->isLogInit() && $this->log->isOpen())
		{
			$this->log->close();
		}
	}

	public function getApiREST($logger = null, $serverInformation = null)
	{
		if (is_null($serverInformation))
		{
			$serverInformation = $this->server_get();
		}
		$webProtocol = ($serverInformation['api_use_ssl'] == '1') ? 'https' : 'http';
		$baseApiUrl  = sprintf("%s://%s:%s/communication_manager/api/resource/", $webProtocol, $serverInformation['api_host'], $serverInformation['api_port']);
		if (! is_null($logger))
		{
			$logger->debug(sprintf(_("Starting REST connection to %s"), $baseApiUrl));
		}
		$pest = new \FreePBX\modules\Cxpanel\CXPestJSON($baseApiUrl);
		$pest->setupAuth($serverInformation['api_username'], $serverInformation['api_password']);
		return $pest;
	}

	/**
	 * 
	 * Generates a random password of the given length
	 * @author http://www.laughing-buddha.net/php/password
	 *
	 */
	public function generate_password ($length = 8)
	{
		$password = "";
		$possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";
		$maxlength = strlen($possible);
		
		if ($length > $maxlength)
		{
			$length = $maxlength;
		}

		for ($i = 0; $i < $length; $i++)
		{
			$char = substr($possible, mt_rand(0, $maxlength-1), 1);
			if (!strstr($password, $char))
			{
				$password .= $char;
			}
		}
		return $password;
	}
	
	public function checkOnline($url, $returnInfo = false, $debug = false)
	{
		$agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
		$ch=curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		if(curl_exec($ch) === false)
		{
			$error = curl_error($ch);
			if ($debug)
			{
				dbug($error);
			}
		}
		$info = curl_getinfo($ch);
		if ($debug)
		{
			dbug($info);
		}
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$status = ($httpcode >= 200 && $httpcode < 300 && $httpcode != 0) ? true : false;
		curl_close($ch);
		return !$returnInfo ? $status : array('status' => $status, 'info' => $info, 'error' => (empty($error) ? '' : $error));
   }
}