<?php

namespace App\Logger\Connectors;

use App\Logger\ConfigException;
use Predis\Client;
use Validator\Validator;

/*
 * @proprety
 */
class RedisConnector
{

	private $config;

	/**
	 * @var Client
	 */
	private $client;

	public function __construct(array $config)
	{
		$validator = new Validator($config);
		$validator->required('host', 'port');
		$validator->notEmpty('host', 'port');
		if ($validator->isValid()) {
			$this->config = $config;
		} else {
			throw new ConfigException;
		}

		$this->createClient();
	}

	private function createClient()
	{
		$parameters = [
			'scheme' => 'tcp',
			'host' => $this->config['host'],
			'port' => $this->config['port'],
		];
		$options = [];
		if (isset($this->config['password']) && $this->config['password'] != 'false') {
			$options = [
				'parameters' => [
					'password' => $this->config['password'],
				],
			];
		}

		$this->client = new Client($parameters, $options);
	}

	public function get(string $key)
	{
		$json = $this->client->get($key);
		return json_decode($json, 1);
	}

	/**
	 * @param string $key
	 * @param $value
	 * @return mixed
	 */
	public function set(string $key, $value)
	{
		$json = json_encode($value);
		return $this->client->set($key, $json);
	}
}