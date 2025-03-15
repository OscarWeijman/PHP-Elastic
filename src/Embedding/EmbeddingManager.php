<?php

namespace OscarWeijman\PhpElastic\Embedding;

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Embedding\EmbeddingService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manager voor het werken met embeddings in Elasticsearch
 */
class EmbeddingManager
{
    private ElasticClient $client;
    private EmbeddingService $embeddingService;
    private array $defaultSettings;

    /**
     * @param ElasticClient $client Elasticsearch client
     * @param EmbeddingService $embeddingService Service voor het genereren van embeddings
     * @param array $defaultSettings Standaard instellingen voor indices
     */
    public function __construct(
        ElasticClient $client, 
        EmbeddingService $embeddingService,
        array $defaultSettings = []
    ) {
        $this->client = $client;
        $this->embeddingService = $embeddingService;
        $this->defaultSettings = array_merge([
            'number_of_shards' => 1,
            'number_of_replicas' => 1
        ], $defaultSettings);
    }

    /**
     * Maak een index met vector mapping
     * 
     * @param string $indexName Naam van de index
     * @param string $modelId ID van het model in de EmbeddingService
     * @param array $additionalFields Extra velden voor de mapping
     * @param array $settings Index instellingen
     * @param string $vectorField Naam van het vector veld
     * @return array Response van Elasticsearch
     */
    public function createVectorIndex(
        string $indexName, 
        string $modelId, 
        array $additionalFields = [], 
        array $settings = [],
        string $vectorField = 'embedding'
    ): array {
        // Haal dimensies op van het model
        $dimensions = $this->embeddingService->getModelDimensions($modelId);
        
        // Maak de mapping met het vector veld
        $mapping = [
            'properties' => array_merge([
                $vectorField => [
                    'type' => 'dense_vector',
                    'dims' => $dimensions,
                    'index' => true,
                    'similarity' => 'cosine'
                ],
                'content' => [
                    'type' => 'text'
                ]
            ], $additionalFields)
        ];
        
        // Combineer met standaard instellingen
        $finalSettings = array_merge($this->defaultSettings, $settings);
        
        // Maak de index
        return $this->client->createIndex($indexName, $finalSettings, $mapping);
    }

    /**
     * Indexeer een document met embedding
     * 
     * @param string $indexName Naam van de index
     * @param string $modelId ID van het model in de EmbeddingService
     * @param string $content Tekst om te embedden
     * @param array $additionalFields Extra velden voor het document
     * @param string|null $documentId Document ID (optioneel)
     * @param string $vectorField Naam van het vector veld
     * @param string $contentField Naam van het content veld
     * @return array Response van Elasticsearch
     */
    public function indexWithEmbedding(
        string $indexName, 
        string $modelId, 
        string $content,
        array $additionalFields = [],
        ?string $documentId = null,
        string $vectorField = 'embedding',
        string $contentField = 'content'
    ): array {
        // Genereer embedding
        $embedding = $this->embeddingService->generateEmbedding($content, $modelId);
        
        // Maak document
        $document = array_merge([
            $contentField => $content,
            $vectorField => $embedding
        ], $additionalFields);
        
        // Indexeer document
        return $this->client->index($indexName, $document, $documentId);
    }

    /**
     * Indexeer meerdere documenten met embeddings in bulk
     * 
     * @param string $indexName Naam van de index
     * @param string $modelId ID van het model in de EmbeddingService
     * @param array $documents Array van documenten met 'content' en optioneel 'id' en andere velden
     * @param string $vectorField Naam van het vector veld
     * @param string $contentField Naam van het content veld
     * @return array Response van Elasticsearch
     */
    public function bulkIndexWithEmbeddings(
        string $indexName, 
        string $modelId, 
        array $documents,
        string $vectorField = 'embedding',
        string $contentField = 'content'
    ): array {
        // Controleer of er documenten zijn
        if (empty($documents)) {
            throw new InvalidArgumentException('Geen documenten opgegeven voor bulk indexering');
        }
        
        // Verzamel alle teksten voor batch embedding
        $texts = [];
        foreach ($documents as $document) {
            if (!isset($document[$contentField])) {
                throw new InvalidArgumentException("Document mist vereist veld '{$contentField}'");
            }
            $texts[] = $document[$contentField];
        }
        
        // Genereer embeddings in batch
        $embeddings = $this->embeddingService->generateBatchEmbeddings($texts, $modelId);
        
        // Maak bulk operaties
        $operations = [];
        foreach ($documents as $i => $document) {
            // Index operatie
            $indexOp = [
                'index' => [
                    '_index' => $indexName
                ]
            ];
            
            // Voeg document ID toe als die er is
            if (isset($document['id'])) {
                $indexOp['index']['_id'] = $document['id'];
                unset($document['id']);
            }
            
            // Voeg embedding toe aan document
            $document[$vectorField] = $embeddings[$i];
            
            // Voeg operaties toe aan bulk array
            $operations[] = $indexOp;
            $operations[] = $document;
        }
        
        // Voer bulk operatie uit
        return $this->client->bulk($operations);
    }

    /**
     * Zoek documenten met vector similarity
     * 
     * @param string $indexName Naam van de index
     * @param string $modelId ID van het model in de EmbeddingService
     * @param string $query Zoekopdracht
     * @param int $size Aantal resultaten
     * @param array $filters Extra filters
     * @param string $vectorField Naam van het vector veld
     * @return array Zoekresultaten
     */
    public function searchWithEmbedding(
        string $indexName, 
        string $modelId, 
        string $query,
        int $size = 10,
        array $filters = [],
        string $vectorField = 'embedding'
    ): array {
        // Genereer embedding voor de query
        $embedding = $this->embeddingService->generateEmbedding($query, $modelId);
        
        // Maak vector search builder
        $vectorSearch = new \OscarWeijman\PhpElastic\Search\VectorSearchBuilder($this->client);
        $vectorSearch->index($indexName)
            ->vectorQuery($vectorField, $embedding)
            ->size($size);
        
        // Voeg filters toe als die er zijn
        foreach ($filters as $filter) {
            $vectorSearch->filter($filter);
        }
        
        // Voer zoekopdracht uit
        return $vectorSearch->execute();
    }

    /**
     * Zoek documenten met hybride search (vector + keyword)
     * 
     * @param string $indexName Naam van de index
     * @param string $modelId ID van het model in de EmbeddingService
     * @param string $query Zoekopdracht
     * @param int $size Aantal resultaten
     * @param array $filters Extra filters
     * @param string $vectorField Naam van het vector veld
     * @param string $textField Naam van het tekst veld
     * @param float $vectorBoost Boost factor voor vector search
     * @param float $textBoost Boost factor voor text search
     * @return array Zoekresultaten
     */
    public function searchHybrid(
        string $indexName, 
        string $modelId, 
        string $query,
        int $size = 10,
        array $filters = [],
        string $vectorField = 'embedding',
        string $textField = 'content',
        float $vectorBoost = 1.0,
        float $textBoost = 1.0
    ): array {
        // Genereer embedding voor de query
        $embedding = $this->embeddingService->generateEmbedding($query, $modelId);
        
        // Maak vector search builder
        $vectorSearch = new \OscarWeijman\PhpElastic\Search\VectorSearchBuilder($this->client);
        $vectorSearch->index($indexName)
            ->vectorQuery($vectorField, $embedding, $vectorBoost)
            ->textQuery(['match' => [$textField => $query]], $textBoost)
            ->size($size);
        
        // Voeg filters toe als die er zijn
        foreach ($filters as $filter) {
            $vectorSearch->filter($filter);
        }
        
        // Voer zoekopdracht uit
        return $vectorSearch->execute();
    }

    /**
     * Krijg de EmbeddingService
     */
    public function getEmbeddingService(): EmbeddingService
    {
        return $this->embeddingService;
    }
}
