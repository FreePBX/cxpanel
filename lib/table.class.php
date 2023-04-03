<?php
/*
 *Name         : tabled.class.php
 *Author       : Michael Yara
 *Created      : Jan 18, 2013
 *Last Updated : Sep 17, 2013
 *Version      : 0.01
 *Purpose      : Provides classes used for database table management
 */

namespace FreePBX\modules\Cxpanel;
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 *
 * Represents a column in the table
 * @author michaely
 *
 */
class cxpanel_column {

	public $name = "";
	public $type = "";
	public $defaultValue = "";
	public $freePBXKey = "";
	public $isUnique = false;
	public $isNotNull = false;

	function __construct($nameVal, $typeVal, $defaultValueVal, $freePBXKeyVal, $isUniqueVal, $isNotNullVal) {
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

	public $name = "";
	public $columns = array();

	function __construct($nameVal, $columnsVal) {
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

	public $table = null;

	function __construct($tableVal) {
		$this->table = $tableVal;
	}

	function build($entries = null)
	{
		out( sprintf( _('Creating "%s" Table...'), $this->table->name));
		if($this->createTableIfItDoesNotExist())
		{
			out(sprintf( _('Populating(New) "%s"...'), $this->table->name));
			if(isset($entries))
			{
				$this->populateTableNew($entries);
			}
		}
		else
		{
			out(sprintf(_('Upgrading "%s"...'), $this->table->name));
			$addedColumns = $this->upgradeTableColumns();
			if(!empty($addedColumns))
			{
				out(sprintf(_('Populating(Upgrade) "%s"...'),$this->table->name));
				$this->populateTableUpgrade($addedColumns);
			}
		}
		out(_("Done"));
	}

	function createTableIfItDoesNotExist()
	{
		global $db;
		//Build query to create table if it does not exists
		$query = "";
		foreach($this->table->columns as $column)
		{
			$query .=  sprintf("%s,", $this->buildColumnEntry($column));
		}
		$query = substr_replace($query, "", -1);
		$query = sprintf("CREATE TABLE %s (%s)", $this->table->name, $query);

		$result = $db->query($query);
		if(DB::IsError($result)) {

			if($result->getCode() != DB_ERROR_ALREADY_EXISTS) {
				die_freepbx($result->getDebugInfo());
			}
			return false;
		}
		return true;
	}

	function upgradeTableColumns()
	{
		global $db;
		$addedColumns = array();
		//Insert any missing columns
		foreach($this->table->columns as $column)
		{
			$query = sprintf("SELECT %s FROM %s", $column->name, $this->table->name);
			$check = $db->getRow($query, DB_FETCHMODE_ASSOC);
			if(DB::IsError($check)) {
				$query = sprintf("ALTER TABLE %s ADD %s", $this->table->name, $this->buildColumnEntry($column));
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

	function populateTableNew($entries)
	{
		global $db;

		//Populate a newly created table
		foreach($entries as $entry)
		{
			$queryKeys = "";
			$queryValues = "";
			$queryValueArray = array();

			foreach($this->table->columns as $column)
			{
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

			$sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->table->name, $queryKeys, $queryValues);
			$query = $db->prepare($sql);
			$result = $db->execute($query, $queryValueArray);
			if(DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
	}

	function populateTableUpgrade($addedColumns)
	{
		global $db;

		//Upgrade a table
		foreach($addedColumns as $column) {
			$sql = sprintf("UPDATE %s SET %s = ?", $this->table->name, $column->name);
			$query = $db->prepare($sql);
			$result = $db->execute($query, array($column->defaultValue));
			if(DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
	}

	function buildColumnEntry($column) {

		$columnEntry = $column->name;
		$modifierEntry = ($column->isUnique ? " UNIQUE" : "") . ($column->isNotNull ? " NOT NULL" : "");
		$varcharSize = $column->isUnique ? "190" : "1000";

		switch ($column->type) {
			case "primary":
				$columnEntry .= " INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
				break;
			case "string":
				$columnEntry .= sprintf(" VARCHAR(%s)%s", $varcharSize, $modifierEntry);
				break;
			case "integer":
				$columnEntry .= sprintf(" INTEGER(10)%s", $modifierEntry);
				break;
			case "boolean":
				$columnEntry .= sprintf(" INTEGER(1)%s", $modifierEntry);
				break;
		}

		return $columnEntry;
	}
}
