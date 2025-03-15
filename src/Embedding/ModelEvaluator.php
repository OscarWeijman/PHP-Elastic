<?php

namespace OscarWeijman\PhpElastic\Embedding;

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use OscarWeijman\PhpElastic\Embedding\EmbeddingManager;
use OscarWeijman\PhpElastic\Search\VectorSearchBuilder;
use InvalidArgumentException;
use RuntimeException;

/**
 * Klasse voor het evalueren van verschillende embedding modellen
 */
class ModelEvaluator
{
    private ElasticClient $client;
    private EmbeddingService $embeddingService;
    private array $testData;
    private array $models;
    private array $results = [];
    private string $indexPrefix;

    /**
     * @param ElasticClient $client Elasticsearch client
     * @param EmbeddingService $embeddingService Service voor het genereren van embeddings
     * @param array $testData Test data met documenten en queries
     * @param array $models Array van model IDs om te testen
     * @param string $indexPrefix Prefix voor test indices
     */
    public function __construct(
        ElasticClient $client, 
        EmbeddingService $embeddingService,
        array $testData,
        array $models,
        string $indexPrefix = 'test_embeddings_'
    ) {
        $this->client = $client;
        $this->embeddingService = $embeddingService;
        $this->testData = $testData;
        $this->models = $models;
        $this->indexPrefix = $indexPrefix;
        
        // Valideer test data
        $this->validateTestData();
    }

    /**
     * Valideer de test data structuur
     */
    private function validateTestData(): void
    {
        if (!isset($this->testData['documents']) || !is_array($this->testData['documents'])) {
            throw new InvalidArgumentException("Test data moet een 'documents' array bevatten");
        }
        
        if (!isset($this->testData['queries']) || !is_array($this->testData['queries'])) {
            throw new InvalidArgumentException("Test data moet een 'queries' array bevatten");
        }
        
        foreach ($this->testData['queries'] as $queryId => $query) {
            if (!isset($query['text'])) {
                throw new InvalidArgumentException("Query '{$queryId}' mist het vereiste 'text' veld");
            }
            
            if (!isset($query['relevant_docs']) || !is_array($query['relevant_docs'])) {
                throw new InvalidArgumentException("Query '{$queryId}' mist het vereiste 'relevant_docs' array");
            }
        }
    }

    /**
     * Maak test indices voor elk model
     */
    public function setupTestIndices(): void
    {
        foreach ($this->models as $modelId) {
            $indexName = $this->indexPrefix . $modelId;
            
            // Controleer of de index al bestaat
            if ($this->client->indexExists($indexName)) {
                $this->client->deleteIndex($indexName);
            }
            
            // Haal dimensies op van het model
            $dimensions = $this->embeddingService->getModelDimensions($modelId);
            
            // Maak de index met de juiste mapping voor vector search
            $this->client->createIndex($indexName, [
                'number_of_shards' => 1,
                'number_of_replicas' => 0
            ], [
                'properties' => [
                    'content' => ['type' => 'text'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => $dimensions,
                        'index' => true,
                        'similarity' => 'cosine'
                    ],
                    'category' => ['type' => 'keyword'],
                    'relevance' => ['type' => 'integer']
                ]
            ]);
            
            echo "Index '{$indexName}' aangemaakt voor model '{$modelId}'\n";
        }
    }

    /**
     * Indexeer de testdata voor elk model
     */
    public function indexTestData(): void
    {
        foreach ($this->models as $modelId) {
            $indexName = $this->indexPrefix . $modelId;
            $modelName = $this->embeddingService->getModels()[$modelId]['name'];
            
            echo "Indexeren van testdata voor model '{$modelId}'...\n";
            
            // Verzamel alle documenten voor batch indexering
            $documents = [];
            foreach ($this->testData['documents'] as $docId => $document) {
                $documents[] = [
                    'id' => $docId,
                    'content' => $document['content'],
                    'category' => $document['category'] ?? null,
                    'relevance' => $document['relevance'] ?? null
                ];
            }
            
            // Maak een embedding manager
            $embeddingManager = new EmbeddingManager($this->client, $this->embeddingService);
            
            try {
                // Bulk indexeer alle documenten
                $embeddingManager->bulkIndexWithEmbeddings($indexName, $modelId, $documents);
                echo "✓ {$indexName}: " . count($documents) . " documenten geïndexeerd\n";
            } catch (\Exception $e) {
                echo "✗ Fout bij indexeren voor model {$modelId}: {$e->getMessage()}\n";
                
                // Probeer documenten één voor één te indexeren als bulk mislukt
                echo "  Probeer documenten één voor één te indexeren...\n";
                foreach ($documents as $document) {
                    try {
                        $docId = $document['id'];
                        unset($document['id']);
                        
                        $embeddingManager->indexWithEmbedding(
                            $indexName,
                            $modelId,
                            $document['content'],
                            [
                                'category' => $document['category'],
                                'relevance' => $document['relevance']
                            ],
                            $docId
                        );
                        echo ".";
                    } catch (\Exception $e) {
                        echo "✗";
                    }
                }
                echo "\n";
            }
            
            // Refresh de index
            $this->client->getClient()->indices()->refresh(['index' => $indexName]);
            
            // Wacht even om zeker te zijn dat de documenten geïndexeerd zijn
            sleep(1);
        }
    }

    /**
     * Evalueer alle modellen met de testqueries
     */
    public function evaluateModels(): array
    {
        foreach ($this->models as $modelId) {
            $indexName = $this->indexPrefix . $modelId;
            
            echo "Evalueren van model '{$modelId}'...\n";
            
            $modelResults = [
                'precision' => [],
                'recall' => [],
                'ndcg' => [],
                'queries' => []
            ];
            
            // Evalueer elke query
            foreach ($this->testData['queries'] as $queryId => $query) {
                try {
                    // Zoek met vector similarity
                    $embeddingManager = new EmbeddingManager($this->client, $this->embeddingService);
                    $searchResults = $embeddingManager->searchWithEmbedding(
                        $indexName,
                        $modelId,
                        $query['text'],
                        10
                    );
                    
                    // Bereken metrics voor deze query
                    $relevantDocs = $query['relevant_docs'];
                    $retrievedDocs = array_map(function($hit) {
                        return $hit['_id'];
                    }, $searchResults['hits']['hits']);
                    
                    // Bereken precision@k en recall@k
                    $precision = $this->calculatePrecision($retrievedDocs, $relevantDocs);
                    $recall = $this->calculateRecall($retrievedDocs, $relevantDocs);
                    $ndcg = $this->calculateNDCG($searchResults['hits']['hits'], $query['relevant_docs']);
                    
                    $modelResults['precision'][$queryId] = $precision;
                    $modelResults['recall'][$queryId] = $recall;
                    $modelResults['ndcg'][$queryId] = $ndcg;
                    $modelResults['queries'][$queryId] = [
                        'query' => $query['text'],
                        'retrieved' => $retrievedDocs,
                        'relevant' => $relevantDocs,
                        'precision' => $precision,
                        'recall' => $recall,
                        'ndcg' => $ndcg
                    ];
                    
                    echo ".";
                } catch (\Exception $e) {
                    echo "\nFout bij evalueren van query {$queryId}: {$e->getMessage()}\n";
                }
            }
            
            // Bereken gemiddelde metrics
            $modelResults['avg_precision'] = array_sum($modelResults['precision']) / count($modelResults['precision']);
            $modelResults['avg_recall'] = array_sum($modelResults['recall']) / count($modelResults['recall']);
            $modelResults['avg_ndcg'] = array_sum($modelResults['ndcg']) / count($modelResults['ndcg']);
            
            $this->results[$modelId] = $modelResults;
            
            echo "\nModel '{$modelId}' geëvalueerd\n";
        }
        
        return $this->results;
    }

    /**
     * Bereken precision@k
     */
    private function calculatePrecision(array $retrieved, array $relevant, int $k = 10): float
    {
        $retrieved = array_slice($retrieved, 0, $k);
        $relevantRetrieved = array_intersect($retrieved, $relevant);
        
        return count($retrieved) > 0 ? count($relevantRetrieved) / count($retrieved) : 0;
    }

    /**
     * Bereken recall@k
     */
    private function calculateRecall(array $retrieved, array $relevant, int $k = 10): float
    {
        $retrieved = array_slice($retrieved, 0, $k);
        $relevantRetrieved = array_intersect($retrieved, $relevant);
        
        return count($relevant) > 0 ? count($relevantRetrieved) / count($relevant) : 0;
    }

    /**
     * Bereken NDCG (Normalized Discounted Cumulative Gain)
     */
    private function calculateNDCG(array $hits, array $relevantDocs, int $k = 10): float
    {
        $hits = array_slice($hits, 0, $k);
        
        // Bereken DCG
        $dcg = 0;
        foreach ($hits as $i => $hit) {
            $docId = $hit['_id'];
            $position = $i + 1;
            $relevance = in_array($docId, $relevantDocs) ? 1 : 0;
            
            // DCG formule: rel_i / log2(i+1)
            $dcg += $relevance / log($position + 1, 2);
        }
        
        // Bereken IDCG (Ideal DCG)
        $relevanceScores = array_fill(0, count($relevantDocs), 1);
        $relevanceScores = array_pad($relevanceScores, $k, 0);
        $relevanceScores = array_slice($relevanceScores, 0, $k);
        
        $idcg = 0;
        foreach ($relevanceScores as $i => $relevance) {
            $position = $i + 1;
            $idcg += $relevance / log($position + 1, 2);
        }
        
        // NDCG = DCG / IDCG
        return $idcg > 0 ? $dcg / $idcg : 0;
    }

    /**
     * Print de evaluatieresultaten
     */
    public function printResults(): void
    {
        echo "\n=== Evaluatieresultaten ===\n\n";
        
        foreach ($this->results as $modelId => $modelResults) {
            echo "Model: {$modelId}\n";
            echo "  Gemiddelde Precision@10: " . number_format($modelResults['avg_precision'], 4) . "\n";
            echo "  Gemiddelde Recall@10: " . number_format($modelResults['avg_recall'], 4) . "\n";
            echo "  Gemiddelde NDCG@10: " . number_format($modelResults['avg_ndcg'], 4) . "\n\n";
        }
        
        // Bepaal het beste model
        $bestModel = null;
        $bestScore = -1;
        
        foreach ($this->results as $modelId => $modelResults) {
            $score = ($modelResults['avg_precision'] + $modelResults['avg_recall'] + $modelResults['avg_ndcg']) / 3;
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModel = $modelId;
            }
        }
        
        echo "Beste model: {$bestModel} met gemiddelde score: " . number_format($bestScore, 4) . "\n";
    }

    /**
     * Krijg de evaluatieresultaten
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Clean up de test indices
     */
    public function cleanup(): void
    {
        foreach ($this->models as $modelId) {
            $indexName = $this->indexPrefix . $modelId;
            
            if ($this->client->indexExists($indexName)) {
                $this->client->deleteIndex($indexName);
                echo "Index '{$indexName}' verwijderd\n";
            }
        }
    }
}
