<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The transmission status Billing keeps on its invoices and credit notes — a
 * read-only projection of what ElectronicInvoicing reports, so that no read
 * path ever calls that context.
 */
interface TransmissionStatusProjectionInterface
{
    public const string KIND_INVOICE     = 'invoice';
    public const string KIND_CREDIT_NOTE = 'credit_note';

    /** Billing's own states, overwritten by any report from ElectronicInvoicing. */
    public const string REQUESTED      = 'requested';
    public const string REQUEST_FAILED = 'request_failed';

    /**
     * The professional document was issued: its transmission is requested,
     * and the neutral document frozen at issue is kept to be sent as is.
     *
     * @param array<string, mixed> $document
     */
    public function markRequested(string $documentId, string $kind, array $document, \DateTimeImmutable $now): void;

    /**
     * The neutral document frozen when the professional document was issued.
     *
     * @return array<string, mixed>|null null when nothing was frozen (not a professional document)
     */
    public function frozenDocument(string $documentId, string $kind): ?array;

    /**
     * The request never reached ElectronicInvoicing: someone must act.
     */
    public function markRequestFailed(string $documentId, string $kind, \DateTimeImmutable $now): void;

    /**
     * Applies a report only when it is newer than the one stored, so that
     * duplicates and out-of-order deliveries never move the projection back.
     *
     * @return bool whether the projection changed
     */
    public function apply(TransmissionStatusUpdate $update): bool;

    /**
     * Professional documents issued before $issuedBefore whose transmission is
     * still only requested.
     *
     * @return list<array{clinicId: string, documentId: string, kind: string, issuedAt: \DateTimeImmutable}>
     */
    public function stillRequested(\DateTimeImmutable $issuedBefore): array;
}
