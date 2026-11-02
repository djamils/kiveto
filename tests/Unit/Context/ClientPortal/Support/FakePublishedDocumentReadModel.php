<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;

/** Answers from public maps; records what each read was asked. */
final class FakePublishedDocumentReadModel implements PublishedDocumentReadModelInterface
{
    /** @var list<ListedDocument> the listable rows, already in page order */
    public array $rows = [];

    /** @var list<array{string, string, ?string, ?string, ?DocumentPosition, int}> */
    public array $listings = [];

    /** @var list<ListedDocument> the listable documents of every visit, newest first */
    public array $visitRows = [];

    /** @var array<string, ListedDocument> by document id */
    public array $byId = [];

    /** @var array<string, ListedDocument> by "type/source" */
    public array $bySource = [];

    /** @var list<string> `<type>/<source>` published to the account then withdrawn */
    public array $withdrawnSources = [];

    /** @var array<string, string> by "document/version" */
    public array $versionKeys = [];

    /** @var array<string, \DateTimeImmutable> by consultation id */
    public array $sharedAt = [];

    /** @var array<string, list<ListedDocument>> by clinic */
    public array $unread = [];

    /** @var list<string> every read asked, `<method>:<args>` */
    public array $calls = [];

    public function listable(
        string $userId,
        string $clinicId,
        ?string $type,
        ?string $petId,
        ?DocumentPosition $after,
        int $limit,
    ): array {
        $this->listings[] = [$userId, $clinicId, $type, $petId, $after, $limit];

        return \array_slice($this->rows, 0, $limit);
    }

    public function listableOfVisits(string $userId, string $clinicId, array $consultationIds): array
    {
        $this->calls[] = 'listableOfVisits:' . $userId . ':' . $clinicId . ':' . implode(',', $consultationIds);

        return array_values(array_filter(
            $this->visitRows,
            static fn (ListedDocument $row): bool => \in_array($row->consultationId, $consultationIds, true),
        ));
    }

    public function find(string $userId, string $clinicId, string $documentId): ?ListedDocument
    {
        $this->calls[] = 'find:' . $userId . ':' . $clinicId . ':' . $documentId;

        return $this->byId[$documentId] ?? null;
    }

    public function findBySource(string $userId, string $clinicId, string $type, string $sourceRef): ?ListedDocument
    {
        $this->calls[] = 'findBySource:' . $userId . ':' . $clinicId . ':' . $type . ':' . $sourceRef;

        return $this->bySource[$type . '/' . $sourceRef] ?? null;
    }

    public function wasPublished(string $userId, string $clinicId, string $type, string $sourceRef): bool
    {
        return isset($this->bySource[$type . '/' . $sourceRef]) || \in_array($type . '/' . $sourceRef, $this->withdrawnSources, true);
    }

    public function versionKey(string $clinicId, string $documentId, int $version): ?string
    {
        $this->calls[] = 'versionKey:' . $clinicId . ':' . $documentId . ':' . $version;

        return $this->versionKeys[$documentId . '/' . $version] ?? null;
    }

    public function reportSharedAt(string $clinicId, string $consultationId): ?\DateTimeImmutable
    {
        $this->calls[] = 'reportSharedAt:' . $clinicId . ':' . $consultationId;

        return $this->sharedAt[$consultationId] ?? null;
    }

    public function unreadPerClinic(string $userId): array
    {
        $this->calls[] = 'unreadPerClinic:' . $userId;

        return $this->unread;
    }
}
