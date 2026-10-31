<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** An amount in minor units, never a float. */
final readonly class MoneyView
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
    }
}
