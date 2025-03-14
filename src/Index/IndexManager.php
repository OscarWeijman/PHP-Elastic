<?php

namespace OscarWeijman\PhpElastic\Index;

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

class IndexManager
{
    private ElasticClient $client;

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Check if an index exists
     *
     * @param string $indexName
     * @return bool
     */
    public function exists(string $indexName): bool
    {
        try {
            $response = $this->client->getClient()->indices()->exists([
                'index' => $indexName
            ]);
            return $response->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new index
     *
     * @param string $indexName
     * @param array $settings
     * @param array $mappings
     * @return bool
     * @throws ElasticClientException
     */
    public function create(string $indexName, array $settings = [], array $mappings = []): bool
    {
        try {
            $params = ['index' => $indexName];

            if (!empty($settings) || !empty($mappings)) {
                $params['body'] = [];
                
                if (!empty($settings)) {
                    $params['body']['settings'] = $settings;
                }
                
                if (!empty($mappings)) {
                    $params['body']['mappings'] = $mappings;
                }
            }

            $response = $this->client->getClient()->indices()->create($params);
            return $response->asBool();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to create index '{$indexName}': {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete an index
     *
     * @param string $indexName
     * @return bool
     * @throws ElasticClientException
     */
    public function delete(string $indexName): bool
    {
        try {
            $response = $this->client->getClient()->indices()->delete([
                'index' => $indexName,
                'ignore_unavailable' => true
            ]);
            return $response->asBool();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to delete index '{$indexName}': {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get index settings
     *
     * @param string $indexName
     * @return array
     * @throws ElasticClientException
     */
    public function getSettings(string $indexName): array
    {
        try {
            $response = $this->client->getClient()->indices()->getSettings([
                'index' => $indexName
            ]);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to get settings for index '{$indexName}': {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get index mappings
     *
     * @param string $indexName
     * @return array
     * @throws ElasticClientException
     */
    public function getMappings(string $indexName): array
    {
        try {
            $response = $this->client->getClient()->indices()->getMapping([
                'index' => $indexName
            ]);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to get mappings for index '{$indexName}': {$e->getMessage()}", 0, $e);
        }
    }
}