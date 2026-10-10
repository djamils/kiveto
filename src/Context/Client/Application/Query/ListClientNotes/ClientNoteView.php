<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientNotes;

final readonly class ClientNoteView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $content,
        public string $authorName,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
