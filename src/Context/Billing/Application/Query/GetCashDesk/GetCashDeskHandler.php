<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCashDeskHandler
{
    public function __construct(private CashDeskReadRepositoryInterface $cashDesk)
    {
    }

    public function __invoke(GetCashDesk $query): CashDeskView
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        return new CashDeskView(
            takings: $this->cashDesk->takings($clinicId, $query->dayStart, $query->dayEnd),
            pendingCheques: $this->cashDesk->pendingCheques($clinicId),
            closing: $this->cashDesk->closingFor($clinicId, $query->businessDate),
            closingCount: $this->cashDesk->closingCount($clinicId),
        );
    }
}
