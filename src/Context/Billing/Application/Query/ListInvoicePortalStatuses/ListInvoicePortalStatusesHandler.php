<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoicePortalStatuses;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListInvoicePortalStatusesHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    /**
     * @return list<InvoicePortalStatus>
     */
    public function __invoke(ListInvoicePortalStatuses $query): array
    {
        if ([] === $query->invoiceIds) {
            return [];
        }

        return $this->invoices->portalStatuses(
            ClinicId::fromString($query->clinicId),
            array_map(static fn (string $id): InvoiceId => InvoiceId::fromString($id), $query->invoiceIds),
        );
    }
}
