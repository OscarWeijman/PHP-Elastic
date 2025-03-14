<?php
/**
 * Geavanceerde zoek voorbeelden
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
$indexName = 'test-articles-' . time();

try {
    // 1. Setup: Maak een test index met Nederlandse analyzer
    setupTestData($indexName, $indexManager, $documentManager, $client);
    echo "✅ Test data toegevoegd\n\n";
    
    // 2. Zoeken met highlighting
    echo "🔍 Zoeken met highlighting op 'ontwikkeling':\n";
    
    $results = $searchBuilder
        ->indices($indexName)
        ->match('content', 'ontwikkeling')
        ->highlight([
            'fields' => [
                'content' => [
                    'pre_tags' => ['<strong>'],
                    'post_tags' => ['</strong>'],
                    'fragment_size' => 150,
                    'number_of_fragments' => 1
                ]
            ]
        ])
        ->execute();
    
    printHighlightedResults($results);
    
    // 3. Boolean query met should clausules
    echo "\n🔍 Boolean query met should clausules (php OF javascript):\n";
    
    // We maken een custom query met should clausules
    $customQuery = [
        'bool' => [
            'should' => [
                ['match' => ['content' => 'php']],
                ['match' => ['content' => 'javascript']]
            ],
            'minimum_should_match' => 1
        ]
    ];
    
    // We gebruiken de raw Elasticsearch client voor deze complexe query
    $params = [
        'index' => $indexName,
        'body' => [
            'query' => $customQuery
        ]
    ];
    
    $response = $client->getClient()->search($params);
    $results = $response->asArray();
    
    printResults($results);
    
    // 4. Fuzzy search voor typfouten
    echo "\n🔍 Fuzzy search voor 'programeren' (met typfout):\n";
    
    $fuzzyParams = [
        'index' => $indexName,
        'body' => [
            'query' => [
                'match' => [
                    'content' => [
                        'query' => 'programeren',
                        'fuzziness' => 'AUTO'
                    ]
                ]
            ]
        ]
    ];
    
    $response = $client->getClient()->search($fuzzyParams);
    $results = $response->asArray();
    
    printResults($results);
    
    // 5. Phrase search
    echo "\n🔍 Phrase search voor 'moderne ontwikkeling':\n";
    
    $phraseParams = [
        'index' => $indexName,
        'body' => [
            'query' => [
                'match_phrase' => [
                    'content' => 'moderne ontwikkeling'
                ]
            ]
        ]
    ];
    
    $response = $client->getClient()->search($phraseParams);
    $results = $response->asArray();
    
    printResults($results);
    
    // 6. Clean up
    $indexManager->delete($indexName);
    echo "\n✅ Test index verwijderd\n";
    
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
    // Maak index met Nederlandse analyzer - gebruik 0 replicas voor een green status in single-node cluster
    $indexManager->create($indexName, [
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
        'properties' => [
            'title' => [
                'type' => 'text',
                'analyzer' => 'dutch_analyzer'
            ],
            'content' => [
                'type' => 'text',
                'analyzer' => 'dutch_analyzer'
            ],
            'author' => [
                'type' => 'keyword'
            ],
            'tags' => [
                'type' => 'keyword'
            ],
            'published_date' => [
                'type' => 'date'
            ]
        ]
    ]);
    
    // Voeg test artikelen toe
    $articles = [
        [
            'title' => 'Moderne PHP ontwikkeling',
            'content' => 'PHP is een veelzijdige programmeertaal voor webontwikkeling. Moderne PHP frameworks zoals Laravel en Symfony maken het ontwikkelen van applicaties eenvoudiger en sneller. PHP 8 heeft veel nieuwe features die de taal krachtiger maken.',
            'author' => 'Jan Jansen',
            'tags' => ['php', 'webdevelopment', 'programming'],
            'published_date' => '2025-01-15T12:00:00Z'
        ],
        [
            'title' => 'JavaScript frameworks vergelijking',
            'content' => 'In dit artikel vergelijken we verschillende JavaScript frameworks zoals React, Vue en Angular. Elk framework heeft zijn eigen sterke punten voor moderne webontwikkeling. De keuze hangt af van je specifieke project vereisten.',
            'author' => 'Piet Pietersen',
            'tags' => ['javascript', 'frameworks', 'frontend'],
            'published_date' => '2025-02-10T14:30:00Z'
        ],
        [
            'title' => 'Elasticsearch voor beginners',
            'content' => 'Elasticsearch is een krachtige zoekmachine gebaseerd op Lucene. Het wordt veel gebruikt voor het indexeren en doorzoeken van grote hoeveelheden data. In combinatie met PHP kun je eenvoudig zoekfunctionaliteit toevoegen aan je applicaties.',
            'author' => 'Anna de Vries',
            'tags' => ['elasticsearch', 'search', 'backend'],
            'published_date' => '2025-03-05T09:15:00Z'
        ],
        [
            'title' => 'Webapplicatie performance optimalisatie',
            'content' => 'Performance is cruciaal voor een goede gebruikerservaring. In dit artikel bespreken we technieken om je webapplicatie te optimaliseren, waaronder caching, code optimalisatie en database queries verbeteren.',
            'author' => 'Klaas Klaassen',
            'tags' => ['performance', 'optimization', 'web'],
            'published_date' => '2025-02-25T16:45:00Z'
        ],
        [
            'title' => 'API ontwikkeling met PHP',
            'content' => 'Het bouwen van RESTful APIs met PHP is eenvoudig met moderne frameworks. We bespreken best practices voor API ontwikkeling, authenticatie en documentatie. Een goed ontworpen API is essentieel voor moderne applicaties.',
            'author' => 'Sophie Jansen',
            'tags' => ['api', 'php', 'rest'],
            'published_date' => '2025-03-12T11:30:00Z'
        ]
    ];
    
    // Bulk index de artikelen
    $operations = [];
    foreach ($articles as $i => $article) {
        $operations[] = ['index' => ['_index' => $indexName, '_id' => 'article-' . ($i + 1)]];
        $operations[] = $article;
    }
    
    $documentManager->bulk($operations);
    
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
        echo "- {$source['title']} (door {$source['author']})\n";
        echo "  " . substr($source['content'], 0, 100) . "...\n";
    }
}

/**
 * Helper functie om zoekresultaten met highlighting te printen
 */
function printHighlightedResults($results) {
    $total = $results['hits']['total']['value'];
    echo "Gevonden: {$total} resultaten\n";
    
    foreach ($results['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        echo "- {$source['title']} (door {$source['author']})\n";
        
        if (isset($hit['highlight']['content'])) {
            echo "  Highlight: " . $hit['highlight']['content'][0] . "\n";
        } else {
            echo "  " . substr($source['content'], 0, 100) . "...\n";
        }
    }
}