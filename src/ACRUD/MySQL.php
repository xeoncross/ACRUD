<?php
/**
 * Database abstraction for the MySQL database. Assumes version 5.x or higher.
 * The ACRUD/Instance factory will initialize this class if needed by the PDO object.
 *
 * @package    ACRUD
 * @author     David Pennington
 * @license    MIT License
 * @copyright  2013
 * @link       http://github.com/Xeoncross/ACRUD
 * @link       http://davidpennington.me
 */
namespace ACRUD;

class MySQL extends Instance
{

	public function getTables()
	{
		return $this->fetch("SHOW TABLES", null, 0);
	}
	
	public function getForeignKeys()
	{
		if($this->foreign_keys) {
			return $this->foreign_keys;
		}

		$sql = "SELECT * FROM information_schema.KEY_COLUMN_USAGE"
			//. " WHERE table_schema = DATABASE() AND CONSTRAINT_NAME != 'PRIMARY'" // If you wanted indexes/uniques
			. " WHERE table_schema = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
			. " ORDER BY table_name, ordinal_position";

		$result = $this->fetch($sql);

		$tables = array();
		foreach($result as $row) {
			
			if(empty($tables[$row->TABLE_NAME])) {
				$tables[$row->TABLE_NAME] = array();
			}

			$tables[$row->TABLE_NAME][$row->COLUMN_NAME] = array(
				'table' => $row->REFERENCED_TABLE_NAME,
				'column' => $row->REFERENCED_COLUMN_NAME
			);
		}

		return $this->foreign_keys = $tables;
	}

	public function getColumns()
	{
		if($this->columns) {
			return $this->columns;
		}

		$foreign_keys = $this->getForeignKeys();

		$sql = "SELECT * FROM information_schema.columns"
			. " WHERE table_schema = DATABASE()"
			. " ORDER BY table_name, ordinal_position";

		$result = $this->fetch($sql);

		$columns = array();
		foreach($result as $column) {

			if($column) {

				$columns[$column->TABLE_NAME][$column->COLUMN_NAME] = array(
					'default' => $column->COLUMN_DEFAULT ?: null,
					'nullable' => $column->IS_NULLABLE === 'YES',
					'type' => $this->mapType($column->DATA_TYPE),
					'length' => $column->CHARACTER_MAXIMUM_LENGTH ?: null,
					'precision' => $column->NUMERIC_PRECISION ?: null,
					'scale' => $column->NUMERIC_SCALE ?: null,
					'comment' => $column->COLUMN_COMMENT ?: null,
					// PRI, MUL, etc...
					'index' => $column->COLUMN_KEY ? true : null,
					'primary' => $column->COLUMN_KEY === 'PRI',
					'unique' => $column->COLUMN_KEY === 'UNI',
				);

				if(isset($foreign_keys[$column->TABLE_NAME][$column->COLUMN_NAME])) {

					$fk = $foreign_keys[$column->TABLE_NAME][$column->COLUMN_NAME];
					$columns[$column->TABLE_NAME][$column->COLUMN_NAME] += $fk;

				}

			}
		}

		return $this->columns = $columns;
	}


	public function mapType($type)
	{
		$types = array(
			'int'		=> 'integer',
			'tinyint'	=> 'integer',
			'smallint'	=> 'integer',
			'mediumint'	=> 'integer',
			'bigint'	=> 'integer',
			'bit'		=> 'integer',

			'double'	=> 'decimal',
			'float'		=> 'decimal',
			'decimal'	=> 'decimal',
			'numeric'	=> 'decimal',

			'boolean'	=> 'boolean',

			'date'		=> 'datetime',
			'time'		=> 'datetime',
			'datetime'	=> 'datetime',
			'timestamp'	=> 'datetime',
			'year'		=> 'datetime',

			'tinytext'	=> 'text',
			'text'		=> 'text',
			'longtext'	=> 'text',
			'mediumtext'=> 'text',
			'blob'		=> 'text',
			'varchar'	=> 'text',
			'char'		=> 'text',

			// Others like enum, polygon, etc...
		);

		$type = strtolower($type);

		if(empty($types[$type])) {
			throw new \Exception("Column type $type not supported");
		}

		return $types[$type];
	}

}