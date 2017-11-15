<?php

namespace App\Commands;

use Carbon\Carbon;
use Coduo\PHPMatcher\Exception\Exception;
use Coduo\PHPMatcher\Factory\SimpleFactory;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use GeoIp2\Database\Reader;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use WhichBrowser\Parser;

class RunCommand extends Command
{
	protected function configure()
	{
		$this
			// the name of the command (the part after "bin/console")
			->setName('app:run')
			// the short description shown while running "php bin/console list"
			->setDescription('Run application');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('Running application...');

		//reader
		$geoLitePath = getenv('GEOLITE_PATH');
		$reader = new Reader(
			getenv('GEOLITE_PATH')
		);

		$output->writeln("- [X] Load {$geoLitePath}");

		//reader
		$builder = ClientBuilder::create();

		$builder->setHosts(
			[getenv('ELASTICSEARCH_HOST')]
		);

		$indexName = getenv('ELATICSEARCH_INDEX_NAME');

		$client = $builder->build();

		$output->writeln('- [X] Connected to elasticsearch server');

		$output->writeln('- [X] Started while');

		$files = Yaml::parse(file_get_contents('./files.yml'));


		$lineCountTemp = [];
		$hashTemp = [];

		foreach ($files AS $file) {
			$lineCountTemp[$file['id']] = 0;
			$hashTemp[$file['id']] = NULL;
		}
		while (true) {
			foreach ($files AS $file) {
				//on hash le fichier
				$hash = hash('sha256', file_get_contents($file['path']));
				//on get le nombre de lignes
				$lines = file($file['path'], FILE_IGNORE_NEW_LINES);
				$count = count($lines);

				//si c'est la première fois on ne fait rien
				if ($hashTemp[$file['id']] != NULL) {

					if ($hash == $hashTemp[$file['id']] AND $count = $lineCountTemp[$file['id']]) {
						//aucun nouveau contenu
//						$output->writeln("- [X] NONE New content found on {$file['path']} (0 new(s) lines)");
					} else {
						//nouveau contenu detecté
						//on filtre les donnés et on les stores
						//on génére un tableau qui contient que les nouvelles lignes
						//on prend le nb de nouvelles lignes
						$newLinesCount = $count - (int)$lineCountTemp[$file['id']];
						if ($newLinesCount > 0) {
							//on prend les dernières lignes
							$data = file($file['path']);
							$i = 0;
							$newLines = [];

							$output->writeln("- [X] New content found on {$file['path']} ({$newLinesCount} new(s) lines)");

							while ($newLinesCount != $i) {
								$line = $data[count($data) - ($newLinesCount - $i)];
								array_push($newLines, $line);
								$i++;
							}


							//for each line, generate rich data from base nginx data
							foreach ($newLines AS $line) {
								//on choisis le mode de parse
								switch ($file['type']) {
									case 'nginx-access':
										//decode
										$line = json_decode($line, 1);
										$extraBody = [];
										try {
											//1. GET GEO IP location
											$record = $reader->city($line['http_x_forwarded_for']);

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
											$extraBody['location'] = $location;
										} catch (\InvalidArgumentException $e) {
											$output->writeln("<error>[ERR] - ERROR while parse ip data : {$e->getMessage()} - {$e->getCode()}</error>");
										}

										//2. GET USER AGENT
										$result = new Parser($line['http_user_agent']);
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
										if (isset($agent['browser']['family']['version'])){
											if (is_int($agent['browser']['family']['version'])){
												$agent['browser']['family']['version'] = (string)"{$agent['browser']['family']['version']}";
											}
										}

										//3. GET Request information
										$request = explode(' ', $line['request']);
										//content type
										if (strpos($line['content_type'], ';') !== false) {
											$charsetInfos = explode(';', $line['content_type']);
											$contentType = $charsetInfos[0];
											$charset = str_replace('charset=', '', $charsetInfos[1]);
											$charset = strtoupper($charset);
										} else {
											$contentType = $line['content_type'];
											$charset = 'none';
										}
										$charset = str_replace(' ', '', $charset);
										$contentType = str_replace(' ', '', $contentType);

										$date = Carbon::now();
										$body = [
											'created_at' => $date->toAtomString(),
											'virtual_host' => $line['virtual_host'],
											'url' => $request[1],
											'method' => $request[0],
											'http_version' => $request[2],
											'http_x_forwarded_for' => $line['http_x_forwarded_for'],
											'http_user_agent_raw' => $line['http_user_agent'],
											'remote_addr' => $line['remote_addr'],
											'time_local' => $line['time_local'],
											'body_bytes_sent' => (int)$line['body_bytes_sent'],
											'request_time' => (float)$line['request_time'],
											'http_referrer' => $line['http_referrer'],
											'request' => $line['request'],
											'content_type' => $contentType,
											'charset' => $charset,
											'content_type_raw' => $line['content_type'],
											'status' => $line['status'],
											'remote_user' => $line['remote_user'],
											'http_user_agent' => $agent
										];
										$body = array_merge($body, $extraBody);
										break;

									case 'nginx-error':
										try {
											$parser = new \TM\ErrorLogParser\Parser(\TM\ErrorLogParser\Parser::TYPE_NGINX); // or TYPE_NGINX;
											$entry = $parser->parse($line);

											$request = explode(' ', $entry->request);
											$date = Carbon::now();
											$body = [
												'created_at' => $date->toAtomString(),
												'time_local' => $entry->date,
												'level' => $entry->type,
												'message' => $entry->message,
												'virtual_host' => $entry->server,
												'request' => $entry->request,
												'url' => $request[1],
												'method' => $request[0],
												'http_version' => $request[2],
												'remote_addr' => $entry->client,
											];
										}catch (\TM\ErrorLogParser\Exception\FormatException $e){
											$output->writeln("<error>[ERR] - ERROR while parse nginx error data : {$e->getMessage()} - {$e->getCode()}</error>");
										}

										break;
								}

								if (isset($body)) {
									$params = [
										'index' => "{$indexName}-{$date->year}.{$date->month}.{$date->day}",
										'type' => $file['type'],
										'body' => $body
									];
									try {
										$client->index($params);
										$output->writeln("- [X] Send data to elasticseach");
									} catch (BadRequest400Exception $e) {
										$output->writeln("<error>[ERR] - ERROR while send data to elasticseach : {$e->getMessage()} - {$e->getCode()}</error>");
									}
								} else {
									$output->writeln("- [X] None data to elasticseach");
								}
							}
							$lineCountTemp[$file['id']] = $count;
							$hashTemp[$file['id']] = $hash;
						}
					}
				} else {
					$lineCountTemp[$file['id']] = $count;
					$hashTemp[$file['id']] = $hash;
				}
			}
			sleep(2);
		}
	}
}
