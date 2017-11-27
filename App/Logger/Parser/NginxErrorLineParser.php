<?php

namespace App\Logger\Parser;

use Carbon\Carbon;

class NginxErrorLineParser extends LineParser
{
	const TYPE = 'nginx-error';
	private $line;
	private $entry;

	public function __construct($line)
	{
		$this->line = $line;
		$this->entry = $this->parse();
	}

	public function getLocalDate()
	{
		try {
			return new \Carbon\Carbon($this->entry['date']);
		} catch (Exception $e) {
			printf("\n <error>[ERR] - ERROR while parse time_local data : {$e->getMessage()} </error>");

			return \Carbon\Carbon::now();
		}
	}

	public function getRemoteAddr(){
		if (isset($this->entry['client'])) {
			return $this->entry['client'];
		}
		return false;
	}

	public function getRemoteAddr(){
		if (isset($this->entry['type'])) {
			return $this->entry['type'];
		}
		return false;
	}

	public function toArray()
	{
		$body = [
			'created_at' => $this->getLocalDate()->toAtomString(),
			'register_at' => Carbon::now()->toAtomString(),
			'raw_message' => $this->line,
			'virtual_host' => $this->getVirtualHost(),
			'url' => $this->getUrl(),
			'method' => $this->getMethod(),
			'http_version' => $this->getHttpVersion(),
			'remote_addr' => $this->getRemoteAddr(),
			'level' => $this->getLevel()
		];
		return $body;
	}

	public function getVirtualHost()
	{
		if (isset($this->entry['server'])) {
			return str_replace('www.', '', $this->entry['server']);
		}
		return false;
	}

	public function parse(){
		try {
			$parser = new \TM\ErrorLogParser\Parser(\TM\ErrorLogParser\Parser::TYPE_NGINX);
			$entry = $parser->parse($this->line);
			return (array) $entry;
		} catch (\TM\ErrorLogParser\Exception\FormatException $e) {
			printf("\n <error>[ERR] - ERROR while parse error nginx data : {$e->getMessage()} </error>");

			return [];
		}
	}

	public function getUrl()
	{
		if (isset($this->entry['request'])) {
			return explode(' ', $this->entry['request'])[1];
		}
		return false;
	}

	public function getMethod()
	{
		if (isset($this->entry['request'])) {
			return explode(' ', $this->entry['request'])[0];
		}
		return false;
	}

	public function getHttpVersion()
	{
		if (isset($this->entry['request'])) {
			return explode(' ', $this->entry['request'])[2];
		}
		return false;
	}
}