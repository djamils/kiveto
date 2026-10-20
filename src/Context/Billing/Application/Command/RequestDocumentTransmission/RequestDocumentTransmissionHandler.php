<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RequestDocumentTransmission;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Hands the document frozen at its issue to the electronic-invoicing context,
 * which ignores a document it already has. Nothing is read again: neither the
 * invoice, nor the seller, nor the payments received since.
 */
#[AsMessageHandler]
final readonly class RequestDocumentTransmissionHandler
{
    public function __construct(
        private TransmissionStatusProjectionInterface $projection,
        private ElectronicInvoiceTransmitterInterface $transmitter,
    ) {
    }

    public function __invoke(RequestDocumentTransmission $command): void
    {
        $document = $this->projection->frozenDocument($command->documentId, $command->kind);

        if (null !== $document) {
            $this->transmitter->transmit($command->clinicId, $document);
        }
    }
}
