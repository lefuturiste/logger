<?php

namespace App\Logger\Parser;

use App\Logger\App;
use Carbon\Carbon;
use GeoIp2\Database\Reader;
use GuzzleHttp\Client;
use WhichBrowser\Parser;

class NginxAccessLineParser extends LineParser
{
	const TYPE = 'nginx-access';
	private $line;

	public function __construct($line, App $logger)
	{
		$this->logger = $logger;
		$this->line = json_decode($line, 1);
	}

	public function getClientIp()
	{
		if ($this->line['http_x_forwarded_for'] != '-') {
			$ip = $this->line['http_x_forwarded_for'];
		} else {
			$ip = $this->line['remote_addr'];
		}

		return $ip;
	}

	public function getClientLocation()
	{
		try {
			$reader = new Reader(
				$this->logger->getGeoIpCityPath()
			);
			$record = $reader->city($this->getClientIp());

			$location = [
				'country' => [
					'code' => $record->country->isoCode,
					'name' => $record->country->name,
				],
				'city' => $record->city->name,
				'postal' => $record->postal->code,
				'max_subdivision' => [
					'code' => $record->mostSpecificSubdivision->isoCode,
					'name' => $record->mostSpecificSubdivision->name
				],
				'location' => "{$record->location->latitude}, {$record->location->longitude}",
				'latitude' => $record->location->latitude,
				'longitude' => $record->location->longitude
			];

			return $location;
		} catch (Exception $e) {
			printf("\n <error>[ERR] - ERROR while parse location geoip data : {$e->getMessage()} </error>");

			return [];
		}
	}

	public function getClientISP($ip)
	{
		$ipDetails = $this->getIpDetails($ip);
		if (isset($ipDetails['org'])){
			return $ipDetails['org'];
		}else{
			return false;
		}
	}

	public function getClientHostName($ip)
	{
		$ipDetails = $this->getIpDetails($ip);
		if (isset($ipDetails['hostname'])){
			return $ipDetails['hostname'];
		}else{
			return false;
		}
	}

	public function getIpDetails($ip)
	{
		try {
			$httpClient = new Client();
			$ipInfoRequest = $httpClient->request('GET', "https://ipinfo.io/{$ip}/json");
			$json = \GuzzleHttp\json_decode($ipInfoRequest->getBody(), 1);
		}catch (\Exception $e){
			printf("\n <error>[ERR] - ERROR while parse http client isp data : {$e->getMessage()} </error>");
			$json = false;
		}

		return $json;
	}

	public function getUserAgent()
	{
		try {
			$result = new Parser($this->line['http_user_agent']);
			$agent = $result->toArray();

			//fix os version array conflict
			if (isset($agent['os']['version'])) {
				if (!is_array($agent['os']['version'])) {
					$version = $agent['os']['version'];
					$agent['os']['version'] = (array)[];
					$agent['os']['version']['value'] = $version;
				}
			}
			// http_user_agent.browser.family.version type conflict
			if (isset($agent['browser']['family']['version'])) {
				if (is_int($agent['browser']['family']['version'])) {
					$agent['browser']['family']['version'] = (string)"{$agent['browser']['family']['version']}";
				}
			}

			return $agent;
		} catch (Exception $e) {
			printf("\n <error>[ERR] - ERROR while parse user_agent data : {$e->getMessage()} </error>");

			return [];
		}

	}

	public function getCharset()
	{
		//content type
		if (strpos($this->line['content_type'], ';') !== false) {
			$charsetInfos = explode(';', $this->line['content_type']);
			$charset = str_replace('charset=', '', $charsetInfos[1]);
			$charset = strtoupper($charset);
		} else {
			$charset = 'none';
		}

		return str_replace(' ', '', $charset);
	}

	public function getContentType()
	{
		//content type
		if (strpos($this->line['content_type'], ';') !== false) {
			$charsetInfos = explode(';', $this->line['content_type']);
			$contentType = $charsetInfos[0];
		} else {
			$contentType = $this->line['content_type'];
		}

		return str_replace(' ', '', $contentType);
	}

	public function getUrl()
	{
		return explode(' ', $this->line['request'])[1];
	}

	public function getMethod()
	{
		return explode(' ', $this->line['request'])[0];
	}

	public function getHttpVersion()
	{
		return explode(' ', $this->line['request'])[2];
	}

	public function getVirtualHost()
	{
		return str_replace('www.', '', $this->line['virtual_host']);
	}

	public function getLocalDate()
	{
		try {
			return new \Carbon\Carbon($this->line['time_local']);
		} catch (Exception $e) {
			printf("\n <error>[ERR] - ERROR while parse time_local data : {$e->getMessage()} </error>");

			return \Carbon\Carbon::now();
		}
	}

	public function toArray()
	{
		return [
			'created_at' => $this->getLocalDate()->toAtomString(),
			'register_at' => Carbon::now()->toAtomString(),
			'virtual_host' => $this->getVirtualHost(),
			'url' => $this->getUrl(),
			'method' => $this->getMethod(),
			'http_version' => $this->getHttpVersion(),
			'real_ip' => $this->getClientIp(),
			'http_x_forwarded_for' => $this->line['http_x_forwarded_for'],
			'http_user_agent_raw' => $this->line['http_user_agent'],
			'remote_addr' => $this->line['remote_addr'],
			'time_local' => $this->line['time_local'],
			'body_bytes_sent' => (int)$this->line['body_bytes_sent'],
			'request_time' => (float)$this->line['request_time'],
			'http_referrer' => $this->line['http_referrer'],
			'request' => $this->line['request'],
			'content_type' => $this->getContentType(),
			'charset' => $this->getCharset(),
			'content_type_raw' => $this->line['content_type'],
			'status' => $this->line['status'],
			'remote_user' => $this->line['remote_user'],
			'location' => $this->getClientLocation(),
			'isp' => $this->getClientISP($this->getClientIp()),
			'client_hostname' => $this->getClientHostName($this->getClientIp()),
			'http_user_agent' => $this->getUserAgent()
		];
	}
}