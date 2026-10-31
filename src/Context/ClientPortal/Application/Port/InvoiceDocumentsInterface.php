<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The invoices the Portal publishes (Billing context). */
interface InvoiceDocumentsInterface
{
    /**
     * The invoice as the Portal publishes it, or null when it is not
     * publishable: unknown, not issued (draft, cancelled) or without payer
     * client (counter sale).
     */
    public function publication(string $clinicId, string $invoiceId): ?InvoicePublication;

    /** Billing's durable PDF of the invoice, rendered at its first use; returns its storage key. */
    public function ensureFile(string $clinicId, string $invoiceId): string;

    /**
     * Live payment state of each invoice, keyed by id; an id missing from
     * the answer, or with a null payment status, is not listable (e.g.
     * cancelled before its withdrawal was consumed).
     *
     * @param list<string> $invoiceIds
     *
     * @return array<string, InvoicePortalState>
     */
    public function states(string $clinicId, array $invoiceIds): array;

    /**
     * Publishable invoices issued since a date, all clinics, by id (go-live import).
     *
     * @return list<PublishableSource>
     */
    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array;
}
