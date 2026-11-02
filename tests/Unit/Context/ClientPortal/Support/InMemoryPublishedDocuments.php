<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;

/**
 * The publication registry in memory, unique on (clinic, type, source).
 * `$beforeInsert` lets a test play a concurrent writer; `$loseInserts`
 * makes every insert lose without any row to find afterwards.
 */
final class InMemoryPublishedDocuments implements PublishedDocumentRepositoryInterface
{
    /** @var array<string, PublishedDocument> by id */
    public array $documents = [];

    public int $saves = 0;

    /** @var list<int> the limits asked to findAwaitingLinkForUpdate */
    public array $awaitingLimits = [];

    /** @var list<\DateTimeImmutable> the `updatedBefore` asked to findStalePendingFilesForUpdate */
    public array $staleBefore = [];

    /** @var (\Closure(PublishedDocument): void)|null */
    public ?\Closure $beforeInsert = null;

    public bool $loseInserts = false;

    /** @var list<array{string, string}> (clinic, client) pairs with a confirmed link */
    public array $confirmedClients = [];

    public function add(PublishedDocument $document): PublishedDocument
    {
        $this->documents[$document->id()->toString()] = $document;

        return $document;
    }

    public function save(PublishedDocument $document): void
    {
        $this->documents[$document->id()->toString()] = $document;
        ++$this->saves;
    }

    public function insertIfAbsent(PublishedDocument $document): bool
    {
        if (null !== $this->beforeInsert) {
            ($this->beforeInsert)($document);
        }

        if ($this->loseInserts) {
            return false;
        }

        if (null !== $this->findForUpdate($document->clinicId(), $document->type(), $document->sourceRef())) {
            return false;
        }

        $this->documents[$document->id()->toString()] = $document;

        return true;
    }

    public function findForUpdate(string $clinicId, DocumentType $type, string $sourceRef): ?PublishedDocument
    {
        foreach ($this->documents as $document) {
            $sameSource = $document->type() === $type && $document->sourceRef() === $sourceRef;

            if ($document->clinicId() === $clinicId && $sameSource) {
                return $document;
            }
        }

        return null;
    }

    public function isServedKey(string $storageKey): bool
    {
        foreach ($this->documents as $document) {
            if ($storageKey === $document->storageKey()) {
                return true;
            }
        }

        return false;
    }

    public function findByIdForUpdate(string $documentId): ?PublishedDocument
    {
        return $this->documents[$documentId] ?? null;
    }

    public function findAwaitingLinkForUpdate(string $clinicId, string $clientId, int $limit): array
    {
        $this->awaitingLimits[] = $limit;

        $found = array_filter(
            $this->documents,
            static fn (PublishedDocument $d): bool => $d->clinicId() === $clinicId && $d->clientId() === $clientId
                && PublicationStatus::AWAITING_LINK === $d->status(),
        );
        usort(
            $found,
            static fn (PublishedDocument $a, PublishedDocument $b): int => $a->publishedAt() <=> $b->publishedAt(),
        );

        return \array_slice($found, 0, $limit);
    }

    public function findStalePendingFilesForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array
    {
        $this->staleBefore[] = $updatedBefore;

        $found = array_filter(
            $this->documents,
            static fn (PublishedDocument $d): bool => PublicationStatus::PENDING_FILE === $d->status()
                && $d->updatedAt() < $updatedBefore && $d->renderAttempts() < PublishedDocument::MAX_RENDER_ATTEMPTS,
        );
        usort(
            $found,
            static fn (PublishedDocument $a, PublishedDocument $b): int => $a->updatedAt() <=> $b->updatedAt(),
        );

        return \array_slice($found, 0, $limit);
    }

    public function findStaleReleasableForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array
    {
        $found = array_filter(
            $this->documents,
            fn (PublishedDocument $d): bool => PublicationStatus::AWAITING_LINK === $d->status() && $d->updatedAt() < $updatedBefore
                && \in_array([$d->clinicId(), $d->clientId()], $this->confirmedClients, true),
        );
        usort(
            $found,
            static fn (PublishedDocument $a, PublishedDocument $b): int => $a->updatedAt() <=> $b->updatedAt(),
        );

        return \array_slice($found, 0, $limit);
    }

    public function findByPatientForUpdate(string $clinicId, string $patientId): array
    {
        return array_values(array_filter(
            $this->documents,
            static fn (PublishedDocument $d): bool => $d->clinicId() === $clinicId && $d->patientId() === $patientId,
        ));
    }

    public function only(): PublishedDocument
    {
        \assert(1 === \count($this->documents));

        return array_values($this->documents)[0];
    }
}
