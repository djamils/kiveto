<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCommunications;

/**
 * One journal entry. An automated one (a message Kiveto sent) also carries
 * its delivery status (Notification value) and French label.
 */
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
        public bool $automated = false,
        public ?string $deliveryStatus = null,
        public ?string $deliveryStatusLabel = null,
        public ?\DateTimeImmutable $statusChangedAt = null,
    ) {
    }
}
