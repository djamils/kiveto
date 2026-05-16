<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetImportHistory;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetImportHistory implements QueryInterface
{
    public function __construct(
        public ?string $source,
        public int $limit = 20,
    ) {
    }
}
