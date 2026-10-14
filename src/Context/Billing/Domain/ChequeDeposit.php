<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain;

use App\Context\Billing\Domain\Exception\InvalidChequeDepositException;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * « Remise de chèques »: the cheques taken at the desk, handed to the bank in
 * one go with their remittance slip.
 *
 * A cheque is a payment of an invoice; depositing it does not change the
 * invoice, only where the cheque is. Each cheque goes to the bank once.
 */
final class ChequeDeposit extends AggregateRoot
{
    /**
     * @param list<PaymentId> $paymentIds
     */
    private function __construct(
        private readonly ChequeDepositId $id,
        private readonly ClinicId $clinicId,
        private readonly array $paymentIds,
        private readonly Money $total,
        private readonly \DateTimeImmutable $depositedAt,
        private readonly ?string $depositedByUserId,
    ) {
    }

    /**
     * @param list<PaymentId> $paymentIds
     */
    public static function record(
        ChequeDepositId $id,
        ClinicId $clinicId,
        array $paymentIds,
        Money $total,
        \DateTimeImmutable $now,
        ?string $depositedByUserId = null,
    ): self {
        if ([] === $paymentIds) {
            throw new InvalidChequeDepositException('A cheque deposit needs at least one cheque.');
        }

        $unique = array_unique(array_map(static fn (PaymentId $id): string => $id->toString(), $paymentIds));

        if (\count($unique) !== \count($paymentIds)) {
            throw new InvalidChequeDepositException('A cheque is deposited only once.');
        }

        if (!$total->isPositive()) {
            throw new InvalidChequeDepositException('A cheque deposit must carry a positive total.');
        }

        return new self($id, $clinicId, $paymentIds, $total, $now, $depositedByUserId);
    }

    /**
     * @param list<PaymentId> $paymentIds
     */
    public static function reconstitute(
        ChequeDepositId $id,
        ClinicId $clinicId,
        array $paymentIds,
        Money $total,
        \DateTimeImmutable $depositedAt,
        ?string $depositedByUserId,
    ): self {
        return new self($id, $clinicId, $paymentIds, $total, $depositedAt, $depositedByUserId);
    }

    public function id(): ChequeDepositId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    /**
     * @return list<PaymentId>
     */
    public function paymentIds(): array
    {
        return $this->paymentIds;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function depositedAt(): \DateTimeImmutable
    {
        return $this->depositedAt;
    }

    public function depositedByUserId(): ?string
    {
        return $this->depositedByUserId;
    }
}
