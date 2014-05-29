<?php
/*
 *Name         : tabled.class.php
 *Author       : Michael Yara
 *Created      : Jan 18, 2013
 *Last Updated : Sep 17, 2013
 *Version      : 0.01
 *Purpose      : Provides classes used for database table management
 */

/**
 * 
 * Represents a column in the table
 * @author michaely
 *
 */
class cxpanel_column {

	var $name = "";
	var $type = "";
	var $defaultValue = "";
	var $freePBXKey = "";
	var $isUnique = false;
	var $isNotNull = false;

	function cxpanel_column($nameVal, $typeVal, $defaultValueVal, $freePBXKeyVal, $isUniqueVal, $isNotNullVal) {
		$this->name = $nameVal;
		$this->type = $typeVal;
		$this->defaultValue = $defaultValueVal;
		$this->freePBXKey = $freePBXKeyVal;
		$this->isUnique = $isUniqueVal;
		$this->isNotNull = $isNotNullVal;
	}
}

/**
 * 
 * Represents a table
 * @author michaely
 *
 */
class cxpanel_table {

	var $name = "";
	var $columns = array();

	function cxpanel_table($nameVal, $columnsVal) {
		$this->name = $nameVal;
		$this->columns = $columnsVal;
	}
}

/**
 * 
 * Class used to build or modify an existing table in order to 
 * match the desired table structure.
 * 
 * Will echo info statemets for display in the FreePBX module install dialog.
 * @author michaely
 *
 */
class cxpanel_table_builder {

	var $table = null;

	function cxpanel_table_builder($tableVal) {
		$this->table = $tableVal;
	}

	function build($entries = null) {

		echo "Creating \"" . $this->table->name . "\" Table....<br>";

		if($this->createTableIfItDoesNotExist()) {
			echo "Populating(New) \"" . $this->table->name. "\"....<br>";

			if(isset($entries)) {
				$this->populateTableNew($entries);
			}
		
		} else {
			echo "Upgrading \"" . $this->table->name. "\"....<br>";
			$addedColumns = $this->upgradeTableColumns();
			if(!empty($addedColumns)){
				echo "Populating(Upgrade) \"" . $this->table->name. "\"....<br>";
				$this->populateTableUpgrade($addedColumns);
			}
		}
		echo "Done<br>";
	}

	function createTableIfItDoesNotExist() {

		global $db;

		//Build query to create table if it does not exists
		$query = "CREATE TABLE " . $this->table->name . "(";

		foreach($this->table->columns as $column) {
			$query .= $this->buildColumnEntry($column) . ",";
		}

		$query = substr_replace($query, "", -1);
		$query .= ")";

		$result = $db->query($query);
		if(DB::IsError($result)) {
				
			if($result->getCode() != DB_ERROR_ALREADY_EXISTS) {
				die_freepbx($result->getDebugInfo());
			}
				
			return false;
		}
			
		return true;
	}

	function upgradeTableColumns() {

		global $db;

		$addedColumns = array();

		//Insert any missing columns
		foreach($this->table->columns as $column) {
			$query = "SELECT $column->name FROM " . $this->table->name;
			$check = $db->getRow($query, DB_FETCHMODE_ASSOC);
				
			if(DB::IsError($check)) {
				$query = "ALTER TABLE " . $this->table->name . " ADD " . $this->buildColumnEntry($column);
				$result = $db->query($query);
				 
				if(DB::IsError($result)) {
					die_freepbx($result->getDebugInfo());
				} else {
					array_push($addedColumns, $column);
				}
			}
		}

		return $addedColumns;
	}

	function populateTableNew($entries) {

		global $db;

		//Populate a newly created table
		foreach($entries as $entry) {
				
			$queryKeys = "";
			$queryValues = "";
			$queryValueArray = array();
				
			foreach($this->table->columns as $column) {
				$queryKeys .= $column->name . ",";
				$queryValues .= "?,";
				$freePBXKey = $column->freePBXKey;

				if($freePBXKey != "") {
					array_push($queryValueArray,  $entry[$freePBXKey]);
				} else {
					array_push($queryValueArray,  $column->defaultValue);
				}
			}
				
			$queryKeys = substr_replace($queryKeys, "", -1);
			$queryValues = substr_replace($queryValues, "", -1);

			$query = $db->prepare("INSERT INTO " . $this->table->name . " (" . $queryKeys . ") VALUES (" . $queryValues . ")");
			$result = $db->execute($query, $queryValueArray);
			if(DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
	}

	function populateTableUpgrade($addedColumns) {

		global $db;

		//Upgrade a table
		foreach($addedColumns as $column) {
			$query = $db->prepare("UPDATE " . $this->table->name . " SET " . $column->name . " = ?");
			$result = $db->execute($query, array($column->defaultValue));
			if(DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
	}

	function buildColumnEntry($column) {

		$columnEntry = $column->name;
		$modifierEntry = ($column->isUnique ? " UNIQUE" : "") . ($column->isNotNull ? " NOT NULL" : "");
		$varcharSize = $column->isUnique ? "255" : "1000";

		switch ($column->type) {
			case "primary":
				$columnEntry .= " INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
				break;
			case "string":
				$columnEntry .= " VARCHAR(" . $varcharSize . ")" . $modifierEntry;
				break;
			case "integer":
				$columnEntry .= " INTEGER(10)" . $modifierEntry;
				break;
			case "boolean":
				$columnEntry .= "  INTEGER(1)" . $modifierEntry;
				break;
		}

		return $columnEntry;
	}
}

