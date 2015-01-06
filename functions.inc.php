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

//Includes
require_once(dirname(__FILE__)."/brand.php");
require_once(dirname(__FILE__)."/lib/dialplan.class.php");
require_once(dirname(__FILE__)."/lib/cxpanel.class.php");
require_once(dirname(__FILE__)."/lib/logger.class.php");
require_once(dirname(__FILE__)."/lib/util.php");
require_once(dirname(__FILE__)."/lib/CXPestJSON.php");

if(!class_exists("PHPMailer")) {
	require_once(dirname(__FILE__)."/lib/PHPMailer/class.phpmailer.php");
}

//Create the logger
$cxPanelLogger = new cxpanel_logger($amp_conf['AMPWEBROOT'] . "/admin/modules/cxpanel/main.log");

//Create the global password mask
$cxpanelUserPasswordMask = "********";

//Setup userman hooks
if(!function_exists('setup_userman')){
	global $amp_conf;
	$um = module_getinfo('userman', MODULE_STATUS_ENABLED);
	if(file_exists($amp_conf['AMPWEBROOT'].'/admin/modules/userman/functions.inc.php') && (isset($um['userman']['status']) && $um['userman']['status'] === MODULE_STATUS_ENABLED)) {
		include_once($amp_conf['AMPWEBROOT'].'/admin/modules/userman/functions.inc.php');
	} else {
		//dont do anymore work, we need userman and it needs to be enabled
	}
}

if(function_exists('setup_userman')) {
	try {
		$userman = setup_userman();
		$userman->registerHook('addUser','cxpanel_userman_add');
		$userman->registerHook('updateUser','cxpanel_userman_update');
	} catch(\Exception $e) {
		//dont do anymore work, we need userman and it needs to be enabled
		return;
	}
}


/**
 *
 * Radio button component that supports onclick and does not include the element name in the value
 * @author michaely
 *
 */
class cxpanel_radio extends guiinput {
	function cxpanel_radio($elemname, $valarray, $currentvalue = '', $prompttext = '', $helptext = '', $disable=false) {
		if (!is_array($valarray)) {
			trigger_error('$valarray must be a valid array in gui_radio');
			return;
		}

		$parent_class = get_parent_class($this);
		parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildradiobuttons($valarray, $currentvalue, $disable);
	}

	function buildradiobuttons($valarray, $currentvalue, $disable=false) {
		$output = '';
		$output .= '<span class="radioset">';

		$count = 0;
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemchecked = ((string) $currentvalue == (string) $itemvalue) ? ' checked=checked' : '';
			$onClick = ((isset($item['onclick']) && $item['onclick'] != "") ? " onclick=\"" . $item['onclick'] . "\"" : "");

			$tabindex = guielement::gettabindex();
			$disable_state = $disable ? 'disabled="true"':'';
			$output .= "<input type=\"radio\" name=\"$this->_elemname\" id=\"$this->_elemname$count\" $disable_state tabindex=\"$tabindex\" value=\"$itemvalue\"$onClick $itemchecked/><label for=\"$this->_elemname$count\">$itemtext</label>\n";
			$count++;
		}
		$output .= '</span>';
		return $output;
	}
}

/**
 *
 * Select box component that supports multi select
 * @author michaely
 *
 */
class cxpanel_multi_selectbox extends guiinput {
	function cxpanel_multi_selectbox($elemname, $valarray, $size = '5', $currentvaluearray = array(), $prompttext = '', $helptext = '', $canbeempty = true, $onchange = '', $disable=false) {
		if (!is_array($valarray)) {
			trigger_error('$valarray must be a valid array in gui_selectbox');
			return;
		}

		// currently no validation functions available for select boxes
		// using the normal $canbeempty to flag if a blank option is provided
		$parent_class = get_parent_class($this);
		parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildselectbox($valarray, $size, $currentvaluearray, $canbeempty, $onchange, $disable);
	}

	// Build select box
	function buildselectbox($valarray, $size, $currentvaluearray, $canbeempty, $onchange, $disable) {
		$output = '';
		$onchange = ($onchange != '') ? " onchange=\"$onchange\"" : '';

		$tabindex = guielement::gettabindex();
		$disable_state = $disable ? 'disabled="true"':'';
		$output .= "\n\t\t\t<select multiple size=\"$size\" name=\"$this->_elemname[]\" id=\"$this->_elemname\" tabindex=\"$tabindex\" $disable_state $onchange >\n";
		// include blank option if required
		if ($canbeempty)
		$output .= "<option value=\"\">&nbsp;</option>";

		// build the options
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemselected = in_array($itemvalue, $currentvaluearray) ? ' selected' : '';

			$output .= "\t\t\t\t<option value=\"$itemvalue\"$itemselected>$itemtext</option>\n";
		}
		$output .= "\t\t\t</select>\n\t\t";

		return $output;
	}
}

/**
 *
 * Component used to manage a phone number list
 * @author michaely
 *
 */
class cxpanel_phone_number_list extends guiinput {
	function cxpanel_phone_number_list($elemname, $currentvaluearray = array(), $prompttext = '', $helptext = '') {

		// currently no validation fucntions availble for select boxes
		// using the normal $canbeempty to flag if a blank option is provided
		$parent_class = get_parent_class($this);
		parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildphonenumberbox($elemname, $currentvaluearray);
	}

	// Build select box
	function buildphonenumberbox($elemname, $currentvaluearray) {

		$output = 	'<script language="javascript">

						 var cxpanelPhoneNumberIndex = ' . count($currentvaluearray) . ';

						 function cxpanelDeletePhoneNumberRow(delRowId) {
						 	var row = document.getElementById("' . $elemname . '-element" + delRowId);
						 	row.parentNode.removeChild(row);

						 	var hinput = document.getElementById("' . $elemname . '-values" + delRowId);
							hinput.parentNode.removeChild(hinput);
						 }

						 function cxpanelAddPhoneNumberRow() {

						 	var number = document.getElementById("' . $elemname . '-number");
							if(number.value == "") {
								alert("Please provide a number to add");
								return;
							}

							var type = document.getElementById("' . $elemname . '-type");
							if(type.value == "") {
								alert("Please provide a type for the phone number");
								return;
							}

							var table = document.getElementById("' . $elemname . '-table");

							var insertIndex = table.rows.length;
							var row = table.insertRow(insertIndex);
							row.id = "' . $elemname . '-element" + cxpanelPhoneNumberIndex;
							row.name = "' . $elemname . '-element" + cxpanelPhoneNumberIndex;

							var numberCell = row.insertCell(0);
							numberCell.innerHTML=number.value;
							var typeCell = row.insertCell(1);
							typeCell.innerHTML=type.value;
							var removeCell = row.insertCell(2);
							removeCell.innerHTML="<a href=\"javascript:return false;\" onclick=\"cxpanelDeletePhoneNumberRow(" + cxpanelPhoneNumberIndex + "); return false;\">Remove</a>";

							var input = document.createElement("input");
							input.id = "' . $elemname . '-values" + cxpanelPhoneNumberIndex;
							input.setAttribute("type", "hidden");
							input.setAttribute("name", "' . $elemname . '-values[]");
							input.setAttribute("value", number.value + "@#" + type.value);
							table.appendChild(input);

							number.value = "";
							type.value = "";
							cxpanelPhoneNumberIndex++;
						 }

					 </script>

		             <table name="' . $elemname . '-table" id="' . $elemname . '-table" style="width: 300px; border-spacing: 3px;">' .
			          	'<tr>' .
				          	'<td>Number</td>' .
							'<td>Type</td>' .
							'<td>Action</td>' .
			          	'</tr>' .
						'<tr>' .
							'<td><input type="text" name="' . $elemname . '-number" id="' . $elemname . '-number" style="width: 100%;" /></td>' .
							'<td><input type="text" name="' . $elemname . '-type" id="' . $elemname . '-type" style="width: 100%;" /></td>' .
							'<td><a href="javascript:return false;" onclick="cxpanelAddPhoneNumberRow(); return false;">Add</a></td>' .
						'</tr>' .
						'<tr><td colspan="3"><hr/></td></tr>';

		$i = 0;
		foreach($currentvaluearray as $value) {
			$valueParts = explode("@#", $value);
			$output .= 	'<tr name="' . $elemname . '-element' . $i . '" id="' . $elemname . '-element' . $i . '">' .
				            '<td>' . $valueParts[0] . '</td>' .
				            '<td>' . $valueParts[1] . '</td>' .
							'<td><a href="javascript:return false;" onclick="cxpanelDeletePhoneNumberRow(' . $i . '); return false;">Remove</a></td>' .
						'</tr>' .
						'<input type="hidden" name="' . $elemname . '-values[]" id="' . $elemname . '-values' . $i . '" value="' . $value . '"/>';
			$i++;
		}

		$output .= "</table>";

		return $output;
	}
}

/**
 *
 * If check box GUI element does not exist add it
 *
 */
if(!class_exists("gui_checkbox")) {
	class gui_checkbox extends guiinput {
		function gui_checkbox($elemname, $checked=false, $prompttext='', $helptext='', $value='on', $post_text = '', $jsonclick = '', $disable=false) {
			$parent_class = get_parent_class($this);
			parent::$parent_class($elemname, '', $prompttext, $helptext);

			$itemchecked = $checked ? 'checked' : '';
			$disable_state = $disable ? 'disabled="true"' : '';
			$js_onclick_include = ($jsonclick != '') ? 'onclick="' . $jsonclick. '"' : '';
			$tabindex = function_exists("guielement::gettabindex") ? "tabindex=" . guielement::gettabindex() : "";

			$this->html_input = "<input type=\"checkbox\" name=\"$this->_elemname\" id=\"$this->_elemname\" $disable_state $tabindex value=\"$value\" $js_onclick_include $itemchecked/>$post_text\n";
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
function cxpanel_get_config($engine) {
	global $ext, $amp_conf, $db, $cxPanelLogger;

	$runningTimeStart = microtime(true);

	//Open the logger
	$cxPanelLogger->open();
	$cxPanelLogger->debug("Starting CXPanel module");

	//Create the manager entry if it does not exist
	cxpanel_create_manager();

	//Get the agent login context
	$agentLoginContext = cxpanel_get_agent_login_context();
	$cxPanelLogger->debug("Agent login context: " . $agentLoginContext);

	//Get the agent interface type
	$agentInterfaceType = cxpanel_get_agent_interface_type();
	$cxPanelLogger->debug("Agent interface type: " . $agentInterfaceType);

	//Query the parking timeout
	$parkingTimeout = cxpanel_get_parking_timeout();
	$cxPanelLogger->debug("Parking lot timeout: " . $parkingTimeout);

	//Generate the custom contexts
	cxpanel_add_contexts("c-x-3-operator-panel", "XMLNamespace", $parkingTimeout);

	//Execute modify script and continue on without waiting for return
	$cxPanelLogger->debug("Executing modify.php");
	exec("php " . $amp_conf['AMPWEBROOT'] . "/admin/modules/cxpanel/modify.php > /dev/null 2>/dev/null &");

	$runningTimeStop = microtime(true);
	$cxPanelLogger->debug("Total Running Time:" . ($runningTimeStop - $runningTimeStart) . "s");

	//Close the logger
	$cxPanelLogger->close();
}

/**
 * Hook that provides the panel settings UI section on the user managemnet page.
 */
function cxpanel_hook_userman() {
	global $currentcomponent, $cxpanelBrandName, $cxPanelLogger;

	$html = '';

	//Do not show the UI addition if sync_with_userman is disabled
	$serverSettings = cxpanel_server_get();
	if($serverSettings['sync_with_userman'] == '1') {

		//Setup userman
		$userman = setup_userman();

		//Query page state
		$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
		$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : null;

		//Only show the gui elements if we are on the add or edit page for the user
		if($action == 'showuser' || $action == 'adduser') {

			//If the user is specified lookup the information for the UI
			if($user != null) {
				$addUser = $userman->getModuleSettingByID($user, 'cxpanel', 'add');
				$addUser = $addUser === false ? '1' : $addUser;
			} else {
				$addUser = '1';
			}

			//Define the section
			$section = $cxpanelBrandName . ' Settings';

			//Create the add GUI element
			$yesNoValueArray = array(array("text" => "yes", "value" => "1"), array("text" => "no", "value" => "0"));
			$addToPanel = new cxpanel_radio("cxpanel_add_user", $yesNoValueArray, $addUser, "Add to $cxpanelBrandName", "Makes this user available in $cxpanelBrandName.");

			//Create contents
			$html = 	'<table>' .
		   					'<tr class="guielToggle" data-toggle_class="cxpanel">' .
		        				'<td colspan="2" ><h4><span class="guielToggleBut">-  </span>' . _($section) . '</h4><hr></td>' .
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
 * Called when a FreePBX user is added to the system.
 *
 * @param Int $id The User Manager ID
 * @param String $display The page in FreePBX that initiated this function
 * @param Array $data an array of all relevant data returned from User Manager
 */
function cxpanel_userman_add($id, $display, $data) {
	$userman = setup_userman();

	//Set the add flag on the user
	$add = isset($_REQUEST['cxpanel_add_user']) ? $_REQUEST['cxpanel_add_user'] : '1';
	$userman->setModuleSettingByID($id, 'cxpanel', 'add', $add);

	//Mark the user's password as dirty
	$userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', '1');

	//Flag FreePBX for reload
	needreload();
}

/**
 * Called when a FreePBX user is updated in the system.
 *
 * @param Int $id The User Manager ID
 * @param String $display The page in FreePBX that initiated this function
 * @param Array $data an array of all relevant data returned from User Manager
 */
function cxpanel_userman_update($id, $display, $data) {
	$userman = setup_userman();

	//Set the add flag on the user
	$add = isset($_REQUEST['cxpanel_add_user']) ? $_REQUEST['cxpanel_add_user'] : '1';
	$userman->setModuleSettingByID($id, 'cxpanel', 'add', $add);

	//If a new password was set mark the user's password as dirty
	$passwordDirty = !empty($data['password']) ? '1' : '0';
	$userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', $passwordDirty);

	//Flag FreePBX for reload if the values have changed
	needreload();
}

/**
 *
 * Function used to hook the extension/user page in FreePBX
 * @param String $pagename the name of the page being loaded
 *
 */
function cxpanel_configpageinit($pagename) {
	global $currentcomponent;

	//Query page state
	$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
	$extdisplay = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
	$extension = isset($_REQUEST["extension"]) ? $_REQUEST["extension"] : null;
	$tech_hardware = isset($_REQUEST["tech_hardware"]) ? $_REQUEST["tech_hardware"] : null;

	//Based on the page state determine if the display or process functions should be added
	if (($pagename != "users") && ($pagename != "extensions")) {
		return;
	} else if ($tech_hardware != null || $pagename == "users") {
		cxpanel_extension_applyhooks();
		$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
	} elseif ($action == "add" || $action == "edit") {
		$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
	} elseif ($extdisplay != '') {
		cxpanel_extension_applyhooks();
		$currentcomponent->addprocessfunc('cxpanel_extension_configprocess', 8);
	}
}

/**
 *
 * Applies hooks to the extension page
 *
 */
function cxpanel_extension_applyhooks() {
	global $currentcomponent;
	$currentcomponent->addguifunc("cxpanel_extension_configpageload");
}

/**
 *
 * Contributes the panel gui elements to the extension page
 *
 */
function cxpanel_extension_configpageload() {
	global $currentcomponent, $cxpanelUserPasswordMask, $cxpanelBrandName;

	//Query page state
	$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
	$display = isset($_REQUEST["display"]) ? $_REQUEST["display"] : null;
	$extension = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;

	//Attempt to query element if not found set defaults
	if(($extension !== null) && (($cxpanelUser = cxpanel_user_get($extension)) !== null)) {
		$addExtension = $cxpanelUser["add_extension"];
		$full = $cxpanelUser["full"];
		$addUser = $cxpanelUser["add_user"];
		$autoAnswer = $cxpanelUser["auto_answer"];
		$password = $cxpanelUserPasswordMask;

		//Build list of bound extensions
		$extensionListValues = cxpanel_user_extension_list($extension);
		$boundExtensionList = array();
		foreach($extensionListValues as $extensionListValue) {
			if($extensionListValue['user_id'] == $extension) {
				array_push($boundExtensionList, "self");
			} else {
				array_push($boundExtensionList, $extensionListValue['user_id']);
			}
		}

		//Build list of phone numbers for the user
		$phoneNumberValues = cxpanel_phone_number_list($extension);
		$phoneNumberList = array();
		foreach($phoneNumberValues as $phoneNumber) {
			array_push($phoneNumberList, $phoneNumber['phone_number'] . "@#" . $phoneNumber['type']);
		}

		//If the user has an inital password set display the inital password and if it is still valid or not
		if($cxpanelUser["initial_password"] != "") {
			$valid = sha1($cxpanelUser['initial_password']) == $cxpanelUser['hashed_password'];
			if($valid) {
				$initalPasswordDisplay = "The inital password for this user is set to <b>" . $cxpanelUser["initial_password"] . "</b>";
			} else {
				$initalPasswordDisplay = "The inital password for this user was never created or has been changed.<br/>If you do not know the password for this user you can change it in the User Password field above.<br/>";
			}
		}

	} else {
		$addExtension = "1";
		$addUser = "1";
		$full = "1";
		$autoAnswer = "0";
		$password = "";
		$initalPasswordDisplay = "";
		$boundExtensionList = array("self");
		$phoneNumberList = array();
	}

	//Create GIU elements if not on delete page
	if ($action != "del") {
		$section = _("$cxpanelBrandName Settings");

		$yesNoValueArray = array(array("text" => "yes", "value" => "1"), array("text" => "no", "value" => "0"));
		$yesNoAddUserValueArray = array(array("text" => "yes", "value" => "1", "onclick" => "document.getElementById('cxpanel_extensions').disabled = false; document.getElementById('cxpanel_password').disabled = false;"),
										array("text" => "no", "value" => "0", "onclick" => "document.getElementById('cxpanel_extensions').disabled = true; document.getElementById('cxpanel_password').disabled = true;"));

		//Build the extension properties
		$currentcomponent->addguielem($section,	new cxpanel_radio("cxpanel_add_extension", $yesNoValueArray, $addExtension, "Add to $cxpanelBrandName", "Makes this extension available in $cxpanelBrandName."));
		$currentcomponent->addguielem($section,	new cxpanel_radio("cxpanel_auto_answer", $yesNoValueArray, $autoAnswer, "Auto Answer", "Makes this extension automatically answer the initial call received from the system when performing an origination within $cxpanelBrandName. Only works with Aastra, Grandstream, Linksys, Polycom, and Snom phones."));

		//If sync_with_userman is not enabled show the user settings
		$serverSettings = cxpanel_server_get();
		if($serverSettings['sync_with_userman'] != '1' || !function_exists('setup_userman')) {

			//Build the user properties
			$currentcomponent->addguielem($section,	new cxpanel_radio("cxpanel_add_user", $yesNoAddUserValueArray, $addUser, "Create User", "Creates an $cxpanelBrandName user login which is associated with this extension."));
			$currentcomponent->addguielem($section,	new cxpanel_radio("cxpanel_full_user", $yesNoValueArray, $full, "Full User", "Makes this extension a full user in $cxpanelBrandName. Full users have access to all the fuctionality in $cxpanelBrandName that the current license allows. The amount of full users allowed in $cxpanelBrandName is restricted via the license. If you mark this user as a full user and there are no more user licenes available the user will remain a lite user."));
			$currentcomponent->addguielem($section,	new cxpanel_radio("cxpanel_email_new_pass", $yesNoValueArray, "0", "Email Password", "When checked the new specified password will be sent to the email cofigured in the voicemail settings. No email will be sent if no email address is specified or the password is not changing."));
			$currentcomponent->addguielem($section, new gui_password("cxpanel_password", $password, "User Password", "Specifies the password to be used for the $cxpanelBrandName User.", "", "", true, "100", !$addUser));

			//Build extension select
			$extensionListValues = cxpanel_user_list();
			$sortedExtensionList = array();
			foreach($extensionListValues as $extensionListValue) {
				if($extensionListValue['user_id'] != $extension) {
					$sortedExtensionList[$extensionListValue["user_id"]] = array("text" => $extensionListValue["user_id"] . " (" . $extensionListValue["display_name"] . ")", "value" => $extensionListValue["user_id"]);
				}
			}
			ksort($sortedExtensionList, SORT_STRING);
			array_unshift($sortedExtensionList, array("text" => "Self", "value" => "self"));

			$extensionListToolTip = "Specifies which extensions will be bound to the $cxpanelBrandName user created for this extension. \"Self\" refers to this extension.";
			$currentcomponent->addguielem($section, new cxpanel_multi_selectbox("cxpanel_extensions", $sortedExtensionList, "10", $boundExtensionList, "User Extensions", $extensionListToolTip, false, "", !$addUser));

			//Add list of phone numbers for the user
			$currentcomponent->addguielem($section, new cxpanel_phone_number_list("cxpanel_phone_numbers", $phoneNumberList, "Alt. Phone Numbers", "Manages alternative phone numbers for this $cxpanelBrandName user."));

			//If the user has an inital password set display the inital password and if it is still valid or not
			if($initalPasswordDisplay != "") {
				$currentcomponent->addguielem($section, new gui_label("cxpanel_inital_password_display", $initalPasswordDisplay));

				//Check if there is a valid email address and password
				$voiceMailBox = voicemail_mailbox_get($extension);
				$validPass = (sha1($cxpanelUser['initial_password']) == $cxpanelUser['hashed_password']);
				$hasEmail = $voiceMailBox != null && isset($voiceMailBox['email']) && $voiceMailBox['email'] != "";

				//If the password is still valid create a link that allows the password to be emailed
				if($validPass && $hasEmail) {
					$linkUrl = cxpanel_get_current_url() . "&cxpanel_email_pass=1";
					$currentcomponent->addguielem($section, new gui_link("cxpanel_email_pass_link", "Email Inital Password", $linkUrl));
				}
			}

			//Create validation javascript that is called when the form is submited
			$js = " if($('input[name=cxpanel_add_user]:checked').val() == '1' &&
						document.getElementById('cxpanel_password').value == '') {
						alert('Please specify a password for the $cxpanelBrandName user or uncheck \"Create User\" under \"$cxpanelBrandName User Settings\"');
						return false;
					}";
			$currentcomponent->addjsfunc('onsubmit()', $js);
		}
	}
}

/**
 *
 * Handles additions removals and updates of extensions.
 *
 */
function cxpanel_extension_configprocess() {
	global $cxpanelUserPasswordMask;

	//Check if the action was aborted
	if(isset($GLOBALS['abort']) && $GLOBALS['abort']) {
		return;
	}

	//Query page state
	$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
	$ext = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
	$extn = isset($_REQUEST["extension"]) ? $_REQUEST["extension"]: null;
	$name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : null;
	$extension = ($ext == "") ? $extn : $ext;

	//Determine peer
	if(isset($_REQUEST["devinfo_dial"]) && ($_REQUEST["devinfo_dial"] != "")) {
		$peer = $_REQUEST["devinfo_dial"];
	} else if (isset($_REQUEST["tech"])){
		$peer = strtoupper($_REQUEST["tech"]) . "/" . $extension;
	} else {
		$peer = "SIP/$extension";
	}

	$addExtension = $_REQUEST["cxpanel_add_extension"] == "1";
	$autoAnswer = $_REQUEST["cxpanel_auto_answer"] == "1";
	$addUser = $_REQUEST["cxpanel_add_user"] == "1";
	$full = $_REQUEST["cxpanel_full_user"] == "1";
	$emailPassword = $_REQUEST["cxpanel_email_new_pass"] == "1";
	$password = isset($_REQUEST['cxpanel_password']) ? trim($_REQUEST["cxpanel_password"]) : $cxpanelUserPasswordMask;
	$extensionList = isset($_REQUEST['cxpanel_extensions']) ? $_REQUEST['cxpanel_extensions'] : array();
	$phoneNumberList = isset($_REQUEST['cxpanel_phone_numbers-values']) ? $_REQUEST['cxpanel_phone_numbers-values'] : array();

	//Modify DB
	if(($extension !== null) && ($extension != "") && ($action !== null)) {

		//Check if this extension needs to be deleted, updated, or added
		if($action == "del") {

			//Clean up all extension relationships
			cxpanel_sync_user_extensions($extension, array());

			//Delete the user
			cxpanel_user_del($extension);

		} else if(($action == "add") || ($action == "edit") && ($name !== null)) {

			//Check if this is an addition or edit
			$addition = cxpanel_user_get($extension) === null;

			/*
			 * If the cxpanel_full_user setting is not set we have hidded the user settings
			 * due to the fact that sync_with_userman is enabled. If so handle the creation and
			 * editing of the user differently.
			 */
			if(!isset($_REQUEST['cxpanel_full_user'])) {

				//Add or update user
				if($addition) {

					//Check if a user is being created for this extension. If so get the password set for the extension's user else create an initial password.
					$password = cxpanel_generate_password(10);
					if($_REQUEST['userman|assign'] == 'add' && !empty($_REQUEST['userman|password'])) {
						$password = $_REQUEST['userman|password'];
					}

					//Add the user
					cxpanel_user_add_with_initial_password($extension, $addExtension, true, $password, $autoAnswer, $peer, $name, true, $extension);

					//Mark the user's password as dirty
					cxpanel_mark_user_password_dirty($extension, true);
				} else {

					//Edit just the extension settings on the user
					cxpanel_extension_update($extension, $addExtension, $autoAnswer, $peer, $name);
				}
			} else {

				//Add or update user
				if($addition) {
					cxpanel_user_add($extension, $addExtension, $addUser, $password, $autoAnswer, $peer, $name, $full);
				} else {
					cxpanel_user_update($extension, $addExtension, $addUser, $password, $autoAnswer, $peer, $name, $full);
				}

				//Sync extension list
				cxpanel_sync_user_extensions($extension, $extensionList);

				//Sync phone number list
				cxpanel_phone_number_del($extension);
				foreach($phoneNumberList as $phoneNumber) {
					$phoneNumberParts = explode('@#', $phoneNumber);
					cxpanel_phone_number_add($extension, $phoneNumberParts[0], $phoneNumberParts[1]);
				}

				//Check if the password needs to be sent
				if(	$password != $cxpanelUserPasswordMask && $emailPassword &&
					isset($_REQUEST['email']) && $_REQUEST['email'] != "") {
					cxpanel_send_password_email($extension, $password, $_REQUEST['email']);
				}

				//Check if the password needs to be marked as dirty
				if($password != $cxpanelUserPasswordMask) {
					cxpanel_mark_user_password_dirty($extension, true);
				}
			}
		}
	}
}

/**
 *
 * Contributes the panel gui elements to the queue page
 * @param String $viewing_itemid the id of the item being viewed
 * @param String $target_menuid the menu id of the page being loaded
 *
 */
function cxpanel_hook_queues($viewing_itemid, $target_menuid) {
	global $cxpanelBrandName;

	//Query page state
	$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
	$display = "";

	//Only hook queues page
	if(($target_menuid == "queues") && ($action != "delete")) {

		//Query queue info
		if(($viewing_itemid != null) && ($queue = cxpanel_queue_get($viewing_itemid))) {
			$checked = ($queue["add_queue"] == "1") ? "checked" : "";
		} else {
			$checked = "checked";
		}

		//Build display
		$display = "	<tr><td colspan=\"2\"><h5>$cxpanelBrandName<hr></h5></td></tr>
						<tr>
							<td><a href=\"#\" class=\"info\">" . _("Add to $cxpanelBrandName") . "<span>" . _("Makes this queue available in $cxpanelBrandName") . "</span></a></td>
							<td><input type=\"checkbox\" name=\"cxpanel_add_queue\" id=\"cxpanel_add_queue\" value=\"on\" $checked/></td>
						</tr>";
	}

	return $display;
}

/**
 *
 * Handles additions removals and updates of queues.
 *
 */
function cxpanel_hookProcess_queues($viewing_itemid, $request) {

	//Query page state
	$queue = isset($request["extdisplay"]) ? $request["extdisplay"] : null;
	$account = isset($request["account"]) ? $request["account"] : null;
	$action = isset($request["action"]) ? $request["action"] : null;
	$name = isset($request["name"]) ? $request["name"] : null;
	$queue = ($queue == null) ? $account : $queue;

	/*
	 * On addition check if the extension is in conflict with another account.
	 * If this is the case we can assume that the FreePBX core
	 * is not going to add this object so we must abort adding it
	 * to the table.
	 *
	 * Note this works because we are called first before the hooked
	 * module makes its own check and addition.
	 */
	if($action == "add") {
		$usage_arr = framework_check_extension_usage($account);
		if (!empty($usage_arr)) {
			return;
		}
	}

	//Query add option
	$addQueue = isset($request["cxpanel_add_queue"]);

	//Update DB
	if(($queue != null) && ($queue != "") && ($action != null)) {

		//Check if this queue needs to be deleted, updated, or added
		if($action == "delete") {
			cxpanel_queue_del($queue);
		} else if(($action == "add") || ($action == "edit") && ($name !== null)) {
			if(cxpanel_queue_get($queue) === null) {
				cxpanel_queue_add($queue, $addQueue, $name);
			} else {
				cxpanel_queue_update($queue, $addQueue, $name);
			}

			cxpanel_queue_eventwhencalled_modify($addQueue);
			cxpanel_queue_eventmemberstatus_modify($addQueue);
		}
	}
}

/**
 *
 * Contributes the panel gui elements to the conference room page
 * @param String $viewing_itemid the id of the item being viewed
 * @param String $target_menuid the menu id of the page being loaded
 *
 */
function cxpanel_hook_conferences($viewing_itemid, $target_menuid) {
	global $cxpanelBrandName;

	//Query page state
	$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
	$display = "";

	//Only hook conferences page
	if(($target_menuid == "conferences") && ($action != "delete")) {

		//Query conference info
		if(($viewing_itemid != null) && ($conferenceRoom = cxpanel_conference_room_get($viewing_itemid))) {
			$checked = ($conferenceRoom["add_conference_room"] == "1") ? "checked" : "";
		} else {
			$checked = "checked";
		}

		//Build display
		$display = "	<tr><td colspan=\"2\"><h5>$cxpanelBrandName<hr></h5></td></tr>
						<tr>
							<td><a href=\"#\" class=\"info\">" . _("Add to $cxpanelBrandName") . "<span>" . _("Makes this conference room available in $cxpanelBrandName") . "</span></a></td>
							<td><input type=\"checkbox\" name=\"cxpanel_add_conference_room\" id=\"cxpanel_add_conference_room\" value=\"on\" $checked/></td>
						</tr>";
	}

	return $display;
}

/**
 *
 * Handles additions removals and updates of queues.
 *
 */
function cxpanel_hookProcess_conferences($viewing_itemid, $request) {

	//Query page state
	$conferenceRoom = isset($request["extdisplay"]) ? $request["extdisplay"] : null;
	$account = isset($request["account"]) ? $request["account"] : null;
	$action = isset($request["action"]) ? $request["action"] : null;
	$name = isset($request["name"]) ? $request["name"] : null;
	$conferenceRoom = ($conferenceRoom == null) ? $account : $conferenceRoom;

	/*
	 * On addition check if the extension is in conflict with another account.
	 * If this is the case we can assume that the FreePBX core
	 * is not going to add this object so we must abort adding it
	 * to the table.
	 *
	 * Note this works because we are called first before the hooked
	 * module makes its own check and addition.
	 */
	if($action == "add") {
		$usage_arr = framework_check_extension_usage($account);
		if (!empty($usage_arr)) {
			return;
		}
	}

	//Query add option
	$addConferenceRoom = isset($request["cxpanel_add_conference_room"]);

	//Update DB
	if(($conferenceRoom != null) && ($conferenceRoom != "") && ($action != null)) {

		//Check if this conference room needs to be deleted, updated, or added
		if($action == "delete") {
			cxpanel_conference_room_del($conferenceRoom);
		} else if(($action == "add") || ($action == "edit") && ($name !== null)) {
			if(cxpanel_conference_room_get($conferenceRoom) === null) {
				cxpanel_conference_room_add($conferenceRoom, $addConferenceRoom, $name);
			} else {
				cxpanel_conference_room_update($conferenceRoom, $addConferenceRoom, $name);
			}
		}
	}
}

/**
 *
 * API function to update the server information
 * @param String $name the slug of the core server to edit
 * @param String $asteriskHost ip or host name used by the panel to connect to the AMI
 * @param String $clientHost ip or host name of the panel client
 * @param Integer $clientPort web port of the panel client
 * @param String $apiHost ip or host name of the panel server REST API
 * @param Integer $apiPort web port of the panel server REST API
 * @param String $apiUserName panel API username used for API authentication
 * @param String $apiPassword panel API password used for API authentication
 * @param Boolean $apiUseSSL if true https will be used for communication with the REST API
 * @param Boolean $syncWithUserman if true the User Management module will control the users that are created in the panel
 *
 */
function cxpanel_server_update($name, $asteriskHost, $clientHost, $clientPort, $apiHost, $apiPort, $apiUserName, $apiPassword, $apiUseSSL, $syncWithUserman) {
	global $db;
	$prepStatement = $db->prepare("UPDATE cxpanel_server SET name = ?, asterisk_host = ?, client_host = ?, client_port = ?, api_host = ?, api_port = ?, api_username = ?, api_password = ?, api_use_ssl = ?, sync_with_userman = ?");
	$values = array($name, $asteriskHost, $clientHost, $clientPort, $apiHost, $apiPort, $apiUserName, $apiPassword, $apiUseSSL, $syncWithUserman);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API fucntion to get the server information
 *
 */
function cxpanel_server_get() {
	global $db;
	$query = "SELECT * FROM cxpanel_server";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * API function to update the voicemail agent information
 * @param String $identifier the agent identifier
 * @param String $directory the root voicemail directory path
 * @param String $resourceHost hostname or ip used to build voicemail playback urls
 * @param String $resourceExtension file extension used to build voicemail playback urls
 *
 */
function cxpanel_voicemail_agent_update($identifier, $directory, $resourceHost, $resourceExtension) {
	global $db;
	$prepStatement = $db->prepare("UPDATE cxpanel_voicemail_agent SET identifier = ?, directory = ?, resource_host = ?, resource_extension = ?");
	$values = array($identifier, $directory, $resourceHost, $resourceExtension);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API fucntion to get the voicemail agent information
 *
 */
function cxpanel_voicemail_agent_get() {
	global $db;
	$query = "SELECT * FROM cxpanel_voicemail_agent";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * API function to update the recording agent information
 * @param String $identifier the agent identifier
 * @param String $directory the root recording directory path
 * @param String $resourceHost hostname or ip used to build recording playback urls
 * @param String $resourceExtension file extension used to build voicemail playback urls
 * @param String $fileNameMask file name mask used to parse recording file names and create recordings
 *
 */
function cxpanel_recording_agent_update($identifier, $directory, $resourceHost, $resourceExtension, $fileNameMask) {
	global $db;
	$prepStatement = $db->prepare("UPDATE cxpanel_recording_agent SET identifier = ?, directory = ?, resource_host = ?, resource_extension = ?, file_name_mask = ?");
	$values = array($identifier, $directory, $resourceHost, $resourceExtension, $fileNameMask);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API fucntion to get the recording agent information
 *
 */
function cxpanel_recording_agent_get() {
	global $db;
	$query = "SELECT * FROM cxpanel_recording_agent";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * API fucntion to get the email information
 *
 */
function cxpanel_email_get() {
	global $db;
	$query = "SELECT * FROM cxpanel_email";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 * API function to update the email information
 * @param String $subject the subject of the email
 * @param String $body the body of the email
 */
function cxpanel_email_update($subject, $body) {
	global $db;
	$prepStatement = $db->prepare("UPDATE cxpanel_email SET subject = ?, body = ?");
	$values = array($subject, $body);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to add a user
 * @param String $userId the user id of the FreePBX user
 * @param Boolean $addExtension true if an extension should be created for the user
 * @param Boolean $addUser true if a user login should be created for the user
 * @param String $password the user login password for the user
 * @param Boolean $autoAnswer true if the user's extension should autoanswer origination callbacks in the panel
 * @param String $peer the peer value for the extension
 * @param String $displayName the user and extension display name
 * @param Boolean $full true if the user should be a full user
 *
 */
function cxpanel_user_add($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full) {
	global $db;
	$addUser = $addUser ? "1" : "0";
	$addExtension = $addExtension ? "1" : "0";
	$autoAnswer = $autoAnswer ? "1" : "0";
	$full = $full ? "1" : "0";

	//Hash the password
	$hashedPassword = sha1($password);

	$prepStatement = $db->prepare("INSERT INTO cxpanel_users (user_id, add_extension, add_user, initial_password, hashed_password, auto_answer, display_name, peer, full) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$values = array($userId, $addExtension, $addUser, "", $hashedPassword, $autoAnswer, $displayName, $peer, $full);
	$db->execute($prepStatement, $values);
}

/**
 *
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
 *
 */
function cxpanel_user_add_with_initial_password($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full, $parentUserId) {
	global $db;
	$addUser = $addUser ? "1" : "0";
	$addExtension = $addExtension ? "1" : "0";
	$autoAnswer = $autoAnswer ? "1" : "0";
	$full = $full ? "1" : "0";

	//Hash the password
	$hashedPassword = sha1($password);

	$prepStatement = $db->prepare("INSERT INTO cxpanel_users (user_id, add_extension, add_user, initial_password, hashed_password, auto_answer, display_name, peer, full, parent_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$values = array($userId, $addExtension, $addUser, $password, $hashedPassword, $autoAnswer, $displayName, $peer, $full, $parentUserId);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to update a user
 * @param String $userId the user id of the FreePBX user
 * @param Boolean $addExtension true if an extension should be created for the user
 * @param Boolean $addUser true if a user login should be created for the user
 * @param String $password the user login password for the user if this is equal to the global $cxpanelUserPasswordMask the password will not be updated
 * @param Boolean $autoAnswer true if the user's extension should autoanswer origination callbacks in the panel
 * @param String $peer the peer value for the extension
 * @param String $displayName the user and extension display name
 * @param Boolean $full true if the user should be a full user
 *
 */
function cxpanel_user_update($userId, $addExtension, $addUser, $password, $autoAnswer, $peer, $displayName, $full) {
	global $db, $cxpanelUserPasswordMask;
	$addUser = $addUser ? "1" : "0";
	$addExtension = $addExtension ? "1" : "0";
	$autoAnswer = $autoAnswer ? "1" : "0";
	$full = $full ? "1" : "0";

	/**
	 * Check if the given password is equal to the password
	 * mask if it is not the password has been changed so we
	 * need to create a new hashed version of the password.
	 */
	$passModify = "";
	$hashedPassword = "";
	if($password != $cxpanelUserPasswordMask) {
		$passModify = ", hashed_password = ?";
		$hashedPassword = sha1($password);
	}

	$prepStatement = $db->prepare("UPDATE cxpanel_users SET add_extension = ?, add_user = ?, auto_answer = ?, peer = ?, display_name = ?, full = ? $passModify WHERE user_id = ?");

	if($hashedPassword == "") {
		$values = array($addExtension, $addUser, $autoAnswer, $peer, $displayName, $full, $userId);
	} else {
		$values = array($addExtension, $addUser, $autoAnswer, $peer, $displayName, $full, $hashedPassword, $userId);
	}

	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to update only the extension properties on a user.
 *
 * @param String $userId of the record to update
 * @param Boolean $addExtension true if the extension should be created
 * @param Boolean $autoAnswer true if the extension should autoanswer origination callbacks in the panel
 * @param String $peer the peer value for the extension
 * @param String $displayName the display name of the extension
 */
function cxpanel_extension_update($userId, $addExtension, $autoAnswer, $peer, $displayName) {
	global $db;
	$addExtension = $addExtension ? "1" : "0";
	$autoAnswer = $autoAnswer ? "1" : "0";

	$prepStatement = $db->prepare("UPDATE cxpanel_users SET add_extension = ?, auto_answer = ?, peer = ?, display_name = ? WHERE user_id = ?");
	$values = array($addExtension, $autoAnswer, $peer, $displayName, $userId);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function used to set the parent user id of a specified user
 * @param String $userId the user id to set the parent user id on.
 * @param String $parentUserId parent user id
 *
 */
function cxpanel_user_set_parent_user_id($userId, $parentUserId) {
	global $db;
	$prepStatement = $db->prepare("UPDATE cxpanel_users SET parent_user_id = ? WHERE user_id = ?");
	$values = array($parentUserId, $userId);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to delete a user
 * @param String $userId the FreePBX user id of the user to delete
 *
 */
function cxpanel_user_del($userId) {
	global $db;
	$query = "DELETE FROM cxpanel_users WHERE user_id = '$userId'";
	$db->query($query);

	//Delete the user's associated phone numbers
	cxpanel_phone_number_del($userId);
}

/**
 *
 * API function to get a list of users
 *
 */
function cxpanel_user_list() {
	global $db;
	$query = "SELECT * FROM cxpanel_users";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to get a specific user
 * @param String $userId the FreePBX user id of the user to get
 *
 */
function cxpanel_user_get($userId) {
	global $db;
	$query = "SELECT * FROM cxpanel_users WHERE user_id = '$userId'";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * API function to get a list of the specified users bound extensions
 * @param String $userId the parent user id
 *
 */
function cxpanel_user_extension_list($userId) {
	global $db;
	$query = "SELECT * FROM cxpanel_users WHERE parent_user_id = '$userId'";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to mark a user's password as dirty or clean.
 * Passwords that have been marked as dirty will be pushed to
 * the server on reload.
 *
 * @param String $userId the user id to mark
 * @param Boolean $dirty true to mark as dirty or false to mark as clean
 */
function cxpanel_mark_user_password_dirty($userId, $dirty) {
	global $db;
	$dirtyString = $dirty ? "1" : "0";
	$prepStatement = $db->prepare("UPDATE cxpanel_users SET password_dirty = ? WHERE user_id = ?");
	$values = array($dirtyString, $userId);
	$db->execute($prepStatement, $values);
}

/**
*
* API function to mark all user password as dirty or clean.
* Passwords that have been marked as dirty will be pushed to
* the server on reload.
*
* @param Boolean $dirty true to mark as dirty or false to mark as clean
*/
function cxpanel_mark_all_user_passwords_dirty($dirty) {
	global $db;
	$dirtyString = $dirty ? "1" : "0";

	//Mark the cxpanel users
	$prepStatement = $db->prepare("UPDATE cxpanel_users SET password_dirty = ?");
	$values = array($dirtyString);
	$db->execute($prepStatement, $values);

	//Mark the FreePBX users
	if(function_exists('setup_userman')) {
		$userman = setup_userman();
		$freePBXUsers = $userman->getAllUsers();
		foreach($freePBXUsers as $freePBXUser) {
			$userman->setModuleSettingByID($freePBXUser['id'], 'cxpanel', 'password_dirty', $dirtyString);
		}
	}
}

/**
 *
 * API function to get a list of all phone numbers
 *
 */
function cxpanel_phone_number_list_all() {
	global $db;
	$query = "SELECT * FROM cxpanel_phone_number";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to get a list of phone numbers associated with a user
 * @param String $userId the user id to get the list of phone numbers for
 *
 */
function cxpanel_phone_number_list($userId) {
	global $db;
	$query = "SELECT * FROM cxpanel_phone_number WHERE user_id = '$userId'";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to delete all phone numbers for a user
 * @param String $userId the user id to delete the phone number for
 */
function cxpanel_phone_number_del($userId) {
	global $db;
	$query = "DELETE FROM cxpanel_phone_number WHERE user_id = '$userId'";
	$db->query($query);
}

/**
 *
 * API function to add a phone number
 * @param String $userId the user id to add the phone number for
 * @param String $phoneNumber the phone number
 * @param String $type the type of the phone number
 */
function cxpanel_phone_number_add($userId, $phoneNumber, $type) {
	global $db;
	$prepStatement = $db->prepare("INSERT INTO cxpanel_phone_number (user_id, phone_number, type) VALUES (?, ?, ?)");
	$values = array($userId, $phoneNumber, $type);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to add a queue
 * @param String $queueId the FreePBX queue id
 * @param Boolean $addQueue true if the queue should be added to the panel
 * @param String $displayName the display name of the queue
 *
 */
function cxpanel_queue_add($queueId, $addQueue, $displayName) {
	global $db;
	$addQueue = $addQueue ? "1" : "0";
	$prepStatement = $db->prepare("INSERT INTO cxpanel_queues (queue_id, add_queue, display_name) VALUES (?, ?, ?)");
	$values = array($queueId, $addQueue, $displayName);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to update a queue
 * @param String $queueId the FreePBX queue id to edit
 * @param Boolean $addQueue true if the queue shoudl be added to the panel
 * @param String $displayName the display name of the queue
 *
 */
function cxpanel_queue_update($queueId, $addQueue, $displayName) {
	global $db;
	$addQueue = $addQueue ? "1" : "0";
	$prepStatement = $db->prepare("UPDATE cxpanel_queues SET add_queue = ?, display_name = ? WHERE queue_id = $queueId");
	$values = array($addQueue, $displayName);
	$db->execute($prepStatement, $values, $displayName);
}

/**
 *
 * API function to delete a queue
 * @param String $queueId the FreePBX queue id to delete
 *
 */
function cxpanel_queue_del($queueId) {
	global $db;
	$query = "DELETE FROM cxpanel_queues WHERE queue_id = '$queueId'";
	$db->query($query);
}

/**
 *
 * API function to get the list of queues
 *
 */
function cxpanel_queue_list() {
	global $db;
	$query = "SELECT * FROM cxpanel_queues";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to get a specific queue
 * @param String $queueId the FreePBX queue id of the queue to get
 *
 */
function cxpanel_queue_get($queueId) {
	global $db;
	$query = "SELECT * FROM cxpanel_queues WHERE queue_id = '$queueId'";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * API function to add a conference room
 * @param String $conferenceRoomId the FreePBX conference room id
 * @param Boolean $addConferenceRoom true if the conference room should be added to the panel
 * @param String $displayName the display name of the conference room
 *
 */
function cxpanel_conference_room_add($conferenceRoomId, $addConferenceRoom, $displayName) {
	global $db;
	$addConferenceRoom = $addConferenceRoom ? "1" : "0";
	$prepStatement = $db->prepare("INSERT INTO cxpanel_conference_rooms (conference_room_id, add_conference_room, display_name) VALUES (?, ?, ?)");
	$values = array($conferenceRoomId, $addConferenceRoom, $displayName);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to update a conference room
 * @param String $conferenceRoomId the FreePBX conference room id
 * @param Boolean $addConferenceRoom true if the conference room should be added to the panel
 * @param String $displayName the display name of the conference room
 *
 */
function cxpanel_conference_room_update($conferenceRoomId, $addConferenceRoom, $displayName) {
	global $db;
	$addConferenceRoom = $addConferenceRoom ? "1" : "0";
	$prepStatement = $db->prepare("UPDATE cxpanel_conference_rooms SET add_conference_room = ?, display_name = ? WHERE conference_room_id = $conferenceRoomId");
	$values = array($addConferenceRoom, $displayName);
	$db->execute($prepStatement, $values);
}

/**
 *
 * API function to delete a conference room
 * @param String $conferenceRoomId the FreePBX conferenc room id to delete
 *
 */
function cxpanel_conference_room_del($conferenceRoomId) {
	global $db;
	$query = "DELETE FROM cxpanel_conference_rooms WHERE conference_room_id = '$conferenceRoomId'";
	$db->query($query);
}

/**
 *
 * API function to get the list of conference rooms
 *
 */
function cxpanel_conference_room_list() {
	global $db;
	$query = "SELECT * FROM cxpanel_conference_rooms";
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
 *
 * API function to get a specific conference room
 * @param String $conferenceRoomId FreePBX id of the conference room to get
 *
 */
function cxpanel_conference_room_get($conferenceRoomId) {
	global $db;
	$query = "SELECT * FROM cxpanel_conference_rooms WHERE conference_room_id = '$conferenceRoomId'";
	$results = sql($query, "getRow", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return null;
	} else {
		return $results;
	}
}

/**
 *
 * Updates the request eventwhencalled flag when editing a queue.
 * Used to force the eventwhencalled flag when adding a queue.
 * @param Boolean $addQueue true if the queue is being added
 *
 */
function cxpanel_queue_eventwhencalled_modify($addQueue) {
	$addQueue = $addQueue ? "1" : "0";
	if ($addQueue == "1") {
		$_REQUEST['eventwhencalled'] = 'yes';
	}
}

/**
 *
 * Updates the request eventmemberstatus flag when editing a queue.
 * Used to force the eventmemberstatus flag when adding a queue.
 * @param Boolean $addQueue true if the queue is being added
 *
 */
function cxpanel_queue_eventmemberstatus_modify($addQueue) {
	$addQueue = $addQueue ? "1" : "0";
	if ($addQueue == "1") {
		$_REQUEST['eventmemberstatus'] = 'yes';
	}
}

/**
 *
 * Creates the manager connection if it does not exist
 *
 */
function cxpanel_create_manager() {
	global $cxPanelLogger;

	$cxPanelLogger->debug("Checking manager connection");

	//Check if a manager profile exists for cxpanel if not create it.
	$managerFound = false;
	if((function_exists("manager_list")) && (($managers = manager_list()) !== null)) {

		//Search for cxpanel manager
		foreach($managers as $manager) {
			if($manager['name'] == "cxpanel" ) {
				$managerFound = true;
				break;
			}
		}
	}

	//If not found create a manager profile for cxpanel
	if((function_exists("manager_add")) && (!$managerFound)) {
		$cxPanelLogger->debug("Creating manager connection");
		manager_add("cxpanel", "cxmanager*con", "0.0.0.0/0.0.0.0", "127.0.0.1/255.255.255.0", "all", "all");

		if(function_exists("manager_gen_conf")) {
			manager_gen_conf();
		}
	}
}

/**
 *
 * Get the agent login context that should be
 * used based on the version of FreePBX
 *
 */
function cxpanel_get_agent_login_context() {
	$freepbxVersion = get_framework_version();
	$freepbxVersion = $freepbxVersion ? $freepbxVersion : getversion();
	$agentLoginContext = "from-internal";
	if(version_compare_freepbx($freepbxVersion, "2.6", ">=")) {
		$agentLoginContext = "from-queue";
	}

	return $agentLoginContext;
}

/**
 *
 * Gets the agent interface type based on the
 * version of FreePBX and if dev state is enabled
 *
 */
function cxpanel_get_agent_interface_type() {
	global $amp_conf;

	$agentInterfaceType = "none";
	$info = engine_getinfo();
	$devStateEnabled = isset($amp_conf["USEDEVSTATE"]) && isset($amp_conf["USEQUEUESTATE"]) && $amp_conf["USEDEVSTATE"] === true && $amp_conf["USEQUEUESTATE"] === true;

	if(version_compare($info["version"], "1.6", ">=") || (version_compare($info["version"], "1.4.25", ">=") && !$devStateEnabled)) {
		$agentInterfaceType = "peer";
	} else if(version_compare($info["version"], "1.4.25", ">=") && $devStateEnabled) {
		$agentInterfaceType = "hint";
	} else {
		$agentInterfaceType = "none";
	}

	return $agentInterfaceType;
}

/**
 *
 * Gets the parking lot timeout
 *
 */
function cxpanel_get_parking_timeout() {
	global $db;

	//Query parking timeout
	$parkingTimeout = 200;
	$sql = "SELECT keyword, data FROM parkinglot WHERE id = '1'";
	$results = $db->getAssoc($sql);
	if(!DB::IsError($results)) {
		$parkingTimeout = $results['parkingtime'];
	}

	return $parkingTimeout;
}

/**
 *
 * Creates the dialplan entries
 *
 * @param String $contextPrefix
 * @param String $variablePrefix
 * @param String $parkingTimeout
 */
function cxpanel_add_contexts($contextPrefix, $variablePrefix, $parkingTimeout) {
	global $ext, $cxPanelLogger;

	$cxPanelLogger->debug("Creating contexts ContextPrefix:" . $contextPrefix . " VariablePrefix:" . $variablePrefix);

	$id = $contextPrefix . "-hold";
	$c = '432111';
	$ext->add($id, $c, '', new ext_musiconhold("\${{$variablePrefix}MusicOnHoldClass}"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-voice-mail";
	$c = '432112';
	$ext->add($id, $c, '', new ext_vm("\${{$variablePrefix}VoiceMailBox}@\${{$variablePrefix}VoiceMailBoxContext},u"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-meetme";
	$c = '432113';
	$ext->add($id, $c, '', new ext_meetme("\${{$variablePrefix}MeetMeRoomNumber}", "\${{$variablePrefix}MeetMeRoomOptions}", ""));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-confbridge";
	$c = '432113';
	$ext->add($id, $c, '', new ext_meetme("\${{$variablePrefix}MeetMeRoomNumber}"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-park";
	$c = '432114';
	$ext->add($id, $c, '', new ext_cxpanel_parkandannounce("pbx-transfer:PARKED", "$parkingTimeout", "Local/432116@" . $contextPrefix . "-park-announce-answer", "\${{$variablePrefix}ParkContext},\${{$variablePrefix}ParkExtension},1"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-park-announce-answer";
	$c = '432116';
	$ext->add($id, $c, '', new ext_answer());
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-listen-to-voice-mail";
	$c = '432115';
	$ext->add($id, $c, '', new ext_cxpanel_controlplayback("\${{$variablePrefix}VoiceMailPath}", "1000", "*", "#", "7", "8" , "9"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-listen-to-recording";
	$c = '432118';
	$ext->add($id, $c, '', new ext_cxpanel_controlplayback("\${{$variablePrefix}RecordingPath}", "1000", "*", "#", "7", "8" , "9"));
	$ext->add($id, $c, '', new ext_hangup());

	$id = $contextPrefix . "-spy";
	$c = '432117';
	$ext->add($id, $c, '', new ext_cxpanel_chanspy("\${{$variablePrefix}ChanSpyChannel}", "\${{$variablePrefix}ChanSpyOptions}"));
	$ext->add($id, $c, '', new ext_hangup());
}

/**
 *
 * Syncs the user and extension relationships
 * @param String $userId the user id of the parent
 * @param Array $userExtensions the proposed list of child extensions
 *
 */
function cxpanel_sync_user_extensions($userId, $userExtensions) {
	global $cxpanelUserPasswordMask;

	//Grab the user info
	$user = cxpanel_user_get($userId);

	//Get the users current extension list
	$currentUserExtensionsAssoc = array();
	$currentUserExtensions = cxpanel_user_extension_list($userId);
	foreach($currentUserExtensions as $currentUserExtension) {
		$currentUserExtensionsAssoc[$currentUserExtension['user_id']] = $currentUserExtension;
	}

	//Grab the list of all proposed user extensions
	$newUserExtensionsAssoc = array();
	foreach($userExtensions as $userExtension) {
		if($userExtension != "self") {
			$userExtension = cxpanel_user_get($userExtension);
			$newUserExtensionsAssoc[$userExtension['user_id']] = $userExtension;
		} else {
			$newUserExtensionsAssoc[$user['user_id']] = $user;
		}
	}

	//Unbind all extensions that are no logner a part of the user
	foreach($currentUserExtensionsAssoc as $checkUserId => $checkUser) {
		if(!array_key_exists($checkUserId, $newUserExtensionsAssoc)) {
			cxpanel_user_set_parent_user_id($checkUserId, "");
		}
	}

	//Bind all extensions that are part of the user
	foreach($newUserExtensionsAssoc as $checkUserId => $checkUser) {

		//If the check user is not self, condition the user as a child.
		if($checkUserId != $userId) {

			//Cleanup any bound extensions the check user has since it is no longer a parent
			$cleanupListValues = cxpanel_user_extension_list($checkUserId);
			foreach($cleanupListValues as $cleanupListValue) {
				cxpanel_user_set_parent_user_id($cleanupListValue['user_id'], "");
			}

			//Make sure that the user has the add extension flag cheked
			cxpanel_user_update($checkUser['user_id'], true,
								false,
								$cxpanelUserPasswordMask,
								$checkUser['auto_answer'] == "1",
								$checkUser['peer'],
								$checkUser['display_name'],
								$checkUser['full'] == "1");
		}

		//Set the extension binding on the user
		cxpanel_user_set_parent_user_id($checkUserId, $userId);
	}
}

/**
 * Gets the list of users that are bound to the given extension based on the relationships
 * managed by the userman module.
 *
 * If the userman module is not installed this function will return an empty array.
 *
 * @param String $extension the extension number
 * @return The list of all know users bound to the given extension
 */
function cxpanel_get_freepbx_users_from_extension($extension) {
	global $db;
	$query = 	'SELECT id, username ' .
				'FROM freepbx_users, freepbx_users_settings ' .
				'WHERE freepbx_users.id = freepbx_users_settings.uid AND freepbx_users_settings.key = "assigned" AND ' .
				'freepbx_users_settings.val LIKE "%\\"' . $extension . '\\"%"';
	$results = sql($query, "getAll", DB_FETCHMODE_ASSOC);
	if((DB::IsError($results)) || (empty($results))) {
		return array();
	} else {
		return $results;
	}
}

/**
*
* Send a password email
* @param String $userId user id
* @param String $pass if specified will be used for the password else the inital
* password will be sent
* @param String $email if specified will be used for the email else the email will be
* queried from the vm module
*
*/
function cxpanel_send_password_email($userId, $pass = "", $email = "") {

	//Collect email settings and user data
	$serverInformation = cxpanel_server_get();
	$emailSettings = cxpanel_email_get();
	$cxpanelUser = cxpanel_user_get($userId);
	$voiceMailBox = voicemail_mailbox_get($userId);

	//Determine password to send
	$password = $pass != "" ? $pass : $cxpanelUser['initial_password'];

	//Determine the email
	$email = $email != "" ? $email : $voiceMailBox['email'];

	/*
	 * If set utilize the client_host stored in the database else utilize the host
	 * from the current URL.
	 */
	$clientHost = $serverInformation['client_host'];
	if($clientHost == "") {
		$httpHost = explode(':', $_SERVER['HTTP_HOST']);
		$clientHost = $httpHost[0];
	}

	//Prepare the subject
	$subject = $emailSettings['subject'];
	$subject = str_replace("%%userId%%", $cxpanelUser['user_id'], $subject);
	$subject = str_replace("%%password%%", $password, $subject);
	$subject = str_replace('%%clientURL%%', 'http://' . $clientHost . ':' . $serverInformation['client_port'] . '/client/client', $subject);

	//Prepare the body contents
	$bodyContents = $emailSettings['body'];
	$bodyContents = str_replace("%%userId%%", $cxpanelUser['user_id'], $bodyContents);
	$bodyContents = str_replace("%%password%%", $password, $bodyContents);
	$bodyContents = str_replace('%%clientURL%%', 'http://' . $clientHost . ':' . $serverInformation['client_port'] . '/client/client', $bodyContents);
	$bodyContents = str_replace('%%logo%%', 'cid:logo', $bodyContents);

	//Create new mailer
	$phpMailer = new PHPMailer();
	$phpMailer->isMail();

	//Create the email
	$phpMailer->isHTML(true);
	$phpMailer->addAddress($email);
	$phpMailer->Subject = $subject;
	$phpMailer->Body    = $bodyContents;
	$phpMailer->AltBody = $bodyContents;
	$phpMailer->AddEmbeddedImage(dirname(__FILE__).'/logo.png', 'logo');

	//Send the email
	$phpMailer->send();
}
