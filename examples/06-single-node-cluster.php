<?php
/**
 * Voorbeeld voor het aanmaken van indices in een single-node cluster
 * 
 * In een single-node Elasticsearch cluster krijgen indices standaard een "yellow" status
 * omdat Elasticsearch standaard replicas aanmaakt, maar er geen andere nodes zijn om deze te plaatsen.
 * Dit voorbeeld laat zien hoe je indices kunt aanmaken zonder replicas.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Index\IndexManager;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
]);

// Maak een IndexManager instance
$indexManager = new IndexManager($client);

// Test index naam
$indexName = 'test-green-' . time();

try {
    // 1. Toon cluster health
    $response = $client->getClient()->cluster()->health();
    $health = $response->asArray();
    
    echo "Cluster status: {$health['status']}\n";
    echo "Aantal nodes: {$health['number_of_nodes']}\n";
    echo "Actieve shards: {$health['active_shards']}\n";
    echo "Unassigned shards: {$health['unassigned_shards']}\n\n";
    
    // 2. Maak een index aan met 0 replicas (voor een green status in single-node cluster)
    echo "Index aanmaken met 0 replicas: {$indexName}\n";
    
    $indexManager->create($indexName, [
        'number_of_shards' => 1,
        'number_of_replicas' => 0
    ]);
    
    // 3. Controleer de index status
    $response = $client->getClient()->indices()->stats(['index' => $indexName]);
    $stats = $response->asArray();
    
    echo "Index aangemaakt: {$indexName}\n";
    
    // 4. Controleer de health van de specifieke index
    $response = $client->getClient()->cluster()->health(['index' => $indexName]);
    $indexHealth = $response->asArray();
    
    echo "Index health status: {$indexHealth['status']}\n\n";
    
    // 5. Voeg een document toe
    $documentManager = new DocumentManager($client);
    $document = [
        'title' => 'Test Document',
        'content' => 'Dit is een test document',
        'created_at' => date('c')
    ];
    
    $result = $documentManager->index($indexName, $document, 'doc-1');
    echo "Document toegevoegd met ID: {$result['_id']}\n";
    
    // 6. Refresh de index
    $client->getClient()->indices()->refresh(['index' => $indexName]);
    
    // 7. Zoek het document
    $params = [
        'index' => $indexName,
        'body' => [
            'query' => [
                'match' => [
                    'title' => 'Test'
                ]
            ]
        ]
    ];
    
    $response = $client->getClient()->search($params);
    $results = $response->asArray();
    
    $total = $results['hits']['total']['value'];
    echo "Zoekresultaten: {$total} documenten gevonden\n";
    
    if ($total > 0) {
        foreach ($results['hits']['hits'] as $hit) {
            echo "- Document ID: {$hit['_id']}, Title: {$hit['_source']['title']}\n";
        }
    }
    
    // 8. Verwijder de index
    $indexManager->delete($indexName);
    echo "\n✅ Index verwijderd\n";
    
} catch (ElasticClientException $e) {
    echo "❌ Fout: {$e->getMessage()}\n";
}