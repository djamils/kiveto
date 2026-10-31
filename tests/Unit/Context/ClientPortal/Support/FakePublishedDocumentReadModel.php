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

    /** @var array<string, ListedDocument> by document id */
    public array $byId = [];

    /** @var array<string, ListedDocument> by "type/source" */
    public array $bySource = [];

    /** @var array<string, string> by "document/version" */
    public array $versionKeys = [];

    /** @var array<string, \DateTimeImmutable> by consultation id */
    public array $sharedAt = [];

    /** @var array<string, int> */
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
