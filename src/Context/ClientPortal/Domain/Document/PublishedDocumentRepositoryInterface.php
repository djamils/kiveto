<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

interface PublishedDocumentRepositoryInterface
{
    /**
     * Inserts or updates the row of the document; a withdrawn row is never
     * changed back (withdrawn is terminal).
     */
    public function save(PublishedDocument $document): void;

    /**
     * Inserts a new row unless one already holds its (clinic, type, source)
     * key — a tombstone or an earlier delivery wins. Returns whether it was
     * inserted.
     */
    public function insertIfAbsent(PublishedDocument $document): bool;

    /** The document of a source, locked until the transaction ends. */
    public function findForUpdate(string $clinicId, DocumentType $type, string $sourceRef): ?PublishedDocument;

    public function findByIdForUpdate(string $documentId): ?PublishedDocument;

    /** Whether a document row holds the key as its served file. */
    public function isServedKey(string $storageKey): bool;

    /**
     * Documents of a client still waiting for a confirmed link, oldest
     * first, locked until the transaction ends.
     *
     * @return list<PublishedDocument>
     */
    public function findAwaitingLinkForUpdate(string $clinicId, string $clientId, int $limit): array;

    /**
     * Documents whose file has been expected since before `$updatedBefore`,
     * with fewer than the maximum render attempts, oldest first; locked
     * until the transaction ends, a row locked by another writer skipped.
     *
     * @return list<PublishedDocument>
     */
    public function findStalePendingFilesForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array;

    /**
     * Documents still waiting for a link since before `$updatedBefore`
     * whereas their client has a confirmed link (a publication that raced
     * the confirmation), oldest first; locked like the stale pending files.
     *
     * @return list<PublishedDocument>
     */
    public function findStaleReleasableForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array;

    /**
     * @return list<PublishedDocument> locked until the transaction ends
     */
    public function findByPatientForUpdate(string $clinicId, string $patientId): array;
}
