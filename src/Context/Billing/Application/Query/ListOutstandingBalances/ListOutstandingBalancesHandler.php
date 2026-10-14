<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListOutstandingBalances;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListOutstandingBalancesHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    /**
     * @return array<string, int>
     */
    public function __invoke(ListOutstandingBalances $query): array
    {
        return $this->invoices->outstandingByPayer(ClinicId::fromString($query->clinicId));
    }
}
