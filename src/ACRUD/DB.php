<?php
/**
 * A simple, 1kb PDO abstraction for MySQL, SQLite, & PostgreSQL. Used to issue raw queries
 * against the PDO database object.
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
 * @link       https://github.com/Xeoncross/DByte
 * @link       http://davidpennington.me
 */
namespace ACRUD;

class DB
{
	// PDO object
	public $pdo = null;

	// Quote Identifier (` = MySQL, " = SQLite/PostgreSQL)
	public $i = null;

	// PostgreSQL needs to use RETURNING on insert
	public $postgresql = false;

	// Store of all queries
	public $queries = array();

	/**
	 * Create a new DB object with a PDO connection instance
	 *
	 * @param object $pdo
	 * @param string $i
	 */
	public function __construct(\PDO $pdo, $i = '`')
	{
		$this->pdo = $pdo;
		$this->i = $i;
	}

	/**
	 * Fetch a column offset from the result set (COUNT() queries)
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @param integer $key index of column offset
	 * @return array|null
	 */
	public function column($query, $params = NULL, $key = 0)
	{
		if($statement = $this->query($query, $params)) {
			return $statement->fetchColumn($key);
		}
	}

	/**
	 * Fetch a single query result row
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return mixed
	 */
	public function row($query, $params = NULL)
	{
		if($statement = $this->query($query, $params)) {
			return $statement->fetch();
		}
	}

	/**
	 * Fetches an associative array of all rows as key-value pairs (first
	 * column is the key, second column is the value).
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return array
	 */
	public function pairs($query, $params = NULL)
	{
		$data = array();

		if($statement = $this->query($query, $params)) {
			while($row = $statement->fetch(\PDO::FETCH_NUM)) {
				$data[$row[0]] = $row[1];
			}
		}

		return $data;
	}

	/**
	 * Fetch all query result rows
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @param int $column the optional column to return
	 * @return array
	 */
	public function fetch($query, $params = NULL, $column = NULL)
	{
		if( ! $statement = $this->query($query, $params)) return;

		// Return an array of records
		if($column === NULL) return $statement->fetchAll();

		// Fetch a certain column from all rows
		return $statement->fetchAll(\PDO::FETCH_COLUMN, $column);
	}

	/**
	 * Prepare and send a query returning the PDOStatement
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return object|null
	 */
	public function query($query, $params = NULL)
	{
		$statement = $this->pdo->prepare($this->queries[] = strtr($query, '`', $this->i));
		$statement->execute($params);
		return $statement;
	}

	/**
	 * Insert a row into the database
	 *
	 * @param string $table name
	 * @param array $data
	 * @return integer|null
	 */
	public function insert($table, array $data)
	{
		$query = "INSERT INTO`$table`(`" . implode('`,`', array_keys($data))
			. '`)VALUES(' . rtrim(str_repeat('?,', count($data = array_values($data))), ',') . ')';
		return $this->postgresql
			? $this->pdo->column($query . 'RETURNING`id`', $data)
			: ($this->query($query, $data) ? $this->pdo->lastInsertId() : NULL);
	}

	/**
	 * Update a database row
	 *
	 * @param string $table name
	 * @param array $data
	 * @param array $w where conditions
	 * @return integer|null
	 */
	function update($table, $data, $value, $column = 'id')
	{
		$keys = implode('`=?,`', array_keys($data));
		if($statement = $this->query(
			"UPDATE`$table`SET`$keys`=? WHERE`$column`=?",
			array_values($data + array($value))
		))
			return $statement->rowCount();
	}

}