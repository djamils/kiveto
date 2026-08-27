<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Clinic;

use App\Context\Billing\Application\Port\InvoiceNumberPrefixProviderInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Asks the Clinic context what prefix the clinic set, through the query bus.
 */
final readonly class QueryBusInvoiceNumberPrefixProvider implements InvoiceNumberPrefixProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function prefixFor(string $clinicId): ?string
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        if (!$settings instanceof ClinicSettingsView) {
            return null;
        }

        $prefix = $settings->billing['invoiceNumberPrefix'] ?? null;

        return \is_string($prefix) && '' !== $prefix ? $prefix : null;
    }
}
