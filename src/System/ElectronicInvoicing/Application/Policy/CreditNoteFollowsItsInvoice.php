<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Policy;

use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;

/**
 * A credit note never reaches the platform before its invoice. It waits while
 * the invoice is not submitted — not transmitted yet, blocked, inadmissible,
 * or not even requested — and goes as soon as it is. It stays internal only
 * when its invoice was refused (210) or rejected (213).
 */
final readonly class CreditNoteFollowsItsInvoice
{
    public function __construct(private OutboundTransmissionRepositoryInterface $transmissions)
    {
    }

    /**
     * Whether the transmission may be sent now; always true for an invoice.
     */
    public function maySend(OutboundTransmission $transmission): bool
    {
        $invoice = $this->invoiceOf($transmission);

        if (false === $invoice) {
            return true;
        }

        return null === $invoice?->refusedOrRejected() && true === $invoice?->wasAccepted();
    }

    /**
     * Puts a credit note where its invoice allows: released when the invoice
     * was submitted, internal when it was refused or rejected, waiting
     * otherwise.
     *
     * @return bool whether it may be sent now
     */
    public function settle(OutboundTransmission $transmission, \DateTimeImmutable $now): bool
    {
        $invoice = $this->invoiceOf($transmission);

        if (false === $invoice) {
            return true;
        }

        $voided = $invoice?->refusedOrRejected();

        if (null !== $voided) {
            $transmission->markInternal($voided, $now);

            return false;
        }

        if (true === $invoice?->wasAccepted()) {
            $transmission->releaseFromPrecedingInvoice($now);

            return TransmissionState::PREPARED === $transmission->state();
        }

        $transmission->awaitPrecedingInvoice($now);

        return false;
    }

    /**
     * @return OutboundTransmission|false|null false when the transmission does
     *                                         not follow an invoice, null when
     *                                         that invoice is not known yet
     */
    private function invoiceOf(OutboundTransmission $transmission): OutboundTransmission|false|null
    {
        $number = $transmission->precedingDocumentNumber();

        if (TransmissionKind::CREDIT_NOTE !== $transmission->kind() || null === $number) {
            return false;
        }

        return $this->transmissions->byDocument($transmission->sellerSiren(), TransmissionKind::INVOICE, $number);
    }
}
