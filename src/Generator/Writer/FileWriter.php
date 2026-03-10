<?php

declare(strict_types=1);

namespace App\Generator\Writer;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Service d'écriture de fichiers avec régénération safe
 *
 * - Écrit uniquement dans le dossier generated/
 * - Écriture atomique (temp file + rename)
 * - Génération idempotente
 */
class FileWriter
{
    private const GENERATED_DIR = 'generated';

    private string $nuxtProjectPath = '';
    private bool $initialized = false;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Initialise le writer avec le chemin de sortie (racine Nuxt)
     *
     * @param string $nuxtProjectPath Chemin absolu vers le projet Nuxt
     *
     * @throws \InvalidArgumentException Si le chemin n'existe pas ou n'est pas accessible
     */
    public function initialize(string $nuxtProjectPath): void
    {
        $this->validateOutputPath($nuxtProjectPath);
        $this->nuxtProjectPath = rtrim($nuxtProjectPath, '/');
        $this->initialized = true;
    }

    /**
     * Nettoie uniquement le dossier generated (jamais les fichiers utilisateur)
     */
    public function cleanGeneratedDirectory(): void
    {
        $this->ensureInitialized();

        $generatedPath = $this->getGeneratedPath();

        if ($this->filesystem->exists($generatedPath)) {
            $this->filesystem->remove($generatedPath);
        }

        $this->filesystem->mkdir($generatedPath);
    }

    /**
     * Écriture atomique d'un fichier
     *
     * @param string $relativePath Chemin relatif depuis generated/ (ex: "core/client.ts")
     * @param string $content Contenu du fichier
     */
    public function write(string $relativePath, string $content): void
    {
        $this->ensureInitialized();

        $fullPath = $this->getFullPath($relativePath);

        // Créer le dossier parent si nécessaire
        $this->filesystem->mkdir(\dirname($fullPath));

        // Écriture atomique (temp file + rename)
        $tempFile = $fullPath . '.tmp.' . uniqid('', true);
        $this->filesystem->dumpFile($tempFile, $content);
        $this->filesystem->rename($tempFile, $fullPath, true);
    }

    /**
     * Génération idempotente (ne réécrit que si différent)
     *
     * @return bool True si le fichier a été écrit, false si inchangé
     */
    public function writeIfChanged(string $relativePath, string $content): bool
    {
        $this->ensureInitialized();

        $fullPath = $this->getFullPath($relativePath);

        if ($this->filesystem->exists($fullPath)) {
            $existing = file_get_contents($fullPath);
            if ($existing === $content) {
                return false;
            }
        }

        $this->write($relativePath, $content);

        return true;
    }

    /**
     * Retourne le chemin complet vers le dossier generated
     */
    public function getGeneratedPath(): string
    {
        $this->ensureInitialized();

        return $this->nuxtProjectPath . '/' . self::GENERATED_DIR;
    }

    /**
     * Vérifie si un fichier existe dans le dossier generated
     */
    public function exists(string $relativePath): bool
    {
        $this->ensureInitialized();

        return $this->filesystem->exists($this->getFullPath($relativePath));
    }

    /**
     * Résout un chemin relatif depuis generated/ vers un chemin absolu
     */
    private function getFullPath(string $relativePath): string
    {
        return $this->getGeneratedPath() . '/' . ltrim($relativePath, '/');
    }

    /**
     * Valide que le chemin de sortie existe et est accessible
     */
    private function validateOutputPath(string $path): void
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(
                \sprintf('Output path "%s" does not exist or is not a directory.', $path)
            );
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException(
                \sprintf('Output path "%s" is not writable.', $path)
            );
        }
    }

    /**
     * Vérifie que le writer a été initialisé
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw new \LogicException('FileWriter must be initialized with initialize() before use.');
        }
    }
}
