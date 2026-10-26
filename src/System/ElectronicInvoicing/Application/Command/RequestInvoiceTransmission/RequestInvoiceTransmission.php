<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Transmit this document to the approved platform. » The entry point of the
 * ElectronicInvoicing context: idempotent on the document id (tracking id).
 */
final readonly class RequestInvoiceTransmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public TransmittableInvoice $invoice,
    ) {
    }
}
