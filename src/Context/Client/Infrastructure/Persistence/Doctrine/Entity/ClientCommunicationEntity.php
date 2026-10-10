<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'client__client_communications')]
#[ORM\Index(name: 'idx_client_communication_client_occurred', columns: ['clinic_id', 'client_id', 'occurred_at'])]
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
    ) {
    }
}
