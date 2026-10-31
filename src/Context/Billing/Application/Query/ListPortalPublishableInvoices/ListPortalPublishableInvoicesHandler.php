<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListPortalPublishableInvoices;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPortalPublishableInvoicesHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    /**
     * @return list<PortalPublishableInvoice>
     */
    public function __invoke(ListPortalPublishableInvoices $query): array
    {
        return $this->invoices->portalPublishable(
            $query->issuedFrom,
            null !== $query->afterId ? InvoiceId::fromString($query->afterId) : null,
            $query->limit,
        );
    }
}
