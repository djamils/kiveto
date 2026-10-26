<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Application;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\Tests\Unit\System\ElectronicInvoicing\Support\TransmittableInvoices;
use PHPUnit\Framework\TestCase;

final class TransmittableInvoiceTest extends TestCase
{
    public function testAnInvoiceSurvivesItsPayload(): void
    {
        $invoice = TransmittableInvoices::invoice();
        $copy    = TransmittableInvoice::fromArray($invoice->toArray());

        self::assertSame($invoice->toArray(), $copy->toArray());
        self::assertFalse($copy->isCreditNote());
    }

    public function testACreditNoteSurvivesItsPayload(): void
    {
        $note = TransmittableInvoices::creditNote();
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($note->toArray()), true);
        $copy    = TransmittableInvoice::fromArray($decoded);

        self::assertTrue($copy->isCreditNote());
        self::assertSame('PAR-2026-000001', $copy->precedingInvoiceNumber);
        self::assertSame('2026-10-21', $copy->precedingInvoiceDate?->format('Y-m-d'));
        self::assertNull($copy->dueDate);
        self::assertSame($note->toArray(), $copy->toArray());
    }
}
