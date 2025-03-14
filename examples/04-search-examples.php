<?php
/**
 * Zoek voorbeelden met SearchBuilder
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Search\SearchBuilder;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Index\IndexManager;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
]);

// Maak instances van de managers
$searchBuilder = new SearchBuilder($client);
$documentManager = new DocumentManager($client);
$indexManager = new IndexManager($client);

// Test index naam
$indexName = 'test-products-' . time();

try {
    // 1. Setup: Maak een test index en voeg wat test data toe
    setupTestData($indexName, $indexManager, $documentManager, $client);
    echo "✅ Test data toegevoegd\n\n";
    
    // Controleer of de index bestaat en documenten bevat
    $response = $client->getClient()->count(['index' => $indexName]);
    $count = $response->asArray()['count'];
    echo "Index bevat {$count} documenten\n\n";
    
    // 2. Basis text zoeken
    echo "🔍 Basis tekst zoeken op 'smartphone':\n";
    
    // Debug: Toon de volledige query
    $query = $searchBuilder
        ->indices($indexName)
        ->match('description', 'smartphone')
        ->getQuery();
    echo "Query: " . json_encode($query, JSON_PRETTY_PRINT) . "\n\n";
    
    $results = $searchBuilder
        ->indices($indexName)
        ->match('description', 'smartphone')
        ->execute();
    
    printResults($results);
    
    // 3. Zoeken met filters
    echo "\n🔍 Zoeken met prijs filter (>= 500):\n";
    $results = $searchBuilder
        ->indices($indexName)
        ->range('price', ['gte' => 500])
        ->sort(['price' => 'desc'])
        ->execute();
    
    printResults($results);
    
    // 4. Zoeken met meerdere condities
    echo "\n🔍 Zoeken op categorie 'electronics' met voorraad:\n";
    $results = $searchBuilder
        ->indices($indexName)
        ->term('category', 'electronics')
        ->term('in_stock', true)
        ->execute();
    
    printResults($results);
    
    // 5. Zoeken met aggregaties
    echo "\n📊 Aggregaties per categorie:\n";
    $results = $searchBuilder
        ->indices($indexName)
        ->size(0) // We willen alleen aggregaties
        ->aggregation('categories', [
            'terms' => ['field' => 'category']
        ])
        ->aggregation('avg_price', [
            'avg' => ['field' => 'price']
        ])
        ->execute();
    
    printAggregations($results);
    
    // 6. Clean up
    try {
        $indexManager->delete($indexName);
        echo "\n✅ Test index verwijderd\n";
    } catch (Exception $e) {
        echo "\n❌ Kon de test index niet verwijderen: {$e->getMessage()}\n";
    }
    
} catch (ElasticClientException $e) {
    echo "❌ Fout: {$e->getMessage()}\n";
    
    // Probeer de test index op te ruimen
    try {
        if ($indexManager->exists($indexName)) {
            $indexManager->delete($indexName);
            echo "✅ Test index opgeruimd\n";
        }
    } catch (Exception $e) {
        echo "❌ Kon de test index niet opruimen: {$e->getMessage()}\n";
    }
}

/**
 * Helper functie om test data op te zetten
 */
function setupTestData($indexName, $indexManager, $documentManager, $client) {
    // Maak index met mappings - gebruik 0 replicas voor een green status in single-node cluster
    $indexManager->create($indexName, [
        'number_of_shards' => 1,
        'number_of_replicas' => 0
    ], [
        'properties' => [
            'name' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'price' => ['type' => 'float'],
            'category' => ['type' => 'keyword'],
            'tags' => ['type' => 'keyword'],
            'in_stock' => ['type' => 'boolean'],
            'created_at' => ['type' => 'date']
        ]
    ]);
    
    // Voeg test producten toe
    $products = [
        [
            'name' => 'iPhone 15 Pro',
            'description' => 'De nieuwste iPhone met geweldige camera',
            'price' => 999.99,
            'category' => 'electronics',
            'tags' => ['smartphone', 'apple', 'ios'],
            'in_stock' => true,
            'created_at' => '2025-03-14T21:54:00Z'
        ],
        [
            'name' => 'Samsung Galaxy S24',
            'description' => 'Android smartphone met AI features',
            'price' => 899.99,
            'category' => 'electronics',
            'tags' => ['smartphone', 'samsung', 'android'],
            'in_stock' => false,
            'created_at' => '2025-03-14T21:55:00Z'
        ],
        [
            'name' => 'AirPods Pro',
            'description' => 'Draadloze oordopjes met noise cancelling',
            'price' => 249.99,
            'category' => 'accessories',
            'tags' => ['audio', 'apple', 'wireless'],
            'in_stock' => true,
            'created_at' => '2025-03-14T21:56:00Z'
        ],
        [
            'name' => 'MacBook Pro 16"',
            'description' => 'Krachtige laptop voor professionals',
            'price' => 2499.99,
            'category' => 'electronics',
            'tags' => ['laptop', 'apple', 'macos'],
            'in_stock' => true,
            'created_at' => '2025-03-14T21:57:00Z'
        ],
        [
            'name' => 'USB-C Kabel',
            'description' => 'Snelle oplaadkabel',
            'price' => 19.99,
            'category' => 'accessories',
            'tags' => ['cable', 'charging'],
            'in_stock' => true,
            'created_at' => '2025-03-14T21:58:00Z'
        ]
    ];
    
    // Voeg elk product individueel toe voor betere controle
    foreach ($products as $i => $product) {
        $documentManager->index($indexName, $product, 'prod-' . ($i + 1));
    }
    
    // Refresh de index om de documenten direct doorzoekbaar te maken
    $client->getClient()->indices()->refresh(['index' => $indexName]);
    
    // Wacht even om zeker te zijn dat de documenten geïndexeerd zijn
    sleep(1);
}

/**
 * Helper functie om zoekresultaten te printen
 */
function printResults($results) {
    $total = $results['hits']['total']['value'];
    echo "Gevonden: {$total} resultaten\n";
    
    foreach ($results['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        echo "- {$source['name']} (€{$source['price']}) - {$source['category']}\n";
    }
}

/**
 * Helper functie om aggregatie resultaten te printen
 */
function printAggregations($results) {
    // Print categorie verdeling
    echo "Producten per categorie:\n";
    if (isset($results['aggregations']['categories']['buckets'])) {
        foreach ($results['aggregations']['categories']['buckets'] as $bucket) {
            echo "- {$bucket['key']}: {$bucket['doc_count']} producten\n";
        }
    } else {
        echo "Geen categorieën gevonden\n";
    }
    
    // Print gemiddelde prijs
    if (isset($results['aggregations']['avg_price']['value'])) {
        $avgPrice = number_format($results['aggregations']['avg_price']['value'], 2);
        echo "Gemiddelde prijs: €{$avgPrice}\n";
    } else {
        echo "Geen gemiddelde prijs beschikbaar\n";
    }
}