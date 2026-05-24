<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Package\GetPackageDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPackageDetail implements QueryInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
    ) {
    }
}
