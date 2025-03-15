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
    public function create(string $index, array $settings = [], array $mappings = []): array
    {
        return $this->client->createIndex($index, $settings, $mappings);
    }

    /**
     * Delete an index
     */
    public function delete(string $index): array
    {
        return $this->client->deleteIndex($index);
    }
}