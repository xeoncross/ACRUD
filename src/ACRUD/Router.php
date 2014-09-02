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
	public $routes = array();

	public function __construct($app_path = '/', $uri_path = null)
	{
		$app_path = trim($app_path, '/');

		// To work in subdirectories, remove the app_path from the requested URI
		if($uri_path === NULL) {
			$uri_path = trim(getenv('REQUEST_URI'), '/');
			$uri_path = trim(substr($uri_path, mb_strlen($app_path)), '/');
		}

		$this->app_path = $app_path;
		$this->uri_path = $uri_path;
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
	public function run()
	{
		try {
			
			foreach($this->routes as $route => $closure) {

				if(preg_match("~^$route$~", $this->uri_path, $match)) {

					$match[0] = $this->app_path;
					$result = call_user_func_array($closure, $match);

					// HTML?
					if(is_string($result)) {
						header('Content-Type: text/html; charset="utf-8"');
						die($result);
					}
					
					// Standard AJAX request?
					if(strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
						header('Content-Type: application/json');
						die(json_encode($result));
					}

					// Direct access? Show the data formatted for debugging
					die('<pre>' . print_r($result, 1) . '<pre>');
				}
			}

		} catch (\Exception $e) {

			error_log(''. $e);
			header('Content-Type: application/json');
			die(json_encode(array('exception' => $e->getMessage())));

		}
	}
}
