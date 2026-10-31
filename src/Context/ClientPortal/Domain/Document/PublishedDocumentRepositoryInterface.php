<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

interface PublishedDocumentRepositoryInterface
{
    /** Inserts or updates the row of the document. */
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

    /**
     * Documents of a client still waiting for a confirmed link, oldest first.
     *
     * @return list<PublishedDocument>
     */
    public function findAwaitingLink(string $clinicId, string $clientId, int $limit): array;

    /**
     * Documents whose file has been expected since before `$updatedBefore`,
     * with fewer than the maximum render attempts, oldest first.
     *
     * @return list<PublishedDocument>
     */
    public function findStalePendingFiles(\DateTimeImmutable $updatedBefore, int $limit): array;

    /**
     * @return list<PublishedDocument>
     */
    public function findByPatient(string $clinicId, string $patientId): array;
}
