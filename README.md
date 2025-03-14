# PHP Elasticsearch Library

Een moderne PHP library voor het werken met Elasticsearch, met focus op eenvoud en type safety.

[![Tests](https://github.com/oscarweijman/php-elastic/actions/workflows/tests.yml/badge.svg)](https://github.com/oscarweijman/php-elastic/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/oscarweijman/php-elastic/v/stable)](https://packagist.org/packages/oscarweijman/php-elastic)
[![License](https://poser.pugx.org/oscarweijman/php-elastic/license)](https://packagist.org/packages/oscarweijman/php-elastic)

## Features

- 🚀 Moderne PHP 8.x syntax
- 🔍 Fluent interface voor zoeken
- 📦 Eenvoudig document management
- 🎯 Index beheer
- ⚡ Bulk operaties
- 🧪 Uitgebreide test suite met Pest
- 💪 Type-safe operaties
- 🔒 Exception handling

## Installatie

```bash
composer require oscarweijman/php-elastic
```

## Basis Gebruik

```php
use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Document\DocumentManager;
use OscarWeijman\PhpElastic\Search\SearchBuilder;

// Maak een client instance
$client = new ElasticClient([
    'hosts' => ['localhost:9200'],
    'basicAuth' => ['user', 'pass'] // optioneel
]);

// Document toevoegen
$documentManager = new DocumentManager($client);
$documentManager->index('my-index', [
    'title' => 'Test Document',
    'content' => 'Dit is een test document',
    'tags' => ['test', 'voorbeeld'],
    'created_at' => '2025-03-14T21:54:00Z'
]);

// Zoeken
$searchBuilder = new SearchBuilder($client);
$results = $searchBuilder
    ->indices('my-index')
    ->match('title', 'test')
    ->term('tags', 'voorbeeld')
    ->sort(['created_at' => 'desc'])
    ->size(10)
    ->execute();
```

## Configuratie

De ElasticClient accepteert de volgende configuratie opties:

```php
$config = [
    'hosts' => ['localhost:9200'], // Array van Elasticsearch hosts
    'basicAuth' => ['username', 'password'], // Basic authentication
    'apiKey' => 'your-api-key', // API key authentication
    'sslVerification' => true, // SSL verificatie aan/uit
    'retries' => 2 // Aantal retry attempts
];

$client = new ElasticClient($config);
```

## Index Management

```php
use OscarWeijman\PhpElastic\Index\IndexManager;

$indexManager = new IndexManager($client);

// Index aanmaken met settings en mappings
$indexManager->create('my-index', [
    'number_of_shards' => 1,
    'number_of_replicas' => 0 // Gebruik 0 replicas voor single-node clusters
], [
    'properties' => [
        'title' => ['type' => 'text'],
        'content' => ['type' => 'text'],
        'tags' => ['type' => 'keyword'],
        'created_at' => ['type' => 'date']
    ]
]);

// Index verwijderen
$indexManager->delete('my-index');
```

## Document Management

```php
$documentManager = new DocumentManager($client);

// Document toevoegen
$documentManager->index('my-index', [
    'title' => 'Mijn Document',
    'content' => 'Document inhoud'
], 'custom-id'); // ID is optioneel

// Document ophalen
$document = $documentManager->get('my-index', 'custom-id');

// Document updaten
$documentManager->update('my-index', 'custom-id', [
    'title' => 'Nieuwe Titel'
]);

// Document verwijderen
$documentManager->delete('my-index', 'custom-id');

// Bulk operaties
$operations = [
    ['index' => ['_index' => 'my-index', '_id' => 'id1']],
    ['title' => 'Document 1'],
    ['index' => ['_index' => 'my-index', '_id' => 'id2']],
    ['title' => 'Document 2']
];

$documentManager->bulk($operations);
```

## Zoeken

De SearchBuilder biedt een fluent interface voor het bouwen van zoekopdrachten:

```php
$searchBuilder = new SearchBuilder($client);

$results = $searchBuilder
    ->indices('my-index')
    // Match query
    ->match('title', 'zoekterm')
    // Term filter
    ->term('tags', 'belangrijk')
    // Range filter
    ->range('created_at', [
        'gte' => '2025-01-01',
        'lte' => '2025-12-31'
    ])
    // Sortering
    ->sort(['created_at' => 'desc'])
    // Paginering
    ->from(0)
    ->size(10)
    // Aggregaties
    ->aggregation('tag_counts', [
        'terms' => ['field' => 'tags']
    ])
    ->execute();
```

## Single-Node Clusters

Als je Elasticsearch in een single-node configuratie gebruikt (zoals in een ontwikkelomgeving), zul je merken dat indices standaard een "yellow" health status hebben. Dit komt omdat Elasticsearch standaard replicas aanmaakt, maar er geen andere nodes zijn om deze te plaatsen.

Om een "green" status te krijgen in een single-node cluster, maak je indices aan met `number_of_replicas` ingesteld op 0:

```php
$indexManager->create('my-index', [
    'number_of_shards' => 1,
    'number_of_replicas' => 0 // Geen replicas voor single-node clusters
]);
```

## Testing

De library gebruikt Pest voor testing. Om de tests uit te voeren:

```bash
./vendor/bin/pest
```

## Licentie

MIT License

## Contributing

Bijdragen zijn welkom! Voel je vrij om issues aan te maken of pull requests in te dienen.

## Support

Voor vragen of problemen, maak een issue aan op GitHub.