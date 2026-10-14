<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetChequeDeposit;

use App\Context\Billing\Application\Query\GetCashDesk\ChequeView;

/**
 * A remittance of cheques, as its slip prints it.
 */
final readonly class ChequeDepositView
{
    /**
     * @param list<ChequeView> $cheques
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $depositedAt,
        public int $totalMinorUnits,
        public string $currency,
        public array $cheques,
    ) {
    }
}
