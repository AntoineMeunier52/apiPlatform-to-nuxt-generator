# API Platform → Nuxt Generator

Générateur de client TypeScript à partir des ressources API Platform (Symfony). Il produit automatiquement les types, les fonctions d'API et les composables prêts à l'emploi dans Nuxt.

## Principe

```
Entités PHP + #[ApiResource]  →  php bin/console app:generate-nuxt-interface  →  generated/
```

Le générateur lit les métadonnées API Platform (opérations, groupes de sérialisation, filtres) et génère un client TypeScript typé sans configuration manuelle.

## Ce qui est généré

Pour chaque ressource API Platform, le générateur produit :

| Dossier | Contenu |
|---|---|
| `generated/types/` | Interfaces TypeScript (`ProgramDetail`, `ProgramList`, `ProgramCreateInput`, …) |
| `generated/queries/` | Types de filtres/pagination (`ProgramQuery`) |
| `generated/api/` | Fonctions async (`getPrograms`, `createProgram`, `deleteProgram`, …) |
| `generated/composables/` | Composables Vue (`useProgramCollection`, `useProgramResource`, `useSave`, `useApiError`) |
| `generated/core/` | Utilitaires partagés (fetcher, helpers Hydra, types d'erreur) |

## Utilisation

### 1. Créer une ressource API Platform

```php
#[ApiResource(
    operations: [new GetCollection(), new Get(), new Post(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['program:read']],
    denormalizationContext: ['groups' => ['program:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
class Program
{
    #[Groups(['program:read'])]
    private ?int $id = null;

    #[Groups(['program:read', 'program:write'])]
    private ?string $name = null;
}
```

### 2. Lancer la génération

```bash
php bin/console app:generate-nuxt-interface
# ou avec un chemin custom :
php bin/console app:generate-nuxt-interface --output=/path/to/nuxt/app
```

### 3. Utiliser dans Nuxt

```typescript
// Importer depuis le point d'entrée unique
import { getPrograms, createProgram } from '~/generated'
import type { ProgramList, ProgramCreateInput, ProgramQuery } from '~/generated'
import { getItems, getTotalItems } from '~/generated/core/hydra'
```

**Lecture (SSR) :**
```vue
<script setup lang="ts">
const { data: collection } = await useAsyncData('programs', () =>
  getPrograms({ page: 1, itemsPerPage: 20, 'order[createdAt]': 'desc' })
)
const programs = computed(() => getItems(collection.value))
</script>
```

**Composable collection :**
```vue
<script setup lang="ts">
import { useProgramCollection } from '~/generated'

const { items, isLoading, totalItems, setPage } = useProgramCollection({ itemsPerPage: 20 })
</script>
```

**Mutation avec gestion d'erreur :**
```vue
<script setup lang="ts">
import { createProgram } from '~/generated'
import { useSave, useApiError } from '~/generated'

const { save, isLoading, error } = useSave(createProgram)
const apiError = useApiError(error)

async function submit() {
  const result = await save({ name: 'Mon programme' })
  if (result) navigateTo(`/programs/${result.id}`)
}
</script>

<template>
  <span v-if="apiError.hasViolation('name')">{{ apiError.getViolation('name') }}</span>
</template>
```

## Configuration

`config/packages/nuxt_generator.yaml` :

```yaml
nuxt_generator:
    output_path: '%env(NUXT_GENERATOR_OUTPUT)%'
    defaults:
        clean_before_generate: true
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
```

Variable d'environnement `.env` :
```
NUXT_GENERATOR_OUTPUT=/chemin/vers/projet/nuxt
```

## Conventions importantes

- **Groupes de sérialisation** : le générateur respecte exactement les `normalizationContext`/`denormalizationContext`
- **L'ID** doit être uniquement dans le groupe lecture (`entity:read`), jamais en écriture
- **Relations inlinées** = type complet généré ; relations sans groupe = type `string` (IRI)
- **Filtres** (`#[ApiFilter]`) → propriétés du type `Query` TypeScript correspondant

## Commandes de debug

```bash
# Inspecter les métadonnées extraites d'une ressource
php bin/console app:debug-extractor Program

# Inspecter la normalisation
php bin/console app:debug-normalizer Program
```

## Documentation détaillée

Voir [GENERATOR_GUIDE.md](GENERATOR_GUIDE.md) pour des exemples complets (entités, DTOs, SSR, pagination, gestion d'erreurs).
