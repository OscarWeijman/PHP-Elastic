# Changelog

Alle belangrijke wijzigingen aan dit project worden in dit bestand gedocumenteerd.

Het format is gebaseerd op [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
en dit project volgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Toegevoegd
- Initiële release van de PHP Elasticsearch Library
- ElasticClient voor verbinding met Elasticsearch
- IndexManager voor het beheren van indices
- DocumentManager voor CRUD operaties op documenten
- SearchBuilder voor het bouwen van zoekopdrachten
- Ondersteuning voor bulk operaties
- Voorbeelden voor basis gebruik, index management, document management en zoeken
- Documentatie voor single-node clusters

### Gewijzigd
- Verbeterde error handling in IndexManager::delete() met ignore_unavailable parameter

### Opgelost
- Probleem met yellow status in single-node clusters door number_of_replicas op 0 te zetten