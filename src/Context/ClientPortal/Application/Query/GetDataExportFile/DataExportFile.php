<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetDataExportFile;

final readonly class DataExportFile
{
    public function __construct(
        public string $exportId,
        public string $storageKey,
    ) {
    }
}
