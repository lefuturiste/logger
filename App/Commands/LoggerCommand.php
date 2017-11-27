<?php

namespace App\Commands;

use App\Logger\App;
use App\Logger\Connectors\ElasticsearchConnector;
use App\Logger\Connectors\RedisConnector;
use App\Logger\Input\FileInputFactory;
use App\Logger\Parser\NginxAccessLineParser;
use App\Logger\Parser\NginxErrorLineParser;
use DI\ContainerBuilder;
use App\Logger\Parser\LineParser;
use function DI\get;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class LoggerCommand extends Command
{
	protected function configure()
	{
		$this
			// the name of the command (the part after "bin/console")
			->setName('app:logger')
			// the short description shown while running "php bin/console list"
			->setDescription('Logger');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$builder = new ContainerBuilder();
		$container = $builder->build();

		$redis = new RedisConnector([
			'host' => getenv('REDIS_HOST'),
			'port' => getenv('REDIS_PORT'),
			'password' => getenv('REDIS_PASSWORD')


		]);
		$container->set(RedisConnector::class, $redis);
		$elasticsearch = new ElasticsearchConnector([
			'host' => getenv('ELASTICSEARCH_HOST'),
			'port' => getenv('ELASTICSEARCH_PORT'),
			'scheme' => getenv('ELASTICSEARCH_SCHEME'),
			'username' => getenv('ELASTICSEARCH_USERNAME'),
			'password' => getenv('ELASTICSEARCH_PASSWORD')
		]);
		$container->set(ElasticsearchConnector::class, $elasticsearch);
		$logger = new App([
			'name' => getenv('LOGGER_NAME'),
			'geoIpCityPath' => getenv('GEOLITE_PATH')
		]);
		$container->set(App::class, $logger);
		$logger->setRedisConnector($container->get(RedisConnector::class));
		$logger->setElasticsearchConnector($container->get(ElasticsearchConnector::class));

		if (getenv('FILES_CONFIG_PATH') != NULL) {
			$files = Yaml::parse(file_get_contents(getenv('FILES_CONFIG_PATH')));

			$filesInput = [];
			foreach ($files as $file) {
				array_push($filesInput, FileInputFactory::create($redis, $logger, $file['path'], $file['type']));
			}
		}
		$logger->setFilesInput($filesInput);

		$output->write("\n - Scanning...");
		foreach ($logger->getFilesInput() as $file) {
			if ($file->hasNew()) {
				//get line
				$lines = $file->getNewLines();

				$output->write("\n - NEW content found in {$file->path} ({$file->newLineCount} lines)");

				foreach ($lines as $line) {
					$parser = new LineParser($line, $file->type, $logger);

					$indexName = 'logger';
					$date = \Carbon\Carbon::now();
					$body = $parser->toArray();
					if (isset($body)) {
						$params = [
							'index' => "{$indexName}-{$date->year}.{$date->month}.{$date->day}",
							'type' => $file->type,
							'body' => $body
						];
						try {
							$response = $elasticsearch->client->index($params);
							$output->writeln("\n - [X] Send data to elasticseach");
						} catch (BadRequest400Exception $e) {
							$output->writeln("<error>[ERR] - ERROR while send data to elasticseach : {$e->getMessage()} - {$e->getCode()}</error>");
						}
					} else {
						$output->writeln("- [X] None data to elasticseach");
					}
				}

				$file->persist();
			} else {
				echo "\n - NONE content found in {$file->path}";
			}

			echo "\n \n";
		}

		$output->write("\n - End of scanning... \n ");
	}
}
