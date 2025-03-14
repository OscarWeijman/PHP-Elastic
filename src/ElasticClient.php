<?php

namespace OscarWeijman\PhpElastic;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

class ElasticClient
{
    private Client $client;
    private array $config;

    /**
     * Create a new ElasticClient instance
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'hosts' => ['localhost:9200'],
            'basicAuth' => null,
            'apiKey' => null,
            'sslVerification' => true,
            'retries' => 2,
        ], $config);

        $this->connect();
    }

    /**
     * Connect to Elasticsearch
     *
     * @return void
     * @throws ElasticClientException
     */
    private function connect(): void
    {
        try {
            $builder = ClientBuilder::create()
                ->setHosts($this->config['hosts'])
                ->setRetries($this->config['retries']);

            if (!$this->config['sslVerification']) {
                $builder->setSSLVerification(false);
            }

            if ($this->config['basicAuth']) {
                [$username, $password] = $this->config['basicAuth'];
                $builder->setBasicAuthentication($username, $password);
            } elseif ($this->config['apiKey']) {
                $builder->setApiKey($this->config['apiKey']);
            }

            $this->client = $builder->build();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to connect to Elasticsearch: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the raw Elasticsearch client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Check if Elasticsearch is reachable
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $response = $this->client->ping();
            return $response->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Elasticsearch cluster info
     *
     * @return array
     * @throws ElasticClientException
     */
    public function info(): array
    {
        try {
            $response = $this->client->info();
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Failed to get cluster info: {$e->getMessage()}", 0, $e);
        }
    }
}