<?php
/**
 * Tupolev Framework
 * @author Nikita Baksalyar <n.baksalyar@yandex.ru>
 */
namespace tu
{
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

		public static function doRoute($url = null)
		{
			if ($url === null) {
				$url = Request::get('url', '/');
			}

			foreach (self::$routes as $route) {
				if (preg_match('/^' . str_replace('/', '\\/', (string)$route) . '$/', $url)) {
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

	/**
	 * Handles classes configuration
	 */
	interface Configurable
	{
		public static function configure(array $settings);
		public static function isConfigured();
	}
}

/**
 * Database abstraction
 */
namespace tu\db
{
	use tu\utils;

	/**
	 * Shortand for Instances::getInstance()->query()
	 */
	function runQuery($q, $driver = null, $instance = null)
	{
		Instances::getInstance($driver, $instance)->query($q);
	}

	class Instances
	{
		private static $instances = array();

		public static function connect($driver, $settings)
		{
			$instanceId = md5(microtime(true));
			self::$instances[$driver][$instanceId] = new $driver($settings);
			return $instanceId;
		}

		public static function getInstance($driver = null, $instanceId = null)
		{
			$allDrivers = array_keys(self::$instances);
			$driversContainer = ($driver === null ? self::$instances[array_pop($allDrivers)] : self::$instances[$driver]);
			$allInstances = array_keys($driversContainer);

			return ($instanceId === null ? $driversContainer[array_pop($allInstances)] : $driversContainer[$instanceId]);
		}
	}

	interface Driver
	{
		public function __construct(array $settings);
		public function escape($str);
		public function query($q);
		public function fetch($result);
	}

	class MysqliDriver implements Driver
	{
		private $mysqli;

		public function __construct(array $settings)
		{
			$this->mysqli = new \mysqli(
				utils\getValue($settings['host']),
				utils\getValue($settings['username']),
				utils\getValue($settings['passwd']),
				utils\getValue($settings['dbname']),
				utils\getValue($settings['socket'])
			);
		}

		public function escape($str)
		{
			return $this->mysqli->escape($q);
		}

		public function query($q)
		{
			return $this->mysqli->query($q);
		}

		public function fetch($result)
		{
			return $result->fetch_object();
		}
	}
}

/**
 * Templates
 */
namespace tu\tpl
{
	use tu\Configurable;

	function render($template, $vars = array())
	{
		if (!TemplatingEngine::isConfigured()) {
			// Templating engine conventions
			TemplatingEngine::configure(array(
				'dir'       => 'templates',
				'extension' => 'php',
			));
		}

		$template = TemplatingEngine::createTemplate($template);
		if ($vars) {
			$template->setVars($vars);
		}

		return (string)$template;
	}

	class TemplatingEngine implements Configurable
	{
		private static $settings = array(
			'dir'       => '',
			'extension' => '',
		);
		private static $helpers = array();
		private static $configured = false;

		public static function configure(array $settings)
		{
			foreach (array_keys(self::$settings) as $key) {
				if (!isset($settings[$key])) {
					continue;
				}
				self::$settings[$key] = $settings[$key];
			}
			self::$configured = true;
		}

		public static function isConfigured()
		{
			return self::$configured;
		}

		public static function createTemplate($templateFile)
		{
			$template = new Template(self::$settings['dir'] . '/' . $templateFile . '.' . self::$settings['extension']);
			return $template->setVars(self::$helpers);
		}

		public static function addHelper($id, Closure $helper)
		{
			self::$helpers[$id] = $helper;
		}
	}

	class Template
	{
		private $template = '';
		private $vars = array();

		public function __construct($templateFile)
		{
			if (!file_exists($templateFile)) {
				throw new Exception('Template \'' . $templateFile . '\' does not exists.');
			}
			$this->template = $templateFile;
		}

		public function render()
		{
			extract($this->vars);
			ob_start();
			include $this->template;
			return ob_get_clean();
		}

		public function __toString()
		{
			return $this->render();
		}

		public function setVars(array $vars)
		{
			foreach ($vars as $k => $v) {
				$this->setVar($k, $v);
			}
			return $this;
		}

		public function setVar($key, $value)
		{
			$this->vars[$key] = $value;
			return $this;
		}
	}
}

/**
 * Utility functions
 */
namespace tu\utils
{
	function getValue(&$v)
	{
		if (empty($v)) {
			return null;
		}
		return $v;
	}
}
