<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp carrying technical metadata for any message (command/query/event).
 */
final readonly class MessageMetadataStamp implements StampInterface
{
    public function __construct(
        public string $messageId,
        public \DateTimeImmutable $occurredAt,
        public ?string $correlationId,
        public ?string $causationId,
        public ?string $actorId = null,
    ) {
    }
}
