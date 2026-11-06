<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;

/**
 * The journal of the source files of the imports: which files each import
 * read, their fingerprints, where they are kept.
 */
interface RegistryImportFileLogInterface
{
    /**
     * @param list<RegistryImportFile> $files
     */
    public function record(string $snapshotId, string $source, array $files): void;

    /**
     * @param list<string> $snapshotIds
     *
     * @return array<string, list<RegistryImportFile>> by snapshot id
     */
    public function filesOf(array $snapshotIds): array;

    /** The SHA-256 of the registry file of the last import of this source that went through. */
    public function lastAppliedRegistryHash(string $source): ?string;
}
