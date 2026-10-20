<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RequestDocumentTransmission;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reloads the issued document — immutable since its issue — and hands it to
 * the electronic-invoicing context, which ignores a document it already has.
 */
#[AsMessageHandler]
final readonly class RequestDocumentTransmissionHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private CreditNoteRepositoryInterface $creditNotes,
        private SellerIdentityProviderInterface $sellers,
        private ElectronicInvoiceTransmitterInterface $transmitter,
    ) {
    }

    public function __invoke(RequestDocumentTransmission $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $seller   = $this->sellers->forClinic($command->clinicId);

        if (TransmissionStatusProjectionInterface::KIND_CREDIT_NOTE === $command->kind) {
            $creditNote = $this->creditNotes->get($clinicId, CreditNoteId::fromString($command->documentId));

            if (ProcessingRule::B2B === $creditNote->processingRule()) {
                $this->transmitter->transmitCreditNote($creditNote, $seller);
            }

            return;
        }

        $invoice = $this->invoices->get($clinicId, InvoiceId::fromString($command->documentId));

        if (null !== $invoice->number() && ProcessingRule::B2B === $invoice->processingRule()) {
            $this->transmitter->transmitInvoice($invoice, $seller);
        }
    }
}
