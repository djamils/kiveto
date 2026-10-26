<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Exception\RenderingFailedException;

interface InvoiceDocumentRendererInterface
{
    /**
     * The document as a file the platform accepts (CII D22B), sent to
     * $buyerElectronicAddress (BT-49), validated before it is returned.
     *
     * @throws RenderingFailedException
     */
    public function render(TransmittableInvoice $invoice, TransmittableElectronicAddress $buyerElectronicAddress): string;
}
