# Installation du générateur sur un nouveau projet Symfony

Le générateur est embarqué directement dans le projet Symfony (namespace `App\`). Pour l'ajouter à un autre projet, il faut copier les fichiers sources et enregistrer la configuration.

## Prérequis

- PHP >= 8.4
- Symfony >= 8.0
- API Platform >= 4.2 (`api-platform/symfony` + `api-platform/doctrine-orm`)
- `symfony/property-info` et `phpdocumentor/reflection-docblock` installés

---

## Étape 1 — Dépendances Composer

```bash
composer require api-platform/symfony api-platform/doctrine-orm
composer require phpdocumentor/reflection-docblock phpstan/phpdoc-parser
```

---

## Étape 2 — Copier les fichiers du générateur

Depuis ce projet, copier dans le nouveau projet :

```
src/NuxtGeneratorBundle.php
src/Command/GenerateNuxtInterfaceCommand.php
src/Command/DebugExtractorCommand.php       (optionnel, utile pour déboguer)
src/Command/DebugNormalizerCommand.php       (optionnel)
src/DependencyInjection/
src/Generator/
```

Structure attendue dans le nouveau projet :

```
src/
├── NuxtGeneratorBundle.php
├── Command/
│   └── GenerateNuxtInterfaceCommand.php
├── DependencyInjection/
│   ├── Configuration.php
│   └── NuxtGeneratorExtension.php
└── Generator/
    ├── Config/
    ├── Deduplication/
    ├── DTO/
    ├── Extractor/
    ├── Naming/
    ├── Normalizer/
    ├── Resolver/
    ├── TypeScript/
    └── Writer/
```

---

## Étape 3 — Enregistrer le bundle

Dans `config/bundles.php`, ajouter :

```php
App\NuxtGeneratorBundle::class => ['all' => true],
```

Exemple complet :

```php
return [
    // ... bundles existants ...
    ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    App\NuxtGeneratorBundle::class => ['all' => true],  // <-- ajouter
];
```

---

## Étape 4 — Configurer les services

Dans `config/services.yaml`, ajouter à la fin :

```yaml
services:
    # ... configuration existante ...

    # Nuxt Generator Configuration
    App\Generator\Config\GeneratorConfiguration:
        factory: ['@App\Generator\Config\GeneratorConfigurationFactory', 'create']

    # Filter Metadata Extractor needs API Platform filter locator
    App\Generator\Extractor\FilterMetadataExtractor:
        arguments:
            $filterLocator: '@api_platform.filter_locator'

    # API Platform interfaces - alias to their implementations
    ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface: '@api_platform.metadata.resource.name_collection_factory'
    ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface: '@api_platform.metadata.resource.metadata_collection_factory'
    ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface: '@api_platform.metadata.property.name_collection_factory'
    ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface: '@api_platform.metadata.property.metadata_factory'
```

> **Important** : la ligne `App\:` avec `resource: '../src/'` doit déjà être présente (elle est là par défaut dans Symfony). Ces lignes viennent en complément, elles ne la remplacent pas.

---

## Étape 5 — Créer le fichier de configuration

Créer `config/packages/nuxt_generator.yaml` :

```yaml
nuxt_generator:
    output_path: '%env(NUXT_GENERATOR_OUTPUT)%'

    defaults:
        clean_before_generate: true
        generate_hydra_helpers: true
        strict_null_checks: true
        default_items_per_page: 30

    client:
        base_url: '/api'
        credentials: 'include'

    naming:
        type_suffixes:
            collection_output: 'List'
            item_output: 'Detail'
            create_input: 'CreateInput'
            update_input: 'UpdateInput'
            replace_input: 'ReplaceInput'
            query: 'Query'
```

---

## Étape 6 — Variable d'environnement

Dans `.env` (ou `.env.local`) :

```dotenv
NUXT_GENERATOR_OUTPUT=/chemin/absolu/vers/votre/projet/nuxt
```

Exemple :

```dotenv
NUXT_GENERATOR_OUTPUT=/home/user/projects/mon-app-nuxt
```

Les fichiers seront générés dans `$NUXT_GENERATOR_OUTPUT/generated/`.

---

## Étape 7 — Vider le cache

```bash
php bin/console cache:clear
```

---

## Étape 8 — Vérifier l'installation

```bash
php bin/console list app
```

Vous devez voir :

```
app:generate-nuxt-interface   Generates TypeScript types and API functions from API Platform resources
app:debug-extractor           (si copié)
app:debug-normalizer          (si copié)
```

---

## Étape 9 — Lancer la génération

```bash
php bin/console app:generate-nuxt-interface
```

Avec un chemin différent de celui configuré :

```bash
php bin/console app:generate-nuxt-interface --output=/autre/chemin
```

Sans nettoyer les fichiers existants :

```bash
php bin/console app:generate-nuxt-interface --no-clean
```

---

## Erreurs courantes

### `There is no extension able to load the configuration for "nuxt_generator"`

L'extension n'est pas chargée. Vérifier :
1. `NuxtGeneratorBundle` est bien enregistré dans `config/bundles.php`
2. `NuxtGeneratorExtension::getAlias()` retourne bien `'nuxt_generator'`
3. Le cache est vidé : `php bin/console cache:clear`

### `Service "api_platform.filter_locator" not found`

API Platform n'est pas installé ou le bundle n'est pas enregistré. Vérifier que `ApiPlatformBundle` est présent dans `bundles.php` et que `config/packages/api_platform.yaml` existe.

### `Output path not specified`

La variable `NUXT_GENERATOR_OUTPUT` n'est pas définie. Ajouter dans `.env.local` ou utiliser l'option `--output` :

```bash
php bin/console app:generate-nuxt-interface --output=/tmp/test
```

### `No API Platform resources found`

Aucune entité n'a l'attribut `#[ApiResource]`. Vérifier vos entités et vider le cache.

### Erreur d'autoload sur les classes `App\Generator\...`

Vérifier que l'autoload PSR-4 dans `composer.json` couvre bien `src/` :

```json
"autoload": {
    "psr-4": {
        "App\\": "src/"
    }
}
```

Puis relancer :

```bash
composer dump-autoload
```

---

## Résumé des fichiers modifiés/créés

| Fichier | Action |
|---|---|
| `src/NuxtGeneratorBundle.php` | Copier |
| `src/Command/GenerateNuxtInterfaceCommand.php` | Copier |
| `src/DependencyInjection/` | Copier (dossier complet) |
| `src/Generator/` | Copier (dossier complet) |
| `config/bundles.php` | Modifier (ajouter `NuxtGeneratorBundle`) |
| `config/services.yaml` | Modifier (ajouter les 5 définitions de services) |
| `config/packages/nuxt_generator.yaml` | Créer |
| `.env` | Modifier (ajouter `NUXT_GENERATOR_OUTPUT`) |
