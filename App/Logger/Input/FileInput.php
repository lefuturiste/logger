<?php

namespace App\Logger\Input;

use App\Logger\App;
use App\Logger\Connectors\RedisConnector;

class FileInput
{
	public $path;
	public $type;
	private $linesCount;
	/**
	 * @var RedisConnector
	 */
	private $redisConnector;
	/**
	 * @var App
	 */
	public $logger;
	public $id;
	public $newLineCount;

	public function __construct(RedisConnector $redisConnector, App $logger)
	{
		$this->redisConnector = $redisConnector;
		$this->logger = $logger;
	}

	public function create(string $path, $type)
	{
		$this->path = $path;
		$this->type = $type;
		$this->id = hash('sha256', serialize($this->path));

		//if the file has already hash in redis
		$lineCountTemp = $this->redisConnector->get("logger_singleRun_lineCountTemp_{$this->logger->config['name']}");
		$hashTemp = $this->redisConnector->get("logger_singleRun_hashTemp_{$this->logger->config['name']}");
		if ($lineCountTemp == NULL && $hashTemp == NULL) {
			// empty key create from zero
			$this->createTmp();
		} elseif ($lineCountTemp != NULL && $hashTemp !== NULL) {
			// keys not empty
			if (!isset($lineCountTemp[$this->id])) {
				$lineCountTemp[$this->id] = $this->getCountLines($this->getLines());
				$this->redisConnector->set("logger_singleRun_lineCountTemp_{$this->logger->config['name']}", $lineCountTemp);
			}
			if (!isset($hashTemp[$this->id])) {
				$hashTemp[$this->id] = $this->getHash();
				$this->redisConnector->set("logger_singleRun_hashTemp_{$this->logger->config['name']}", $hashTemp);
			}
		}

		return $this;
	}

	public function persist(){
		$hashTemp[$this->id] = $this->getHash();
		$this->redisConnector->set("logger_singleRun_hashTemp_{$this->logger->config['name']}", $hashTemp);
		$lineCountTemp[$this->id] = $this->getCountLines($this->getLines());
		$this->redisConnector->set("logger_singleRun_lineCountTemp_{$this->logger->config['name']}", $lineCountTemp);
	}

	public function getHash()
	{
		return hash('sha256', file_get_contents($this->path));
	}

	public function getLastHash()
	{
		return $this->redisConnector->get("logger_singleRun_hashTemp_{$this->logger->config['name']}")[$this->id];
	}

	public function getLastLineCount()
	{
		return $this->redisConnector->get("logger_singleRun_lineCountTemp_{$this->logger->config['name']}")[$this->id];
	}


	public function getLines()
	{
		$lines = file($this->path, FILE_IGNORE_NEW_LINES);
		$this->linesCount = $lines;

		return $lines;
	}

	public function getCountLines($lines)
	{
		return count($lines);
	}

	public function hasNew()
	{
		$hash = $this->getLastHash();
		$lineCount = $this->getLastLineCount();
		if ($hash !== $this->getHash() OR $lineCount !== $this->getCountLines($this->getLines())) {
			//le hash différe ou la ligne différe
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Create key if the key don't exist and asign hash and key
	 */
	public function createTmp()
	{
		$lineCountTemp = [];
		$hashTemp = [];
		$lineCountTemp[$this->id] = $this->getCountLines($this->getLines());
		$hashTemp[$this->id] = $this->getHash();
		$this->redisConnector->set("logger_singleRun_hashTemp_{$this->logger->config['name']}", $hashTemp);
		$this->redisConnector->set("logger_singleRun_lineCountTemp_{$this->logger->config['name']}", $lineCountTemp);
	}

	public function getNewLines()
	{
		$newLinesCount = $this->getCountLines($this->getLines()) - (int)$this->getLastLineCount();
		if ($newLinesCount > 0) {
			//on prend les dernières lignes
			$data = file($this->path);
			$i = 0;
			$newLines = [];

			while ($newLinesCount != $i) {
				$line = $data[count($data) - ($newLinesCount - $i)];
				array_push($newLines, $line);
				$i++;
			}

			$this->newLineCount = $newLinesCount;

			return $newLines;
		}else{
			false;
		}
	}
}