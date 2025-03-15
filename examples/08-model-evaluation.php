<?php
/**
 * Embedding Model Evaluatie Script
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use OscarWeijman\PhpElastic\Embedding\ModelEvaluator;

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
        ],
        'paraphrase-multilingual' => [
            'name' => 'paraphrase-multilingual:latest',
            'dims' => 768,
            'type' => 'ollama'
        ],
        'mxbai-embed-large' => [
            'name' => 'mxbai-embed-large:latest',
            'dims' => 1024,
            'type' => 'ollama'
        ]
        // Je kunt andere modellen toevoegen als je die hebt
        // 'all-minilm' => [
        //     'name' => 'all-minilm',
        //     'dims' => 384,
        //     'type' => 'ollama'
        // ],
        // 'e5-small' => [
        //     'name' => 'e5-small-v2',
        //     'dims' => 384,
        //     'type' => 'ollama'
        // ]
    ]
];

// Testdata
$testData = [
    'documents' => [
        'doc1' => [
            'content' => 'Amsterdam is de hoofdstad van Nederland en bekend om zijn grachten.',
            'category' => 'geography',
            'relevance' => 3
        ],
        'doc2' => [
            'content' => 'Rotterdam heeft de grootste haven van Europa.',
            'category' => 'geography',
            'relevance' => 2
        ],
        'doc3' => [
            'content' => 'Machine learning is een vorm van kunstmatige intelligentie.',
            'category' => 'technology',
            'relevance' => 3
        ],
        'doc4' => [
            'content' => 'Python is een populaire programmeertaal voor data science.',
            'category' => 'technology',
            'relevance' => 2
        ],
        'doc5' => [
            'content' => 'Elasticsearch is een zoekmachine gebaseerd op Lucene.',
            'category' => 'technology',
            'relevance' => 3
        ],
        'doc6' => [
            'content' => 'Vector search maakt semantisch zoeken mogelijk in Elasticsearch.',
            'category' => 'technology',
            'relevance' => 3
        ],
        'doc7' => [
            'content' => 'De Eiffeltoren staat in Parijs, Frankrijk.',
            'category' => 'geography',
            'relevance' => 1
        ],
        'doc8' => [
            'content' => 'PHP is een programmeertaal voor webontwikkeling.',
            'category' => 'technology',
            'relevance' => 2
        ],
        'doc9' => [
            'content' => 'Amsterdam heeft veel musea, waaronder het Rijksmuseum.',
            'category' => 'geography',
            'relevance' => 2
        ],
        'doc10' => [
            'content' => 'Deep learning is een subset van machine learning met neurale netwerken.',
            'category' => 'technology',
            'relevance' => 3
        ],
    ],
    'queries' => [
        'q1' => [
            'text' => 'Wat is de hoofdstad van Nederland?',
            'relevant_docs' => ['doc1', 'doc9']
        ],
        'q2' => [
            'text' => 'Vertel me over machine learning en AI',
            'relevant_docs' => ['doc3', 'doc10']
        ],
        'q3' => [
            'text' => 'Hoe werkt vector search in Elasticsearch?',
            'relevant_docs' => ['doc5', 'doc6']
        ],
        'q4' => [
            'text' => 'Programmeertalen voor data analyse',
            'relevant_docs' => ['doc4', 'doc8']
        ],
        'q5' => [
            'text' => 'Grote havens in Nederland',
            'relevant_docs' => ['doc2']
        ]
    ]
];

try {
    // Maak client instances
    $client = new ElasticClient($config['elastic']);
    $embeddingService = new EmbeddingService(
        $config['embedding_api']['url'],
        $config['embedding_api']['key'],
        $config['models']
    );
    
    // Controleer of Elasticsearch beschikbaar is
    if (!$client->ping()) {
        throw new \RuntimeException("Elasticsearch is niet beschikbaar. Controleer of de server draait.");
    }
    
    // Maak evaluator
    $evaluator = new ModelEvaluator(
        $client, 
        $embeddingService, 
        $testData, 
        array_keys($config['models'])
    );
    
    // Setup en evaluatie
    echo "=== Start Embedding Model Evaluatie ===\n\n";
    
    echo "1. Setup test indices...\n";
    $evaluator->setupTestIndices();
    
    echo "\n2. Indexeren van testdata...\n";
    $evaluator->indexTestData();
    
    echo "\n3. Evalueren van modellen...\n";
    $results = $evaluator->evaluateModels();
    
    echo "\n4. Resultaten:\n";
    $evaluator->printResults();
    
    // Optioneel: clean up
    echo "\n5. Clean up...\n";
    $evaluator->cleanup();
    
    echo "\n=== Evaluatie voltooid ===\n";
    
} catch (\Exception $e) {
    echo "Fout: " . $e->getMessage() . "\n";
    exit(1);
}
