<?php

/**
 * Document management voorbeeld
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Index\IndexManager;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
]);

// Maak DocumentManager en IndexManager instances
$documentManager = new DocumentManager($client);
$indexManager = new IndexManager($client);

// Test index naam
$indexName = 'test-products-' . time();

try {
    // 1. Maak eerst een test index
    $indexManager->create($indexName);
    echo "✅ Test index aangemaakt: {$indexName}\n";

    // 2. Voeg een enkel document toe
    $document = [
        'name' => 'Test Product',
        'description' => 'Dit is een test product',
        'price' => 29.99,
        'category' => 'electronics',
        'tags' => ['test', 'voorbeeld'],
        'in_stock' => true,
        'created_at' => '2025-03-14T21:54:00Z'
    ];

    $result = $documentManager->index($indexName, $document, 'prod-1');
    echo "✅ Document toegevoegd met ID: {$result['_id']}\n";

    // 3. Haal het document op
    $retrievedDoc = $documentManager->get($indexName, 'prod-1');
    echo "Document opgehaald:\n";
    echo json_encode($retrievedDoc['_source'], JSON_PRETTY_PRINT) . "\n";

    // 4. Update het document
    $update = [
        'price' => 24.99,
        'in_stock' => false
    ];

    $documentManager->update($indexName, 'prod-1', $update);
    echo "✅ Document geüpdatet\n";

    // 5. Voeg meerdere documenten toe met bulk operatie
    $bulkDocs = [];

    // Eerste document
    $bulkDocs[] = ['index' => ['_index' => $indexName, '_id' => 'prod-2']];
    $bulkDocs[] = [
        'name' => 'Tweede Product',
        'description' => 'Nog een test product',
        'price' => 49.99,
        'category' => 'electronics',
        'tags' => ['test', 'bulk'],
        'in_stock' => true,
        'created_at' => '2025-03-14T22:00:00Z'
    ];

    // Tweede document
    $bulkDocs[] = ['index' => ['_index' => $indexName, '_id' => 'prod-3']];
    $bulkDocs[] = [
        'name' => 'Derde Product',
        'description' => 'Het laatste test product',
        'price' => 19.99,
        'category' => 'accessories',
        'tags' => ['test', 'bulk', 'goedkoop'],
        'in_stock' => true,
        'created_at' => '2025-03-14T22:05:00Z'
    ];

    $bulkResult = $documentManager->bulk($bulkDocs);
    echo "✅ Bulk operatie uitgevoerd\n";
    echo "Toegevoegde documenten: " . count($bulkResult['items']) . "\n";

    // 6. Verwijder een document
    $documentManager->delete($indexName, 'prod-1');
    echo "✅ Document prod-1 verwijderd\n";

    // 7. Clean up: verwijder de test index
    // $indexManager->delete($indexName);
    // echo "✅ Test index verwijderd\n";

} catch (ElasticClientException $e) {
    echo "❌ Fout: {$e->getMessage()}\n";

    // Probeer de test index op te ruimen als er iets fout gaat
    try {
        if ($indexManager->exists($indexName)) {
            $indexManager->delete($indexName);
            echo "✅ Test index opgeruimd\n";
        }
    } catch (Exception $e) {
        echo "❌ Kon de test index niet opruimen: {$e->getMessage()}\n";
    }
}
