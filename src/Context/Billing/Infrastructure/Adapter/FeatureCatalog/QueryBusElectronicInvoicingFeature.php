<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\FeatureCatalog;

use App\Context\Billing\Application\Port\ElectronicInvoicingFeatureInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;

final readonly class QueryBusElectronicInvoicingFeature implements ElectronicInvoicingFeatureInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function isOnFor(string $clinicId): bool
    {
        return true === $this->queryBus->ask(new IsFeatureEnabled($clinicId, 'billing.electronic_invoicing'));
    }
}
