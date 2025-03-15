<?php

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Index\IndexManager;
use OscarWeijman\PhpElastic\Search\SearchBuilder;

beforeEach(function () {
    $this->client = new ElasticClient([
        'hosts' => [getElasticsearchHost()]
    ]);

    if (!$this->client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }

    $this->testIndex = 'test-integration-' . time();
});

afterEach(function () {
    if (isset($this->testIndex)) {
        try {
            $indexManager = new IndexManager($this->client);
            $indexManager->delete($this->testIndex);
        } catch (Exception $e) {
            // Ignore deletion errors in cleanup
        }
    }
});

test('full integration test with index, document and search', function () {
    // 1. Create an index with mappings
    $indexManager = new IndexManager($this->client);
    $result = $indexManager->create($this->testIndex, [
        'number_of_shards' => 1,
        'number_of_replicas' => 0
    ], [
        'properties' => [
            'title' => ['type' => 'text'],
            'content' => ['type' => 'text'],
            'tags' => ['type' => 'keyword'],
            'rating' => ['type' => 'integer']
        ]
    ]);

    expect($result)->toBeTrue();
    expect($indexManager->exists($this->testIndex))->toBeTrue();

    // 2. Index some documents
    $documentManager = new DocumentManager($this->client);
    
    $doc1 = [
        'title' => 'Elasticsearch Guide',
        'content' => 'A comprehensive guide to using Elasticsearch',
        'tags' => ['elasticsearch', 'guide'],
        'rating' => 5
    ];
    
    $doc2 = [
        'title' => 'PHP Development',
        'content' => 'Learn PHP development with practical examples',
        'tags' => ['php', 'development'],
        'rating' => 4
    ];
    
    $doc3 = [
        'title' => 'Elasticsearch with PHP',
        'content' => 'How to integrate Elasticsearch with PHP applications',
        'tags' => ['elasticsearch', 'php', 'integration'],
        'rating' => 5
    ];

    $indexResult1 = $documentManager->index($this->testIndex, $doc1, 'doc-1');
    $indexResult2 = $documentManager->index($this->testIndex, $doc2, 'doc-2');
    $indexResult3 = $documentManager->index($this->testIndex, $doc3, 'doc-3');

    expect($indexResult1)->toHaveKey('result')
        ->and($indexResult1['result'])->toBe('created');

    // Refresh index to make documents searchable immediately
    $this->client->getClient()->indices()->refresh(['index' => $this->testIndex]);

    // 3. Search for documents
    $searchBuilder = new SearchBuilder($this->client);
    
    // Search for documents with 'elasticsearch' in the title
    $results = $searchBuilder
        ->indices($this->testIndex)
        ->match('title', 'elasticsearch')
        ->execute();

    expect($results['hits']['total']['value'])->toBe(2);
    
    // Search for documents with both 'elasticsearch' and 'php' tags
    $results = $searchBuilder
        ->indices($this->testIndex)
        ->terms('tags', ['elasticsearch', 'php'])
        ->execute();

    // We expect at least 1 document with these tags
    expect($results['hits']['total']['value'])->toBeGreaterThanOrEqual(1);
    
    // Search for documents with high rating
    $results = $searchBuilder
        ->indices($this->testIndex)
        ->range('rating', ['gte' => 5])
        ->execute();

    expect($results['hits']['total']['value'])->toBe(2);
    
    // 4. Update a document
    $update = [
        'rating' => 3,
        'tags' => ['php', 'development', 'updated']
    ];
    
    $updateResult = $documentManager->update($this->testIndex, 'doc-2', $update);
    expect($updateResult['result'])->toBe('updated');
    
    // Refresh index
    $this->client->getClient()->indices()->refresh(['index' => $this->testIndex]);
    
    // Verify update
    $doc = $documentManager->get($this->testIndex, 'doc-2');
    expect($doc['_source']['rating'])->toBe(3)
        ->and($doc['_source']['tags'])->toContain('updated');
    
    // 5. Delete a document
    $deleteResult = $documentManager->delete($this->testIndex, 'doc-1');
    expect($deleteResult['result'])->toBe('deleted');
    
    // Refresh index
    $this->client->getClient()->indices()->refresh(['index' => $this->testIndex]);
    
    // Verify document count
    $results = $searchBuilder
        ->indices($this->testIndex)
        ->size(10)
        ->execute();
    
    expect($results['hits']['total']['value'])->toBeGreaterThanOrEqual(1);
});