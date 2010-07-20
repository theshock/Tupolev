<?php
require_once 'core.php';

// Simple controllers
tu\controller('/',
	function () {
		return '<a href="./examples.php?url=another">Another page</a>';
	});

tu\controller('/?another',
	function () {
		return 'Try <a href="./examples.php?url=json">encoded json.';
	});

// Controller decorated with strtolower(json_encode(<closure>))
tu\controller('/?json',
	tu\decorator('strtolower',
		tu\decorator('json_encode',
			function () {
				return array('Say', 'Hello', 'World', 'To', 'Json');
			})));

// Route '/?json' decorated with strtoupper
tu\controller('/?json_ucase',
	tu\decorator('strtoupper',
		clone tu\findRoute('/?json')));

// Route '/?json_ucase' decorated with curryed str_ireplace
tu\controller('/?json_goodbye',
	tu\decorator(
		tu\curry('str_ireplace', array('HELLO', 'JSON'), array('GOODBYE', 'PHP')),
		clone tu\findRoute('/?json_ucase')));

// Creating routes & controllers in cycle
foreach (array(1, 2, 3) as $num) {
	tu\controller('/?' . $num, function () use ($num) {
		echo $num . ' * ' . $num . ' = ' . pow($num, $num);
	});
}

// Bootstrap
tu\Router::doRoute();
