<?php

namespace App\Logger\Parser;

use App\Logger\App;
use GeoIp2\Database\Reader;
use Ramsey\Uuid\Uuid;

class LineParser
{

	private $line;
	private $type;
	private $parser;

	/**
	 * @var App
	 */
	protected $logger;

	/**
	 * UUID for this line
	 *
	 * @var string
	 */
	public $id;

	public function __construct($line, $type, App $logger)
	{
		$this->line = $line;
		$this->type = $type;
		$this->logger = $logger;
		$this->id = $this->generateId();
		switch ($this->type) {
			case NginxAccessLineParser::TYPE:
				$this->parser = new NginxAccessLineParser($this->line, $this->logger);
				break;

			case NginxErrorLineParser::TYPE:
				$this->parser = new NginxErrorLineParser($this->line);
				break;
		}
	}

	/**
	 * @return mixed
	 */
	public function getLine()
	{
		return $this->line;
	}

	/**
	 * Generate id for this line
	 *
	 * @return string
	 */
	public function generateId()
	{
		$uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'php.net');
		return $uuid5->toString();
	}

	public function toArray()
	{
		$body = $this->parser->toArray();

		return array_merge($body, [
			'logger' => $this->logger->config['name']
		]);
	}

	/**
	 * @return mixed
	 */
	public function getType()
	{
		return $this->type;
	}

}