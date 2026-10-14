<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

/**
 * The till of one business day: what it took and gave back, the cheques
 * waiting for the bank, and whether the day is closed.
 */
final readonly class CashDeskView
{
    /**
     * @param list<ChequeView> $pendingCheques
     */
    public function __construct(
        public TakingsView $takings,
        public array $pendingCheques,
        public ?CashClosingView $closing,
        public int $closingCount,
    ) {
    }

    public function pendingChequesTotal(): int
    {
        return array_sum(array_map(static fn (ChequeView $cheque): int => $cheque->amountMinorUnits, $this->pendingCheques));
    }
}
