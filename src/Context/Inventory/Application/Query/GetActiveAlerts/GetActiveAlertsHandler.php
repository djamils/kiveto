<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetActiveAlerts;

use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetActiveAlertsHandler
{
    public function __construct(
        private readonly StockAlertReadRepositoryInterface $alertRepository,
    ) {
    }

    /**
     * @return list<StockAlertView>
     */
    public function __invoke(GetActiveAlerts $query): array
    {
        $severity = null !== $query->severity ? StockAlertSeverity::from($query->severity) : null;

        return $this->alertRepository->findActive(
            ClinicId::fromString($query->clinicId),
            $severity,
        );
    }
}
