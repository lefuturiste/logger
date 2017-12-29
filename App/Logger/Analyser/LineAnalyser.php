<?php
namespace App\Logger\Analyser;

use App\Logger\Parser\LineParser;
use App\Logger\Parser\NginxAccessLineParser;

class LineAnalyser {
	/**
	 * @var LineParser
	 */
	private $parser;
	private $discordWhUrl;
	private $analyser;

	public function __construct(LineParser $parser){

		$this->parser = $parser;

		switch ($this->parser->getType())
		{
			case NginxAccessLineParser::TYPE:
				$this->analyser = new NginxAccessLineAnalyser($this->parser, $this);
				break;
		}
	}

	public function run(){
		//switch type
		$this->analyser->run();
	}

	/**
	 * @param mixed $discordWhUrl
	 */
	public function setDiscordWhUrl($discordWhUrl)
	{
		$this->discordWhUrl = $discordWhUrl;
	}

	/**
	 * @return mixed
	 */
	public function getDiscordWhUrl()
	{
		return $this->discordWhUrl;
	}
}