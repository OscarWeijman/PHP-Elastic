<?php
/**
 * Basis gebruik van de PHP Elasticsearch Library
 */

require_once __DIR__ . '/../vendor/autoload.php';

use OscarWeijman\PhpElastic\ElasticClient;
use OscarWeijman\PhpElastic\Exception\ElasticClientException;

// Maak een client instance
try {
    $client = new ElasticClient([
        'hosts' => ['localhost:9200'],
        // Uncomment voor authenticatie
        // 'basicAuth' => ['username', 'password'],
    ]);

    // Check of Elasticsearch bereikbaar is
    if ($client->ping()) {
        echo "✅ Verbinding met Elasticsearch succesvol!\n";
        
        // Toon cluster info
        $info = $client->info();
        echo "Elasticsearch versie: {$info['version']['number']}\n";
        echo "Cluster naam: {$info['cluster_name']}\n";
    } else {
        echo "❌ Kan geen verbinding maken met Elasticsearch\n";
    }
} catch (ElasticClientException $e) {
    echo "❌ Fout bij verbinden met Elasticsearch: {$e->getMessage()}\n";
}