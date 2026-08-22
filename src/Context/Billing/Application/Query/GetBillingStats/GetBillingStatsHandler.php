<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetBillingStats;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetBillingStatsHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    public function __invoke(GetBillingStats $query): BillingStats
    {
        return $this->invoices->stats(ClinicId::fromString($query->clinicId), $query->from, $query->to);
    }
}
