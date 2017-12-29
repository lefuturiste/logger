<?php
/**
 * Developed by: mbess
 * Date: 29/12/2017
 * Time: 11:28
 */

namespace App\Logger\Analyser;


use App\Logger\Analyser\LineAnalyser;
use App\Logger\Parser\LineParser;
use DiscordWebhooks\Client;
use DiscordWebhooks\Embed;

class NginxAccessLineAnalyser
{

	/**
	 * @var LineParser
	 */
	private $parser;
	/**
	 * @var LineAnalyser
	 */
	private $analyser;

	public function __construct(LineParser $parser, LineAnalyser $analyser)
	{
		$this->parser = $parser;
		$this->analyser = $analyser;
	}

	public function run()
	{
		$body = $this->parser->toArray();
		if (isset($body['status'])) {
			switch ($body['status']) {
				case 500:
					//send discord log
					$client = new Client($this->analyser->getDiscordWhUrl());
					$embed = new Embed();
					$embed->title('Unexpected HTTP ERROR 500, Internal server error');
					$embed->color(12597547);
					$embed->field('Logger', $body['logger']);
					$embed->field('Virtual host', $body['virtual_host']);
					$embed->field('Request', $body['request']);
					$embed->field('Client Real Ip', $body['real_ip']);
					$embed->field('Created at', $body['created_at']);
					$client->embed($embed)->send();
					break;
			}
		}
	}
}