<?php

namespace OscarWeijman\PhpElastic\Search;

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

class SearchBuilder
{
    private ElasticClient $client;
    private array $query = [];
    private array $indices = [];
    private int $from = 0;
    private int $size = 10;
    private array $sort = [];
    private array $aggs = [];
    private array $source = [];
    private array $highlight = [];

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set the indices to search
     *
     * @param string|array $indices
     * @return $this
     */
    public function indices(string|array $indices): self
    {
        $this->indices = is_array($indices) ? $indices : [$indices];
        return $this;
    }

    /**
     * Set the from parameter (pagination)
     *
     * @param int $from
     * @return $this
     */
    public function from(int $from): self
    {
        $this->from = $from;
        return $this;
    }

    /**
     * Set the size parameter (pagination)
     *
     * @param int $size
     * @return $this
     */
    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Set the sort parameter
     *
     * @param array $sort
     * @return $this
     */
    public function sort(array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * Set the _source parameter
     *
     * @param array|bool $source
     * @return $this
     */
    public function source(array|bool $source): self
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Set the highlight parameter
     *
     * @param array $highlight
     * @return $this
     */
    public function highlight(array $highlight): self
    {
        $this->highlight = $highlight;
        return $this;
    }

    /**
     * Add a match query
     *
     * @param string $field
     * @param mixed $value
     * @param float $boost
     * @return $this
     */
    public function match(string $field, mixed $value, float $boost = 1.0): self
    {
        $this->query['bool']['must'][] = [
            'match' => [
                $field => [
                    'query' => $value,
                    'boost' => $boost
                ]
            ]
        ];
        return $this;
    }

    /**
     * Add a term query
     *
     * @param string $field
     * @param mixed $value
     * @return $this
     */
    public function term(string $field, mixed $value): self
    {
        $this->query['bool']['filter'][] = [
            'term' => [
                $field => $value
            ]
        ];
        return $this;
    }

    /**
     * Add a terms query
     *
     * @param string $field
     * @param array $values
     * @return $this
     */
    public function terms(string $field, array $values): self
    {
        $this->query['bool']['filter'][] = [
            'terms' => [
                $field => $values
            ]
        ];
        return $this;
    }

    /**
     * Add a range query
     *
     * @param string $field
     * @param array $conditions
     * @return $this
     */
    public function range(string $field, array $conditions): self
    {
        $this->query['bool']['filter'][] = [
            'range' => [
                $field => $conditions
            ]
        ];
        return $this;
    }

    /**
     * Add a should query
     *
     * @param array $query
     * @return $this
     */
    public function should(array $query): self
    {
        if (!isset($this->query['bool']['should'])) {
            $this->query['bool']['should'] = [];
        }
        $this->query['bool']['should'][] = $query;
        return $this;
    }

    /**
     * Add an aggregation
     *
     * @param string $name
     * @param array $agg
     * @return $this
     */
    public function aggregation(string $name, array $agg): self
    {
        $this->aggs[$name] = $agg;
        return $this;
    }

    /**
     * Execute the search
     *
     * @param array $options Additional options to pass to the search
     * @return array
     * @throws ElasticClientException
     */
    public function execute(array $options = []): array
    {
        try {
            $params = [
                'body' => [
                    'from' => $this->from,
                    'size' => $this->size,
                ]
            ];

            if (!empty($this->indices)) {
                $params['index'] = implode(',', $this->indices);
            }

            if (!empty($this->query)) {
                $params['body']['query'] = $this->query;
            }

            if (!empty($this->sort)) {
                $params['body']['sort'] = $this->sort;
            }

            if (!empty($this->aggs)) {
                $params['body']['aggs'] = $this->aggs;
            }

            if (!empty($this->source)) {
                $params['body']['_source'] = $this->source;
            }

            if (!empty($this->highlight)) {
                $params['body']['highlight'] = $this->highlight;
            }

            if (!empty($options)) {
                $params = array_merge_recursive($params, $options);
            }

            $response = $this->client->getClient()->search($params);
            return $response->asArray();
        } catch (\Exception $e) {
            throw new ElasticClientException("Search failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the raw query array
     *
     * @return array
     */
    public function getQuery(): array
    {
        $query = [
            'from' => $this->from,
            'size' => $this->size,
        ];

        if (!empty($this->query)) {
            $query['query'] = $this->query;
        }

        if (!empty($this->sort)) {
            $query['sort'] = $this->sort;
        }

        if (!empty($this->aggs)) {
            $query['aggs'] = $this->aggs;
        }

        if (!empty($this->source)) {
            $query['_source'] = $this->source;
        }

        if (!empty($this->highlight)) {
            $query['highlight'] = $this->highlight;
        }

        return $query;
    }
}