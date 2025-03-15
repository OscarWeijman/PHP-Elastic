<?php

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Search\SearchBuilder;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Index\IndexManager;

beforeEach(function () {
    $this->client = new ElasticClient([
        'hosts' => ['localhost:9200']
    ]);

    if (!$this->client->ping()) {
        $this->markTestSkipped('Elasticsearch server is not available');
    }

    $this->testIndex = 'test-search-' . time();
    $indexManager = new IndexManager($this->client);
    
    // Create test index with mappings
    $indexManager->create($this->testIndex, [
        'number_of_shards' => 1,
        'number_of_replicas' => 0
    ], [
        'properties' => [
            'title' => ['type' => 'text'],
            'content' => ['type' => 'text'],
            'tags' => ['type' => 'keyword'],
            'rating' => ['type' => 'integer'],
            'created_at' => ['type' => 'date']
        ]
    ]);

    // Add test documents
    $documentManager = new DocumentManager($this->client);
    
    $documents = [
        [
            'title' => 'Elasticsearch Guide',
            'content' => 'A comprehensive guide to Elasticsearch',
            'tags' => ['elasticsearch', 'guide', 'search'],
            'rating' => 5,
            'created_at' => '2025-01-15T12:00:00Z'
        ],
        [
            'title' => 'PHP Development',
            'content' => 'Learn PHP development with practical examples',
            'tags' => ['php', 'development', 'programming'],
            'rating' => 4,
            'created_at' => '2025-02-20T14:30:00Z'
        ],
        [
            'title' => 'Elasticsearch with PHP',
            'content' => 'How to use Elasticsearch with PHP applications',
            'tags' => ['elasticsearch', 'php', 'integration'],
            'rating' => 5,
            'created_at' => '2025-03-10T09:15:00Z'
        ],
        [
            'title' => 'Web Development Basics',
            'content' => 'Introduction to web development concepts',
            'tags' => ['web', 'development', 'basics'],
            'rating' => 3,
            'created_at' => '2025-01-05T16:45:00Z'
        ]
    ];

    $operations = [];
    foreach ($documents as $i => $doc) {
        $operations[] = ['index' => ['_index' => $this->testIndex, '_id' => 'doc-' . ($i + 1)]];
        $operations[] = $doc;
    }

    $documentManager->bulk($operations);

    // Refresh index to make documents searchable immediately
    $this->client->getClient()->indices()->refresh(['index' => $this->testIndex]);

    $this->searchBuilder = new SearchBuilder($this->client);
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

test('SearchBuilder can perform a basic match query', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->match('title', 'elasticsearch')
        ->execute();

    expect($results)->toBeArray()
        ->and($results)->toHaveKey('hits')
        ->and($results['hits'])->toHaveKey('total')
        ->and($results['hits']['total']['value'])->toBe(2);
});

test('SearchBuilder can filter results with term query', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->term('tags', 'php')
        ->execute();

    expect($results['hits']['total']['value'])->toBe(2);
    
    // Check that all results have the 'php' tag
    foreach ($results['hits']['hits'] as $hit) {
        expect($hit['_source']['tags'])->toContain('php');
    }
});

test('SearchBuilder can use range filters', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->range('rating', ['gte' => 4])
        ->execute();

    expect($results['hits']['total']['value'])->toBe(3);
    
    // Check that all results have rating >= 4
    foreach ($results['hits']['hits'] as $hit) {
        expect($hit['_source']['rating'])->toBeGreaterThanOrEqual(4);
    }
});

test('SearchBuilder can use pagination', function () {
    // First page (2 results)
    $page1 = $this->searchBuilder
        ->indices($this->testIndex)
        ->from(0)
        ->size(2)
        ->execute();

    // Second page (2 results)
    $page2 = $this->searchBuilder
        ->indices($this->testIndex)
        ->from(2)
        ->size(2)
        ->execute();

    expect($page1['hits']['hits'])->toHaveCount(2)
        ->and($page2['hits']['hits'])->toHaveCount(2)
        ->and($page1['hits']['hits'][0]['_id'])->not->toBe($page2['hits']['hits'][0]['_id']);
});

test('SearchBuilder can sort results', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->sort(['created_at' => 'asc'])
        ->execute();

    $dates = array_map(function ($hit) {
        return $hit['_source']['created_at'];
    }, $results['hits']['hits']);

    // Check that dates are in ascending order
    $sortedDates = $dates;
    sort($sortedDates);
    expect($dates)->toBe($sortedDates);
});

test('SearchBuilder can use aggregations', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->aggregation('avg_rating', ['avg' => ['field' => 'rating']])
        ->aggregation('tags', ['terms' => ['field' => 'tags']])
        ->size(0) // We only want aggregations, not results
        ->execute();

    expect($results)->toHaveKey('aggregations')
        ->and($results['aggregations'])->toHaveKey('avg_rating')
        ->and($results['aggregations'])->toHaveKey('tags')
        ->and($results['aggregations']['avg_rating'])->toHaveKey('value')
        ->and($results['aggregations']['tags'])->toHaveKey('buckets');
});

test('SearchBuilder can combine multiple query conditions', function () {
    $results = $this->searchBuilder
        ->indices($this->testIndex)
        ->match('content', 'elasticsearch')
        ->term('tags', 'php')
        ->range('rating', ['gte' => 4])
        ->execute();

    expect($results['hits']['total']['value'])->toBe(1);
    expect($results['hits']['hits'][0]['_source']['title'])->toBe('Elasticsearch with PHP');
});

test('SearchBuilder can build and return raw query', function () {
    $this->searchBuilder
        ->indices($this->testIndex)
        ->match('title', 'elasticsearch')
        ->term('tags', 'php')
        ->from(0)
        ->size(5)
        ->sort(['rating' => 'desc']);

    $query = $this->searchBuilder->buildQuery();

    expect($query)->toBeArray()
        ->and($query)->toHaveKey('from')
        ->and($query)->toHaveKey('size')
        ->and($query)->toHaveKey('query')
        ->and($query)->toHaveKey('sort')
        ->and($query['from'])->toBe(0)
        ->and($query['size'])->toBe(5);
});