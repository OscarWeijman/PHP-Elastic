<?php

namespace OscarWeijman\PhpElastic\Search;

use OscarWeijman\PhpElastic\ElasticClient;
use InvalidArgumentException;

/**
 * Builder voor vector search queries in Elasticsearch
 */
class VectorSearchBuilder
{
    private ElasticClient $client;
    private array $params = [];
    private ?array $vectorQuery = null;
    private array $filters = [];
    private string $similarityMetric = 'cosine';
    private ?array $hybridQuery = null;
    private float $vectorBoost = 1.0;
    private float $textBoost = 1.0;

    public function __construct(ElasticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set the index to search
     */
    public function index(string $index): self
    {
        $this->params['index'] = $index;
        return $this;
    }

    /**
     * Set multiple indices to search
     */
    public function indices(array $indices): self
    {
        $this->params['index'] = implode(',', $indices);
        return $this;
    }

    /**
     * Set the vector query
     * 
     * @param string $field Het veld dat de vector bevat
     * @param array $vector De query vector
     * @param float $boost Boost factor voor deze query
     */
    public function vectorQuery(string $field, array $vector, float $boost = 1.0): self
    {
        $this->vectorQuery = [
            'field' => $field,
            'vector' => $vector,
            'boost' => $boost
        ];
        $this->vectorBoost = $boost;
        return $this;
    }

    /**
     * Voeg een text query toe voor hybride zoeken
     * 
     * @param array $query Een Elasticsearch query (bijv. match, multi_match)
     * @param float $boost Boost factor voor deze query
     */
    public function textQuery(array $query, float $boost = 1.0): self
    {
        $this->hybridQuery = $query;
        $this->textBoost = $boost;
        return $this;
    }

    /**
     * Set the similarity metric (cosine, dot_product, l2_norm)
     */
    public function similarityMetric(string $metric): self
    {
        $validMetrics = ['cosine', 'dot_product', 'l2_norm'];
        
        if (!in_array($metric, $validMetrics)) {
            throw new InvalidArgumentException(
                "Ongeldige similarity metric: '{$metric}'. Geldige opties zijn: " . implode(', ', $validMetrics)
            );
        }
        
        $this->similarityMetric = $metric;
        return $this;
    }

    /**
     * Add a filter
     */
    public function filter(array $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * Add a term filter
     */
    public function term(string $field, $value): self
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
     * Set the size parameter
     */
    public function size(int $size): self
    {
        $this->params['size'] = $size;
        return $this;
    }

    /**
     * Set the from parameter (for pagination)
     */
    public function from(int $from): self
    {
        $this->params['from'] = $from;
        return $this;
    }

    /**
     * Add an aggregation
     */
    public function aggregation(string $name, array $aggregation): self
    {
        if (!isset($this->params['body']['aggs'])) {
            $this->params['body']['aggs'] = [];
        }
        
        $this->params['body']['aggs'][$name] = $aggregation;
        return $this;
    }

    /**
     * Build and get the query
     */
    public function getQuery(): array
    {
        $this->buildQuery();
        return $this->params;
    }

    /**
     * Execute the search
     */
    public function execute(): array
    {
        $this->buildQuery();
        return $this->client->search($this->params);
    }

    /**
     * Build the query
     */
    private function buildQuery(): void
    {
        // Als er geen vector query is en geen hybride query, dan is er een fout
        if ($this->vectorQuery === null && $this->hybridQuery === null) {
            throw new InvalidArgumentException('Vector query of text query is vereist');
        }

        // Basis query structuur
        $this->params['body'] = $this->params['body'] ?? [];
        
        // Filters toevoegen als die er zijn
        $filterQuery = !empty($this->filters) 
            ? ['bool' => ['filter' => $this->filters]] 
            : ['match_all' => new \stdClass()];
            
        // Als we alleen een vector query hebben
        if ($this->vectorQuery !== null && $this->hybridQuery === null) {
            $this->buildVectorOnlyQuery($filterQuery);
        } 
        // Als we alleen een text query hebben
        elseif ($this->hybridQuery !== null && $this->vectorQuery === null) {
            $this->buildTextOnlyQuery($filterQuery);
        }
        // Als we beide hebben, maken we een hybride query
        else {
            $this->buildHybridQuery($filterQuery);
        }
    }

    /**
     * Bouw een query met alleen vector search
     */
    private function buildVectorOnlyQuery(array $filterQuery): void
    {
        $field = $this->vectorQuery['field'];
        $vector = $this->vectorQuery['vector'];
        $boost = $this->vectorQuery['boost'];

        // Script score query voor vector similarity
        $this->params['body']['query'] = [
            'script_score' => [
                'query' => $filterQuery,
                'script' => [
                    'source' => $this->getSimilarityScript($field),
                    'params' => [
                        'query_vector' => $vector
                    ]
                ],
                'boost' => $boost
            ]
        ];
    }

    /**
     * Bouw een query met alleen text search
     */
    private function buildTextOnlyQuery(array $filterQuery): void
    {
        // Combineer de text query met filters
        if (!empty($this->filters)) {
            $this->params['body']['query'] = [
                'bool' => [
                    'must' => $this->hybridQuery,
                    'filter' => $this->filters
                ]
            ];
        } else {
            $this->params['body']['query'] = $this->hybridQuery;
        }
    }

    /**
     * Bouw een hybride query met zowel vector als text search
     */
    private function buildHybridQuery(array $filterQuery): void
    {
        $field = $this->vectorQuery['field'];
        $vector = $this->vectorQuery['vector'];
        
        // Maak een bool query met should voor text en script_score voor vector
        $this->params['body']['query'] = [
            'bool' => [
                'should' => [
                    // Vector deel
                    [
                        'script_score' => [
                            'query' => ['match_all' => new \stdClass()],
                            'script' => [
                                'source' => $this->getSimilarityScript($field),
                                'params' => [
                                    'query_vector' => $vector
                                ]
                            ],
                            'boost' => $this->vectorBoost
                        ]
                    ],
                    // Text deel
                    $this->hybridQuery
                ],
                'minimum_should_match' => 1,
                // Filters toevoegen als die er zijn
                'filter' => !empty($this->filters) ? $this->filters : null
            ]
        ];
        
        // Verwijder null filter als er geen filters zijn
        if (empty($this->filters)) {
            unset($this->params['body']['query']['bool']['filter']);
        }
    }

    /**
     * Krijg het juiste script voor de gekozen similarity metric
     */
    private function getSimilarityScript(string $field): string
    {
        switch ($this->similarityMetric) {
            case 'cosine':
                return "cosineSimilarity(params.query_vector, '{$field}') + 1.0";
            case 'dot_product':
                return "dotProduct(params.query_vector, '{$field}')";
            case 'l2_norm':
                return "1 / (1 + l2norm(params.query_vector, '{$field}'))";
            default:
                return "cosineSimilarity(params.query_vector, '{$field}') + 1.0";
        }
    }
}
