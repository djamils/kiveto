<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Act\GetActDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetActDetail implements QueryInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
    ) {
    }
}
