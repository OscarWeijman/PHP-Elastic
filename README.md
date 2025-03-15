# PHP Elastic

Een moderne PHP library voor het werken met Elasticsearch, met focus op eenvoud en type safety.

## Installatie

```bash
composer require oscarweijman/php-elastic
```

## Basis gebruik

```php
use OscarWeijman\PhpElastic\ElasticClient;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
]);

// Controleer of Elasticsearch beschikbaar is
if ($client->ping()) {
    echo "Verbonden met Elasticsearch!";
}

// Maak een index
$client->createIndex('my-index', [
    'number_of_shards' => 1,
    'number_of_replicas' => 1
], [
    'properties' => [
        'title' => ['type' => 'text'],
        'content' => ['type' => 'text'],
        'tags' => ['type' => 'keyword'],
        'published_at' => ['type' => 'date']
    ]
]);

// Voeg een document toe
$client->index('my-index', [
    'title' => 'Mijn eerste document',
    'content' => 'Dit is de inhoud van mijn document',
    'tags' => ['elasticsearch', 'php'],
    'published_at' => '2023-01-01T12:00:00Z'
], 'doc-1');

// Zoek documenten
$results = $client->search([
    'index' => 'my-index',
    'body' => [
        'query' => [
            'match' => [
                'content' => 'document'
            ]
        ]
    ]
]);

// Verwijder een document
$client->delete('my-index', 'doc-1');

// Verwijder een index
$client->deleteIndex('my-index');
```

## Vector Search en Embeddings

PHP Elastic ondersteunt ook vector search en embeddings voor semantisch zoeken:

```php
use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use OscarWeijman\PhpElastic\Embedding\EmbeddingManager;

// Maak client instances
$client = new ElasticClient(['hosts' => ['localhost:9200']]);
$embeddingService = new EmbeddingService(
    'http://localhost:11434',  // Ollama API URL
    '',  // API key (niet nodig voor Ollama)
    [
        'nomic-embed' => [
            'name' => 'nomic-embed-text',
            'dims' => 768,
            'type' => 'ollama'
        ]
    ]
);
$embeddingManager = new EmbeddingManager($client, $embeddingService);

// Maak een index met vector mapping
$embeddingManager->createVectorIndex('articles', 'nomic-embed', [
    'title' => ['type' => 'text'],
    'category' => ['type' => 'keyword']
]);

// Voeg een document toe met embedding
$embeddingManager->indexWithEmbedding(
    'articles',
    'nomic-embed',
    'Dit is een artikel over machine learning en AI.',
    [
        'title' => 'Machine Learning Introductie',
        'category' => 'technology'
    ],
    'article-1'
);

// Zoek semantisch vergelijkbare documenten
$results = $embeddingManager->searchWithEmbedding(
    'articles',
    'nomic-embed',
    'Wat is kunstmatige intelligentie?',
    5
);

// Hybride zoeken (vector + keyword)
$results = $embeddingManager->searchHybrid(
    'articles',
    'nomic-embed',
    'machine learning technieken',
    5,
    [],
    'embedding',
    'content',
    0.7,  // vector boost
    0.3   // text boost
);
```

## Evaluatie van Embedding Modellen

Je kunt verschillende embedding modellen evalueren om te bepalen welke het beste presteert voor jouw use case:

```php
use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use OscarWeijman\PhpElastic\Embedding\ModelEvaluator;

// Configureer modellen
$embeddingService = new EmbeddingService('http://localhost:11434');
$embeddingService->addModel('nomic-embed', 'nomic-embed-text', 768, 'ollama');
$embeddingService->addModel('all-minilm', 'all-minilm', 384, 'ollama');
$embeddingService->addModel('e5-small', 'e5-small-v2', 384, 'ollama');

// Maak evaluator met test data
$evaluator = new ModelEvaluator(
    $client, 
    $embeddingService, 
    $testData,  // Array met documenten en queries
    ['nomic-embed', 'all-minilm', 'e5-small']
);

// Voer evaluatie uit
$evaluator->setupTestIndices();
$evaluator->indexTestData();
$results = $evaluator->evaluateModels();
$evaluator->printResults();
```

## Geavanceerd gebruik

Bekijk de voorbeelden in de `examples` directory voor meer geavanceerde gebruiksscenario's:

- `01-basic-usage.php`: Basis gebruik van de client
- `02-index-management.php`: Index beheer
- `03-document-management.php`: Document beheer
- `04-search-examples.php`: Zoekvoorbeelden
- `05-advanced-search.php`: Geavanceerde zoekopdrachten
- `06-single-node-cluster.php`: Werken met een single-node cluster
- `07-vector-search.php`: Vector search en embeddings
- `08-model-evaluation.php`: Evaluatie van embedding modellen

## Documentatie

Volledige documentatie is beschikbaar in de [wiki](https://github.com/oscarweijman/php-elastic/wiki).

## Licentie

MIT
