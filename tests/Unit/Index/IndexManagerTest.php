<?php

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Index\IndexManager;

test('IndexManager can be instantiated', function () {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);
    
    $indexManager = new IndexManager($client);
    
    expect($indexManager)->toBeInstanceOf(IndexManager::class);
});

test('IndexManager can create and delete an index', function () {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);
    
    // Skip test if Elasticsearch is not available
    if (!$client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }
    
    $indexManager = new IndexManager($client);
    $testIndex = 'test-index-' . time();
    
    // Create index
    $result = $indexManager->create($testIndex, [
        'number_of_shards' => 1,
        'number_of_replicas' => 0
    ]);
    
    expect($result)->toBeTrue();
    expect($indexManager->exists($testIndex))->toBeTrue();
    
    // Get settings
    $settings = $indexManager->getSettings($testIndex);
    expect($settings)->toBeArray();
    
    // Delete index
    $deleteResult = $indexManager->delete($testIndex);
    expect($deleteResult)->toBeTrue();
    expect($indexManager->exists($testIndex))->toBeFalse();
});