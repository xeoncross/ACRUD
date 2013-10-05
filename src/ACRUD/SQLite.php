<?php
/**
 * Database abstraction for the SQLite database. Assumes version 2 or higher.
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

class SQLite extends Instance
{

	public function getTables()
	{
		return $this->fetch("SELECT name FROM sqlite_master WHERE type='table'", null, 0);
	}

	public function getForeignKeys()
	{
		$tables = array();
		foreach($this->getTables() as $table) {
			
			$tables[$table] = array();

			$keys = $this->fetch("PRAGMA foreign_key_list($table)");

			/* foreign_key_list(orders) ->
			[id] => 1
			[seq] => 0
			[table] => customers
			[from] => customer_id
			[to] => id
			[on_update] => NO ACTION
			[on_delete] => NO ACTION
			[match] => NONE
			*/

			foreach($keys as $key) {
				$tables[$table][$key->from] = array(
					'table' => $key->table,
					'column' => $key->to
				);
			}
		}

		return $tables;
	}


	public function getColumns()
	{
		$foreign_keys = $this->getForeignKeys();

		// Returns an array of tables followed by any indexes
		$meta = $this->fetch('SELECT * FROM sqlite_master ORDER BY type DESC');

		$columns = array();
		foreach($meta as $item) {

			if($item->type === 'table') {

				$table = $item->name;

				$columns[$table] = array();

				$fields = $this->fetch("PRAGMA table_info($table)");

				/*
				[cid] => 5
				[name] => number
				[type] => NUMERIC
				[notnull] => 0
				[dflt_value] => 
				[pk] => 0
				*/

				foreach($fields as $column) {

					$columns[$table][$column->name] = array(
						'default' => $column->dflt_value !== '' ? $column->dflt_value : null,
						'nullable' => $column->notnull == 0 ? true : false,
						'type' => $this->mapType($column->type),
						'length' => null,
						'precision' => null,
						'scale' => null,
						'comment' => null,
						// PRI, MUL, etc...
						'index' => null,
						'primary' => $column->pk != 0,
						'unique' => null,
					);

					if(isset($foreign_keys[$table][$column->name])) {

						$fk = $foreign_keys[$table][$column->name];
						$columns[$table][$column->name] += $fk;
						
					}

				}

			} elseif($item->type == 'index') {

				/**
				 * Warning, this system only supports indexes on a SINGLE column.
				 * If an index exists on TWO columns then only the first will be acknowledged
				 */

				// What is the name of the column(s) this index exists on?
				$index = $this->row("PRAGMA index_info(" . $item->name . ")");

				if(strpos($item->sql, 'UNIQUE')) {
					$columns[$item->tbl_name][$index->name]['unique'] = true;
				} else {
					$columns[$item->tbl_name][$index->name]['index'] = true;
				}

			}

		}

		return $columns;
	}

	public function mapType($type)
	{
		$types = array(
			'null'		=> 'integer',
			'integer'	=> 'integer',
			'real'		=> 'decimal',
			'numeric'	=> 'decimal',
			'text'		=> 'text',
			'blob'		=> 'text',
		);

		$type = strtolower($type);

		if(empty($types[$type])) {
			throw new \Exception("Column type $type not supported");
		}

		return $types[$type];
	}

}