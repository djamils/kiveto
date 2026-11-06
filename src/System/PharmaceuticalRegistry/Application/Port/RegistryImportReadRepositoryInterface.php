<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryDiffLine;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;

/**
 * The imports of the registry as the console reads them: each snapshot with
 * its counts by kind of change, and the changes of one.
 */
interface RegistryImportReadRepositoryInterface
{
    /**
     * Newest first.
     *
     * @return list<RegistryImportView>
     */
    public function imports(string $source, int $limit): array;

    public function import(string $id): ?RegistryImportView;

    /**
     * @return list<RegistryDiffLine>
     */
    public function diff(string $id, ?string $kind, int $offset, int $limit): array;

    public function countDiff(string $id, ?string $kind): int;

    /** The marketing authorisations the registry holds now. */
    public function authorizationCount(): int;
}
