<?php
// vim: set ai ts=4 sw=4 ft=php:
//
namespace FreePBX\modules;
class Cxpanel implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
		$this->userman = $this->freepbx->Userman;
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
	}

	/**
	 * Hook functionality from userman when a user is updated
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanUpdateUser($id, $display, $data) {
		if(isset($_REQUEST['cxpanel_add_user'])) {
			$add = isset($_REQUEST['cxpanel_add_user']) ? $_REQUEST['cxpanel_add_user'] : '1';
			$this->userman->setModuleSettingByID($id, 'cxpanel', 'add', $add);
			$isUser = $add;
		} else {
			$isUser = $this->userman->getModuleSettingByID($id, 'cxpanel', 'add');
		}
		if($isUser && (!empty($data['password']) || ($data['prevUsername'] != $data['username']))) {
			$this->userman->setModuleSettingByID($id, 'cxpanel', 'password_dirty', '1');
			exec("php " . $amp_conf['AMPWEBROOT'] . "/admin/modules/cxpanel/modify.php > /dev/null 2>/dev/null &");
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


		$final = array();
		$final[] = "\t".sprintf(_('%s Login: %s'), $cxpanelBrandName, 'http://' . $clientHost . ':' . $serverInformation['client_port'] . '/client/client');
		return $final;
	}
}
