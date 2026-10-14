<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCreditNoteDetails;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCreditNoteDetailsHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    public function __invoke(GetCreditNoteDetails $query): CreditNoteDetails
    {
        return $this->invoices->creditNoteDetails(
            ClinicId::fromString($query->clinicId),
            CreditNoteId::fromString($query->creditNoteId),
        );
    }
}
