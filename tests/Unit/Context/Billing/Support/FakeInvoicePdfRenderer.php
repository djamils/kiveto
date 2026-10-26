<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;

final class FakeInvoicePdfRenderer implements InvoicePdfRendererInterface
{
    /** @var list<string> */
    public array $rendered = [];

    public function __construct(private readonly string $filename = 'FAC-2026-000012.pdf')
    {
    }

    public function invoice(string $clinicId, string $invoiceId): RenderedDocument
    {
        $this->rendered[] = $invoiceId;

        return new RenderedDocument($this->filename, '%PDF-' . $invoiceId);
    }

    public function creditNote(string $clinicId, string $creditNoteId): RenderedDocument
    {
        throw new \LogicException('Credit notes are never sent.');
    }
}
