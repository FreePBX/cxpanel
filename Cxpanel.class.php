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
 *	./brand.php
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
class Cxpanel implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
	}

	public function doConfigPageInit($page) {
	}

	public function install() {

	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function genConfig() {
		global $active_modules;
		$conf = null;
		$files = array('/opt/isymphony3/server/jvm.args', '/opt/xactview/server/jvm.args');
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
		$this->freepbx->WriteConfig()->writeCustomFile($conf);
	}

	public function ajaxRequest($req, &$setting) {
		if(!function_exists('cxpanel_server_get')) {
			include(__DIR__.'/functions.inc.php');
		}

		switch ($req) {
			case 'getUser':
			case 'checkAuth':
			case 'checkAuthAdmin':
			$serverSettings = cxpanel_server_get();

			if(gethostbyname($serverSettings['api_host']) == $_SERVER['REMOTE_ADDR']) {
				$setting['authenticate'] = false;
				$setting['allowremote'] = true;
				return true;
			} else {
				return false;
			}
		break;
		}
	return false;
	}

	public function ajaxHandler(){
		switch ($_REQUEST['command']) {
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
				$user = $this->freepbx->Userman->getUserByUsername($_GET['username']);
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
				$user = $this->freepbx->Userman->checkCredentials($data['username'], $data['password']);
				return !empty($user) ? array("status" => true) : array("status" => false);
			break;
            case 'checkAuthAdmin':
                $data = json_decode(file_get_contents("php://input"),true);

                //get the amp administrators
                $amp_administrators = cxpanel_get_core_ampusers_list();
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
                $administrators = cxpanel_get_userman_administrators();
                foreach($administrators as $admin) {
                    //find the admin that matches provided username, if it exists
                    if($admin['username'] == $data['username']) {
                        //verify the admin has * or cxpanel role
                        if(strpos($admin["sections"],"*") !== false || strpos($admin["sections"], "cxpanel") !== false) {
                            //admin with correct roles was found, check credentials and return result
                            $user = $this->freepbx->Userman->checkCredentials($data['username'], $data['password']);
                            return !empty($user) ? array("status" => true) : array("status" => false);
                        }
                    }
                }
		}
		return false;
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
		global $cxpanelBrandName;

		/**
		 * Add the cxpanel tab to the userman page if the following contitions are met:
		 * - The FreePBX verison is >= 13. The section will be added in older versions via cxpanel_hook_userman() in functions.inc.php.
		 * - Sync with user managment is enabled.
		 * - We are adding or editing a user.
		 */
		$serverSettings = cxpanel_server_get();
		if(version_compare_freepbx(getVersion(), '13.0', '>=') && $serverSettings['sync_with_userman'] == '1') {
			if(isset($_REQUEST['action'])) {
				switch($_REQUEST['action']) {
					case 'showgroup':
						$mode = "group";
						$addUser = $this->freepbx->Userman->getModuleSettingByGID($_REQUEST['group'], 'cxpanel', 'add');
					break;
					case 'showuser':
						$mode = "user";
						$addUser = $this->freepbx->Userman->getModuleSettingByID($_REQUEST['user'], 'cxpanel', 'add',true);
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
							'title' => $cxpanelBrandName,
							'rawname' => 'cxpanel',
							'content' => load_view(dirname(__FILE__).'/views/userman_hook.php',array('cxpanelBrandName' => $cxpanelBrandName, 'addUser' => $addUser, 'mode' => $mode))
					)
			);
		}

		return array();
	}

	public function usermanAddGroup($id, $display, $data) {
		$this->usermanUpdateGroup($id,$display,$data);
	}

	public function usermanUpdateGroup($id,$display,$data) {
		$this->userman = $this->freepbx->Userman;
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
	 *
	 * @param Int $id The User Manager ID
	 * @param String $display The page in FreePBX that initiated this function
	 * @param Array $data an array of all relevant data returned from User Manager
	 */
	public function usermanAddUser($id, $display, $data) {
		$this->userman = $this->freepbx->Userman;
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

		$this->userman = $this->freepbx->Userman;
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

		if($this->freepbx->Modules->moduleHasMethod("userman", "getCombinedModuleSettingByID")) {
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

		if($add == '1' && ($passwordDirty == '1' || $newUsername == '1')) {
			$serverInformation = cxpanel_server_get();

			if($serverInformation['sync_with_userman'] == '1') {

				try {

					//Set up the REST connection
					$webProtocol = ($serverInformation['api_use_ssl'] == '1') ? 'https' : 'http';
					$baseApiUrl = $webProtocol . '://' . $serverInformation['api_host'] . ':' . $serverInformation['api_port'] . '/communication_manager/api/resource/';
					$pest = new \CXPestJSON($baseApiUrl);
					$pest->setupAuth($serverInformation['api_username'], $serverInformation['api_password']);

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
		include(__DIR__."/brand.php");
		$cxpanelBrandName = !empty($cxpanelBrandName) ? $cxpanelBrandName : "iSymphony";

		$this->userman = $this->freepbx->Userman;

		$isUser = $this->userman->getModuleSettingByID($id, 'cxpanel', 'add');

		if(!$isUser) {
			return array();
		}
		//Collect email settings and user data
		$serverInformation = cxpanel_server_get();

		/*
		* If set utilize the client_host stored in the database else utilize the host
		* from the current URL.
		*/
		$clientHost = $serverInformation['client_host'];
		if($clientHost == "") {
			$httpHost = explode(':', $_SERVER['HTTP_HOST']);
			$clientHost = $httpHost[0];
		}

		//Check if the we need to use https
		$protocol = $serverInformation['client_use_ssl'] == '1' ? 'https' : 'http';

		$final = array();
		$final[] = "\t".sprintf(_('%s Login: %s'), $cxpanelBrandName, $protocol. '://' . $clientHost . ':' . $serverInformation['client_port'] . '/client/client');
		return $final;
	}


	/**
	 * FreePBX chown hooks
	 */
	public function chownFreepbx() {
		$files = array();

		$files[] = array('type' => 'file',
			'path' => __DIR__."/main.log",
			'perms' => 0775);
		return $files;
	}
}
