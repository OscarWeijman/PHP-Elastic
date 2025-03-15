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
    
    // The create method returns an array in GitHub Actions but a boolean locally
    expect($indexManager->exists($testIndex))->toBeTrue();
    
    // Delete index
    $deleteResult = $indexManager->delete($testIndex);
    // Check if index no longer exists instead of checking the return value
    expect($indexManager->exists($testIndex))->toBeFalse();
});