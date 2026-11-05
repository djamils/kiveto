<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListOutstandingInvoicesOfPayers;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The clinic's outstanding invoices (issued or partly paid) read once, then
 * kept for the asked payers.
 */
#[AsMessageHandler]
final readonly class ListOutstandingInvoicesOfPayersHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    /**
     * @return array<string, list<string>>
     */
    public function __invoke(ListOutstandingInvoicesOfPayers $query): array
    {
        if ([] === $query->payerClientIds) {
            return [];
        }

        $payers  = array_flip($query->payerClientIds);
        $byPayer = [];

        foreach ($this->invoices->outstandingInvoices(ClinicId::fromString($query->clinicId)) as $invoice) {
            if (null !== $invoice->payerClientId && isset($payers[$invoice->payerClientId])) {
                $byPayer[$invoice->payerClientId][] = $invoice->id;
            }
        }

        return $byPayer;
    }
}
