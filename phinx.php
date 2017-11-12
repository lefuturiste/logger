<?php
$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

return [
	'paths' => [
		'migrations' => '%%PHINX_CONFIG_DIR%%/App/Database/Migrations',
		'seeds' =>  '%%PHINX_CONFIG_DIR%%/App/Database/Seeds'
	],
	'environments' => [
		'default_database' => 'development',
		'development' => [
			'adapter' => 'mysql',
			'host' => getenv('MYSQL_HOST'),
			'name' => getenv('MYQSL_DATABASE'),
			'user' => getenv('MYSQL_USERNAME'),
			'pass' => getenv('MYSQL_PASSWORD'),
		]
	]
];