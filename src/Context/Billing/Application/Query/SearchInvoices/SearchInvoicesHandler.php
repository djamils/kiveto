<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchInvoices;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchInvoicesHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    public function __invoke(SearchInvoices $query): InvoiceListItemView
    {
        $result = $this->invoices->search(ClinicId::fromString($query->clinicId), $query->criteria);

        return new InvoiceListItemView(
            items: $result['items'],
            total: $result['total'],
            page: $query->criteria->page,
            perPage: $query->criteria->perPage,
        );
    }
}
