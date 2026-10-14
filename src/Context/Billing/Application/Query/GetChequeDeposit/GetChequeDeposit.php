<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetChequeDeposit;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetChequeDeposit implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $depositId,
    ) {
    }
}
