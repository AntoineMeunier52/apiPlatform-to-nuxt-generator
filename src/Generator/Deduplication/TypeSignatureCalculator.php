<?php

declare(strict_types=1);

namespace App\Generator\Deduplication;

use App\Generator\DTO\Output\GeneratedType;
use App\Generator\DTO\Output\TypeProperty;

/**
 * Calculates unique signatures for types to enable deduplication.
 *
 * Two types with identical signatures can share the same definition,
 * with one being an alias of the other.
 *
 * Example:
 * - ProgramDetail and ProgramReplaceInput might have identical properties
 * - We can generate: type ProgramReplaceInput = ProgramDetail
 */
readonly class TypeSignatureCalculator
{
    /**
     * Calculates a signature string for a type.
     *
     * The signature is based on:
     * - Property names
     * - Property types
     * - Property optionality
     * - Property array status
     *
     * Order-independent (properties are sorted alphabetically).
     */
    public function calculateSignature(GeneratedType $type): string
    {
        $properties = $this->sortPropertiesByName($type->properties);
        $signatureParts = [];

        foreach ($properties as $property) {
            $signatureParts[] = $this->calculatePropertySignature($property);
        }

        return implode('|', $signatureParts);
    }

    /**
     * Calculates a signature for a single property.
     */
    private function calculatePropertySignature(TypeProperty $property): string
    {
        $optional = $property->isOptional ? '?' : '';
        $array = $property->isArray ? '[]' : '';

        return "{$property->name}:{$optional}{$property->type}{$array}";
    }

    /**
     * Sorts properties by name for consistent signatures.
     *
     * @param TypeProperty[] $properties
     * @return TypeProperty[]
     */
    private function sortPropertiesByName(array $properties): array
    {
        $sorted = $properties;
        usort($sorted, fn(TypeProperty $a, TypeProperty $b) => $a->name <=> $b->name);
        return $sorted;
    }

    /**
     * Checks if two types have the same signature.
     */
    public function haveSameSignature(GeneratedType $type1, GeneratedType $type2): bool
    {
        return $this->calculateSignature($type1) === $this->calculateSignature($type2);
    }

    /**
     * Calculates a hash of the signature for faster comparison.
     */
    public function calculateHash(GeneratedType $type): string
    {
        return md5($this->calculateSignature($type));
    }
}
