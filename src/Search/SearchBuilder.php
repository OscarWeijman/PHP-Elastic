<?php

namespace OscarWeijman\PhpElastic\Search;

use OscarWeijman\PhpElastic\ElasticClient;

class SearchBuilder
{
    private ElasticClient $client;
    private array $params = [];
    private array $query = [];
    private array $filters = [];
    private array $aggregations = [];

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set the indices to search
     */
    public function indices(string|array $indices): self
    {
        $this->params['index'] = is_array($indices) ? implode(',', $indices) : $indices;
        return $this;
    }

    /**
     * Add a match query
     */
    public function match(string $field, mixed $value): self
    {
        $this->query['match'][$field] = $value;
        return $this;
    }

    /**
     * Add a match phrase query
     */
    public function matchPhrase(string $field, string $value): self
    {
        $this->query['match_phrase'][$field] = $value;
        return $this;
    }

    /**
     * Add a term filter
     */
    public function term(string $field, mixed $value): self
    {
        $this->filters[] = ['term' => [$field => $value]];
        return $this;
    }

    /**
     * Add a terms filter
     */
    public function terms(string $field, array $values): self
    {
        $this->filters[] = ['terms' => [$field => $values]];
        return $this;
    }

    /**
     * Add a range filter
     */
    public function range(string $field, array $conditions): self
    {
        $this->filters[] = ['range' => [$field => $conditions]];
        return $this;
    }

    /**
     * Set the from parameter (for pagination)
     */
    public function from(int $from): self
    {
        $this->params['body']['from'] = $from;
        return $this;
    }

    /**
     * Set the size parameter (for pagination)
     */
    public function size(int $size): self
    {
        $this->params['body']['size'] = $size;
        return $this;
    }

    /**
     * Set the sort parameter
     */
    public function sort(array $sort): self
    {
        $this->params['body']['sort'] = $sort;
        return $this;
    }

    /**
     * Add an aggregation
     */
    public function aggregation(string $name, array $aggregation): self
    {
        $this->aggregations[$name] = $aggregation;
        return $this;
    }

    /**
     * Build the final query and return the body
     * 
     * @return array
     */
    public function buildQuery(): array
    {
        $this->buildQueryInternal();
        return $this->params['body'] ?? [];
    }

    /**
     * Execute the search
     */
    public function execute(): array
    {
        $this->buildQueryInternal();
        return $this->client->search($this->params);
    }

    /**
     * Build the final query
     */
    private function buildQueryInternal(): void
    {
        $body = [];

        // Add query if exists
        if (!empty($this->query)) {
            $body['query']['bool']['must'][] = $this->query;
        }

        // Add filters if exist
        if (!empty($this->filters)) {
            $body['query']['bool']['filter'] = $this->filters;
        }

        // Add aggregations if exist
        if (!empty($this->aggregations)) {
            $body['aggs'] = $this->aggregations;
        }

        // Merge with existing body
        if (isset($this->params['body'])) {
            $this->params['body'] = array_merge($this->params['body'], $body);
        } else {
            $this->params['body'] = $body;
        }
    }
}