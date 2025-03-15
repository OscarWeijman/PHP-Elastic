<?php

namespace OscarWeijman\PhpElastic;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;

class ElasticClient
{
    private Client $client;

    public function __construct(array $config)
    {
        $builder = ClientBuilder::create();
        
        if (isset($config['hosts'])) {
            $builder->setHosts($config['hosts']);
        }
        
        if (isset($config['basicAuth'])) {
            [$username, $password] = $config['basicAuth'];
            $builder->setBasicAuthentication($username, $password);
        }
        
        if (isset($config['apiKey'])) {
            $builder->setApiKey($config['apiKey']);
        }
        
        if (isset($config['sslVerification']) && $config['sslVerification'] === false) {
            $builder->setSSLVerification(false);
        }
        
        if (isset($config['retries'])) {
            $builder->setRetries((int) $config['retries']);
        }
        
        $this->client = $builder->build();
    }
    
    /**
     * Get the underlying Elasticsearch client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
    
    /**
     * Get Elasticsearch cluster information
     */
    public function info(): array
    {
        try {
            $response = $this->client->info();
            return $response->asArray();
        } catch (ClientResponseException | ServerResponseException | Exception $e) {
            throw new \RuntimeException('Failed to get Elasticsearch info: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Check if Elasticsearch is available
     */
    public function ping(): bool
    {
        try {
            $this->client->info();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if an index exists
     */
    public function indexExists(string $index): bool
    {
        try {
            $response = $this->client->indices()->exists([
                'index' => $index
            ]);
            return $response->asBool();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to check if index '{$index}' exists: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Create an index
     */
    public function createIndex(string $index, array $settings = [], array $mappings = []): array
    {
        $params = ['index' => $index];
        
        if (!empty($settings)) {
            $params['body']['settings'] = $settings;
        }
        
        if (!empty($mappings)) {
            $params['body']['mappings'] = $mappings;
        }
        
        try {
            $response = $this->client->indices()->create($params);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to create index '{$index}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Delete an index
     */
    public function deleteIndex(string $index): array
    {
        try {
            $response = $this->client->indices()->delete([
                'index' => $index
            ]);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to delete index '{$index}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Index a document
     */
    public function index(string $index, array $document, ?string $id = null): array
    {
        $params = [
            'index' => $index,
            'body' => $document
        ];
        
        if ($id !== null) {
            $params['id'] = $id;
        }
        
        try {
            $response = $this->client->index($params);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to index document: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get a document by ID
     */
    public function get(string $index, string $id): array
    {
        try {
            $response = $this->client->get([
                'index' => $index,
                'id' => $id
            ]);
            return $response->asArray();
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                throw new \RuntimeException("Document not found in index '{$index}' with ID '{$id}'", 404, $e);
            }
            throw new \RuntimeException("Failed to get document: " . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to get document: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Update a document
     */
    public function update(string $index, string $id, array $document): array
    {
        try {
            $response = $this->client->update([
                'index' => $index,
                'id' => $id,
                'body' => [
                    'doc' => $document
                ]
            ]);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to update document: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Delete a document
     */
    public function delete(string $index, string $id): array
    {
        try {
            $response = $this->client->delete([
                'index' => $index,
                'id' => $id
            ]);
            return $response->asArray();
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                throw new \RuntimeException("Document not found in index '{$index}' with ID '{$id}'", 404, $e);
            }
            throw new \RuntimeException("Failed to delete document: " . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to delete document: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Search documents
     */
    public function search(array $params): array
    {
        try {
            $response = $this->client->search($params);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Search failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Perform bulk operations
     */
    public function bulk(array $operations): array
    {
        try {
            $response = $this->client->bulk([
                'body' => $operations
            ]);
            return $response->asArray();
        } catch (Exception $e) {
            throw new \RuntimeException("Bulk operation failed: " . $e->getMessage(), 0, $e);
        }
    }
}