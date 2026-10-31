<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\InvoicePublication;
use App\Context\ClientPortal\Application\Port\PublishableSource;

final class FakeInvoiceDocuments implements InvoiceDocumentsInterface
{
    /** @var array<string, InvoicePublication> by invoice id */
    public array $publications = [];

    /** @var array<string, InvoicePortalState> by invoice id */
    public array $states = [];

    /** @var list<PublishableSource> ordered by id */
    public array $publishable = [];

    /** @var list<list<string>> the ids asked to states() */
    public array $stateRequests = [];

    /** @var list<string> `<since Y-m-d>:<after|->:<limit>` */
    public array $pages = [];

    public ?\Throwable $ensureFailure = null;

    public function publication(string $clinicId, string $invoiceId): ?InvoicePublication
    {
        return $this->publications[$invoiceId] ?? null;
    }

    public function ensureFile(string $clinicId, string $invoiceId): string
    {
        if (null !== $this->ensureFailure) {
            throw $this->ensureFailure;
        }

        return 'billing/invoices/' . $clinicId . '/' . $invoiceId . '.pdf';
    }

    public function states(string $clinicId, array $invoiceIds): array
    {
        $this->stateRequests[] = $invoiceIds;

        return array_intersect_key($this->states, array_flip($invoiceIds));
    }

    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array
    {
        $this->pages[] = $issuedFrom->format('Y-m-d') . ':' . ($afterId ?? '-') . ':' . $limit;

        return SourcePages::after($this->publishable, $afterId, $limit);
    }
}
