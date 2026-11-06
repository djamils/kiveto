<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\Shared\Application\Bus\QueryInterface;

/**
 * G3 « Télécharger »: where a kept source file of an import is ('amm' or
 * 'dict'), and the name to give it. Null when the import, the file or its
 * copy in the archive is gone.
 */
final readonly class GetRegistryImportFile implements QueryInterface
{
    public function __construct(
        public string $importId,
        public string $kind,
        public string $source = 'ANMV',
    ) {
    }
}
