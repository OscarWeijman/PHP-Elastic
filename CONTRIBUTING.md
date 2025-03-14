# Contributing to PHP Elasticsearch Library

Bedankt voor je interesse om bij te dragen aan de PHP Elasticsearch Library! Elke bijdrage is welkom, of het nu gaat om het melden van bugs, het voorstellen van nieuwe features, of het verbeteren van de documentatie.

## Issues

Als je een bug vindt of een idee hebt voor een nieuwe feature, maak dan een issue aan op GitHub. Zorg ervoor dat je:

- Een duidelijke titel en beschrijving geeft
- Stappen om het probleem te reproduceren (in geval van een bug)
- Verwacht gedrag en wat er in plaats daarvan gebeurt
- Relevante logs of screenshots

## Pull Requests

We verwelkomen pull requests! Om een pull request in te dienen:

1. Fork de repository
2. Maak een nieuwe branch vanaf `main`
3. Voeg je wijzigingen toe
4. Zorg ervoor dat alle tests slagen
5. Dien een pull request in naar de `main` branch

### Codestijl

Deze library volgt de [PSR-12](https://www.php-fig.org/psr/psr-12/) codestijl standaard. Zorg ervoor dat je code hieraan voldoet voordat je een pull request indient.

### Tests

Alle nieuwe code moet worden gedekt door tests. Deze library gebruikt [Pest](https://pestphp.com/) voor testing. Om de tests uit te voeren:

```bash
./vendor/bin/pest
```

## Development Setup

Om aan deze library te werken, heb je het volgende nodig:

1. PHP 8.0 of hoger
2. Composer
3. Een draaiende Elasticsearch instance (lokaal of via Docker)

### Installatie

```bash
# Clone de repository
git clone https://github.com/jouw-username/php-elastic.git
cd php-elastic

# Installeer dependencies
composer install
```

### Docker voor Elasticsearch

Je kunt Elasticsearch eenvoudig draaien met Docker:

```bash
docker run -d --name elasticsearch -p 9200:9200 -p 9300:9300 -e "discovery.type=single-node" -e "xpack.security.enabled=false" elasticsearch:8.12.0
```

## Licentie

Door bij te dragen aan dit project, ga je ermee akkoord dat je bijdragen worden gelicenseerd onder dezelfde [MIT License](LICENSE) die het project gebruikt.