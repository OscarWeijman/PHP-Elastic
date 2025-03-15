<?php

/**
 * Vector Search en Embeddings voorbeeld
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use OscarWeijman\PhpElastic\Embedding\EmbeddingManager;
use OscarWeijman\PhpElastic\Search\VectorSearchBuilder;

// Configuratie
$config = [
    'elastic' => [
        'hosts' => ['localhost:9200'],
    ],
    'embedding_api' => [
        'url' => 'http://localhost:11434',  // Ollama API URL
        'key' => ''  // Niet nodig voor Ollama
    ],
    'models' => [
        'nomic-embed-text' => [
            'name' => 'nomic-embed-text:latest',
            'dims' => 768,
            'type' => 'ollama'
        ]
    ]
];

// Maak client instances
$client = new ElasticClient($config['elastic']);
$embeddingService = new EmbeddingService(
    $config['embedding_api']['url'],
    $config['embedding_api']['key'],
    $config['models']
);
$embeddingManager = new EmbeddingManager($client, $embeddingService);

// Test index naam
$indexName = 'vector-search-demo-' . time();
$modelId = 'nomic-embed-text';

// Debug: Test de embedding API direct
try {
    echo "Test embedding API voor model '{$config['models'][$modelId]['name']}'...\n";
    $testEmbedding = $embeddingService->generateEmbedding("Dit is een test", $modelId);
    echo "✅ Embedding API werkt! (vector dimensie: " . count($testEmbedding) . ")\n\n";
} catch (\Exception $e) {
    echo "❌ Fout bij testen van embedding API: " . $e->getMessage() . "\n\n";
    exit(1);
}

try {
    // 1. Controleer of Elasticsearch beschikbaar is
    if (!$client->ping()) {
        throw new \RuntimeException("Elasticsearch is niet beschikbaar. Controleer of de server draait.");
    }
    echo "✅ Verbonden met Elasticsearch\n\n";

    // 2. Maak een index met vector mapping
    echo "Maak index '{$indexName}' met vector mapping...\n";
    $embeddingManager->createVectorIndex($indexName, $modelId, [
        'title' => ['type' => 'text'],
        'category' => ['type' => 'keyword']
    ]);
    echo "✅ Index aangemaakt\n\n";

    // 3. Voeg wat documenten toe met embeddings
    echo "Documenten toevoegen met embeddings...\n";
    $documents = [
        [
            'id' => 'doc1',
            'content' => 'Amsterdam is de hoofdstad van Nederland en bekend om zijn grachten.',
            'title' => 'Amsterdam',
            'category' => 'geography'
        ],
        [
            'id' => 'doc2',
            'content' => 'Rotterdam heeft de grootste haven van Europa.',
            'title' => 'Rotterdam',
            'category' => 'geography'
        ],
        [
            'id' => 'doc3',
            'content' => 'Machine learning is een vorm van kunstmatige intelligentie.',
            'title' => 'Machine Learning',
            'category' => 'technology'
        ],
        [
            'id' => 'doc4',
            'content' => 'Python is een populaire programmeertaal voor data science.',
            'title' => 'Python',
            'category' => 'technology'
        ],
        [
            'id' => 'doc5',
            'content' => 'Elasticsearch is een zoekmachine gebaseerd op Lucene.',
            'title' => 'Elasticsearch',
            'category' => 'technology'
        ],
        [
            'id' => 'doc6',
            'content' => 'Vector search maakt semantisch zoeken mogelijk in Elasticsearch.',
            'title' => 'Vector Search',
            'category' => 'technology'
        ],
        [
            'id' => 'doc7',
            'content' => 'De Eiffeltoren staat in Parijs, Frankrijk.',
            'title' => 'Eiffeltoren',
            'category' => 'geography'
        ],
        [
            'id' => 'doc8',
            'content' => 'PHP is een programmeertaal voor webontwikkeling.',
            'title' => 'PHP',
            'category' => 'technology'
        ]
    ];

    // Bulk indexeer documenten
    $embeddingManager->bulkIndexWithEmbeddings($indexName, $modelId, $documents);
    echo "✅ " . count($documents) . " documenten geïndexeerd\n\n";

    // Refresh de index
    $client->getClient()->indices()->refresh(['index' => $indexName]);
    sleep(1); // Wacht even om zeker te zijn dat de documenten geïndexeerd zijn

    // 4. Zoek met vector similarity
    echo "🔍 Vector search voor 'Wat is de hoofdstad van Nederland?':\n";
    $results = $embeddingManager->searchWithEmbedding(
        $indexName,
        $modelId,
        'Wat is de hoofdstad van Nederland?',
        5
    );

    printResults($results);

    // 5. Zoek met vector similarity en filters
    echo "\n🔍 Vector search voor 'programmeertalen' met filter op category=technology:\n";
    $results = $embeddingManager->searchWithEmbedding(
        $indexName,
        $modelId,
        'programmeertalen',
        5,
        [['term' => ['category' => 'technology']]]
    );

    printResults($results);

    // 6. Hybride zoeken (vector + keyword)
    echo "\n🔍 Hybride search voor 'elasticsearch vector':\n";
    $results = $embeddingManager->searchHybrid(
        $indexName,
        $modelId,
        'elasticsearch vector',
        5,
        [],
        'embedding',
        'content',
        0.7,  // vector boost
        0.3   // text boost
    );

    printResults($results);

    // 7. Directe vector search met VectorSearchBuilder
    echo "\n🔍 Directe vector search met VectorSearchBuilder:\n";

    // Genereer embedding voor de query
    $queryEmbedding = $embeddingService->generateEmbedding('machine learning en AI', $modelId);

    // Gebruik VectorSearchBuilder
    $vectorSearch = new VectorSearchBuilder($client);
    $results = $vectorSearch
        ->index($indexName)
        ->vectorQuery('embedding', $queryEmbedding)
        ->term('category', 'technology')
        ->size(5)
        ->execute();

    printResults($results);

    // 8. Clean up
    echo "\nOpruimen...\n";
    $client->deleteIndex($indexName);
    echo "✅ Index '{$indexName}' verwijderd\n";

    echo "\n✅ Voorbeeld voltooid\n";
} catch (\Exception $e) {
    echo "❌ Fout: " . $e->getMessage() . "\n";

    // Probeer de test index op te ruimen
    try {
        if ($client->indexExists($indexName)) {
            $client->deleteIndex($indexName);
            echo "✅ Test index opgeruimd\n";
        }
    } catch (\Exception $e) {
        echo "❌ Kon de test index niet opruimen: " . $e->getMessage() . "\n";
    }
}

/**
 * Helper functie om zoekresultaten te printen
 */
function printResults($results)
{
    $total = $results['hits']['total']['value'];
    echo "Gevonden: {$total} resultaten\n";

    foreach ($results['hits']['hits'] as $hit) {
        $score = number_format($hit['_score'], 4);
        $source = $hit['_source'];
        echo "- [{$score}] {$source['title']}: {$source['content']}\n";
    }
}
