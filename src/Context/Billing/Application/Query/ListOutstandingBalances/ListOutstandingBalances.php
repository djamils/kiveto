<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListOutstandingBalances;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What each client of a clinic still owes, for the directory's balance column
 * and its « Solde impayé » filter. Answers client id => minor units, debtors only.
 */
final readonly class ListOutstandingBalances implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
