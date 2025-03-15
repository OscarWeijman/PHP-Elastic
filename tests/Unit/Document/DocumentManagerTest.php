<?php

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Index\IndexManager;

beforeEach(function () {
    $this->client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);

    if (!$this->client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }

    $this->testIndex = 'test-documents-' . time();
    $indexManager = new IndexManager($this->client);
    $indexManager->create($this->testIndex);

    $this->documentManager = new DocumentManager($this->client);
});

afterEach(function () {
    if (isset($this->testIndex)) {
        $indexManager = new IndexManager($this->client);
        try {
            $indexManager->delete($this->testIndex);
        } catch (Exception $e) {
            // Ignore deletion errors in cleanup
        }
    }
});

test('DocumentManager can index a document', function () {
    $document = [
        'title' => 'Test Document',
        'content' => 'This is a test document',
        'tags' => ['test', 'document'],
        'created_at' => '2025-03-14T21:54:00Z'
    ];

    $result = $this->documentManager->index($this->testIndex, $document);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('_id')
        ->and($result)->toHaveKey('result')
        ->and($result['result'])->toBe('created');
});

test('DocumentManager can get a document by ID', function () {
    $document = [
        'title' => 'Test Document',
        'content' => 'This is a test document'
    ];

    // Index a document first
    $indexResult = $this->documentManager->index($this->testIndex, $document, 'test-id-1');
    
    // Get the document
    $result = $this->documentManager->get($this->testIndex, 'test-id-1');

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('_source')
        ->and($result['_source'])->toMatchArray($document);
});

test('DocumentManager can update a document', function () {
    $document = [
        'title' => 'Original Title',
        'content' => 'Original content'
    ];

    // Index original document
    $indexResult = $this->documentManager->index($this->testIndex, $document, 'test-id-2');

    // Update the document
    $update = [
        'title' => 'Updated Title',
        'updated_at' => '2025-03-14T21:54:00Z'
    ];

    $updateResult = $this->documentManager->update($this->testIndex, 'test-id-2', $update);

    // Get the updated document
    $result = $this->documentManager->get($this->testIndex, 'test-id-2');

    expect($result['_source'])->toHaveKey('title')
        ->and($result['_source']['title'])->toBe('Updated Title')
        ->and($result['_source'])->toHaveKey('content')
        ->and($result['_source']['content'])->toBe('Original content')
        ->and($result['_source'])->toHaveKey('updated_at');
});

test('DocumentManager can delete a document', function () {
    $document = [
        'title' => 'To Be Deleted',
        'content' => 'This document will be deleted'
    ];

    // Index a document
    $indexResult = $this->documentManager->index($this->testIndex, $document, 'test-id-3');
    
    // Ensure document is indexed before trying to delete
    $this->client->getClient()->indices()->refresh(['index' => $this->testIndex]);

    // Delete the document
    $deleteResult = $this->documentManager->delete($this->testIndex, 'test-id-3');

    expect($deleteResult)->toBeArray()
        ->and($deleteResult)->toHaveKey('result')
        ->and($deleteResult['result'])->toBe('deleted');

    // Verify document is gone - we need to catch the exception here
    try {
        $this->documentManager->get($this->testIndex, 'test-id-3');
        $this->fail('Expected exception was not thrown');
    } catch (\RuntimeException $e) {
        // If we get a 404 exception, that's what we expect
        expect($e->getMessage())->toContain('Document not found');
        expect($e->getCode())->toBe(404);
    }
});

test('DocumentManager can perform bulk operations', function () {
    $operations = [];
    
    // Create bulk index operations
    $operations[] = ['index' => ['_index' => $this->testIndex, '_id' => 'bulk-1']];
    $operations[] = ['title' => 'Bulk Doc 1', 'content' => 'First bulk document'];
    
    $operations[] = ['index' => ['_index' => $this->testIndex, '_id' => 'bulk-2']];
    $operations[] = ['title' => 'Bulk Doc 2', 'content' => 'Second bulk document'];

    $result = $this->documentManager->bulk($operations);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('items')
        ->and($result['items'])->toHaveCount(2)
        ->and($result['errors'])->toBeFalse();

    // Verify documents were created
    $doc1 = $this->documentManager->get($this->testIndex, 'bulk-1');
    $doc2 = $this->documentManager->get($this->testIndex, 'bulk-2');

    expect($doc1['_source']['title'])->toBe('Bulk Doc 1')
        ->and($doc2['_source']['title'])->toBe('Bulk Doc 2');
});