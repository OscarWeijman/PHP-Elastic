<?php

use OscarWeijman\PhpElastic\ElasticClient;

test('can create elastic client', function () {
    $client = new ElasticClient([
        'hosts' => [getElasticsearchHost()]
    ]);
    
    expect($client)->toBeInstanceOf(ElasticClient::class);
});

test('can connect to elasticsearch', function () {
    $client = new ElasticClient([
        'hosts' => [getElasticsearchHost()]
    ]);
    
    $info = $client->info();
    expect($info)
        ->toBeArray()
        ->toHaveKey('version')
        ->toHaveKey('tagline')
        ->and($info['tagline'])->toBe('You Know, for Search');
});