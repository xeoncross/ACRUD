<?php
/**
 * Default ACRUD routing class for running ACRUD in stand-alone mode.
 * If using ACRUD from within another PHP frameworks/system you probably don't need this.
 *
 * 	// Change "/" to "/sub/folder" if inside a subfolder
 *	$router = new \ACRUD\Router('/');
 * 	$router->route('fields/(\w+)', function($app_path, $table) use (&$acrud)
 *	{
 *		$columns = $acrud->getColumns();
 *
 *		if( ! isset($columns[$table])) {
 *			throw new Exception("Table $table doesn't exist");
 *		}
 *
 *		return $columns[$table];
 *	});
 * 	$router->run();
 *
 * @package    ACRUD
 * @author     David Pennington
 * @license    MIT License
 * @copyright  2013
 * @link       http://github.com/Xeoncross/ACRUD
 * @link       http://davidpennington.me
 */
namespace ACRUD;

class Router
{

	public $app_path = null;
	public $uri_path = null;
	public $http_method = null;
	public $routes = array();

	public function __construct($app_path = '/', $uri_path = null, $http_method = null)
	{
		if($uri_path === NULL) {
			$uri_path = rawurldecode(trim(parse_url(getenv('REQUEST_URI'), PHP_URL_PATH), '/'));
		}

		if($http_method === null) {
			$http_method = $_SERVER['REQUEST_METHOD'];
		}

		$app_path = trim($app_path, '/');

		// To work in subdirectories, remove the app_path from the requested URI
		if($app_path) {
			$uri_path = trim(substr($uri_path, mb_strlen($app_path)), '/');
		}

		$this->app_path = $app_path;
		$this->uri_path = $uri_path;
		$this->http_method = $http_method;
	}

	/**
	 * Map paths to callbacks objects/closures
	 *
	 * @param string $path
	 * @param mixed $closure
	 * @return mixed
	 */
	public function route($path, $closure)
	{
		return $this->routes[$path] = $closure;
	}

	/**
	 * Run the routes
	 */
	public function run($catch = true)
	{
		try {
			
			foreach($this->routes as $route => $closure) {

				if(preg_match("~^$route$~", $this->uri_path, $match)) {

					$match[0] = $this->app_path;
					$result = call_user_func_array($closure, $match);

					// JSON
					if(is_array($result)) {
						header('Content-Type: application/json; charset="utf-8"');
						die(json_encode($result));
					}

					header('Content-Type: text/html; charset="utf-8"');
					die($result);

				}
			}

		} catch (\Exception $e) {

			if ( ! $catch) {
				throw $e;
			}

			error_log(''. $e);
			header('Content-Type: application/json');
			die(json_encode(array('exception' => $e->getMessage())));

		}
	}
}
