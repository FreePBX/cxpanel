<?php 
/*
 *Name         : util.php
 *Author       : Michael Yara
 *Created      : Jan 18, 2013
 *Last Updated : Jan 18, 2013
 *History      : 1.0
 *Purpose      : Provides utility functions
 */

/**
 * 
 * Generates a random password of the given length
 * @author http://www.laughing-buddha.net/php/password
 *
 */
function cxpanel_generate_password ($length = 8) {

	$password = "";
	$possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";
	$maxlength = strlen($possible);
	
	if ($length > $maxlength) {
		$length = $maxlength;
	}
	
	$i = 0; 
	while ($i < $length) { 
		$char = substr($possible, mt_rand(0, $maxlength-1), 1);
	
		if (!strstr($password, $char)) { 
			$password .= $char;
			$i++;
		}
	}
	    
	return $password;
}

/**
 * 
 * Reads the contents of a file and returns it as a string
 * @param String $file path to file that needs to be read
 * 
 */
function cxpanel_read_file($file) {
	$contents = "";
	if(($contentFile = fopen($file, 'r')) !== false) {
		while (!feof($contentFile)) {
			$contents .= fgets($contentFile, 4096);
		}
		fclose($contentFile);
	}
	return $contents;
}

/**
 * 
 * Converts a 2 dimensional array into a table
 * @param Array $array the array to convert
 * @param String $tagAdditions content to add to the <table> tag
 * 
 */
function cxpanel_array_to_table_2d($array, $tagAdditions = "") {
	$table = "<table $tagAdditions>";
	$header = true;
	foreach($array as $a) {
		if($header) {
			$table .= cxpanel_get_keys($a);			
			$header = false;
		}
		$table .= cxpanel_get_values($a);
	}
	$table .= "</table>";
	return $table;
}

/**
 *
 * Converts a 1 dimensional array into a table
 * @param Array $array the array to convert
 *
 */
function cxpanel_array_to_table_1d($array) {
	$table = "<table>";
	$table .= cxpanel_get_keys($array);
	$table .= cxpanel_get_values($array);
	$table .= "</table>";
	return $table;
}

/**
 * 
 * Converts the keys in an assoc array to a set of table rows
 * @param Array $array
 * 
 */
function cxpanel_get_keys($array) {
	$keys = "<tr>";
	foreach($array as $key => $value) {
		$keys .= "<td>$key:</td>";
    }
	$keys .= "</tr>";
	return $keys;
}

/**
 *
 * Converts the values in an assoc array to a set of table rows
 * @param Array $array
 *
 */
function cxpanel_get_values($array) {
	$values = "<tr>";
	foreach($array as $key => $value) {
		$values .= "<td>$value</td>";
	}
	$values .= "</tr>";
	return $values;
}

/**
 *
 * Gets the current full url
 *
 */
function cxpanel_get_current_url() {
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}
  
/**
 * 
 * Generates a pseudo-random v4 UUID
 * 
 */
function cxpanel_gen_uuid() {
	return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),

			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
	);
}

/**
 * 
 * Checks if a given string starts with the given prefix
 * 
 * @param String $source the string to check on
 * @param String $prefix the prefix to check for
 * @return boolean true if $source starts with $prefix
 */
function cxpanel_startswith($source, $prefix) {
	return strncmp($source, $prefix, strlen($prefix)) === 0;
}
