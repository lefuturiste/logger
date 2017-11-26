<?php
namespace App\Logger;

use App\Logger\Connectors\ElasticsearchConnector;
use App\Logger\Connectors\RedisConnector;
use App\Logger\Input\FileInput;
use Validator\Validator;

class App{
	const FILE_INPUT = 'file_input';

	public $config;
	private $redis;
	private $elasticsearch;
	public $filesInput;

	public function __construct($config)
	{
		$validator = new Validator($config);
		$validator->required('name');
		$validator->notEmpty('name');
		if($validator->isValid()){
			$this->config = $config;
		}else{
			throw new ConfigException;
		}
	}

	public function setRedisConnector(RedisConnector $redis){
		$this->redis = $redis;
	}

	public function setElasticsearchConnector(ElasticsearchConnector $elasticsearch){
		$this->elasticsearch = $elasticsearch;
	}

	public function setFilesInput(array $files) {
		$this->filesInput = $files;
	}

	/**
	 * @return mixed
	 */
	public function getFilesInput()
	{
		return $this->filesInput;
	}

	public function getGeoIpCityPath(){
		return $this->config['geoIpCityPath'];
	}
}