<?php

declare(strict_types=1);

namespace App\Generator\Deduplication;

use App\Generator\DTO\Output\GeneratedType;
use App\Generator\DTO\Output\TypeAlias;

/**
 * Deduplicates types by identifying identical type definitions
 * and creating aliases for duplicates.
 *
 * Example result:
 * - export interface ProgramDetail { id: number; name: string }
 * - export type ProgramReplaceInput = ProgramDetail
 */
readonly class TypeDeduplicator
{
    public function __construct(
        private TypeSignatureCalculator $signatureCalculator,
    ) {}

    /**
     * Deduplicates an array of types.
     *
     * Returns an array with:
     * - 'types': GeneratedType[] - Unique types to generate as full definitions
     * - 'aliases': TypeAlias[] - Duplicate types to generate as type aliases
     *
     * @param GeneratedType[] $types
     * @return array{types: GeneratedType[], aliases: TypeAlias[]}
     */
    public function deduplicate(array $types): array
    {
        $uniqueTypes = [];
        $aliases = [];
        $signatureMap = []; // signature hash => type name

        foreach ($types as $type) {
            $signature = $this->signatureCalculator->calculateHash($type);

            if (isset($signatureMap[$signature])) {
                // Duplicate found - create alias
                $canonicalTypeName = $signatureMap[$signature];

                $aliases[] = new TypeAlias(
                    aliasName: $type->name,
                    targetName: $canonicalTypeName,
                );
            } else {
                // New unique type
                $uniqueTypes[] = $type;
                $signatureMap[$signature] = $type->name;
            }
        }

        return [
            'types' => $uniqueTypes,
            'aliases' => $aliases,
        ];
    }

    /**
     * Deduplicates types with a priority system.
     *
     * Prefers certain type names as canonical (e.g., Detail over Input).
     *
     * @param GeneratedType[] $types
     * @return array{types: GeneratedType[], aliases: TypeAlias[]}
     */
    public function deduplicateWithPriority(array $types): array
    {
        $uniqueTypes = [];
        $aliases = [];
        $signatureMap = []; // signature hash => GeneratedType

        foreach ($types as $type) {
            $signature = $this->signatureCalculator->calculateHash($type);

            if (isset($signatureMap[$signature])) {
                // Duplicate found
                $existingType = $signatureMap[$signature];

                // Determine which should be canonical based on priority
                if ($this->getTypePriority($type) > $this->getTypePriority($existingType)) {
                    // New type has higher priority - make it canonical
                    $aliases[] = new TypeAlias(
                        aliasName: $existingType->name,
                        targetName: $type->name,
                    );

                    // Update map
                    $signatureMap[$signature] = $type;

                    // Replace in uniqueTypes
                    $uniqueTypes = array_filter(
                        $uniqueTypes,
                        fn(GeneratedType $t) => $t->name !== $existingType->name
                    );
                    $uniqueTypes[] = $type;
                } else {
                    // Existing type has higher priority - keep it canonical
                    $aliases[] = new TypeAlias(
                        aliasName: $type->name,
                        targetName: $existingType->name,
                    );
                }
            } else {
                // New unique type
                $uniqueTypes[] = $type;
                $signatureMap[$signature] = $type;
            }
        }

        return [
            'types' => array_values($uniqueTypes),
            'aliases' => $aliases,
        ];
    }

    /**
     * Determines type priority for canonical selection.
     *
     * Higher priority = more likely to be canonical.
     */
    private function getTypePriority(GeneratedType $type): int
    {
        $name = $type->name;

        // Prefer output types over input types
        if (!$type->isInput) {
            $priority = 100;
        } else {
            $priority = 50;
        }

        // Prefer shorter names
        $priority -= strlen($name);

        // Prefer "Detail" suffix
        if (str_ends_with($name, 'Detail')) {
            $priority += 20;
        }

        // Prefer "List" suffix
        if (str_ends_with($name, 'List')) {
            $priority += 15;
        }

        // Deprioritize "Input" suffix
        if (str_ends_with($name, 'Input')) {
            $priority -= 10;
        }

        return $priority;
    }

    /**
     * Groups types by signature.
     *
     * Useful for debugging and analysis.
     *
     * @param GeneratedType[] $types
     * @return array<string, GeneratedType[]> signature hash => types
     */
    public function groupBySignature(array $types): array
    {
        $groups = [];

        foreach ($types as $type) {
            $signature = $this->signatureCalculator->calculateHash($type);

            if (!isset($groups[$signature])) {
                $groups[$signature] = [];
            }

            $groups[$signature][] = $type;
        }

        return $groups;
    }

    /**
     * Finds duplicate types (multiple types with same signature).
     *
     * @param GeneratedType[] $types
     * @return array<string, GeneratedType[]>
     */
    public function findDuplicates(array $types): array
    {
        $groups = $this->groupBySignature($types);

        return array_filter($groups, fn(array $group) => count($group) > 1);
    }
}
