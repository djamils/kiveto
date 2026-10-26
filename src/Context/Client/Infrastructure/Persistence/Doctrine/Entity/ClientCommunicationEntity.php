<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\CommunicationOrigin;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'client__client_communications')]
#[ORM\Index(name: 'idx_client_communication_client_occurred', columns: ['clinic_id', 'client_id', 'occurred_at'])]
#[ORM\UniqueConstraint(name: 'uniq_client_communication_notification', columns: ['notification_message_id'])]
class ClientCommunicationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clientId,
        #[ORM\Column(type: 'string', length: 20, enumType: ContactChannel::class)]
        public ContactChannel $channel,
        #[ORM\Column(type: 'string', length: 20, enumType: CommunicationDirection::class)]
        public CommunicationDirection $direction,
        #[ORM\Column(type: 'string', length: 160)]
        public string $subject,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $body,
        #[ORM\Column(type: 'string', length: 255)]
        public string $authorName,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: 'string', length: 20, enumType: CommunicationOrigin::class, options: ['default' => 'manual'])]
        public CommunicationOrigin $origin = CommunicationOrigin::MANUAL,
        #[ORM\Column(type: 'string', length: 20, nullable: true, enumType: DeliveryStatus::class)]
        public ?DeliveryStatus $deliveryStatus = null,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        public ?Uuid $notificationMessageId = null,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $deliveryStatusSequence = null,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $statusChangedAt = null,
    ) {
    }
}
