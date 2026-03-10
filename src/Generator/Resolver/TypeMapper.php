<?php

declare(strict_types=1);

namespace App\Generator\Resolver;

/**
 * Service de mapping des types PHP vers TypeScript
 */
readonly class TypeMapper
{
    /**
     * Mapping des types scalaires PHP vers TypeScript
     */
    private const TYPE_MAP = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'unknown[]',
        'object' => 'Record<string, unknown>',
        'mixed' => 'unknown',
        'null' => 'null',
        'void' => 'void',
        'true' => 'true',
        'false' => 'false',
        'never' => 'never',
    ];

    /**
     * Types qui sont mappés vers string (dates, etc.)
     */
    private const STRING_TYPES = [
        'DateTime',
        'DateTimeInterface',
        'DateTimeImmutable',
        '\DateTime',
        '\DateTimeInterface',
        '\DateTimeImmutable',
        'Uuid',
        'Ulid',
        'Ramsey\Uuid\Uuid',
        'Ramsey\Uuid\UuidInterface',
        'Symfony\Component\Uid\Uuid',
        'Symfony\Component\Uid\Ulid',
    ];

    /**
     * Mappe un type PHP vers TypeScript
     */
    public function mapPhpTypeToTypeScript(string $phpType): string
    {
        // Nettoyer le type
        $phpType = trim($phpType);

        // Gérer la nullabilité (préfixe ?)
        if (str_starts_with($phpType, '?')) {
            $innerType = substr($phpType, 1);

            return $this->mapPhpTypeToTypeScript($innerType) . ' | null';
        }

        // Gérer les unions (Type1|Type2)
        if (str_contains($phpType, '|')) {
            $types = explode('|', $phpType);
            $tsTypes = array_map(fn ($t) => $this->mapPhpTypeToTypeScript(trim($t)), $types);

            return implode(' | ', array_unique($tsTypes));
        }

        // Types scalaires
        if (isset(self::TYPE_MAP[$phpType])) {
            return self::TYPE_MAP[$phpType];
        }

        // Types mappés vers string (dates, UUID, etc.)
        foreach (self::STRING_TYPES as $stringType) {
            if ($phpType === $stringType || str_ends_with($phpType, '\\' . $stringType)) {
                return 'string';
            }
        }

        // Tableaux typés (array<string, int> ou Type[])
        if (preg_match('/^array<(.+),\s*(.+)>$/', $phpType, $matches)) {
            $valueType = $this->mapPhpTypeToTypeScript(trim($matches[2]));

            return "Record<string, {$valueType}>";
        }

        if (preg_match('/^array<(.+)>$/', $phpType, $matches)) {
            $valueType = $this->mapPhpTypeToTypeScript(trim($matches[1]));

            return "{$valueType}[]";
        }

        if (str_ends_with($phpType, '[]')) {
            $innerType = substr($phpType, 0, -2);

            return $this->mapPhpTypeToTypeScript($innerType) . '[]';
        }

        // Collections Doctrine
        if ($this->isDoctrineCollection($phpType)) {
            // Par défaut, les collections de relations sont des IRIs
            return 'string[]';
        }

        // Enums PHP (BackedEnum)
        if ($this->isBackedEnum($phpType)) {
            return $this->mapEnumToTypeScript($phpType);
        }

        // Classes inconnues -> traiter comme objet
        if (class_exists($phpType) || interface_exists($phpType)) {
            // Si c'est une entité API Platform, ce sera un IRI
            return 'string';
        }

        // Fallback
        return 'unknown';
    }

    /**
     * Vérifie si le type est une collection Doctrine
     */
    public function isDoctrineCollection(string $phpType): bool
    {
        $collectionTypes = [
            'Collection',
            'ArrayCollection',
            'Doctrine\Common\Collections\Collection',
            'Doctrine\Common\Collections\ArrayCollection',
        ];

        foreach ($collectionTypes as $collectionType) {
            if ($phpType === $collectionType || str_contains($phpType, $collectionType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si le type est un BackedEnum
     */
    public function isBackedEnum(string $phpType): bool
    {
        if (!class_exists($phpType) && !interface_exists($phpType)) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($phpType);

            return $reflection->isEnum() && $reflection->implementsInterface(\BackedEnum::class);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Mappe un BackedEnum vers TypeScript
     */
    public function mapEnumToTypeScript(string $enumClass): string
    {
        try {
            $reflection = new \ReflectionEnum($enumClass);
            $cases = $reflection->getCases();

            $values = [];
            foreach ($cases as $case) {
                /** @var \ReflectionEnumBackedCase $case */
                $backingValue = $case->getBackingValue();
                if (\is_string($backingValue)) {
                    $values[] = "'{$backingValue}'";
                } else {
                    $values[] = (string) $backingValue;
                }
            }

            return implode(' | ', $values);
        } catch (\ReflectionException) {
            return 'string';
        }
    }

    /**
     * Retourne le nom court d'une classe
     */
    public function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return array_pop($parts);
    }
}
