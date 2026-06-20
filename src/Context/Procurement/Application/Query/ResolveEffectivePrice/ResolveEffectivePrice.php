<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ResolveEffectivePrice;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ResolveEffectivePrice implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $entryId,
        public string $referenceDate,
    ) {
    }
}
