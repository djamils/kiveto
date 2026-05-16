<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle;

final readonly class RunFullImportCycle
{
    public function __construct(
        public string $source,
        public string $filePath,
        public string $dictionaryPath,
    ) {
    }
}
