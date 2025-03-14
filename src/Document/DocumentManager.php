<?php

namespace OscarWeijman\PhpElastic\Document;

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

class DocumentManager
{
    private ElasticClient $client;

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Get the ElasticClient instance
     *
     * @return ElasticClient
     */
    public function getClient(): ElasticClient
    {
        return $this->client;
    }

    /**
     * Index a document
     *
     * @param string $index
     * @param array $document
     * @param string|null $id
     * @param array $options
     * @return array
     * @throws ElasticClientException
     */
    public function index(string $index, array $document, ?string $id = null, array $options = []): array
    {
        try {
            $params = [
                'index' => $index,
                'body' => $document,
            ];

            if ($id !== null) {
                $params['id'] = $id;
            }

            if (!empty($options)) {
                $params = array_merge($params, $options);
            }

            $response = $this->client->getClient()->index($params);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to index document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get a document by ID
     *
     * @param string $index
     * @param string $id
     * @param array $options
     * @return array|null
     * @throws ElasticClientException
     */
    public function get(string $index, string $id, array $options = []): ?array
    {
        try {
            $params = [
                'index' => $index,
                'id' => $id,
            ];

            if (!empty($options)) {
                $params = array_merge($params, $options);
            }

            $response = $this->client->getClient()->get($params);
            return $response->asArray();
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not_found')) {
                return null;
            }
            throw new ElasticClientException("Failed to get document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update a document
     *
     * @param string $index
     * @param string $id
     * @param array $document
     * @param array $options
     * @return array
     * @throws ElasticClientException
     */
    public function update(string $index, string $id, array $document, array $options = []): array
    {
        try {
            $params = [
                'index' => $index,
                'id' => $id,
                'body' => [
                    'doc' => $document
                ]
            ];

            if (!empty($options)) {
                $params = array_merge($params, $options);
            }

            $response = $this->client->getClient()->update($params);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to update document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete a document
     *
     * @param string $index
     * @param string $id
     * @param array $options
     * @return array
     * @throws ElasticClientException
     */
    public function delete(string $index, string $id, array $options = []): array
    {
        try {
            $params = [
                'index' => $index,
                'id' => $id,
            ];

            if (!empty($options)) {
                $params = array_merge($params, $options);
            }

            $response = $this->client->getClient()->delete($params);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to delete document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Bulk operation
     *
     * @param array $operations
     * @param array $options
     * @return array
     * @throws ElasticClientException
     */
    public function bulk(array $operations, array $options = []): array
    {
        try {
            $params = ['body' => $operations];

            if (!empty($options)) {
                $params = array_merge($params, $options);
            }

            $response = $this->client->getClient()->bulk($params);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to execute bulk operation: {$e->getMessage()}", 0, $e);
        }
    }
}