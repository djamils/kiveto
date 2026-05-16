<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Domain\Snapshot;

interface RegistryImporterInterface
{
    public function stage(Snapshot $snapshot, string $filePath, string $dictionaryPath): void;
}
