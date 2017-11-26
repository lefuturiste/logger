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
			return new \Carbon\Carbon($this->entry->date);
		} catch (Exception $e) {
			printf("\n <error>[ERR] - ERROR while parse time_local data : {$e->getMessage()} </error>");

			return \Carbon\Carbon::now();
		}
	}

	public function toArray()
	{
		return [
			'created_at' => $this->getLocalDate(),
			'register_at' => Carbon::now()->toAtomString(),
			'raw_message' => $this->line,
			'time_local' => $this->entry->date,
			'level' => $this->entry->type,
			'message' => $this->entry->message,
			'virtual_host' => $this->getVirtualHost(),
			'request' => $this->entry->request,
			'url' => $this->getUrl(),
			'method' => $this->getMethod(),
			'http_version' => $this->getHttpVersion(),
			'remote_addr' => $this->entry->client,
		];
	}

	public function getVirtualHost()
	{
		return str_replace('www.', '', $this->entry->server);
	}

	public function parse(){
		$parser = new \TM\ErrorLogParser\Parser(\TM\ErrorLogParser\Parser::TYPE_NGINX); // or TYPE_NGINX;
		return $parser->parse($this->line);
	}

	public function getUrl()
	{
		return explode(' ', $this->entry->request)[1];
	}

	public function getMethod()
	{
		return explode(' ', $this->entry->request)[0];
	}

	public function getHttpVersion()
	{
		return explode(' ', $this->entry->request)[2];
	}
}