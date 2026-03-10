# Guide complet : Créer une ressource API Platform et l'utiliser dans Nuxt

Ce guide vous montre **exactement** comment créer une entité complète côté Symfony et l'utiliser côté Nuxt (SSR et client-side).

---

## 📋 Table des matières

1. [Côté Backend : Créer l'entité](#backend)
2. [Côté Backend : Créer le DTO (optionnel)](#dto)
3. [Générer l'interface TypeScript](#generation)
4. [Côté Frontend : Utilisation sans SSR](#frontend-client)
5. [Côté Frontend : Utilisation avec SSR](#frontend-ssr)
6. [Exemples complets](#exemples)

---

## <a name="backend"></a>🔧 Côté Backend : Créer l'entité

### ✅ Exemple complet : Entité `Program`

```php
<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    // Opérations disponibles
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
        // Opération custom : publier un programme
        new Post(
            uriTemplate: '/programs/{id}/publish',
            name: 'publish',
            normalizationContext: ['groups' => ['program:read']],
        ),
    ],
    // ✅ IMPORTANT : Définir les groupes par défaut
    normalizationContext: ['groups' => ['program:read']],
    denormalizationContext: ['groups' => ['program:write']],
    // Pagination
    paginationEnabled: true,
    paginationItemsPerPage: 30,
)]
// ✅ IMPORTANT : Ajouter les filtres pour générer les query types
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'description' => 'partial'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
class Program
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    // ✅ L'ID est toujours en lecture seule
    #[Groups(['program:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    // ✅ En lecture ET en écriture
    #[Groups(['program:read', 'program:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    // ✅ Champ optionnel (nullable)
    #[Groups(['program:read', 'program:write'])]
    private ?string $description = null;

    #[ORM\Column]
    // ✅ Lecture seule (calculé automatiquement)
    #[Groups(['program:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['program:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // ✅ Relation OneToMany
    #[ORM\OneToMany(targetEntity: ProgramWeek::class, mappedBy: 'program', cascade: ['persist', 'remove'])]
    // ✅ IMPORTANT : Ajouter un groupe sur la relation pour l'inliner
    #[Groups(['program:read'])]
    private Collection $weeks;

    // ✅ Relation ManyToOne
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    // ✅ Sans groupe = sera retourné en IRI uniquement ("/api/users/123")
    // ✅ Avec groupe = sera inliné ({ id: 123, name: "John" })
    #[Groups(['program:read'])]
    private ?User $author = null;

    #[ORM\Column]
    #[Groups(['program:read'])]
    private bool $published = false;

    public function __construct()
    {
        $this->weeks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ✅ Getters et setters standards
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, ProgramWeek>
     */
    public function getWeeks(): Collection
    {
        return $this->weeks;
    }

    public function addWeek(ProgramWeek $week): self
    {
        if (!$this->weeks->contains($week)) {
            $this->weeks->add($week);
            $week->setProgram($this);
        }
        return $this;
    }

    public function removeWeek(ProgramWeek $week): self
    {
        if ($this->weeks->removeElement($week)) {
            if ($week->getProgram() === $this) {
                $week->setProgram(null);
            }
        }
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;
        return $this;
    }
}
```

### ⚠️ Ce qu'il faut faire et ne PAS faire

#### ✅ À FAIRE

1. **Toujours ajouter `#[ApiResource]`** sur l'entité
2. **Définir les groupes de sérialisation** (`normalizationContext` et `denormalizationContext`)
3. **Ajouter `#[Groups]`** sur CHAQUE propriété que vous voulez exposer
4. **Utiliser les filtres** (`#[ApiFilter]`) pour générer les query types
5. **L'ID doit être en `program:read`** uniquement (lecture seule)
6. **Les dates auto doivent être en `program:read`** uniquement
7. **Ajouter des validations** (`#[Assert]`) sur les champs éditables

#### ❌ À NE PAS FAIRE

1. ❌ **NE PAS** mettre l'ID dans `program:write` (il est auto-généré)
2. ❌ **NE PAS** mettre les timestamps (`createdAt`, `updatedAt`) dans `program:write`
3. ❌ **NE PAS** oublier les groupes sur les relations (sinon = IRI uniquement)
4. ❌ **NE PAS** utiliser des types complexes non supportés (objets custom, etc.)
5. ❌ **NE PAS** oublier `nullable: true` dans `#[ORM\Column]` si le champ est optionnel

---

## <a name="dto"></a>📦 Côté Backend : Créer un DTO (optionnel)

Si vous voulez des types input différents de votre entité, utilisez des DTOs.

### Exemple : DTO pour la création

```php
<?php

namespace App\DTO;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ProgramCreateDTO
{
    #[Groups(['program:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    public ?string $name = null;

    #[Groups(['program:write'])]
    public ?string $description = null;

    // ✅ Vous pouvez accepter un tableau de semaines
    #[Groups(['program:write'])]
    public array $weeks = [];
}
```

Puis dans votre `#[ApiResource]` :

```php
#[ApiResource(
    operations: [
        new Post(
            input: ProgramCreateDTO::class,
            output: Program::class,
        ),
    ],
)]
```

⚠️ **Note** : Les DTOs sont optionnels. Si vous n'en avez pas besoin, utilisez directement l'entité.

---

## <a name="generation"></a>⚙️ Générer l'interface TypeScript

Une fois votre entité créée :

```bash
# 1. Créer la migration
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# 2. Générer l'interface TypeScript
php bin/console app:generate-nuxt-interface
```

### Résultat généré

Pour l'entité `Program` ci-dessus, le générateur crée :

#### `generated/types/Program.ts`

```typescript
/**
 * AUTO-GENERATED FILE - DO NOT EDIT
 */

export interface ProgramDetail {
  id: number
  name: string
  description?: string
  createdAt: string
  updatedAt: string
  weeks: ProgramWeek[]
  author: User
  published: boolean
}

export interface ProgramList {
  id: number
  name: string
  description?: string
  createdAt: string
  published: boolean
}

export interface ProgramCreateInput {
  name: string
  description?: string
}

export interface ProgramUpdateInput {
  name?: string
  description?: string
}

export type ProgramReplaceInput = ProgramCreateInput
```

#### `generated/queries/Program.ts`

```typescript
export interface ProgramQuery {
  name?: string
  description?: string
  'createdAt[before]'?: string
  'createdAt[strictly_before]'?: string
  'createdAt[after]'?: string
  'createdAt[strictly_after]'?: string
  'order[name]'?: 'asc' | 'desc'
  'order[createdAt]'?: 'asc' | 'desc'
  page?: number
  itemsPerPage?: number
}
```

#### `generated/api/program.ts`

```typescript
export async function getPrograms(query?: ProgramQuery): Promise<HydraCollection<ProgramList>> {
  return apiFetcher.get('/api/programs', { query })
}

export async function getProgram(id: number): Promise<ProgramDetail> {
  return apiFetcher.get(`/api/programs/${id}`)
}

export async function createProgram(data: ProgramCreateInput): Promise<ProgramDetail> {
  return apiFetcher.post('/api/programs', { body: data })
}

export async function updateProgram(id: number, data: ProgramUpdateInput): Promise<ProgramDetail> {
  return apiFetcher.patch(`/api/programs/${id}`, { body: data })
}

export async function replaceProgram(id: number, data: ProgramReplaceInput): Promise<ProgramDetail> {
  return apiFetcher.put(`/api/programs/${id}`, { body: data })
}

export async function deleteProgram(id: number): Promise<void> {
  return apiFetcher.delete(`/api/programs/${id}`)
}

export async function publishProgram(id: number): Promise<ProgramDetail> {
  return apiFetcher.post(`/api/programs/${id}/publish`)
}
```

---

## <a name="frontend-client"></a>💻 Côté Frontend : Utilisation SANS SSR (Client-side)

### Exemple 1 : Liste avec pagination

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPrograms } from '~/generated'
import type { ProgramList, ProgramQuery } from '~/generated'
import { getItems, getTotalItems, hasNextPage } from '~/generated/core/hydra'

// État
const programs = ref<ProgramList[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)
const currentPage = ref(1)
const totalItems = ref(0)
const hasNext = ref(false)

// Filtres
const filters = ref<ProgramQuery>({
  itemsPerPage: 20,
  'order[createdAt]': 'desc'
})

// Fonction de chargement
async function loadPrograms() {
  isLoading.value = true
  error.value = null

  try {
    const collection = await getPrograms({
      ...filters.value,
      page: currentPage.value
    })

    programs.value = getItems(collection)
    totalItems.value = getTotalItems(collection)
    hasNext.value = hasNextPage(collection)
  } catch (err: any) {
    error.value = err.detail || 'Erreur lors du chargement'
  } finally {
    isLoading.value = false
  }
}

// Pagination
function nextPage() {
  if (hasNext.value) {
    currentPage.value++
    loadPrograms()
  }
}

function previousPage() {
  if (currentPage.value > 1) {
    currentPage.value--
    loadPrograms()
  }
}

// Recherche
function search(searchTerm: string) {
  filters.value.name = searchTerm
  currentPage.value = 1
  loadPrograms()
}

// Chargement initial
onMounted(() => {
  loadPrograms()
})
</script>

<template>
  <div>
    <h1>Mes programmes</h1>

    <!-- Recherche -->
    <input
      type="text"
      placeholder="Rechercher..."
      @input="search($event.target.value)"
    />

    <!-- Loading -->
    <div v-if="isLoading">Chargement...</div>

    <!-- Erreur -->
    <div v-else-if="error" class="error">{{ error }}</div>

    <!-- Liste -->
    <div v-else>
      <div v-for="program in programs" :key="program.id" class="program-card">
        <h2>{{ program.name }}</h2>
        <p>{{ program.description }}</p>
        <small>Créé le {{ new Date(program.createdAt).toLocaleDateString() }}</small>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <button @click="previousPage" :disabled="currentPage === 1">
          Précédent
        </button>
        <span>Page {{ currentPage }} ({{ totalItems }} résultats)</span>
        <button @click="nextPage" :disabled="!hasNext">
          Suivant
        </button>
      </div>
    </div>
  </div>
</template>
```

### Exemple 2 : Créer un programme avec `useSave`

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { createProgram } from '~/generated'
import type { ProgramCreateInput } from '~/generated'
import { useSave } from '~/generated/composables'
import { useApiError } from '~/generated/composables'

// ✅ Utiliser useSave pour gérer loading/error automatiquement
const { save, isLoading, error, data } = useSave(createProgram)
const apiError = useApiError(error)

// Formulaire
const form = ref<ProgramCreateInput>({
  name: '',
  description: ''
})

async function handleSubmit() {
  const result = await save(form.value)

  if (result) {
    // ✅ Succès - rediriger ou afficher un message
    console.log('Programme créé avec succès:', result)
    navigateTo(`/programs/${result.id}`)
  }
}
</script>

<template>
  <div>
    <h1>Créer un programme</h1>

    <form @submit.prevent="handleSubmit">
      <!-- Nom -->
      <div>
        <label>Nom *</label>
        <input
          v-model="form.name"
          type="text"
          required
        />
        <!-- ✅ Afficher l'erreur de validation pour ce champ -->
        <span v-if="apiError.hasViolation('name')" class="error">
          {{ apiError.getViolation('name') }}
        </span>
      </div>

      <!-- Description -->
      <div>
        <label>Description</label>
        <textarea v-model="form.description" />
        <span v-if="apiError.hasViolation('description')" class="error">
          {{ apiError.getViolation('description') }}
        </span>
      </div>

      <!-- Erreur globale -->
      <div v-if="apiError.hasError.value && !apiError.isValidationError.value" class="error">
        {{ apiError.errorMessage.value }}
      </div>

      <!-- Submit -->
      <button type="submit" :disabled="isLoading">
        {{ isLoading ? 'Création...' : 'Créer' }}
      </button>
    </form>
  </div>
</template>
```

### Exemple 3 : Détail et modification

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getProgram, updateProgram, deleteProgram } from '~/generated'
import type { ProgramDetail, ProgramUpdateInput } from '~/generated'
import { useSave } from '~/generated/composables'

const route = useRoute()
const programId = parseInt(route.params.id as string)

const program = ref<ProgramDetail | null>(null)
const isLoading = ref(false)

// ✅ useSave pour la modification
const { save: saveUpdate, isLoading: isSaving, error } = useSave(
  (data: ProgramUpdateInput) => updateProgram(programId, data)
)

// Chargement
async function loadProgram() {
  isLoading.value = true
  try {
    program.value = await getProgram(programId)
  } catch (err) {
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

// Modification
async function handleUpdate() {
  if (!program.value) return

  const result = await saveUpdate({
    name: program.value.name,
    description: program.value.description
  })

  if (result) {
    console.log('Programme mis à jour')
  }
}

// Suppression
async function handleDelete() {
  if (!confirm('Êtes-vous sûr ?')) return

  try {
    await deleteProgram(programId)
    navigateTo('/programs')
  } catch (err) {
    console.error(err)
  }
}

onMounted(() => {
  loadProgram()
})
</script>

<template>
  <div v-if="isLoading">Chargement...</div>
  <div v-else-if="program">
    <h1>{{ program.name }}</h1>

    <!-- Édition -->
    <form @submit.prevent="handleUpdate">
      <input v-model="program.name" />
      <textarea v-model="program.description" />
      <button type="submit" :disabled="isSaving">
        {{ isSaving ? 'Sauvegarde...' : 'Sauvegarder' }}
      </button>
    </form>

    <!-- Semaines -->
    <h2>Semaines</h2>
    <div v-for="week in program.weeks" :key="week.id">
      {{ week.name }}
    </div>

    <!-- Suppression -->
    <button @click="handleDelete" class="danger">
      Supprimer
    </button>
  </div>
</template>
```

---

## <a name="frontend-ssr"></a>🌐 Côté Frontend : Utilisation AVEC SSR

### Exemple 1 : Liste avec SSR

```vue
<script setup lang="ts">
import { getPrograms } from '~/generated'
import type { ProgramQuery } from '~/generated'
import { getItems, getTotalItems } from '~/generated/core/hydra'

// ✅ useAsyncData pour SSR
const route = useRoute()

const page = computed(() => parseInt(route.query.page as string || '1'))

const filters = computed<ProgramQuery>(() => ({
  page: page.value,
  itemsPerPage: 20,
  name: route.query.search as string,
  'order[createdAt]': 'desc'
}))

// ✅ Les données sont chargées côté serveur
const { data: collection, pending, error, refresh } = await useAsyncData(
  'programs',
  () => getPrograms(filters.value),
  {
    // ✅ Recharger quand les filtres changent
    watch: [filters]
  }
)

const programs = computed(() => collection.value ? getItems(collection.value) : [])
const totalItems = computed(() => collection.value ? getTotalItems(collection.value) : 0)
</script>

<template>
  <div>
    <h1>Mes programmes</h1>

    <!-- Recherche -->
    <NuxtLink :to="{ query: { ...route.query, search: 'fitness' } }">
      Rechercher "fitness"
    </NuxtLink>

    <!-- Loading -->
    <div v-if="pending">Chargement...</div>

    <!-- Erreur -->
    <div v-else-if="error">Erreur</div>

    <!-- Liste -->
    <div v-else>
      <div v-for="program in programs" :key="program.id">
        <NuxtLink :to="`/programs/${program.id}`">
          <h2>{{ program.name }}</h2>
        </NuxtLink>
      </div>

      <!-- Pagination avec NuxtLink (SEO friendly) -->
      <div class="pagination">
        <NuxtLink
          v-if="page > 1"
          :to="{ query: { ...route.query, page: page - 1 } }"
        >
          Précédent
        </NuxtLink>

        <span>Page {{ page }} ({{ totalItems }} résultats)</span>

        <NuxtLink :to="{ query: { ...route.query, page: page + 1 } }">
          Suivant
        </NuxtLink>
      </div>

      <!-- Bouton refresh manuel -->
      <button @click="refresh()">Rafraîchir</button>
    </div>
  </div>
</template>
```

### Exemple 2 : Détail avec SSR

```vue
<script setup lang="ts">
import { getProgram } from '~/generated'

const route = useRoute()
const programId = parseInt(route.params.id as string)

// ✅ Chargement SSR
const { data: program, pending, error } = await useAsyncData(
  `program-${programId}`,
  () => getProgram(programId)
)

// ✅ SEO : definePageMeta
definePageMeta({
  title: computed(() => program.value?.name || 'Programme'),
  description: computed(() => program.value?.description || '')
})
</script>

<template>
  <div v-if="pending">Chargement...</div>
  <div v-else-if="error">Erreur 404</div>
  <div v-else-if="program">
    <!-- ✅ Le HTML est généré côté serveur -->
    <h1>{{ program.name }}</h1>
    <p>{{ program.description }}</p>

    <h2>Semaines ({{ program.weeks.length }})</h2>
    <div v-for="week in program.weeks" :key="week.id">
      {{ week.name }}
    </div>

    <!-- ✅ Auteur (inliné grâce aux groups) -->
    <p>Auteur : {{ program.author.name }}</p>
  </div>
</template>
```

### Exemple 3 : Formulaire hybride (SSR + client)

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { createProgram, getPrograms } from '~/generated'
import type { ProgramCreateInput } from '~/generated'
import { useSave } from '~/generated/composables'
import { getItems } from '~/generated/core/hydra'

// ✅ Charger les programmes existants en SSR
const { data: collection } = await useAsyncData(
  'programs-list',
  () => getPrograms({ itemsPerPage: 10 })
)

const programs = computed(() => collection.value ? getItems(collection.value) : [])

// ✅ Formulaire côté client
const { save, isLoading, error } = useSave(createProgram)

const form = ref<ProgramCreateInput>({
  name: '',
  description: ''
})

async function handleSubmit() {
  const result = await save(form.value)

  if (result) {
    // ✅ Rafraîchir la liste après création
    refreshNuxtData('programs-list')
    // Réinitialiser le formulaire
    form.value = { name: '', description: '' }
  }
}
</script>

<template>
  <div>
    <!-- Liste SSR -->
    <h2>Programmes existants</h2>
    <ul>
      <li v-for="program in programs" :key="program.id">
        {{ program.name }}
      </li>
    </ul>

    <!-- Formulaire client -->
    <h2>Créer un nouveau programme</h2>
    <form @submit.prevent="handleSubmit">
      <input v-model="form.name" placeholder="Nom" required />
      <textarea v-model="form.description" placeholder="Description" />
      <button type="submit" :disabled="isLoading">Créer</button>
      <div v-if="error" class="error">{{ error.detail }}</div>
    </form>
  </div>
</template>
```

---

## <a name="exemples"></a>🎯 Exemples complets

### Cas d'usage 1 : Appel direct sans composable

```typescript
import { getProgram, updateProgram } from '~/generated'

// ✅ Simple et direct
try {
  const program = await getProgram(123)
  console.log(program.name)

  const updated = await updateProgram(123, { name: 'Nouveau nom' })
  console.log(updated.name)
} catch (err: any) {
  console.error(err.detail)
}
```

### Cas d'usage 2 : Avec useSave pour gérer l'état

```typescript
import { updateProgram } from '~/generated'
import { useSave } from '~/generated/composables'

const { save, isLoading, error, data } = useSave(
  (input) => updateProgram(123, input)
)

// ✅ État géré automatiquement
await save({ name: 'Nouveau nom' })

if (error.value) {
  console.error(error.value.detail)
}

if (data.value) {
  console.log('Mis à jour:', data.value)
}
```

### Cas d'usage 3 : Pagination manuelle

```typescript
import { getPrograms } from '~/generated'
import { getItems, getTotalItems, getCurrentPage, getTotalPages } from '~/generated/core/hydra'

const collection = await getPrograms({ page: 2, itemsPerPage: 20 })

const items = getItems(collection)
const total = getTotalItems(collection)
const currentPage = getCurrentPage(collection)
const totalPages = getTotalPages(collection, 20)

console.log(`Page ${currentPage}/${totalPages} - ${total} programmes`)
```

### Cas d'usage 4 : Filtres avancés

```typescript
import { getPrograms } from '~/generated'
import type { ProgramQuery } from '~/generated'

const filters: ProgramQuery = {
  name: 'fitness',
  'createdAt[after]': '2024-01-01',
  'order[name]': 'asc',
  page: 1,
  itemsPerPage: 50
}

const collection = await getPrograms(filters)
```

### Cas d'usage 5 : Gestion d'erreur avancée

```typescript
import { createProgram } from '~/generated'
import { useSave } from '~/generated/composables'
import { useApiError } from '~/generated/composables'

const { save, error } = useSave(createProgram)
const apiError = useApiError(error)

await save({ name: '' }) // ❌ Validation error

if (apiError.isValidationError.value) {
  // Erreur 422 avec violations
  apiError.violations.value.forEach(v => {
    console.log(`${v.propertyPath}: ${v.message}`)
  })
}

if (apiError.isNotFoundError.value) {
  // Erreur 404
  console.log('Ressource non trouvée')
}

if (apiError.isUnauthorizedError.value) {
  // Erreur 401
  navigateTo('/login')
}
```

---

## 📚 Résumé

### Backend ✅
- Créer l'entité avec `#[ApiResource]`
- Définir les **groupes de sérialisation**
- Ajouter les **filtres** pour les query params
- L'ID et les timestamps en **lecture seule**
- Les relations avec groupes = **inlinées**, sans groupes = **IRI**

### Frontend ✅
- **Sans SSR** : `onMounted()` + `ref()` + fonctions API
- **Avec SSR** : `useAsyncData()` + fonctions API
- **useSave** pour gérer loading/error automatiquement
- **useApiError** pour analyser les erreurs
- **Helpers Hydra** pour la pagination

### Points clés 🎯
1. Le générateur respecte **exactement** vos groupes de sérialisation
2. Utilisez `useSave` pour les mutations, pas pour les lectures
3. `useAsyncData` pour SSR, `onMounted` pour client-side
4. Les filtres API Platform → query types TypeScript automatiquement
5. Les relations inlinées = types complets, IRI = `string`

---

**Votre générateur est maintenant prêt à l'emploi !** 🚀
