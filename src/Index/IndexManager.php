<?php

namespace OscarWeijman\PhpElastic\Index;

use OscarWeijman\PhpElastic\ElasticClient;

class IndexManager
{
    private ElasticClient $client;

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Check if an index exists
     */
    public function exists(string $index): bool
    {
        return $this->client->indexExists($index);
    }

    /**
     * Create an index
     */
    public function create(string $index, array $settings = [], array $mappings = []): bool
    {
        $result = $this->client->createIndex($index, $settings, $mappings);
        return isset($result['acknowledged']) && $result['acknowledged'] === true;
    }

    /**
     * Delete an index
     */
    public function delete(string $index): bool
    {
        $result = $this->client->deleteIndex($index);
        return isset($result['acknowledged']) && $result['acknowledged'] === true;
    }
    
    /**
     * Get index settings
     */
    public function getSettings(string $index): array
    {
        try {
            $response = $this->client->getClient()->indices()->getSettings([
                'index' => $index
            ]);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to get settings for index '{$index}': " . $e->getMessage(), 0, $e);
        }
    }
}