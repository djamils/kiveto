<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A provider event whose message id is not stored yet (the webhook arrived
 * before the dispatch outcome): kept until the id is recorded, then replayed.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_notif_unmatched_external', columns: ['channel', 'external_id'])]
class UnmatchedDeliveryEventEntity
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,
        #[ORM\Column(type: 'string', length: 8)]
        private string $channel,
        #[ORM\Column(name: 'external_id', type: 'string', length: 128)]
        private string $externalId,
        #[ORM\Column(type: 'json')]
        private array $payload,
        #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $receivedAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
