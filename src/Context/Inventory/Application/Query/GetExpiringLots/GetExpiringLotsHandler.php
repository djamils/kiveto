<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetExpiringLots;

use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetExpiringLotsHandler
{
    public function __construct(
        private readonly StockItemReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<ExpiringLotView>
     */
    public function __invoke(GetExpiringLots $query): array
    {
        return $this->readRepository->findLotsExpiringWithin(
            ClinicId::fromString($query->clinicId),
            $query->withinDays,
        );
    }
}
