<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCommunications;

final readonly class ClientCommunicationView
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $direction,
        public string $subject,
        public ?string $body,
        public string $authorName,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
