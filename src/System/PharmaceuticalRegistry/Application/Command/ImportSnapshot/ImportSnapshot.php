<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ImportSnapshot implements CommandInterface
{
    public function __construct(
        public string $source,
        public string $filePath,
        public string $dictionaryPath,
    ) {
    }
}
