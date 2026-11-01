<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Reads of the publication registry for an owner account. Every read is
 * scoped in SQL to the clients the account reaches through a confirmed link
 * to the clinic, and to listable rows: a served file (version ≥ 1) of a
 * document neither withdrawn nor waiting for a link.
 */
interface PublishedDocumentReadModelInterface
{
    /**
     * Newest first (`issued_at DESC, id DESC`), after the given position.
     *
     * @return list<ListedDocument>
     */
    public function listable(string $userId, string $clinicId, ?string $type, ?string $petId, ?DocumentPosition $after, int $limit): array;

    /**
     * The listable documents of the given visits (`consultation_id`), newest
     * first: an invoice only for its payer.
     *
     * @param list<string> $consultationIds
     *
     * @return list<ListedDocument>
     */
    public function listableOfVisits(string $userId, string $clinicId, array $consultationIds): array;

    /** A listable document of the account, or null. */
    public function find(string $userId, string $clinicId, string $documentId): ?ListedDocument;

    /** The listable document published for a source (e.g. a prescription), or null. */
    public function findBySource(string $userId, string $clinicId, string $type, string $sourceRef): ?ListedDocument;

    /**
     * Whether the source was ever published to a client file the account
     * reaches through a confirmed link, withdrawn since or not (a cancelled
     * prescription is still the account's, just no longer listed).
     */
    public function wasPublished(string $userId, string $clinicId, string $type, string $sourceRef): bool;

    /**
     * The storage key of one served version of a document still published,
     * or null (unknown, withdrawn, never served, version not yet stored).
     * No account scope: the caller holds a signed URL.
     */
    public function versionKey(string $clinicId, string $documentId, int $version): ?string;

    /**
     * When the report of a consultation was last shared (the row's last
     * update), or null when never shared or withdrawn. Clinic side: no
     * account scope.
     */
    public function reportSharedAt(string $clinicId, string $consultationId): ?\DateTimeImmutable;

    /**
     * Listable documents the account has not opened, per clinic.
     *
     * @return array<string, int>
     */
    public function unreadPerClinic(string $userId): array;
}
