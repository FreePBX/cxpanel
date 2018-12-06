<?php
/*
 *Name         : dialplan.class.php
 *Author       : Michael Yara
 *Created      : Jan 18, 2013
 *Last Updated : Jan 18, 2013
 *Version      : 3.0
 *Purpose      : Provides classes for missing dialplan application that are required by the module
 */

/**
 *
 * ParkAndAnnounce application class
 * @author michaely
 *
 */
class ext_cxpanel_parkandannounce {
	public $template;
	public $timeout;
	public $dial;
	public $return_context;

	function __construct($template, $timeout, $dial, $return_context) {
		$this->template = $template;
		$this->timeout = $timeout;
		$this->dial = $dial;
		$this->return_context = $return_context;
	}

	function output() {
		return "ParkAndAnnounce(" . $this->template . "," . $this->timeout . "," . $this->dial . "," . $this->return_context . ")";
	}
}

/**
 *
 * ControlPlayback application class
 * @author michaely
 *
 */
class ext_cxpanel_controlplayback {
	public $fileName;
	public $skipMinutes;
	public $ff;
	public $rew;
	public $stop;
	public $pause;
	public $restart;

	function __construct($fileName, $skipMinutes, $ff, $rew, $stop, $pause, $restart) {
		$this->fileName = $fileName;
		$this->skipMinutes = $skipMinutes;
		$this->ff = $ff;
		$this->rew = $rew;
		$this->stop = $stop;
		$this->pause = $pause;
		$this->restart = $restart;
	}

	function output() {
		return "ControlPlayback(" . $this->fileName . "," . $this->skipMinutes . "," . $this->ff . "," . $this->rew . "," . $this->stop . "," . $this->pause . "," . $this->restart . ")";
	}
}

/**
 *
 * ChanSpy application class
 * @author michaely
 *
 */
class ext_cxpanel_chanspy {
	public $prefix;
	public $options;

	function __construct($prefix, $options) {
		$this->prefix = $prefix;
		$this->options = $options;
	}

	function output() {
		return "ChanSpy(" . $this->prefix . "," . $this->options . ")";
	}
}


