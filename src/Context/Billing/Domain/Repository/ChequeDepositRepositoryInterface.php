<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Repository;

use App\Context\Billing\Domain\ChequeDeposit;

interface ChequeDepositRepositoryInterface
{
    public function save(ChequeDeposit $deposit): void;
}
