<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\DepositPendingCheques;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Domain\ChequeDeposit;
use App\Context\Billing\Domain\Exception\InvalidChequeDepositException;
use App\Context\Billing\Domain\Repository\ChequeDepositRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DepositPendingChequesHandler
{
    public function __construct(
        private CashDeskReadRepositoryInterface $cashDesk,
        private ChequeDepositRepositoryInterface $deposits,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DepositPendingCheques $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $cheques  = $this->cashDesk->pendingCheques($clinicId);

        if ([] === $cheques) {
            throw new InvalidChequeDepositException('There is no cheque to deposit.');
        }

        $total = 0;

        foreach ($cheques as $cheque) {
            $total += $cheque->amountMinorUnits;
        }

        $deposit = ChequeDeposit::record(
            id: ChequeDepositId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            paymentIds: array_map(static fn ($cheque): PaymentId => PaymentId::fromString($cheque->paymentId), $cheques),
            total: Money::fromMinorUnits($total, CurrencyCode::fromString($cheques[0]->currency)),
            now: $this->clock->now(),
            depositedByUserId: $command->depositedByUserId,
        );

        $this->deposits->save($deposit);

        return $deposit->id()->toString();
    }
}
