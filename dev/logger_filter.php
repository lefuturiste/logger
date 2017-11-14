<?php
require '../vendor/autoload.php';

$builder = ClientBuilder::create();

$builder->setHosts(
	['localhost']
);

$indexName = 'logger';

$client = $builder->build();

$params = [
    'index' => 'logger-*',
    'type' => 'nginx-access',
    'body' => [
        'query' => [
            'match' => [
                'charset' => ''
            ]
        ]
    ]
];

$response = $client->search($params);

foreach ($response['hits']['hits'] as $value) {
	//change index
	$body = $value['_source'];
	$body['charset'] = 'none';
	$params = [
	    'index' => 'logger-2017.11.14',
	    'type' => 'nginx-access',
	    'id' => $value['_id'],
	    'body' => $body
	];
	$response = $client->update($params);
}