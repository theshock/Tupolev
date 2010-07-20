<?php
namespace tu;

/**
 * Tupolev Framework
 * @author Nikita Baksalyar <n.baksalyar@yandex.ru>
 */

/**
 * Defines new route with supplied controller function
 *
 * @param string $route
 * @param function|Route $controller
 * @return Route
 */ 
function controller($route, $controller)
{
	if (!is_callable($controller) && !($controller instanceof Route)) {
		throw new \Exception('Invalid arguments provided for controller <' . $route . '>');
	}
	return Router::add($route, ($controller instanceof Route ? $controller->getControllerFunc() : $controller));
}

/**
 * Returns decorated route
 *
 * @param function $decorator Decorator function (e.g., json_encode)
 * @param fucntion|Route $route
 */
function decorator($decorator, $route)
{
	if (!is_callable($decorator) && (!($route instanceof Route) || is_callable($route))) {
		throw new \Exception('Invalid arguments provided for decorator');
	}

	$func = (is_callable($route) ? $route : $route->getControllerFunc());

	$decorated = function() use ($func, $decorator) {
		return $decorator($func());
	};

	if ($route instanceof Route) {
		$route->setControllerFunc($decorated);
		return $route;
	}
	return $decorated;
}

/**
 * Shortcut for Router::findRoute, finds route by its match pattern
 *
 * @return Route|null
 */
function findRoute($pattern)
{
	return Router::findRoute($pattern);
}

/**
 * Utility function for currying
 */
function curry()
{
	if (func_num_args() < 2) {
		throw new \Exception('Cannot curry');
	}
	$args = func_get_args();
	$proc = $args[0];
	unset($args[0]);

	$curry = function () use (&$args, $proc) {
		$_mixin_args = func_get_args();
		if ($_mixin_args) {
			$args = array_merge($args, $_mixin_args);
		}

		$func = new \ReflectionFunction($proc);
		$numParams = $func->getNumberOfParameters();

		if ($numParams == count($args)) {
			return call_user_func_array($proc, $args);
		} else {
			$callCurry = function () use ($proc, $args) {
				return call_user_func_array($proc, array_merge($args, func_get_args()));
			};
			return $callCurry;
		}
	};
	return $curry();
}

class Request
{
	public static function get($id, $defaultValue = null)
	{
		if (!isset($_REQUEST[$id])) {
			return $defaultValue;
		}
		return $_REQUEST[$id];
	}
}

class Route
{
	private $controllerFn;
	private $route;

	public function __construct($route, $controllerFn)
	{
		$this->route = $route;
		$this->controllerFn = $controllerFn;
	}

	public function getControllerFunc()
	{
		return $this->controllerFn;
	}

	public function getRoute()
	{
		return $this->route;
	}

	public function setControllerFunc($controllerFn)
	{
		$this->controllerFn = $controllerFn;
	}

	public function __toString()
	{
		return $this->route;
	}
}

/**
 */
class Router
{
	private static $routes;

	public static function add($route, $controller)
	{
		$route = new Route($route, $controller);
		self::$routes[] = $route;
		return $route;
	}

	public static function doRoute()
	{
		foreach (self::$routes as $route) {
			if (preg_match('/^' . str_replace('/', '\\/', (string)$route) . '$/', Request::get('url', '/'))) {
				$func = $route->getControllerFunc();
				echo $func();
				return true;
			}
		}
		return false;
	}

	public static function findRoute($pattern)
	{
		foreach (self::$routes as $route) {
			if ((string)$route == $pattern) {
				return $route;
			}
		}
		return false;
	}
}
