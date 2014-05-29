<?php 
/*
 *Name         : logger.class.php
 *Author       : Michael Yara
 *Created      : Jan 18, 2013
 *Last Updated : Jan 18, 2013
 *Version      : 3.0
 *Purpose      : Provides a class used to log into a specified file.
 */

/**
 * 
 * Logs messages to a file
 * @author michaely
 *
 */
class cxpanel_logger {
	
	var $file;
	var $fd;
	var $echoLog = false;
	
	/**
	 * 
	 * Constructor for logger
	 * @param String $file log file
	 * 
	 */
	function cxpanel_logger($file) {
		$this->file = $file;
	}
	
	/**
	 * 
	 * Open the logger.
	 * Will create the log file if it does not exist.
	 * Will overwite any existing contents.
	 * 
	 */
	function open() {
		if(!isset($this->fd)) {
			$this->fd = fopen($this->file, 'w');
		}
	}
	
	/**
	 * 
	 * Close the logger
	 * 
	 */
	function close() {
		if(isset($this->fd)) {
			fclose($this->fd);
			$this->fd = null;
		}
	}
	
	/**
	 * 
	 * Write a log message to the log file
	 * @param String $type the log type
	 * @param String $content the content of the log message
	 * 
	 */
	function write($type, $content) {
		if(isset($this->fd)) {
			fwrite($this->fd, "(" .date('d M Y H:i:s') . ")-[" . $type . "]: " . $content . "\n");
			if($this->echoLog) {
				echo $this->fd, "(" .date('d M Y H:i:s') . ")-[" . $type . "]: " . $content . "\n";
			}
		}
	}
	
	/**
	 * 
	 * Creates a debug log message
	 * @param String $content the content of the log message
	 * 
	 */
	function debug($content) {
		$this->write("DEBUG", $content);
	}
	
	
	/**
	 * 
	 * Creates a error log message
	 * @param String $content the contenet of the log message
	 * 
	 */
	function error($content) {
		$this->write("ERROR", $content);
	}
	
	/**
	 * 
	 * Creates a error log message with an exception
	 * @param String $content the contenet of the log message
	 * @param Exception $exception
	 * 
	 */
	function error_exception($content, $exception) {
		$this->write("ERROR", $content . "\n" . $exception->__toString());
	}
	
}

