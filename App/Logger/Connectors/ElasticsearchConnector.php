<?php
namespace App\Logger\Connectors;

use App\Logger\ConfigException;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Validator\Validator;

class ElasticsearchConnector {

	private $config;
	/**
	 * @var Client
	 */
	public $client;

	public function __construct($config)
	{
		$validator = new Validator($config);
		$validator->required('host', 'port');
		$validator->notEmpty('host', 'port');
		if($validator->isValid()){
			$this->config = $config;
		}else{
			throw new ConfigException;
		}

		$this->createClient();
	}

	public function createClient()
	{
		$builder = ClientBuilder::create();
		$auth = [];
		if (isset($this->config['username']) && isset($this->config['password'])){
			$auth = [
				'user' => $this->config['username'],
				'pass' => $this->config['password']
			];
		}
		$host = [
				'host' => $this->config['host'],
				'port' => $this->config['port'],
				'scheme' => $this->config['scheme']
			];
		$hosts = [
			array_merge($host, $auth)
		];
		$builder->setHosts($hosts);
		$this->client = $builder->build();
	}
}