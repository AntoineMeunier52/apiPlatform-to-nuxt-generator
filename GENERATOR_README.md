# Nuxt Generator — Documentation complète

Générateur de code TypeScript pour projets **Symfony 7 + API Platform 4 → Nuxt 4**. Il introspècte les métadonnées d'API Platform et génère automatiquement des types TypeScript, des fonctions API, des composables Vue et des helpers Hydra, entièrement typés et prêts à l'emploi.

---

## Table des matières

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Déclarer les entités](#3-déclarer-les-entités)
4. [Déclarer les DTOs](#4-déclarer-les-dtos)
5. [Lancer la génération](#5-lancer-la-génération)
6. [Structure des fichiers générés](#6-structure-des-fichiers-générés)
7. [Types TypeScript](#7-types-typescript)
8. [Fonctions API](#8-fonctions-api)
9. [Composables collection](#9-composables-collection)
10. [Composables resource](#10-composables-resource)
11. [Composable useSave](#11-composable-usesave)
12. [Composable useApiError](#12-composable-useapierror)
13. [Helpers Hydra (core)](#13-helpers-hydra-core)
14. [Fetcher et personnalisation](#14-fetcher-et-personnalisation)
15. [Règles de nommage](#15-règles-de-nommage)
16. [Cas particuliers et corner cases](#16-cas-particuliers-et-corner-cases)

---

## 1. Installation

### 1.1 Prérequis

- PHP 8.2+
- Symfony 7.x
- API Platform 4.x
- Doctrine ORM
- Nuxt 4.x (Vue 3)

### 1.2 Enregistrer le bundle

Dans `config/bundles.php` :

```php
return [
    // ...
    App\NuxtGeneratorBundle::class => ['all' => true],
];
```

### 1.3 Variables d'environnement

Dans `.env` :

```env
# Chemin de sortie des fichiers générés (relatif à la racine du projet)
NUXT_GENERATOR_OUTPUT=../mon-nuxt-app/generated
```

Pour le développement local, surcharger dans `.env.local` :

```env
NUXT_GENERATOR_OUTPUT=/chemin/absolu/vers/nuxt/generated
```

---

## 2. Configuration

Créer le fichier `config/packages/nuxt_generator.yaml` :

```yaml
nuxt_generator:
    # Chemin de sortie (peut utiliser une variable d'env)
    output_path: '%env(NUXT_GENERATOR_OUTPUT)%'

    defaults:
        # Supprime et recrée le dossier generated à chaque génération
        clean_before_generate: true
        # Génère les types et helpers Hydra JSON-LD
        generate_hydra_helpers: true
        # Active les vérifications de nullabilité strictes
        strict_null_checks: true
        # Nombre d'éléments par page par défaut dans les composables collection
        default_items_per_page: 30

    client:
        # URL de base de l'API (préfixe de toutes les URLs générées)
        base_url: '/api'
        # Mode de credentials pour fetch()
        credentials: 'include'   # 'include' | 'same-origin' | 'omit'

    naming:
        # Suffixes des types TypeScript générés (tous personnalisables)
        type_suffixes:
            collection_output: 'List'       # ProgramList
            item_output: 'Detail'           # ProgramDetail
            create_input: 'CreateInput'     # ProgramCreateInput
            update_input: 'UpdateInput'     # ProgramUpdateInput
            replace_input: 'ReplaceInput'   # ProgramReplaceInput
            query: 'Query'                  # ProgramQuery
```

### 2.1 Configuration des services Symfony

Dans `config/services.yaml`, ajouter les alias pour API Platform :

```yaml
services:
    # Configuration du générateur
    App\Generator\Config\GeneratorConfiguration:
        factory: ['@App\Generator\Config\GeneratorConfigurationFactory', 'create']

    # Filtre API Platform
    App\Generator\Extractor\FilterMetadataExtractor:
        arguments:
            $filterLocator: '@api_platform.filter_locator'

    # Alias des interfaces API Platform
    ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface:
        alias: 'api_platform.metadata.resource.name_collection_factory'
    ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface:
        alias: 'api_platform.metadata.resource.metadata_collection_factory'
    ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface:
        alias: 'api_platform.metadata.property.name_collection_factory'
    ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface:
        alias: 'api_platform.metadata.property.metadata_factory'
```

---

## 3. Déclarer les entités

Le générateur lit les attributs API Platform sur vos entités Doctrine pour produire le code TypeScript correspondant.

### 3.1 Entité basique — CRUD standard

```php
<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProgramRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['program:read']],
    denormalizationContext: ['groups' => ['program:write']],
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['program:list']]),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: ProgramRepository::class)]
class Program
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['program:list', 'program:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['program:list', 'program:read', 'program:write'])]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['program:read', 'program:write'])]
    private ?string $description = null;
}
```

**Ce qui est généré :**

```typescript
// types/Program.ts
export interface ProgramList    { id: number; name: string }
export interface ProgramDetail  { id: number; name: string; description?: string }
export interface ProgramCreateInput { name: string; description?: string }
export interface ProgramUpdateInput { name?: string; description?: string }

// api/program.ts
listPrograms(query?: ProgramQuery): Promise<HydraCollection<ProgramList>>
getProgram(id: number): Promise<ProgramDetail>
createProgram(data: ProgramCreateInput): Promise<ProgramDetail>
updateProgram(id: number, data: ProgramUpdateInput): Promise<ProgramDetail>
deleteProgram(id: number): Promise<void>
```

### 3.2 Groupes de sérialisation différents par opération

Utilisez `normalizationContext` et `denormalizationContext` sur chaque opération pour exposer des propriétés différentes selon le contexte.

```php
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['exercise_log:list']],
        ),
        new Get(
            normalizationContext: ['groups' => ['exercise_log:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['exercise_log:create']],
            normalizationContext:   ['groups' => ['exercise_log:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['exercise_log:update']],
            normalizationContext:   ['groups' => ['exercise_log:read']],
        ),
        new Put(
            denormalizationContext: ['groups' => ['exercise_log:replace']],
            normalizationContext:   ['groups' => ['exercise_log:read']],
        ),
        new Delete(),
    ]
)]
class ExerciseLog
{
    #[Groups(['exercise_log:list', 'exercise_log:read'])]
    private ?int $id = null;

    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:replace'])]
    private string $exerciseName = '';

    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:update', 'exercise_log:replace'])]
    private int $sets = 1;

    // Champ lecture seule — jamais dans les inputs
    #[Groups(['exercise_log:list', 'exercise_log:read'])]
    private string $status = 'pending';

    // Champ écriture seule — jamais dans les outputs
    #[Groups(['exercise_log:create'])]
    private ?string $internalNote = null;
}
```

**Résultat :** `ExerciseLogList`, `ExerciseLogDetail`, `ExerciseLogCreateInput`, `ExerciseLogUpdateInput`, `ExerciseLogReplaceInput` — chacun avec exactement les propriétés de ses groupes.

### 3.3 Identifiant UUID

```php
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class NutritionLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['nutrition_log:read', 'nutrition_log:list'])]
    private ?Uuid $id = null;
}
```

**Résultat :** le paramètre `id` est typé `string` dans toutes les fonctions.

```typescript
getNutritionLog(id: string): Promise<NutritionLogDetail>
updateNutritionLog(id: string, data: NutritionLogUpdateInput): Promise<NutritionLogDetail>
```

### 3.4 BackedEnum comme propriété

Les enums PHP `BackedEnum` sont automatiquement convertis en union de literals TypeScript :

```php
// src/Enum/NutritionGoalEnum.php
enum NutritionGoalEnum: string
{
    case WEIGHT_LOSS  = 'weight_loss';
    case MUSCLE_GAIN  = 'muscle_gain';
    case MAINTENANCE  = 'maintenance';
    case PERFORMANCE  = 'performance';
}

// Dans l'entité
#[ORM\Column(enumType: NutritionGoalEnum::class, nullable: true)]
#[Groups(['nutrition_log:list', 'nutrition_log:read', 'nutrition_log:create'])]
private ?NutritionGoalEnum $goal = null;
```

**Résultat :** `goal?: 'weight_loss' | 'muscle_gain' | 'maintenance' | 'performance'`

### 3.5 Relations entre entités

#### Relation ManyToOne — IRI par défaut

Par défaut, une relation est sérialisée comme une IRI (`string`) :

```php
#[ORM\ManyToOne(targetEntity: User::class)]
#[Groups(['workout:list', 'workout:read'])]
private ?User $user = null;
```

**Résultat :** `user?: string` (ex. `/api/users/42`)

#### Relation inlinée — objet imbriqué

Pour qu'une relation soit inlinée, les groupes de l'opération courante doivent être présents sur les propriétés de l'entité cible :

```php
// Dans Exercise
#[ORM\ManyToMany(targetEntity: MuscleGroup::class)]
#[Groups(['exercise:read'])]
private Collection $muscleGroups;

// Dans MuscleGroup — même groupe = inlinée
#[Groups(['exercise:read'])] private ?int $id = null;
#[Groups(['exercise:read'])] private string $name = '';
```

**Résultat :** `muscleGroups: MuscleGroupDetail[]` avec import automatique depuis `./MuscleGroup`.

#### Relation auto-référentielle

```php
#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
#[Groups(['goal:read'])]
private ?Goal $parent = null;

#[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
#[Groups(['goal:read'])]
private Collection $children;
```

**Résultat :**

```typescript
export interface GoalDetail {
  parent?: string         // IRI (ManyToOne non inlinée)
  children?: GoalDetail[] // Référence récursive typée correctement
}
```

### 3.6 Opérations personnalisées

#### GET avec URI custom et DTO de sortie explicite

```php
new Get(
    uriTemplate: '/api/exercise_logs/{id}/analytics',
    name: 'exercise_log_analytics',
    output: ExerciseLogAnalyticsOutput::class,
),
```

**Résultat :** `analyticsExerciseLog(id: number): Promise<ExerciseLogAnalyticsOutput>`

#### POST signal-only — sans body

Un POST utilisateur (avec `name:` + `uriTemplate:`) **sans** `input:` explicite ne génère pas de paramètre `data` :

```php
new Post(
    uriTemplate: '/api/exercise_logs/{id}/complete',
    name: 'exercise_log_complete',
    normalizationContext: ['groups' => ['exercise_log:read']],
),
```

**Résultat :** `completeExerciseLog(id: number): Promise<ExerciseLogDetail>`

#### POST sans réponse — `output: false`

```php
new Post(
    uriTemplate: '/api/nutrition_logs/batch',
    name: 'nutrition_log_batch',
    input: NutritionLogBatchInput::class,
    output: false,
),
```

**Résultat :** `batchNutritionLog(data: NutritionLogBatchInput): Promise<void>`

#### GET collection custom avec DTO

```php
new GetCollection(
    uriTemplate: '/api/exercise_logs/summaries',
    name: 'exercise_log_summaries',
    output: ExerciseLogSummaryOutput::class,
    normalizationContext: ['groups' => ['exercise_log:summary', 'exercise_log:summary_extended']],
),
```

**Résultat :** `summariesExerciseLog(query?: ExerciseLogQuery): Promise<HydraCollection<ExerciseLogSummaryOutput>>`

---

## 4. Déclarer les DTOs

### 4.1 Output DTO sans groupes — toutes les propriétés exposées

```php
// src/Dto/Output/ExerciseLogAnalyticsOutput.php
namespace App\Dto\Output;

final class ExerciseLogAnalyticsOutput
{
    public int $totalSets = 0;
    public int $totalReps = 0;
    public float $totalVolumeKg = 0.0;
    public float $averageRepsPerSet = 0.0;
    public float $maxWeightKg = 0.0;
    public float $progressRate = 0.0;
    public ?string $trend = null;
}
```

Sur l'opération, ne pas mettre de `normalizationContext` :

```php
new Get(
    uriTemplate: '/api/exercise_logs/{id}/analytics',
    name: 'exercise_log_analytics',
    output: ExerciseLogAnalyticsOutput::class,
    // Pas de normalizationContext → toutes les props exposées
),
```

**Résultat :**

```typescript
export interface ExerciseLogAnalyticsOutput {
  totalSets: number
  totalReps: number
  totalVolumeKg: number
  averageRepsPerSet: number
  maxWeightKg: number
  progressRate: number
  trend?: string
}
```

### 4.2 Output DTO avec groupes — filtrage par opération

```php
// src/Dto/Output/ExerciseLogSummaryOutput.php
final class ExerciseLogSummaryOutput
{
    #[Groups(['exercise_log:summary'])]
    public int $exerciseLogId = 0;

    #[Groups(['exercise_log:summary'])]
    public string $exerciseName = '';

    #[Groups(['exercise_log:summary'])]
    public int $totalSets = 0;

    // Présent dans les deux groupes
    #[Groups(['exercise_log:summary', 'exercise_log:summary_extended'])]
    public float $totalVolumeKg = 0.0;

    // Seulement dans le groupe étendu
    #[Groups(['exercise_log:summary_extended'])]
    public ?float $estimatedOneRepMax = null;

    #[Groups(['exercise_log:summary_extended'])]
    public ?string $progressSinceLastSession = null;
}
```

**Même DTO utilisé avec deux normalizationContexts différents :**

```php
new Get(
    output: ExerciseLogSummaryOutput::class,
    normalizationContext: ['groups' => ['exercise_log:summary']],
),
new GetCollection(
    output: ExerciseLogSummaryOutput::class,
    normalizationContext: ['groups' => ['exercise_log:summary', 'exercise_log:summary_extended']],
),
```

**Résultat :** les deux variantes sont fusionnées, les props de la variante étendue deviennent optionnelles :

```typescript
export interface ExerciseLogSummaryOutput {
  exerciseLogId: number
  exerciseName: string
  totalSets: number
  totalVolumeKg: number
  estimatedOneRepMax?: number        // optionnel : absent du groupe basique
  progressSinceLastSession?: string  // idem
}
```

### 4.3 Input DTO sans groupes

```php
// src/Dto/Input/ExerciseLogBulkInput.php
final class ExerciseLogBulkInput
{
    /** @var int[] */
    public array $exerciseIds = [];
    public ?string $date = null;
    public ?int $workoutId = null;
    public ?string $notes = null;
}
```

```php
new Post(
    uriTemplate: '/api/exercise_logs/bulk',
    name: 'exercise_log_bulk_create',
    input: ExerciseLogBulkInput::class,
    // Pas de denormalizationContext → toutes les props acceptées
),
```

**Résultat :**

```typescript
export interface ExerciseLogBulkInput {
  exerciseIds: unknown[]
  date?: string
  workoutId?: number
  notes?: string
}
```

### 4.4 DTOs imbriqués — value objects

Les classes PHP non-entité et non-enum dans `App\` sont automatiquement détectées et générées inline dans le fichier de la ressource parente :

```php
// src/Dto/Output/Nutrition/MacroItemOutput.php
final class MacroItemOutput
{
    public float $grams = 0.0;
    public float $calories = 0.0;
    public float $percentage = 0.0;
}

// src/Dto/Output/Nutrition/MacroBreakdownOutput.php
final class MacroBreakdownOutput
{
    public MacroItemOutput $carbs;
    public MacroItemOutput $protein;
    public MacroItemOutput $fat;
    public float $totalCalories = 0.0;

    /** @var MacroItemOutput[] */
    public array $byMeal = [];
}
```

**Résultat dans `NutritionLog.ts` :**

```typescript
// Pas d'import externe — généré dans le même fichier
export interface MacroBreakdownOutput {
  carbs: MacroItemOutput    // typage correct, pas 'string'
  protein: MacroItemOutput
  fat: MacroItemOutput
  totalCalories: number
  byMeal: MacroItemOutput[]
}

export interface MacroItemOutput {
  grams: number
  calories: number
  percentage: number
}
```

### 4.5 Champs write-only et read-only

```php
/** Read-only: calculé côté serveur, jamais accepté en input */
#[Groups(['nutrition_log:read'])]
private ?float $nutritionScore = null;

/** Write-only: accepté à la création, jamais retourné */
#[Groups(['nutrition_log:create'])]
private ?string $externalFoodId = null;
```

- `nutritionScore` → dans `NutritionLogDetail`, absent de tous les `*Input`
- `externalFoodId` → dans `NutritionLogCreateInput`, absent de `NutritionLogDetail`

---

## 5. Lancer la génération

```bash
# Génération standard
php bin/console app:generate-nuxt-interface

# Avec chemin de sortie personnalisé
php bin/console app:generate-nuxt-interface --output=/chemin/vers/nuxt/generated

# Sans nettoyer le dossier avant génération
php bin/console app:generate-nuxt-interface --no-clean
```

---

## 6. Structure des fichiers générés

```
generated/
└── generated/
    ├── index.ts                       # Export global (tout est ré-exporté ici)
    ├── types/
    │   ├── index.ts
    │   ├── Program.ts                 # Types par ressource
    │   ├── ExerciseLog.ts
    │   ├── NutritionLog.ts
    │   └── ...
    ├── queries/
    │   ├── index.ts
    │   ├── Program.ts                 # Types de query params (pagination, filtres)
    │   └── ...
    ├── api/
    │   ├── index.ts
    │   ├── program.ts                 # Fonctions API par ressource
    │   ├── exerciselog.ts
    │   └── ...
    ├── composables/
    │   ├── index.ts
    │   ├── useProgramCollection.ts    # Composable liste + pagination
    │   ├── useProgramResource.ts      # Composable CRUD item unique
    │   ├── useSave.ts                 # Composable générique create/update
    │   ├── useApiError.ts             # Composable helpers erreur
    │   └── ...
    └── core/
        ├── index.ts
        ├── fetcher.ts                 # Client HTTP (fetch natif)
        ├── hydra.ts                   # Types + helpers Hydra JSON-LD
        ├── apiError.ts                # Types + helpers erreur API
        └── types.ts                   # Interface ApiFetcher
```

### Configurer l'alias dans Nuxt 4

> **Nuxt 4** : le `srcDir` est `app/` par défaut. L'alias `~/` pointe donc sur `<rootDir>/app/`, pas sur la racine du projet. Les fichiers générés étant en dehors de `app/`, il faut déclarer un alias dédié avec un chemin absolu.

Dans `nuxt.config.ts` :

```typescript
import { resolve } from 'node:path'

export default defineNuxtConfig({
  // Alias pointant sur le dossier generated (en dehors de app/)
  alias: {
    '#api': resolve(__dirname, './generated/generated'),
  },
})
```

Puis dans vos fichiers TypeScript/Vue :

```typescript
// Import via l'alias
import { listPrograms, createProgram } from '#api'
import type { ProgramDetail } from '#api'

// Ou import ciblé pour les tree-shaking
import { listPrograms } from '#api/api/program'
import type { ProgramDetail } from '#api/types/Program'
```

Pour l'autocompletion TypeScript, ajouter dans `tsconfig.json` (ou `app/tsconfig.json`) :

```json
{
  "compilerOptions": {
    "paths": {
      "#api": ["./generated/generated/index.ts"],
      "#api/*": ["./generated/generated/*"]
    }
  }
}
```

---

## 7. Types TypeScript

### 7.1 Convention de nommage

| Opération API Platform | Type TypeScript | Exemple |
|------------------------|-----------------|---------|
| `GetCollection` | `{Resource}List` | `ProgramList` |
| `Get` (item) | `{Resource}Detail` | `ProgramDetail` |
| `Post` | `{Resource}CreateInput` | `ProgramCreateInput` |
| `Patch` | `{Resource}UpdateInput` | `ProgramUpdateInput` |
| `Put` | `{Resource}ReplaceInput` | `ProgramReplaceInput` |
| Query params | `{Resource}Query` | `ProgramQuery` |
| DTO explicite (`output:`) | Nom exact de la classe | `ExerciseLogAnalyticsOutput` |

### 7.2 Mappage PHP → TypeScript

| Type PHP | Type TypeScript |
|----------|----------------|
| `int`, `integer` | `number` |
| `float`, `double` | `number` |
| `string` | `string` |
| `bool`, `boolean` | `boolean` |
| `\DateTimeImmutable`, `\DateTime` | `string` |
| `Symfony\Component\Uid\Uuid` | `string` |
| `Symfony\Component\Uid\Ulid` | `string` |
| `BackedEnum` string | `'val1' \| 'val2' \| ...` |
| `BackedEnum` int | `1 \| 2 \| 3` |
| Relation IRI | `string` |
| Relation inlinée (entité) | `{Entity}Detail` |
| DTO value object imbriqué | `{DtoClassName}` |
| `array<T>` | `T[]` |
| `array<string, T>` | `Record<string, T>` |
| `?T` nullable | champ `?:` ou `T \| null` |
| `mixed`, inconnu | `unknown` |

### 7.3 Déduplication

Quand deux opérations produisent des types avec exactement les mêmes propriétés, le générateur crée un alias :

```typescript
export type ProgramList = ProgramDetail               // Si List === Detail
export type ExerciseLogReplaceInput = ExerciseLogCreateInput  // Si Replace === Create
```

### 7.4 Import

> **Nuxt 4** : les fichiers générés ne sont pas dans `app/`, ils ne sont donc **pas** auto-importés par Nuxt. Tous les imports doivent être explicites via l'alias `#api`.

```typescript
// Import via l'index global
import type { ProgramDetail, ExerciseLogDetail } from '#api'

// Import ciblé (meilleur tree-shaking)
import type { ProgramDetail } from '#api/types/Program'
import type { ExerciseLogAnalyticsOutput } from '#api/types/ExerciseLog'
```

---

## 8. Fonctions API

### 8.1 Nommage des fonctions

| Opération | Pattern | Exemple |
|-----------|---------|---------|
| `GetCollection` | `list{Resources}` | `listPrograms` |
| `Get` | `get{Resource}` | `getProgram` |
| `Post` | `create{Resource}` | `createProgram` |
| `Patch` | `update{Resource}` | `updateProgram` |
| `Put` | `replace{Resource}` | `replaceProgram` |
| `Delete` | `delete{Resource}` | `deleteProgram` |
| Custom (segment URI) | `{segment}{Resource}` | `analyticsExerciseLog` |

### 8.2 Signatures générées

```typescript
// Standard CRUD
listPrograms(query?: ProgramQuery): Promise<HydraCollection<ProgramList>>
getProgram(id: number): Promise<ProgramDetail>
createProgram(data: ProgramCreateInput): Promise<ProgramDetail>
updateProgram(id: number, data: ProgramUpdateInput): Promise<ProgramDetail>
replaceProgram(id: number, data: ProgramReplaceInput): Promise<ProgramDetail>
deleteProgram(id: number): Promise<void>

// UUID identifier → string
getNutritionLog(id: string): Promise<NutritionLogDetail>

// DTO de sortie explicite
analyticsExerciseLog(id: number): Promise<ExerciseLogAnalyticsOutput>

// Signal-only (pas de body)
completeExerciseLog(id: number): Promise<ExerciseLogDetail>
achieveGoal(id: number): Promise<GoalDetail>

// output: false → Promise<void>
batchNutritionLog(data: NutritionLogBatchInput): Promise<void>

// Collection custom avec DTO
summariesExerciseLog(query?: ExerciseLogQuery): Promise<HydraCollection<ExerciseLogSummaryOutput>>
```

### 8.3 Exemples d'utilisation directe

```typescript
import {
  listPrograms, getProgram, createProgram,
  updateProgram, deleteProgram
} from '#api'

// Collection avec pagination
const collection = await listPrograms({ page: 1, itemsPerPage: 20 })
const items = collection['hydra:member']
const total = collection['hydra:totalItems']

// Récupérer un item
const program = await getProgram(42)

// Créer
const created = await createProgram({ name: 'Mon programme' })
console.log(created.id)

// Modifier (PATCH — mise à jour partielle)
await updateProgram(42, { name: 'Nouveau nom' })

// Remplacer (PUT — mise à jour complète)
await replaceProgram(42, { name: 'Autre nom', description: '...' })

// Supprimer
await deleteProgram(42)

// Custom avec DTO
const analytics = await analyticsExerciseLog(7)
console.log(analytics.totalVolumeKg)

// Signal-only
await completeExerciseLog(7)

// Sans réponse
await batchNutritionLog({ entries: [...], date: '2024-01-15', overwriteExisting: false })
```

### 8.4 Gestion des erreurs bas niveau

```typescript
import { getProgram } from '#api'
import { formatApiError } from '#api/core/apiError'

try {
  const program = await getProgram(42)
} catch (err) {
  const error = formatApiError(err)
  // error.status     → 404, 422, 500...
  // error.title      → "Not Found"
  // error.detail     → message lisible
  // error.violations → [{ propertyPath, message }] pour les erreurs de validation
}
```

---

## 9. Composables collection

Un composable `use{Resource}Collection` est généré pour chaque ressource ayant une opération `GetCollection`. Il gère état, pagination et filtres.

### 9.1 Interface complète

```typescript
interface UseProgramCollectionOptions {
  defaultQuery?: ProgramQuery    // Query initiale (défaut: { page: 1, itemsPerPage: 30 })
  autoFetch?: boolean            // Refetch automatiquement à chaque changement de query
  immediate?: boolean            // Fetch dès la création du composable
}

interface PaginationInfo {
  currentPage: number
  totalItems: number
  totalPages: number
  itemsPerPage: number
  hasNextPage: boolean
  hasPreviousPage: boolean
}

// Retour du composable
{
  // État réactif (Ref)
  items: Ref<ProgramList[]>
  raw: Ref<HydraCollection<ProgramList> | null>   // Réponse Hydra brute
  query: Ref<ProgramQuery>
  pending: Ref<boolean>
  error: Ref<ApiError | null>

  // Pagination (ComputedRef)
  pagination: ComputedRef<PaginationInfo | null>

  // Méthodes (toutes async et awaitable)
  fetch(): Promise<void>
  refresh(): Promise<void>                              // alias de fetch
  setPage(page: number): Promise<void>                 // remet page, auto-fetch si activé
  setItemsPerPage(n: number): Promise<void>            // remet page à 1, auto-fetch
  patchQuery(partial: Partial<ProgramQuery>): Promise<void>   // merge + remet page à 1
  replaceQuery(query: ProgramQuery): Promise<void>     // remplace tout
  resetQuery(): Promise<void>                          // revient à defaultQuery
  remove(id: number | string): Promise<void>           // delete + refresh
}
```

### 9.2 Utilisation basique — liste avec pagination

```vue
<script setup lang="ts">
import { useProgramCollection } from '#api'

const programs = useProgramCollection({
  defaultQuery: { page: 1, itemsPerPage: 20 },
  autoFetch: true,
  immediate: true,
})
</script>

<template>
  <div>
    <div v-if="programs.pending.value">Chargement...</div>
    <div v-if="programs.error.value" class="error">
      {{ programs.error.value.detail }}
    </div>

    <ul>
      <li v-for="p in programs.items.value" :key="p.id">
        {{ p.name }}
        <button @click="programs.remove(p.id!)">Supprimer</button>
      </li>
    </ul>

    <!-- Pagination -->
    <div v-if="programs.pagination.value" class="pagination">
      <button
        :disabled="!programs.pagination.value.hasPreviousPage"
        @click="programs.setPage(programs.pagination.value!.currentPage - 1)"
      >Précédent</button>

      <span>
        Page {{ programs.pagination.value.currentPage }} /
        {{ programs.pagination.value.totalPages }}
        ({{ programs.pagination.value.totalItems }} résultats)
      </span>

      <button
        :disabled="!programs.pagination.value.hasNextPage"
        @click="programs.setPage(programs.pagination.value!.currentPage + 1)"
      >Suivant</button>
    </div>
  </div>
</template>
```

### 9.3 Recherche et filtres

```vue
<script setup lang="ts">
import { useProgramCollection } from '#api'

const programs = useProgramCollection({
  autoFetch: true,
  immediate: true,
})

// Recherche textuelle (si le filtre est déclaré sur l'entité)
async function search(term: string) {
  // patchQuery reset automatiquement à la page 1
  await programs.patchQuery({ 'name': term })
}

// Tri
async function sortBy(field: string, dir: 'asc' | 'desc') {
  await programs.patchQuery({ [`order[${field}]`]: dir })
}

// Reset tous les filtres
async function reset() {
  await programs.resetQuery()
}
</script>
```

### 9.4 Contrôle manuel — SSR / useAsyncData

> **Nuxt 4** : `useAsyncData` et `useFetch` s'exécutent côté serveur au premier rendu puis côté client lors des navigations. Pour le SSR, ne pas utiliser `immediate: true` — utiliser `useAsyncData` à la place.

```typescript
// Option A : via useAsyncData (recommandé SSR)
import { listPrograms } from '#api/api/program'
import { getItems, getTotalItems } from '#api/core/hydra'

const { data, pending, error } = await useAsyncData(
  'programs',
  () => listPrograms({ page: 1, itemsPerPage: 20 })
)

const items = computed(() => data.value ? getItems(data.value) : [])
const total = computed(() => data.value ? getTotalItems(data.value) : 0)


// Option B : composable avec contrôle manuel (client-side uniquement)
import { useProgramCollection } from '#api'

const programs = useProgramCollection({ autoFetch: false })

// Appel explicite — sur onMounted ou en réponse à un événement
onMounted(() => programs.fetch())


// Option C : composable dans useAsyncData
const programs = useProgramCollection({ autoFetch: false })

const { pending } = await useAsyncData('programs-composable', () => programs.fetch())
// programs.items.value est maintenant rempli côté serveur et client
```

---

## 10. Composables resource

Un composable `use{Resource}Resource` est généré pour chaque ressource. Il gère le CRUD sur un item unique.

### 10.1 Interface complète

```typescript
{
  // État réactif
  item: Ref<ProgramDetail | null>
  pending: Ref<boolean>
  error: Ref<ApiError | null>

  // Navigation
  fetch(id: number | string): Promise<void>
  refresh(): Promise<void>    // recharge le même id

  // Mutations (générées seulement si l'opération existe)
  create(input: ProgramCreateInput): Promise<ProgramDetail | null>
  update(id: number | string, input: ProgramUpdateInput): Promise<ProgramDetail | null>
  replace(id: number | string, input: ProgramReplaceInput): Promise<ProgramDetail | null>
  remove(id: number | string): Promise<void>

  // Réinitialisation
  clear(): void    // vide item, currentId et error
}
```

> Les méthodes `create`, `update`, `replace`, `remove` ne sont présentes que si l'opération correspondante est définie sur la ressource.

### 10.2 Chargement d'un item

> **Nuxt 4** : pour le SSR, utiliser `useAsyncData` ou `callOnce`. Utiliser `watch` avec `{ immediate: true }` uniquement en client-side.

```vue
<script setup lang="ts">
import { useProgramResource } from '#api'

const route = useRoute()
const program = useProgramResource()

// Option SSR-safe : useAsyncData
const { pending } = await useAsyncData(
  `program-${route.params.id}`,
  () => program.fetch(Number(route.params.id))
)

// Option client-only : watch avec immediate
// watch(() => route.params.id, id => program.fetch(Number(id)), { immediate: true })
</script>

<template>
  <div v-if="program.pending.value">Chargement...</div>
  <div v-else-if="program.error.value">{{ program.error.value.detail }}</div>
  <div v-else-if="program.item.value">
    <h1>{{ program.item.value.name }}</h1>
  </div>
</template>
```

### 10.3 Formulaire de création/modification

```vue
<script setup lang="ts">
import { useProgramResource } from '#api'
import type { ProgramCreateInput } from '#api'

// navigateTo et useRoute sont auto-importés par Nuxt 4
const props = defineProps<{ id?: number }>()
const resource = useProgramResource()

const form = reactive<ProgramCreateInput>({ name: '', description: '' })

// Pré-remplir si modification (SSR-safe avec useAsyncData)
if (props.id) {
  await useAsyncData(`program-edit-${props.id}`, () => resource.fetch(props.id!))
  watch(resource.item, item => {
    if (item) { form.name = item.name; form.description = item.description ?? '' }
  }, { immediate: true })
}

async function submit() {
  const result = props.id
    ? await resource.update(props.id, form)
    : await resource.create(form)

  if (result) await navigateTo(`/programs/${result.id}`)
  // En cas d'erreur : resource.error.value est rempli
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.name" required />
    <textarea v-model="form.description" />
    <p v-if="resource.error.value?.status === 422">
      Erreur de validation
    </p>
    <button :disabled="resource.pending.value">
      {{ resource.pending.value ? 'Enregistrement...' : 'Enregistrer' }}
    </button>
  </form>
</template>
```

### 10.4 Suppression

```typescript
const resource = useProgramResource()

async function remove(id: number) {
  if (!confirm('Supprimer ?')) return
  await resource.remove(id)
  if (!resource.error.value) navigateTo('/programs')
}
```

---

## 11. Composable useSave

Composable générique pour les formulaires CRUD avec lifecycle hooks, gestion des violations et détection automatique create/update.

### 11.1 Interface

```typescript
// Options
interface UseSaveOptions<TCreate, TUpdate, TReplace, TOutput> {
  createFn?: (input: TCreate) => Promise<TOutput>
  updateFn?: (id: string | number, input: TUpdate) => Promise<TOutput>
  replaceFn?: (id: string | number, input: TReplace) => Promise<TOutput>

  onSuccess?: (entity: TOutput, ctx: SaveContext) => void | Promise<void>
  onError?: (error: ApiError, ctx: SaveContext) => void | Promise<void>
  onFinally?: (ctx: SaveContext) => void | Promise<void>

  onCreateSuccess?: (entity: TOutput) => void | Promise<void>
  onUpdateSuccess?: (entity: TOutput) => void | Promise<void>
  onReplaceSuccess?: (entity: TOutput) => void | Promise<void>
}

interface SaveContext {
  operation: 'create' | 'update' | 'replace'
  id?: string | number | null
  input: unknown
}

// Retour
{
  saving: Ref<boolean>
  error: Ref<ApiError | null>
  data: Ref<TOutput | null>

  violations: ComputedRef<ApiViolation[]>
  hasViolation(field: string): boolean
  getViolation(field: string): string | null
  getViolations(field: string): string[]

  create(input: TCreate): Promise<TOutput | null>
  update(id: string | number, input: TUpdate): Promise<TOutput | null>
  replace(id: string | number, input: TReplace): Promise<TOutput | null>
  save(id: string | number | null, input, opts?: { replace?: boolean }): Promise<TOutput | null>
  clearError(): void
  reset(): void
}
```

### 11.2 Logique de `save()` — résolution automatique

```
save(id, input, opts):
  id === null + createFn     → create(input)
  id !== null + replace:true → replace(id, input)
  id !== null + updateFn     → update(id, input)
  sinon                      → throw Error
```

### 11.3 Formulaire avec violations de validation

```vue
<script setup lang="ts">
import { useSave } from '#api'
import { createProgram, updateProgram } from '#api'
import type { ProgramCreateInput } from '#api'

const props = defineProps<{ programId?: number }>()

const form = reactive<ProgramCreateInput>({ name: '', description: '' })

const { save, saving, error, hasViolation, getViolation, violations } = useSave({
  createFn: createProgram,
  updateFn: updateProgram,
  onSuccess: (program, ctx) => {
    navigateTo(`/programs/${program.id}`)
  },
})

const submit = () => save(props.programId ?? null, form)
</script>

<template>
  <form @submit.prevent="submit">
    <div>
      <input v-model="form.name" />
      <!-- Violation de validation pour ce champ -->
      <span v-if="hasViolation('name')" class="error">
        {{ getViolation('name') }}
      </span>
    </div>

    <!-- Toutes les violations -->
    <ul v-if="violations.value.length">
      <li v-for="v in violations.value" :key="v.propertyPath">
        {{ v.propertyPath }}: {{ v.message }}
      </li>
    </ul>

    <!-- Erreur non-validation -->
    <div v-if="error.value && !violations.value.length" class="error">
      {{ error.value.detail }}
    </div>

    <button :disabled="saving.value">Enregistrer</button>
  </form>
</template>
```

### 11.4 Lifecycle hooks avancés

```typescript
const { save } = useSave({
  createFn: createProgram,
  updateFn: updateProgram,

  onSuccess: async (program, ctx) => {
    await queryClient.invalidateQueries(['programs'])
    toast.success(ctx.operation === 'create' ? 'Créé !' : 'Mis à jour !')
  },

  onError: (err, ctx) => {
    if (err.status === 409) toast.error('Conflit — rechargez la page')
    logger.error({ operation: ctx.operation, status: err.status })
  },

  onCreateSuccess: (program) => {
    // Spécifique à la création
    analytics.track('program_created', { id: program.id })
  },
})
```

---

## 12. Composable useApiError

Transforme une `ApiError` en computed properties pour simplifier les templates.

### 12.1 Interface

```typescript
function useApiError(error: ApiError | null): {
  hasError: ComputedRef<boolean>
  errorMessage: ComputedRef<string>           // detail || title
  violations: ComputedRef<ApiViolation[]>
  getViolation(path: string): string | null
  hasViolation(path: string): boolean
  isValidationError: ComputedRef<boolean>     // status === 422
  isNotFoundError: ComputedRef<boolean>       // status === 404
  isUnauthorizedError: ComputedRef<boolean>   // status === 401
  isForbiddenError: ComputedRef<boolean>      // status === 403
}
```

### 12.2 Utilisation

```vue
<script setup lang="ts">
import { useApiError } from '#api'

const resource = useProgramResource()
// Recalculé à chaque changement de l'erreur
const err = computed(() => useApiError(resource.error.value))
</script>

<template>
  <div v-if="err.value.isValidationError.value">
    <p v-for="v in err.value.violations.value" :key="v.propertyPath">
      {{ v.propertyPath }}: {{ v.message }}
    </p>
  </div>
  <div v-else-if="err.value.isNotFoundError.value">
    Ressource introuvable
  </div>
  <div v-else-if="err.value.isUnauthorizedError.value">
    <NuxtLink to="/login">Connectez-vous</NuxtLink>
  </div>
  <div v-else-if="err.value.hasError.value">
    {{ err.value.errorMessage.value }}
  </div>
</template>
```

---

## 13. Helpers Hydra (core)

### 13.1 Types

```typescript
interface HydraCollection<T> {
  '@type': 'hydra:Collection'
  'hydra:member': T[]              // Items de la page
  'hydra:totalItems': number       // Total global
  'hydra:view'?: HydraView         // Liens first/last/prev/next
  'hydra:search'?: HydraSearch     // Filtres disponibles (IriTemplate)
}
```

### 13.2 Fonctions

```typescript
import {
  getItems,        // Extrait hydra:member
  getTotalItems,   // Retourne hydra:totalItems
  hasNextPage,     // Vérifie hydra:view.hydra:next
  hasPreviousPage, // Vérifie hydra:view.hydra:previous
  getTotalPages,   // Math.ceil(total / itemsPerPage)
  getCurrentPage,  // Extrait ?page= de hydra:view.@id
} from '#api/core/hydra'

const collection = await listPrograms({ page: 2, itemsPerPage: 10 })

const items      = getItems(collection)           // ProgramList[]
const total      = getTotalItems(collection)      // number
const hasNext    = hasNextPage(collection)        // boolean
const hasPrev    = hasPreviousPage(collection)    // boolean
const pages      = getTotalPages(collection, 10)  // number
const current    = getCurrentPage(collection)     // number
```

---

## 14. Fetcher et personnalisation

### 14.1 Comportement par défaut

Le fetcher généré utilise `fetch` natif avec :

- `Content-Type: application/ld+json`
- `Accept: application/ld+json`
- `credentials: 'include'` (configurable)
- Gestion automatique 204 No Content → `undefined`
- Parse JSON de la réponse d'erreur Hydra

### 14.2 Remplacer par `$fetch` Nuxt 4

> **Nuxt 4** : les plugins sont dans `app/plugins/` et sont auto-importés. `$fetch` reste disponible globalement.

```typescript
// app/plugins/api-fetcher.ts
import { apiFetcher } from '#api/core/fetcher'
import type { ApiFetcher, ApiFetcherOptions } from '#api/core/types'

class NuxtFetcher implements ApiFetcher {
  async get<T>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return $fetch<T>(path, {
      method: 'GET',
      params: options?.query,
      headers: { Accept: 'application/ld+json', ...options?.headers },
    })
  }
  async post<T>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return $fetch<T>(path, {
      method: 'POST',
      body: options?.body,
      headers: { 'Content-Type': 'application/ld+json', ...options?.headers },
    })
  }
  async patch<T>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return $fetch<T>(path, {
      method: 'PATCH',
      body: options?.body,
      // PATCH JSON Merge Patch — requis par API Platform
      headers: { 'Content-Type': 'application/merge-patch+json', ...options?.headers },
    })
  }
  async put<T>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return $fetch<T>(path, {
      method: 'PUT',
      body: options?.body,
      headers: { 'Content-Type': 'application/ld+json', ...options?.headers },
    })
  }
  async delete<T>(path: string, options?: ApiFetcherOptions): Promise<T> {
    return $fetch<T>(path, { method: 'DELETE' }) as Promise<T>
  }
}

export default defineNuxtPlugin(() => {
  Object.assign(apiFetcher, new NuxtFetcher())
})
```

### 14.3 Injection de token JWT

```typescript
// app/plugins/auth-fetcher.ts
import { apiFetcher } from '#api/core/fetcher'

export default defineNuxtPlugin(() => {
  const auth = useAuthStore()

  const withAuth = (original: Function) =>
    (path: string, options?: any) =>
      original(path, {
        ...options,
        headers: {
          Authorization: `Bearer ${auth.token}`,
          ...options?.headers,
        },
      })

  apiFetcher.get    = withAuth(apiFetcher.get.bind(apiFetcher))
  apiFetcher.post   = withAuth(apiFetcher.post.bind(apiFetcher))
  apiFetcher.patch  = withAuth(apiFetcher.patch.bind(apiFetcher))
  apiFetcher.put    = withAuth(apiFetcher.put.bind(apiFetcher))
  apiFetcher.delete = withAuth(apiFetcher.delete.bind(apiFetcher))
})
```

---

## 15. Règles de nommage

### 15.1 Fonctions — URI canonique vs personnalisée

Le générateur distingue :

- **URI canonique** : `/api/{resources}` (collection) ou `/api/{resources}/{id}` (item) → nommage standard (`listPrograms`, `getProgram`)
- **URI non canonique** : tout autre URI → segment sémantique extrait (`analyticsExerciseLog`, `completeExerciseLog`)

### 15.2 Exemples de noms dérivés de l'URI

| URI custom | Nom généré |
|------------|-----------|
| `/api/exercise_logs/{id}/analytics` | `analyticsExerciseLog` |
| `/api/exercise_logs/{id}/complete` | `completeExerciseLog` |
| `/api/exercise_logs/{id}/summary` | `summaryExerciseLog` |
| `/api/exercise_logs/summaries` | `summariesExerciseLog` |
| `/api/exercise_logs/bulk` | `bulkCreateExerciseLog` |
| `/api/nutrition_logs/{id}/macros` | `macrosNutritionLog` |
| `/api/nutrition_logs/batch` | `batchNutritionLog` |
| `/api/goals/{id}/achieve` | `achieveGoal` |
| `/api/goals/{id}/tree` | `treeGoal` |

### 15.3 Types avec DTO explicite

Le type prend le nom exact de la classe PHP (sans namespace) :

| Classe PHP | Type TypeScript |
|------------|----------------|
| `App\Dto\Output\ExerciseLogAnalyticsOutput` | `ExerciseLogAnalyticsOutput` |
| `App\Dto\Input\ExerciseLogBulkInput` | `ExerciseLogBulkInput` |
| `App\Dto\Output\Nutrition\MacroBreakdownOutput` | `MacroBreakdownOutput` |

---

## 16. Cas particuliers et corner cases

### 16.1 Deux POST avec des URIs différentes — pas de doublon

```php
new Post(),                                         // → createExerciseLog
new Post(uriTemplate: '/api/exercise_logs/bulk'),   // → bulkCreateExerciseLog
```

Le générateur détecte l'URI non canonique et déduit le nom depuis l'URI.

### 16.2 Même DTO, deux groupes différents — fusion

```php
new Get(output: SummaryOutput::class, normalizationContext: ['groups' => ['summary']]),
new GetCollection(output: SummaryOutput::class, normalizationContext: ['groups' => ['summary', 'extended']]),
```

→ Un seul type `SummaryOutput` avec les props de `extended` marquées `?` (optionnel).

### 16.3 Signal-only POST — sans body

Tout POST défini par l'utilisateur (`name:` + `uriTemplate:`) **sans** `input:` explicite est traité comme signal-only. Pas de paramètre `data`.

```php
new Post(uriTemplate: '/api/goals/{id}/achieve', name: 'goal_achieve')
// → achieveGoal(id: number): Promise<GoalDetail>
```

### 16.4 `output: false` — Promise<void>

```php
new Post(output: false, input: MyInput::class)
// → myOperation(data: MyInput): Promise<void>
```

### 16.5 UUID — path param toujours `string`

Les paramètres de chemin ne sont jamais nullables : `string`, pas `string | null`.

### 16.6 BackedEnum — union de literals

Aucune configuration : les enums PHP `BackedEnum` sont automatiquement mappés.

```php
enum Status: string { case ACTIVE = 'active'; case DONE = 'done'; }
```

→ `status: 'active' | 'done'`

### 16.7 DTOs value objects imbriqués

Toute classe PHP dans `App\` qui n'est ni entité Doctrine, ni enum, ni ressource API Platform est traitée comme value object. Son type TypeScript est généré **inline** dans le fichier de la ressource qui l'utilise, sans import externe.

### 16.8 Relations auto-référentielles

```typescript
export interface GoalDetail {
  parent?: string          // ManyToOne → IRI string
  children?: GoalDetail[]  // OneToMany → référence récursive avec Detail suffix
}
```

### 16.9 Champs write-only filtrés des outputs

Un champ avec uniquement des groupes de dénormalisation (`create`, `write`) n'apparaît jamais dans les types de sortie (`Detail`, `List`).

### 16.10 Relations inlinées vs IRI — détection automatique

La décision IRI vs objet se fait par comparaison des groupes : si les groupes de l'opération courante apparaissent sur les propriétés de l'entité cible → inlinée (`MuscleGroupDetail[]`), sinon IRI (`string`).

Les relations inlinées utilisent automatiquement le type `Detail` de la ressource cible et génèrent l'import correspondant.
