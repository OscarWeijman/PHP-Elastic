<?php

/**
 * Index management voorbeeld
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Index\IndexManager;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
]);

// Maak een IndexManager instance
$indexManager = new IndexManager($client);

// Naam van de test index
$indexName = 'test-products-' . time();

try {
    // 1. Maak een nieuwe index met settings en mappings
    echo "Index aanmaken: {$indexName}\n";

    $indexManager->create($indexName, [
        // Index settings
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
        'analysis' => [
            'analyzer' => [
                'dutch_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'dutch_stop', 'dutch_stemmer']
                ]
            ],
            'filter' => [
                'dutch_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_dutch_'
                ],
                'dutch_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'dutch'
                ]
            ]
        ]
    ], [
        // Index mappings
        'properties' => [
            'name' => [
                'type' => 'text',
                'analyzer' => 'dutch_analyzer',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                        'ignore_above' => 256
                    ]
                ]
            ],
            'description' => [
                'type' => 'text',
                'analyzer' => 'dutch_analyzer'
            ],
            'price' => [
                'type' => 'float'
            ],
            'category' => [
                'type' => 'keyword'
            ],
            'tags' => [
                'type' => 'keyword'
            ],
            'in_stock' => [
                'type' => 'boolean'
            ],
            'created_at' => [
                'type' => 'date'
            ]
        ]
    ]);

    echo "✅ Index succesvol aangemaakt\n";

    // 2. Controleer of de index bestaat
    if ($indexManager->exists($indexName)) {
        echo "✅ Index bestaat\n";
    } else {
        echo "❌ Index bestaat niet\n";
    }

    // 3. Haal de index mappings op
    $mappings = $indexManager->getMappings($indexName);
    echo "Index mappings:\n";
    echo json_encode($mappings, JSON_PRETTY_PRINT) . "\n";

    // 4. Haal de index settings op
    $settings = $indexManager->getSettings($indexName);
    echo "Index settings:\n";
    echo json_encode($settings, JSON_PRETTY_PRINT) . "\n";

    // 5. Verwijder de index
    echo "Index verwijderen...\n";
    $indexManager->delete($indexName);
    echo "✅ Index succesvol verwijderd\n";
} catch (ElasticClientException $e) {
    echo "❌ Fout: {$e->getMessage()}\n";
}
