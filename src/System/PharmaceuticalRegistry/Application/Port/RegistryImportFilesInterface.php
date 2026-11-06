<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

/**
 * The files of the next import (storage/pharma-registry/<source>/current/).
 */
interface RegistryImportFilesInterface
{
    /**
     * The registry file and its dictionary, when both are there.
     *
     * @return array{file: string, dictionary: string, modifiedAt: \DateTimeImmutable}|null
     */
    public function current(string $source): ?array;

    /** Moves the files imported to the archive. */
    public function archive(string $source, \DateTimeImmutable $at): void;
}
