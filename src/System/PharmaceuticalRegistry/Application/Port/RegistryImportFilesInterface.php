<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

/**
 * The files of the next import (storage/pharma-registry/<source>/current/)
 * and the archive where each import keeps its own.
 */
interface RegistryImportFilesInterface
{
    /**
     * The registry file and its dictionary, when both are there, with their
     * SHA-256 and sizes.
     *
     * @return array{file: string, dictionary: string, modifiedAt: \DateTimeImmutable, fileHash: string, fileSize: int, dictionaryHash: string, dictionarySize: int}|null
     */
    public function current(string $source): ?array;

    /**
     * Moves the files imported to the archive.
     *
     * @return array{file: string, dictionary: string} where they are kept, relative to the source's folder
     */
    public function archive(string $source, \DateTimeImmutable $at): array;

    /** The kept file at this place of the archive; null when it is gone, or not in the archive. */
    public function kept(string $source, string $storedPath): ?string;
}
