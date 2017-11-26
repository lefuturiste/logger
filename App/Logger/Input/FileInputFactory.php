<?php
namespace App\Logger\Input;
use App\Logger\App;
use App\Logger\Connectors\RedisConnector;

class FileInputFactory{
	public static function create(RedisConnector $redisConnector, App $logger, $path, $type){
		$fileInput = new FileInput($redisConnector, $logger);
		$fileInput->create($path, $type);
		return $fileInput;
	}
}