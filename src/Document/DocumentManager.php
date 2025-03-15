<?php

namespace OscarWeijman\PhpElastic\Document;

use OscarWeijman\PhpElastic\ElasticClient;

class DocumentManager
{
    private ElasticClient $client;

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Index a document
     */
    public function index(string $index, array $document, ?string $id = null): array
    {
        return $this->client->index($index, $document, $id);
    }

    /**
     * Get a document by ID
     */
    public function get(string $index, string $id): array
    {
        return $this->client->get($index, $id);
    }

    /**
     * Update a document
     */
    public function update(string $index, string $id, array $document): array
    {
        return $this->client->update($index, $id, $document);
    }

    /**
     * Delete a document
     */
    public function delete(string $index, string $id): array
    {
        return $this->client->delete($index, $id);
    }

    /**
     * Perform bulk operations
     */
    public function bulk(array $operations): array
    {
        return $this->client->bulk($operations);
    }
}