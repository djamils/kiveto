<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Query\GetCashDesk\CashClosingView;
use App\Context\Billing\Application\Query\GetCashDesk\ChequeView;
use App\Context\Billing\Application\Query\GetCashDesk\TakingsView;
use App\Context\Billing\Application\Query\GetChequeDeposit\ChequeDepositView;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;

/**
 * Read side of the till: what came in and went out, the cheques still in the
 * drawer, the remittances and the closed days.
 */
interface CashDeskReadRepositoryInterface
{
    /**
     * Cheques taken and not deposited yet, oldest first.
     *
     * @return list<ChequeView>
     */
    public function pendingCheques(ClinicId $clinicId): array;

    /**
     * Payments received and refunds given back over a period, per method.
     */
    public function takings(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): TakingsView;

    public function chequeDeposit(ClinicId $clinicId, ChequeDepositId $depositId): ?ChequeDepositView;

    public function closingFor(ClinicId $clinicId, string $businessDate): ?CashClosingView;

    public function closingCount(ClinicId $clinicId): int;
}
