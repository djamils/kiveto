<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Exception\RenderingFailedException;
use App\System\ElectronicInvoicing\Application\Port\InvoiceDocumentRendererInterface;

final class FakeRenderer implements InvoiceDocumentRendererInterface
{
    public function __construct(private readonly bool $fail = false)
    {
    }

    public function render(TransmittableInvoice $invoice, TransmittableElectronicAddress $buyerElectronicAddress): string
    {
        if ($this->fail) {
            throw new RenderingFailedException(['line 1: broken']);
        }

        return '<xml/>';
    }
}
