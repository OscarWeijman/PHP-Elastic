<?php

namespace OscarWeijman\PhpElastic\Embedding;

use RuntimeException;
use InvalidArgumentException;

/**
 * Service voor het genereren van embeddings via verschillende API's
 */
class EmbeddingService
{
    private string $apiUrl;
    private string $apiKey;
    private array $modelConfig;

    /**
     * @param string $apiUrl De URL van de embedding API (bijv. Ollama of OpenAI)
     * @param string $apiKey API key indien nodig
     * @param array $modelConfig Configuratie voor verschillende modellen
     */
    public function __construct(string $apiUrl, string $apiKey = '', array $modelConfig = [])
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->modelConfig = $modelConfig;
    }

    /**
     * Genereer een embedding voor een tekst met een specifiek model
     * 
     * @param string $text De tekst om te embedden
     * @param string $modelName De naam van het model
     * @return array De embedding vector
     * @throws InvalidArgumentException Als het model niet is geconfigureerd
     * @throws RuntimeException Als de API call mislukt
     */
    public function generateEmbedding(string $text, string $modelName): array
    {
        // Controleer of het model bestaat in de configuratie
        if (!isset($this->modelConfig[$modelName])) {
            throw new InvalidArgumentException("Model '{$modelName}' is niet geconfigureerd");
        }

        $modelConfig = $this->modelConfig[$modelName];
        $apiType = $modelConfig['type'] ?? 'ollama';

        // Kies de juiste API implementatie
        switch ($apiType) {
            case 'ollama':
                return $this->callOllamaApi($text, $modelName);
            case 'openai':
                return $this->callOpenAiApi($text, $modelName);
            case 'huggingface':
                return $this->callHuggingFaceApi($text, $modelName);
            default:
                throw new InvalidArgumentException("API type '{$apiType}' wordt niet ondersteund");
        }
    }

    /**
     * Genereer embeddings voor meerdere teksten in batch
     * 
     * @param array $texts Array van teksten om te embedden
     * @param string $modelName De naam van het model
     * @return array Array van embedding vectors
     */
    public function generateBatchEmbeddings(array $texts, string $modelName): array
    {
        $embeddings = [];
        
        foreach ($texts as $text) {
            $embeddings[] = $this->generateEmbedding($text, $modelName);
        }
        
        return $embeddings;
    }

    /**
     * Roep de Ollama API aan voor embeddings
     */
    private function callOllamaApi(string $text, string $modelName): array
    {
        $url = rtrim($this->apiUrl, '/') . '/api/embeddings';
        
        $data = [
            'model' => $modelName,
            'prompt' => $text
        ];

        $response = $this->makeHttpRequest($url, $data);
        
        if (!isset($response['embedding'])) {
            // Debug informatie toevoegen
            $debug = json_encode($response);
            throw new RuntimeException("Geen embedding ontvangen van Ollama API. Response: {$debug}");
        }

        return $response['embedding'];
    }

    /**
     * Roep de OpenAI API aan voor embeddings
     */
    private function callOpenAiApi(string $text, string $modelName): array
    {
        $url = 'https://api.openai.com/v1/embeddings';
        
        $data = [
            'model' => $modelName,
            'input' => $text
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $response = $this->makeHttpRequest($url, $data, $headers);
        
        if (!isset($response['data'][0]['embedding'])) {
            throw new RuntimeException("Geen embedding ontvangen van OpenAI API");
        }

        return $response['data'][0]['embedding'];
    }

    /**
     * Roep de HuggingFace API aan voor embeddings
     */
    private function callHuggingFaceApi(string $text, string $modelName): array
    {
        $url = 'https://api-inference.huggingface.co/pipeline/feature-extraction/' . $modelName;
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $response = $this->makeHttpRequest($url, $text, $headers, false);
        
        if (!is_array($response) || empty($response)) {
            throw new RuntimeException("Geen embedding ontvangen van HuggingFace API");
        }

        // HuggingFace retourneert een 2D array voor een enkele input
        // We nemen het gemiddelde van alle token embeddings
        if (isset($response[0]) && is_array($response[0])) {
            return $this->meanPooling($response);
        }

        return $response;
    }

    /**
     * Bereken het gemiddelde van token embeddings (mean pooling)
     */
    private function meanPooling(array $tokenEmbeddings): array
    {
        $sum = array_fill(0, count($tokenEmbeddings[0]), 0);
        $count = count($tokenEmbeddings);
        
        foreach ($tokenEmbeddings as $embedding) {
            foreach ($embedding as $i => $value) {
                $sum[$i] += $value;
            }
        }
        
        return array_map(function($value) use ($count) {
            return $value / $count;
        }, $sum);
    }

    /**
     * Maak een HTTP request
     */
    private function makeHttpRequest(string $url, $data, array $headers = [], bool $jsonEncode = true): array
    {
        $ch = curl_init($url);
        
        if (empty($headers)) {
            $headers = ['Content-Type: application/json'];
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonEncode ? json_encode($data) : $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Debug: Toon de request
        // echo "Request URL: {$url}\n";
        // echo "Request data: " . json_encode($data) . "\n";
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request mislukt: {$error}");
        }
        
        curl_close($ch);
        
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("API request mislukt met status code: {$statusCode}, response: {$response}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Ongeldige JSON response: " . json_last_error_msg() . ", raw response: " . substr($response, 0, 100) . "...");
        }
        
        return $decoded;
    }

    /**
     * Voeg een model toe aan de configuratie
     */
    public function addModel(string $modelId, string $modelName, int $dimensions, string $type = 'ollama'): self
    {
        $this->modelConfig[$modelId] = [
            'name' => $modelName,
            'dims' => $dimensions,
            'type' => $type
        ];
        
        return $this;
    }

    /**
     * Haal de dimensies op van een model
     */
    public function getModelDimensions(string $modelId): int
    {
        if (!isset($this->modelConfig[$modelId])) {
            throw new InvalidArgumentException("Model '{$modelId}' is niet geconfigureerd");
        }
        
        return $this->modelConfig[$modelId]['dims'];
    }

    /**
     * Haal alle geconfigureerde modellen op
     */
    public function getModels(): array
    {
        return $this->modelConfig;
    }
}
