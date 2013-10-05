<?php
/**
 * Core ACRUD Instance factory and object. Handles loading the correct database abstraction,
 * figuring out validation rules, and saving data. You can have muiltiple instances for each
 * database you wish to connect to.
 *
 * 	// Create a new PDO connection
 * 	$pdo = new PDO(
 * 		'mysql:dbname=mydatabase;host=localhost',
 * 		'root',
 * 		'',
 * 		array(
 * 			\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
 * 			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
 * 			\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION
 * 		)
 * 	);
 * 
 * 	$instance = \ACRUD\Instance::factory($pdo);
 *
 * @package    ACRUD
 * @author     David Pennington
 * @license    MIT License
 * @copyright  2013
 * @link       http://github.com/Xeoncross/ACRUD
 * @link       http://davidpennington.me
 */
namespace ACRUD;

class Instance extends DB
{
	public $columns = null;
	public $foreign_keys = null;
	public $callbacks = array();

	/**
	 * Return the correct ACRUD database wrapper based on the PDO object given.
	 *
	 * @param PDO $pdo
	 * @return object
	 */
	public static function factory(\PDO $pdo)
	{
		$driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

		if($driver == 'mysql') {
			$driver = 'MySQL';
		} else if($driver == 'sqlite' OR $driver == 'sqlite2') {
			$driver = 'SQLite';
		} else {
			throw new \Exception("Unsupported Driver '$driver'");
		}

		$driver = __NAMESPACE__ . '\\' . $driver;

		return new $driver($pdo);
	}

	/**
	 * Get an array of foreign keys for each table
	 *
	 * @return array
	 */
	public function getForeignKeys() { }

	/**
	 * Get an array of all columns for each table
	 *
	 * @return array
	 */
	public function getColumns() { }

	/**
	 * Validate the data given aginst what is know about the columns and foreign keys
	 *
	 * @param array $data
	 * @param array $columns
	 * @param array $foreign_keys
	 * @return array
	 */
	public function validate($table, array $data, array $columns = null)
	{
		if( ! $columns) {
			$this->columns = $this->getColumns();
		}

		// Make sure only valid columns for this table are being submitted
		if($keys = array_diff_key($data, $columns)) {

			// Each of these unexpected columns needs to be shared
			foreach($keys as $key => $v) {
				$keys[$key] = 'nonexistent';
			}

			return $keys;
		}

		$errors = array();

		// Make sure all the required fields are set
		foreach($columns as $name => $column) {

			// Primary keys are only given on update
			if($column['primary']) {

				if(!empty($data[$name])) {

					// Does this record even exist?
					if( ! $this->column("SELECT 1 FROM $table WHERE $name = ?", array($data[$name]))) {
						$errors[$name] = 'missing';
					}

				}

				continue;
			}

			// The column can only be empty if there is a default or null is allowed
			if(empty($data[$name])) {

				if( ! $column['default'] AND ! $column['nullable']) {
					$errors[$name] = 'empty';
				}

				continue;
			}

			// If this is a foreign key, check that the other table record exists
			if(isset($columns[$name]['table'])) {

				$fk_column = $columns[$name]['column'];
				$fk_table = $columns[$name]['table'];

				if( ! $this->column("SELECT 1 FROM $fk_table WHERE $fk_column = ?", array($data[$name]))) {
					$errors[$name] = 'foreign_key';
				}

				continue;
			}

			// Only digits can be saved in an integer column
			if(strpos($column['type'], 'int') !== FALSE) {

				if( ! ctype_digit($data[$name])) {
					$errors[$name] = 'integer';
					continue;
				}

			}

			// Strings cannot be longer than allowed (varchar, longtext, etc...)
			if(strpos($column['type'], 'text') !== FALSE OR strpos($column['type'], 'char') !== FALSE) {

				if($column['length'] AND mb_strlen($data[$name]) > $column['length']) {
					$errors[$name] = 'length';
					continue;
				}

			}

			// Custom callback defined for this field?
			if(isset($this->callbacks["$table.$name"])) {
				if($error = $this->callbacks["$table.$name"]($data[$name], $data)) {
					$errors[$name] = $error;
				}
			}
		}

		// Is there a custom callback defined for this table?
		if( ! $errors AND isset($this->callbacks[$table])) {
			$errors = $this->callbacks[$table]($data);
		}

		return $errors;
	}


	/**
	 * Add a callback for each event
	 */
	public function on($key, $closure)
	{
		$this->callbacks[$key] = $closure;
		
		return $this;
	}


	/**
	 * Save the given data. This function assumes the data has been validated.
	 *
	 * @param array $data
	 * @param array $columns
	 * @param array $foreign_keys
	 * @return array
	 */
	public function save($table, array $data, array $columns)
	{
		// Primary keys (often called "table.id") are only given on update
		$id = null;

		// Look for a primary key to differentiate an update from an insert
		foreach($columns as $name => $column) {

			if($column['primary']) {

				if( ! empty($data[$name])) {
					$id = $data[$name];
					unset($data[$name]);
				}
				
				break;
			}
		}

		if($id) {

			return $this->update($table, $data, $id);

		} else {

			return $this->insert($table, $data);
		}
	}

}