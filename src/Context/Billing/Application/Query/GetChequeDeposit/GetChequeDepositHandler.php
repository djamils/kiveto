<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetChequeDeposit;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetChequeDepositHandler
{
    public function __construct(private CashDeskReadRepositoryInterface $cashDesk)
    {
    }

    /**
     * @throws \DomainException when the remittance is not this clinic's
     */
    public function __invoke(GetChequeDeposit $query): ChequeDepositView
    {
        $deposit = $this->cashDesk->chequeDeposit(
            ClinicId::fromString($query->clinicId),
            ChequeDepositId::fromString($query->depositId),
        );

        if (null === $deposit) {
            throw new \DomainException(\sprintf('Cheque deposit %s not found.', $query->depositId));
        }

        return $deposit;
    }
}
