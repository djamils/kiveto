<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class ListedDocument
{
    /**
     * @param list<string> $petIds
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $sourceRef,
        public string $title,
        public array $petIds,
        public \DateTimeImmutable $issuedAt,
        public bool $unread,
        public int $fileVersion,
        public string $storageKey,
        public ?string $practitionerName,
        /** The visit the document belongs to, when it has one. */
        public ?string $consultationId = null,
    ) {
    }
}
