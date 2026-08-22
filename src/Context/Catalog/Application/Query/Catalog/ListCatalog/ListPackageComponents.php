<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPackageComponents implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $packageId,
    ) {
    }
}
