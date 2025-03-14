<?php

use OscarWeijman\PhpElastic\ElasticClient;

test('ElasticClient can be instantiated', function () {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);
    
    expect($client)->toBeInstanceOf(ElasticClient::class);
});

test('ElasticClient can ping Elasticsearch server', function () {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);
    
    // This test will be skipped if Elasticsearch is not running
    if (!$client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }
    
    expect($client->ping())->toBeTrue();
});

test('ElasticClient can get info from Elasticsearch server', function () {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);
    
    // This test will be skipped if Elasticsearch is not running
    if (!$client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }
    
    $info = $client->info();
    
    expect($info)->toBeArray()
        ->and($info)->toHaveKey('version')
        ->and($info['version'])->toHaveKey('number');
});